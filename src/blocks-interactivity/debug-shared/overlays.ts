/**
 * DOM overlays for the shared scroll-debug tooling.
 *
 * Two kinds:
 * - Boundary overlays: viewport-fixed boxes showing the observer's root
 *   box. Refcounted and keyed by (role + geometry) so any number of
 *   debugged blocks sharing a boundary share ONE overlay, and blocks
 *   with different boundaries each get their own instead of fighting
 *   over a single element.
 * - Element overlay: an absolutely positioned box tracking the observed
 *   element, holding the entry/exit trigger lines, labels, and the
 *   entry zone.
 *
 * @package Aggressive Apparel
 */

import type { DebugBoundary, IntersectionPhase } from './types';
import { boundaryExtendsViewport, el, invertValue } from './utils';

// =============================================================================
// BOUNDARY OVERLAYS (shared, refcounted)
// =============================================================================

export type BoundaryRole = 'configured' | 'effective';

interface BoundaryEntry {
  element: HTMLElement;
  holders: Set<string>;
}

const boundaryRegistry = new Map<string, BoundaryEntry>();
/** Which registry keys each debug instance currently holds. */
const holderKeys = new Map<string, Set<string>>();

const boundaryKey = (role: BoundaryRole, boundary: DebugBoundary): string =>
  `${role}|${boundary.top}|${boundary.right}|${boundary.bottom}|${boundary.left}`;

/**
 * Show a boundary overlay for a debug instance. Overlays are shared:
 * instances with identical geometry reuse one element.
 */
export const acquireBoundaryOverlay = (
  instanceId: string,
  role: BoundaryRole,
  boundary: DebugBoundary,
  label: string
): void => {
  const key = boundaryKey(role, boundary);
  let entry = boundaryRegistry.get(key);

  if (!entry) {
    const element = el('div', `aa-dbg-boundary aa-dbg-boundary--${role}`);
    element.style.inset = `${invertValue(boundary.top || '0%')} ${invertValue(
      boundary.right || '0%'
    )} ${invertValue(boundary.bottom || '0%')} ${invertValue(
      boundary.left || '0%'
    )}`;

    const labelText = boundaryExtendsViewport(boundary)
      ? `${label} · extends beyond viewport`
      : label;
    element.appendChild(el('span', 'aa-dbg-boundary__label', labelText));

    document.body.appendChild(element);
    entry = { element, holders: new Set() };
    boundaryRegistry.set(key, entry);
  }

  entry.holders.add(instanceId);

  let keys = holderKeys.get(instanceId);
  if (!keys) {
    keys = new Set();
    holderKeys.set(instanceId, keys);
  }
  keys.add(key);
};

/** Release every boundary overlay held by a debug instance. */
export const releaseBoundaryOverlays = (instanceId: string): void => {
  const keys = holderKeys.get(instanceId);
  if (!keys) {
    return;
  }
  keys.forEach(key => {
    const entry = boundaryRegistry.get(key);
    if (!entry) {
      return;
    }
    entry.holders.delete(instanceId);
    if (entry.holders.size === 0) {
      entry.element.remove();
      boundaryRegistry.delete(key);
    }
  });
  holderKeys.delete(instanceId);
};

// =============================================================================
// ELEMENT OVERLAY (per instance)
// =============================================================================

export interface ElementOverlayOptions {
  /** Entry threshold 0..1. */
  threshold: number;
  /** Optional exit threshold (reverse-on-scroll-back hysteresis). */
  exitThreshold?: number;
  /**
   * Instance tag rendered above the outline — ties the outline to its
   * panel when several blocks are debugged at once.
   */
  label?: string;
  /**
   * Per-instance identity color (CSS color string) for the outline and
   * tag, so multiple debugged elements are distinguishable. State stays
   * on the phase-colored lines/zone.
   */
  accent?: string;
}

export interface ElementOverlay {
  /** Re-sync position/size with the observed element. */
  reposition: () => void;
  /** Update phase-driven colors on lines, labels, and zone. */
  setPhase: (phase: IntersectionPhase) => void;
  destroy: () => void;
}

/** Hide the secondary line when it would sit on top of the primary. */
const MIN_LINE_SEPARATION_PX = 20;

export const createElementOverlay = (
  target: HTMLElement,
  options: ElementOverlayOptions
): ElementOverlay => {
  const container = el('div', 'aa-dbg-element');
  if (options.accent) {
    container.style.setProperty('--aa-dbg-identity', options.accent);
  }

  const zone = el('div', 'aa-dbg-zone');

  const entryLine = el('div', 'aa-dbg-line aa-dbg-line--entry');
  const entryLabel = el(
    'span',
    'aa-dbg-line-label aa-dbg-line-label--entry',
    `Entry (bottom) ${Math.round(options.threshold * 100)}%`
  );

  const topEntryLine = el('div', 'aa-dbg-line aa-dbg-line--entry-top');
  const topEntryLabel = el(
    'span',
    'aa-dbg-line-label aa-dbg-line-label--entry-top',
    `Entry (top) ${Math.round(options.threshold * 100)}%`
  );

  container.append(zone, entryLine, entryLabel, topEntryLine, topEntryLabel);

  if (options.label) {
    container.appendChild(el('span', 'aa-dbg-element__tag', options.label));
  }

  let exitLine: HTMLElement | null = null;
  let exitLabel: HTMLElement | null = null;
  if (typeof options.exitThreshold === 'number') {
    exitLine = el('div', 'aa-dbg-line aa-dbg-line--exit');
    exitLabel = el(
      'span',
      'aa-dbg-line-label aa-dbg-line-label--exit',
      `Exit ≤ ${Math.round(options.exitThreshold * 100)}%`
    );
    container.append(exitLine, exitLabel);
  }

  document.body.appendChild(container);

  let currentPhase: IntersectionPhase = 'waiting';

  const reposition = (): void => {
    const rect = target.getBoundingClientRect();
    container.style.top = `${rect.top + window.scrollY}px`;
    container.style.left = `${rect.left + window.scrollX}px`;
    container.style.width = `${rect.width}px`;
    container.style.height = `${rect.height}px`;

    const height = rect.height;
    const entryY = height * options.threshold;
    const topEntryY = height * (1 - options.threshold);

    entryLine.style.top = `${entryY}px`;
    entryLabel.style.top = `${entryY}px`;
    zone.style.top = `${entryY}px`;
    zone.style.height = `${Math.max(0, height - entryY)}px`;

    const overlapsPrimary =
      Math.abs(topEntryY - entryY) < MIN_LINE_SEPARATION_PX;
    topEntryLine.hidden = overlapsPrimary;
    topEntryLabel.hidden = overlapsPrimary;
    topEntryLine.style.top = `${topEntryY}px`;
    topEntryLabel.style.top = `${topEntryY}px`;

    if (exitLine && exitLabel && typeof options.exitThreshold === 'number') {
      const exitY = height * options.exitThreshold;
      const exitOverlaps = Math.abs(exitY - entryY) < MIN_LINE_SEPARATION_PX;
      exitLine.hidden = exitOverlaps;
      exitLabel.hidden = exitOverlaps;
      exitLine.style.top = `${exitY}px`;
      exitLabel.style.top = `${exitY}px`;
    }
  };

  const setPhase = (phase: IntersectionPhase): void => {
    if (phase === currentPhase) {
      return;
    }
    container.classList.remove(`is-${currentPhase}`);
    container.classList.add(`is-${phase}`);
    currentPhase = phase;
  };

  container.classList.add(`is-${currentPhase}`);
  reposition();

  return {
    reposition,
    setPhase,
    destroy: () => container.remove(),
  };
};
