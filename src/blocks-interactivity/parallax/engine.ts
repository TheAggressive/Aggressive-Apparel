/**
 * Shared frame engine for all parallax instances on a page.
 *
 * One scroll listener, one pointer listener, one orientation listener and
 * one requestAnimationFrame loop drive every container. The loop runs in
 * two phases — read all layout values first, then write all styles — so
 * multiple instances never interleave reads and writes (layout thrash).
 * It self-suspends as soon as scrolling stops and the smoothed pointer
 * settles, so an idle page costs zero main-thread time.
 *
 * @package Aggressive Apparel
 */

import type { CachedLayer } from './layers';
import { applyLayerFrame } from './transforms';
import type { ParallaxContext } from './types';
import {
  clamp,
  measureProgressGeometry,
  progressFromGeometry,
  type ProgressGeometry,
} from './utils';

export interface ParallaxInstance {
  /** Block wrapper element (interactivity root). */
  root: HTMLElement;
  /** Inner `__container` element that receives the 3D card tilt. */
  container: HTMLElement | null;
  ctx: ParallaxContext;
  layers: CachedLayer[];
  /** Toggled by the IntersectionObserver; inactive instances are skipped. */
  active: boolean;
  /** Optional per-frame hook used by the (lazily loaded) debug tooling. */
  onFrame?: (progress: number) => void;

  // Engine-managed state below.
  /** Raw pointer target, -0.5..0.5. */
  pointerTargetX: number;
  pointerTargetY: number;
  /** Smoothed pointer, eased toward the target each frame. */
  pointerX: number;
  pointerY: number;
  progress: number;
  /**
   * Progress value at which every layer sits exactly where the editor
   * placed it (zero parallax offset). Calibrated once at init: blocks
   * already on screen at load use their load-time progress — so the
   * page renders exactly like the editor — while offscreen blocks use
   * 0.5 (layers rest at natural position mid-viewport).
   */
  baselineProgress: number;
  lastTiltX: number;
  lastTiltY: number;
  /**
   * Cached progress geometry — refreshed on resize, observer events, and
   * every GEOMETRY_REFRESH_FRAMES active frames. Keeps offset-chain walks
   * and boundary parsing out of the per-frame path.
   */
  geom: ProgressGeometry | null;
  framesSinceMeasure: number;
}

const POINTER_SETTLE_EPSILON = 0.0005;
const MAX_FRAME_DELTA_MS = 100;
/** Self-heal cadence for layout shifts the observers miss (~1s at 60Hz). */
const GEOMETRY_REFRESH_FRAMES = 60;

const instances = new Set<ParallaxInstance>();

let rafId: number | null = null;
let lastFrameTime = 0;
let listenersAttached = false;
let pointerListenersAttached = false;

const hasFinePointer = (): boolean =>
  window.matchMedia('(pointer: fine)').matches;

/** iOS requires a user-gesture permission prompt for orientation events. */
const orientationNeedsPermission = (): boolean =>
  typeof (
    DeviceOrientationEvent as unknown as {
      requestPermission?: () => Promise<string>;
    }
  ).requestPermission === 'function';

const requestTick = (): void => {
  if (rafId === null) {
    lastFrameTime = performance.now();
    rafId = requestAnimationFrame(tick);
  }
};

const handleScroll = (): void => {
  requestTick();
};

const handleResize = (): void => {
  // Viewport-relative boundary px and cached positions are stale now;
  // re-measured lazily on the next frame each instance renders.
  instances.forEach(instance => {
    instance.geom = null;
  });
  requestTick();
};

const handlePointerMove = (event: PointerEvent): void => {
  const targetX = clamp(event.clientX / window.innerWidth - 0.5, -0.5, 0.5);
  const targetY = clamp(event.clientY / window.innerHeight - 0.5, -0.5, 0.5);
  let needsFrame = false;
  instances.forEach(instance => {
    if (instance.ctx.enableMouseInteraction) {
      instance.pointerTargetX = targetX;
      instance.pointerTargetY = targetY;
      needsFrame = true;
    }
  });
  if (needsFrame) {
    requestTick();
  }
};

const handleOrientation = (event: DeviceOrientationEvent): void => {
  // Gamma: left/right tilt, Beta: front/back tilt. Map ±45° onto the same
  // -0.5..0.5 range the pointer uses.
  const targetX = clamp((event.gamma ?? 0) / 90, -0.5, 0.5);
  const targetY = clamp((event.beta ?? 0) / 90, -0.5, 0.5);
  let needsFrame = false;
  instances.forEach(instance => {
    if (instance.ctx.enableMouseInteraction) {
      instance.pointerTargetX = targetX;
      instance.pointerTargetY = targetY;
      needsFrame = true;
    }
  });
  if (needsFrame) {
    requestTick();
  }
};

const attachPointerListeners = (): void => {
  if (pointerListenersAttached) {
    return;
  }
  pointerListenersAttached = true;

  if (hasFinePointer()) {
    window.addEventListener('pointermove', handlePointerMove, {
      passive: true,
    });
  }

  // Device tilt as the pointer source on mobile. iOS gates this behind a
  // user-gesture permission prompt, so it is only attached where events
  // flow without one (Android, desktop browsers that expose the API).
  if (window.DeviceOrientationEvent && !orientationNeedsPermission()) {
    window.addEventListener('deviceorientation', handleOrientation, {
      passive: true,
    });
  }
};

const attachListeners = (): void => {
  if (listenersAttached) {
    return;
  }
  listenersAttached = true;
  window.addEventListener('scroll', handleScroll, { passive: true });
  window.addEventListener('resize', handleResize, { passive: true });
};

const detachAllListeners = (): void => {
  if (listenersAttached) {
    window.removeEventListener('scroll', handleScroll);
    window.removeEventListener('resize', handleResize);
    listenersAttached = false;
  }
  if (pointerListenersAttached) {
    window.removeEventListener('pointermove', handlePointerMove);
    window.removeEventListener('deviceorientation', handleOrientation);
    pointerListenersAttached = false;
  }
  if (rafId !== null) {
    cancelAnimationFrame(rafId);
    rafId = null;
  }
};

/**
 * Ease the smoothed pointer toward its target. Frame-rate independent:
 * `alpha = 1 - e^(-dt/tau)` converges identically at 60Hz and 144Hz.
 * Returns true while the pointer is still in motion.
 */
const smoothPointer = (
  instance: ParallaxInstance,
  deltaMs: number
): boolean => {
  const tau = Math.max(instance.ctx.transitionDuration ?? 0.1, 0.016) * 1000;
  const alpha = 1 - Math.exp(-deltaMs / tau);

  const dx = instance.pointerTargetX - instance.pointerX;
  const dy = instance.pointerTargetY - instance.pointerY;

  if (
    Math.abs(dx) < POINTER_SETTLE_EPSILON &&
    Math.abs(dy) < POINTER_SETTLE_EPSILON
  ) {
    instance.pointerX = instance.pointerTargetX;
    instance.pointerY = instance.pointerTargetY;
    return false;
  }

  instance.pointerX += dx * alpha;
  instance.pointerY += dy * alpha;
  return true;
};

/**
 * Write the container's 3D card tilt from the smoothed pointer. The tilt
 * lives on a container WITHOUT `preserve-3d` and WITHOUT `will-change`
 * (see style.css) — both of those make Chromium hit-test descendant links
 * at a location that diverges from where they paint, which was the
 * "only a corner of the button is clickable" bug.
 */
const writeContainerTilt = (instance: ParallaxInstance): void => {
  const container = instance.container;
  if (!container) {
    return;
  }
  const maxRotation = instance.ctx.maxMouseRotation ?? 5;
  const tiltX = instance.pointerY * maxRotation * 2;
  const tiltY = -instance.pointerX * maxRotation * 2;

  if (
    Math.abs(tiltX - instance.lastTiltX) > 0.005 ||
    Math.abs(tiltY - instance.lastTiltY) > 0.005
  ) {
    instance.lastTiltX = tiltX;
    instance.lastTiltY = tiltY;
    container.style.setProperty(
      '--parallax-card-rotate-x',
      `${tiltX.toFixed(3)}deg`
    );
    container.style.setProperty(
      '--parallax-card-rotate-y',
      `${tiltY.toFixed(3)}deg`
    );
  }
};

/** Read-phase queries for one instance (no style writes). */
const readInstance = (instance: ParallaxInstance): void => {
  // Measure rarely; the per-frame path is pure arithmetic on scrollY.
  if (
    !instance.geom ||
    ++instance.framesSinceMeasure >= GEOMETRY_REFRESH_FRAMES
  ) {
    instance.geom = measureProgressGeometry(
      instance.root,
      instance.ctx.detectionBoundary
    );
    instance.framesSinceMeasure = 0;
  }

  const { progress } = progressFromGeometry(
    instance.geom,
    window.scrollY,
    window.innerHeight,
    instance.ctx.visibilityTrigger
  );
  instance.progress = progress;

  if (instance.ctx.enableMouseInteraction) {
    instance.layers.forEach(layer => {
      if (layer.needsRect) {
        layer.rect = layer.element.getBoundingClientRect();
      }
    });
  }
};

/** Write-phase style application for one instance. */
const renderInstance = (instance: ParallaxInstance): void => {
  const is3D = Boolean(instance.ctx.enableMouseInteraction);
  if (is3D) {
    writeContainerTilt(instance);
  }

  const frame = {
    progress: instance.progress,
    baseline: instance.baselineProgress,
    pointerX: instance.pointerX,
    pointerY: instance.pointerY,
    is3D,
  };
  instance.layers.forEach(layer => applyLayerFrame(layer, frame, instance.ctx));
  instance.onFrame?.(instance.progress);
};

const tick = (now: number): void => {
  rafId = null;
  const deltaMs = Math.min(now - lastFrameTime, MAX_FRAME_DELTA_MS);
  lastFrameTime = now;

  let pointerInMotion = false;

  // READ phase: layout queries for every active instance, no style writes.
  instances.forEach(instance => {
    if (instance.active) {
      readInstance(instance);
    }
  });

  // WRITE phase: pointer smoothing + style application.
  instances.forEach(instance => {
    if (!instance.active) {
      return;
    }
    if (
      instance.ctx.enableMouseInteraction &&
      smoothPointer(instance, deltaMs)
    ) {
      pointerInMotion = true;
    }
    renderInstance(instance);
  });

  // Keep animating only while the pointer is still easing; scroll events
  // request their own ticks, so an idle page runs no frames at all.
  if (pointerInMotion) {
    rafId = requestAnimationFrame(tick);
  }
};

/**
 * Register an instance with the shared engine. Returns an unregister
 * callback; listeners are detached when the last instance leaves.
 */
export const registerInstance = (instance: ParallaxInstance): (() => void) => {
  instances.add(instance);
  attachListeners();
  if (instance.ctx.enableMouseInteraction) {
    attachPointerListeners();
  }
  requestTick();

  return () => {
    instances.delete(instance);
    if (instances.size === 0) {
      detachAllListeners();
    }
  };
};

/**
 * Calibrate the baseline and render one frame immediately, regardless of
 * active state. Called once at init: blocks visible at load anchor to
 * their load-time progress so the page opens looking exactly like the
 * editor; offscreen blocks anchor to mid-viewport. Effects (scroll
 * opacity, blur, depth scale) also start from their correct values.
 */
export const primeInstance = (instance: ParallaxInstance): void => {
  readInstance(instance);

  const rect = instance.root.getBoundingClientRect();
  const inInitialViewport = rect.top < window.innerHeight && rect.bottom > 0;
  instance.baselineProgress = inInitialViewport ? instance.progress : 0.5;

  renderInstance(instance);
};

/**
 * Cheap geometry refresh from an IntersectionObserver entry rect — the
 * rect is free (no forced layout) and the root carries no transforms,
 * so it is layout-true. Keeps cached positions honest when images load
 * or content above the block shifts.
 */
export const refreshInstanceGeometry = (
  instance: ParallaxInstance,
  rect: DOMRectReadOnly
): void => {
  if (instance.geom) {
    instance.geom.docTop = rect.top + window.scrollY;
    instance.geom.elementHeight = rect.height;
    instance.framesSinceMeasure = 0;
  }
};

/** Mark an instance (in)active — called by its IntersectionObserver. */
export const setInstanceActive = (
  instance: ParallaxInstance,
  active: boolean
): void => {
  if (instance.active === active) {
    return;
  }
  instance.active = active;
  instance.root.classList.toggle(
    'aggressive-apparel-parallax--intersecting',
    active
  );
  if (active) {
    requestTick();
  } else {
    // Render one last settled frame so layers freeze at their clamped
    // end position (0 or 1) instead of wherever a fast scroll left them.
    instance.pointerX = instance.pointerTargetX;
    instance.pointerY = instance.pointerTargetY;
    readInstance(instance);
    renderInstance(instance);
  }
};

/** Build a fresh engine instance record for a container. */
export const createInstance = (
  root: HTMLElement,
  container: HTMLElement | null,
  ctx: ParallaxContext,
  layers: CachedLayer[]
): ParallaxInstance => ({
  root,
  container,
  ctx,
  layers,
  active: false,
  pointerTargetX: 0,
  pointerTargetY: 0,
  pointerX: 0,
  pointerY: 0,
  progress: 0,
  baselineProgress: 0.5,
  lastTiltX: 0,
  lastTiltY: 0,
  geom: null,
  framesSinceMeasure: 0,
});
