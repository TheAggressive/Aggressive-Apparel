/**
 * Orchestrator for the shared scroll-debug tooling.
 *
 * Wires together the intersection probe, boundary/element overlays,
 * floating panel, and frame-time monitor for one debugged block. The
 * per-block debug entries (parallax, animate-on-scroll) are thin
 * adapters over this controller.
 *
 * Accuracy notes:
 * - The probe observes with the block's EFFECTIVE rootMargin (e.g. the
 *   parallax engine adds a pre-activation buffer), so the live ratio,
 *   phase, and overlays reflect what the production observer really
 *   sees — not just what was configured in the editor.
 * - When the effective boundary differs from the configured one, BOTH
 *   are drawn, each labeled.
 *
 * @package Aggressive Apparel
 */

import {
  acquireBoundaryOverlay,
  createElementOverlay,
  releaseBoundaryOverlays,
  type ElementOverlay,
} from './overlays';
import { createDebugPanel, type PanelRowSpec } from './panel';
import { createPerfMonitor } from './perf-monitor';
import { createIntersectionProbe } from './probe';
import { fmt, getStrings, type DebugStrings } from './i18n';
import type { DebugBoundary, IntersectionPhase } from './types';
import {
  boundaryToRootMargin,
  getIntersectionPhase,
  getMaxReachableRatio,
  getRootBoxHeight,
  getValidIntersectionRatio,
  nextDebugInstanceIndex,
} from './utils';

const phaseLabel = (strings: DebugStrings, phase: IntersectionPhase): string =>
  ({
    waiting: strings.phaseWaiting,
    approaching: strings.phaseApproaching,
    active: strings.phaseActive,
  })[phase];

export interface EngineRowConfig {
  /** Row label, e.g. "Engine" or "Animation". */
  label: string;
  /** Badge text while the block logic is active, e.g. "Active"/"Shown". */
  active: string;
  /** Badge text while idle, e.g. "Idle"/"Hidden". */
  idle: string;
}

export interface ScrollDebugOptions {
  /** Unique block instance id. */
  id: string;
  /** Storage/telemetry namespace, e.g. 'parallax'. */
  namespace: string;
  /** Panel title. */
  title: string;
  /** The observed element. */
  element: HTMLElement;
  /** Boundary exactly as configured in the editor. */
  configuredBoundary: DebugBoundary;
  /** rootMargin the production IntersectionObserver actually uses. */
  effectiveRootMargin: string;
  /** Entry threshold 0..1. */
  threshold: number;
  /** Optional exit threshold (reverse-on-scroll-back hysteresis). */
  exitThreshold?: number;
  /** Config for the block-logic state row; omit to hide the row. */
  engine?: EngineRowConfig;
  /** Adds a progress meter driven via setProgress() (parallax). */
  trackProgress?: boolean;
  /** Extra static info rows in the Basic section. */
  info?: Array<{ label: string; value: string }>;
}

export interface ScrollDebugController {
  /** Reflect the production observer / block logic state. */
  setEngineState: (active: boolean) => void;
  /** Drive the progress meter (requires trackProgress). */
  setProgress: (progress: number) => void;
  destroy: () => void;
}

/**
 * Identity colors cycled across debug instances (devtools-style): each
 * debugged block's element outline, ID tag, and panel dot share one hue
 * so multiple instances are distinguishable at a glance. State (phase)
 * stays on the trigger lines/zone/badges.
 */
const IDENTITY_COLORS = [
  'oklch(72% 0.17 230deg)', // blue
  'oklch(70% 0.19 330deg)', // magenta
  'oklch(76% 0.15 70deg)', // orange
  'oklch(75% 0.13 175deg)', // teal
  'oklch(68% 0.19 300deg)', // violet
  'oklch(80% 0.16 115deg)', // lime
];

/**
 * Live meters (visibility ratio, engine progress) receive updates every
 * frame while scrolling; writing panel text that often forces layout +
 * style recalc per frame and can halve the frame rate the panel is
 * supposed to measure. 10Hz reads perfectly for a human and costs ~6
 * writes/s instead of ~60.
 */
const METER_UPDATE_INTERVAL_MS = 100;

/** Normalize a rootMargin shorthand into four explicit sides. */
export const rootMarginToBoundary = (rootMargin: string): DebugBoundary => {
  const parts = rootMargin.trim().split(/\s+/);
  const [a = '0px', b = a, c = a, d = b] = parts;
  return { top: a, right: b, bottom: c, left: d };
};

export const createScrollDebugController = (
  options: ScrollDebugOptions
): ScrollDebugController => {
  const { element, threshold, exitThreshold } = options;
  const abort = new AbortController();
  const { signal } = abort;
  const overlayId = `${options.namespace}:${options.id}`;
  const strings = getStrings();
  // DOM-coordinated index: stays unique even across the separate module
  // copies each block type bundles.
  const accent =
    IDENTITY_COLORS[nextDebugInstanceIndex() % IDENTITY_COLORS.length];

  // ---- Boundary overlays ------------------------------------------------
  const configuredMargin = boundaryToRootMargin(options.configuredBoundary);
  const effectiveBoundary = rootMarginToBoundary(options.effectiveRootMargin);
  const boundariesDiffer =
    boundaryToRootMargin(effectiveBoundary) !== configuredMargin;

  acquireBoundaryOverlay(
    overlayId,
    'configured',
    options.configuredBoundary,
    strings.boundaryConfigured
  );
  if (boundariesDiffer) {
    acquireBoundaryOverlay(
      overlayId,
      'effective',
      effectiveBoundary,
      strings.boundaryEffective
    );
  }

  // ---- Element overlay ----------------------------------------------------
  const elementOverlay: ElementOverlay = createElementOverlay(element, {
    threshold,
    exitThreshold,
    label: options.id,
    accent,
  });

  // ---- Panel ---------------------------------------------------------------
  const meterMarkers = [threshold];
  if (typeof exitThreshold === 'number') {
    meterMarkers.push(exitThreshold);
  }

  const basicRows: PanelRowSpec[] = [
    { id: 'state', label: strings.rowState, kind: 'badge' },
    {
      id: 'visibility',
      label: strings.rowVisibility,
      kind: 'meter',
      markers: meterMarkers,
    },
  ];
  if (options.trackProgress) {
    basicRows.push({
      id: 'progress',
      label: strings.rowProgress,
      kind: 'meter',
    });
  }
  basicRows.push({
    id: 'direction',
    label: strings.rowDirection,
    kind: 'text',
  });
  if (options.engine) {
    basicRows.push({
      id: 'engine',
      label: options.engine.label,
      kind: 'badge',
    });
  }
  (options.info ?? []).forEach((info, index) => {
    basicRows.push({ id: `info-${index}`, label: info.label, kind: 'text' });
  });

  const advancedRows: PanelRowSpec[] = [
    { id: 'threshold', label: strings.rowThreshold, kind: 'text' },
    { id: 'framerate', label: strings.rowFramerate, kind: 'badge' },
    { id: 'size', label: strings.rowSize, kind: 'text' },
    { id: 'boundary', label: strings.rowBoundary, kind: 'text' },
  ];
  if (boundariesDiffer) {
    advancedRows.push({
      id: 'observer',
      label: strings.rowObserver,
      kind: 'text',
    });
  }

  // Explains every on-page overlay; collapsed so it informs without
  // costing space once the shapes are familiar.
  const thresholdPct = Math.round(threshold * 100);
  const legend = [
    {
      swatch: 'boundary',
      text: strings.legendBoundary,
    },
    ...(boundariesDiffer
      ? [
          {
            swatch: 'effective',
            text: strings.legendEffective,
          },
        ]
      : []),
    {
      swatch: 'element',
      text: strings.legendElement,
    },
    {
      swatch: 'entry',
      text: fmt(strings.legendEntry, { pct: thresholdPct }),
    },
    {
      swatch: 'entry-top',
      text: fmt(strings.legendEntryTop, { pct: thresholdPct }),
    },
    ...(typeof exitThreshold === 'number'
      ? [
          {
            swatch: 'exit',
            text: fmt(strings.legendExit, {
              pct: Math.round(exitThreshold * 100),
            }),
          },
        ]
      : []),
    {
      swatch: 'zone',
      text: strings.legendZone,
    },
  ];

  const panel = createDebugPanel({
    title: options.title,
    subtitle: options.id,
    accent,
    storageKey: `aa-dbg:${options.namespace}:${options.id}`,
    sections: [
      { id: 'basic', label: strings.sectionLive, rows: basicRows },
      {
        id: 'advanced',
        label: strings.sectionDetails,
        startCollapsed: true,
        rows: advancedRows,
      },
    ],
    legend,
  });

  // ---- Static rows ---------------------------------------------------------
  const thresholdText =
    typeof exitThreshold === 'number'
      ? fmt(strings.thresholdEntryExit, {
          entry: Math.round(threshold * 100),
          exit: Math.round(exitThreshold * 100),
        })
      : fmt(strings.thresholdEntry, { pct: Math.round(threshold * 100) });
  panel.setText('threshold', thresholdText);
  panel.setText('boundary', configuredMargin);
  if (boundariesDiffer) {
    panel.setText('observer', options.effectiveRootMargin);
  }
  panel.setBadge('state', strings.phaseWaiting, 'waiting');
  if (options.engine) {
    panel.setBadge('engine', options.engine.idle, 'idle');
  }
  (options.info ?? []).forEach((info, index) => {
    panel.setText(`info-${index}`, info.value);
  });

  const refreshSize = (): void => {
    panel.setText(
      'size',
      `${Math.round(element.offsetWidth)} × ${Math.round(
        element.offsetHeight
      )} px`
    );
  };
  refreshSize();

  // ---- Unreachable-threshold warning ----------------------------------------
  const refreshWarning = (): void => {
    const rootBoxHeight = getRootBoxHeight(
      options.effectiveRootMargin,
      window.innerHeight
    );
    const maxRatio = getMaxReachableRatio(element.offsetHeight, rootBoxHeight);
    if (maxRatio < threshold) {
      panel.setWarning(
        fmt(strings.warnUnreachable, {
          pct: Math.round(threshold * 100),
          elem: Math.round(element.offsetHeight),
          root: Math.round(rootBoxHeight),
          max: Math.round(maxRatio * 100),
        })
      );
    } else {
      panel.setWarning(null);
    }
  };
  refreshWarning();

  // ---- Live intersection data (debug-only dense probe) -----------------------
  let lastPhase: IntersectionPhase = 'waiting';
  let lastVisibilityWrite = 0;
  const probe = createIntersectionProbe(
    element,
    options.effectiveRootMargin,
    sample => {
      const ratio = getValidIntersectionRatio(sample.ratio);
      const phase = getIntersectionPhase(
        sample.isIntersecting,
        ratio,
        threshold
      );
      const now = performance.now();
      if (
        now >= lastVisibilityWrite + METER_UPDATE_INTERVAL_MS ||
        ratio === 0 ||
        ratio === 1
      ) {
        lastVisibilityWrite = now;
        panel.setMeter('visibility', ratio, `${(ratio * 100).toFixed(1)}%`);
      }
      if (phase !== lastPhase) {
        lastPhase = phase;
        panel.setBadge('state', phaseLabel(strings, phase), phase);
        elementOverlay.setPhase(phase);
      }
    }
  );

  // ---- Scroll direction (rAF-coalesced, no per-event DOM writes) -------------
  let previousScrollY = window.scrollY;
  let scrollRafId: number | null = null;
  window.addEventListener(
    'scroll',
    () => {
      if (scrollRafId !== null) {
        return;
      }
      scrollRafId = requestAnimationFrame(() => {
        scrollRafId = null;
        const scrollY = window.scrollY;
        if (scrollY !== previousScrollY) {
          panel.setText(
            'direction',
            scrollY > previousScrollY
              ? strings.directionDown
              : strings.directionUp
          );
          previousScrollY = scrollY;
        }
      });
    },
    { passive: true, signal }
  );
  panel.setText('direction', strings.directionDown);

  // ---- Layout tracking ---------------------------------------------------
  const onLayoutChange = (): void => {
    elementOverlay.reposition();
    refreshSize();
    refreshWarning();
  };
  let resizeRafId: number | null = null;
  window.addEventListener(
    'resize',
    () => {
      if (resizeRafId !== null) {
        return;
      }
      resizeRafId = requestAnimationFrame(() => {
        resizeRafId = null;
        onLayoutChange();
      });
    },
    { passive: true, signal }
  );

  // Late-loading images etc. resize the element without a window resize.
  let resizeObserver: ResizeObserver | null = null;
  if (typeof ResizeObserver !== 'undefined') {
    resizeObserver = new ResizeObserver(onLayoutChange);
    resizeObserver.observe(element);
  }

  // ---- Frame-time monitor ----------------------------------------------------
  const perfMonitor = createPerfMonitor(snapshot => {
    panel.setBadge(
      'framerate',
      `${snapshot.fps.toFixed(0)} fps · ${snapshot.averageFrameTime.toFixed(
        1
      )} ms`,
      snapshot.status
    );
  });
  panel.setBadge('framerate', strings.measuring, 'good');

  // ---- Public handle -----------------------------------------------------
  let lastProgressWrite = 0;
  return {
    setEngineState: active => {
      if (options.engine) {
        panel.setBadge(
          'engine',
          active ? options.engine.active : options.engine.idle,
          active ? 'active' : 'idle'
        );
      }
    },

    setProgress: progress => {
      if (!options.trackProgress) {
        return;
      }
      const clamped = getValidIntersectionRatio(progress);
      const now = performance.now();
      // Always land the resting values so the meter never freezes mid-bar.
      if (
        now >= lastProgressWrite + METER_UPDATE_INTERVAL_MS ||
        clamped === 0 ||
        clamped === 1
      ) {
        lastProgressWrite = now;
        panel.setMeter('progress', clamped, `${(clamped * 100).toFixed(0)}%`);
      }
    },

    destroy: () => {
      abort.abort();
      if (scrollRafId !== null) {
        cancelAnimationFrame(scrollRafId);
      }
      if (resizeRafId !== null) {
        cancelAnimationFrame(resizeRafId);
      }
      resizeObserver?.disconnect();
      probe.disconnect();
      perfMonitor.stop();
      elementOverlay.destroy();
      releaseBoundaryOverlays(overlayId);
      panel.destroy();
    },
  };
};
