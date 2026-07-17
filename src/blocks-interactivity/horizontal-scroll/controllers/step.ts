import {
  clamp,
  computeProgress,
  getSlideIndexFromProgress,
  getStepScrollPosition,
  isEditableTarget,
  isScrollInPinnedRange,
  resolveEntrySlideIndex,
  resolveKeyboardTarget,
} from '../logic';
import { paintScrollPosition } from './paint';
import { StepCoast } from './step-coast';
import {
  CLAMP_DRIFT_PX,
  RANGE_SLACK_PX,
  REDUCED_MOTION_QUERY,
  STOP_EPSILON_PX,
  TOUCH_STEP_THRESHOLD_PX,
  WHEEL_INTENT_PX,
} from './step-constants';
import { startScrollTween } from './step-tween';
import { normalizeWheelDeltaY, shouldListenWindowWheel } from './step-wheel';
import type {
  Controller,
  ControllerElements,
  Geometry,
  Presentation,
} from './types';

/**
 * Directional snap controller — enterprise paging model:
 *
 * - While the document is inside the gallery's scroll range, we own vertical
 *   wheel, touch, and keyboard (Arrow / Page / Home / End).
 * - One intentional gesture → one slide. Mid-glide input queues at most one
 *   extra step so holding Arrow Down / a trackpad flick walks slides without
 *   feeling dead or skipping.
 * - Enter: seat on the edge/nearest slide immediately, no coast lock.
 * - Exit: at the first/last slide, same-direction input releases ownership
 *   with an exit latch so we do not immediately reclaim.
 * - Multiple instances on one page are safe: each ignores events outside its
 *   own range; only the owning controller preventDefaults.
 * - Prefers-reduced-motion (or stepDurationMs 0) snaps instantly.
 * - Non-passive window wheel is bound only near the pin range (hysteresis),
 *   and while owning/animating, so the rest of the page stays cheap.
 */
export class StepController implements Controller {
  private geometry: Geometry;
  private readonly abortController = new AbortController();
  private readonly coast = new StepCoast();

  private settledIndex: number;
  private owning = false;
  /**
   * After an intentional exit at the first/last slide, ignore re-entry until
   * scrollY leaves the range. Prevents release → immediate reclaim thrash.
   */
  private exitLatch = false;

  private animating = false;
  private tweenFrame = 0;
  private tweenGeneration = 0;
  private clampFrame = 0;

  /** Window wheel capture is active only when near / owning the range. */
  private windowWheelBound = false;

  /** Queued step direction while a glide is in flight (at most one). */
  private pendingDirection: 1 | -1 | 0 = 0;

  private touchStartY = 0;
  private touchHandled = false;
  private destroyed = false;

  constructor(
    private readonly elements: ControllerElements,
    private readonly presentation: Presentation,
    geometry: Geometry
  ) {
    this.geometry = geometry;
    this.settledIndex = this.nearestIndex();
    this.owning = this.isInRange();

    const { signal } = this.abortController;

    window.addEventListener('scroll', this.onScroll, { passive: true, signal });

    this.elements.ref.addEventListener('touchstart', this.onTouchStart, {
      passive: true,
      signal,
    });
    this.elements.ref.addEventListener('touchmove', this.onTouchMove, {
      passive: false,
      signal,
    });
    this.elements.ref.addEventListener('touchend', this.onTouchEnd, {
      passive: true,
      signal,
    });

    this.syncWindowWheel();
    this.reflectState();
    if (this.owning) {
      // Quiet seat on construct — announcements are reserved for user motion.
      this.seatNow(this.settledIndex, { announce: false });
    } else {
      this.render();
    }
  }

  updateGeometry = (geometry: Geometry): void => {
    const wasOwning = this.owning;

    this.cancelTween();
    this.geometry = geometry;
    this.settledIndex = clamp(
      this.settledIndex,
      0,
      Math.max(0, geometry.slides.length - 1)
    );

    if (wasOwning) {
      window.scrollTo({
        top: this.stopPosition(this.settledIndex),
        behavior: 'auto',
      });
    } else {
      this.settledIndex = this.nearestIndex();
    }

    this.owning = this.isInRange();
    this.syncWindowWheel();
    this.render();
  };

  /**
   * Shared keyboard handler (window capture + runtime focus path).
   * Returns true when the event was consumed.
   */
  keydown = (event: KeyboardEvent): boolean => {
    if (this.destroyed || this.abortController.signal.aborted) return false;
    if (isEditableTarget(event.target)) return false;

    const target = resolveKeyboardTarget({
      key: event.key,
      currentIndex: this.settledIndex,
      slideCount: this.geometry.slides.length,
      rtl: this.geometry.rtl,
    });
    if (target === null) return false;

    // Only own keys while inside (or mid-glide). Outside → page scrolls.
    // After an exit latch, still allow keys while physically in-range so
    // End / opposite arrows can reclaim without requiring a wheel re-entry.
    if (!this.owning && !this.animating) {
      if (!this.isInRange()) return false;
      this.exitLatch = false;
      this.owning = true;
    }

    if (this.animating) {
      const direction: 1 | -1 | 0 =
        target > this.settledIndex ? 1 : target < this.settledIndex ? -1 : 0;
      if (direction !== 0) this.pendingDirection = direction;
      return true;
    }

    if (target === this.settledIndex) {
      // Boundary: release so Arrow Up/Down can leave the gallery.
      this.releaseOwnership(true);
      return false;
    }

    this.coast.clear();
    this.startStep(target);
    return true;
  };

  goToIndex = (index: number): boolean => {
    if (this.destroyed || this.abortController.signal.aborted) return false;

    const target = clamp(index, 0, this.geometry.slides.length - 1);
    if (target === this.settledIndex && this.isAtStop(target)) return false;

    if (this.animating) {
      this.pendingDirection =
        target > this.settledIndex ? 1 : target < this.settledIndex ? -1 : 0;
      return true;
    }

    this.coast.clear();
    this.owning = true;
    this.startStep(target);
    return true;
  };

  destroy = (): void => {
    if (this.destroyed) return;
    this.destroyed = true;
    this.cancelTween();
    this.cancelClamp();
    this.syncWindowWheel();
    this.abortController.abort();
    this.elements.ref.removeAttribute('data-aa-hscroll-step-state');
  };

  /**
   * Bind non-passive window wheel only while near / owning the pin range.
   * Hysteresis (enter vs exit slack) avoids bind/unbind thrash at the edge.
   */
  private shouldListenWheel = (): boolean =>
    shouldListenWindowWheel({
      destroyed: this.destroyed,
      animating: this.animating,
      owning: this.owning,
      windowWheelBound: this.windowWheelBound,
      scrollY: window.scrollY,
      scrollStart: this.geometry.scrollStart,
      scrollDistance: this.geometry.scrollDistance,
    });

  private syncWindowWheel = (): void => {
    const want = this.shouldListenWheel();
    if (want && !this.windowWheelBound) {
      window.addEventListener('wheel', this.onWheel, {
        passive: false,
        capture: true,
      });
      this.windowWheelBound = true;
    } else if (!want && this.windowWheelBound) {
      window.removeEventListener('wheel', this.onWheel, { capture: true });
      this.windowWheelBound = false;
    }
  };

  private isInRange = (): boolean =>
    isScrollInPinnedRange({
      scrollY: window.scrollY,
      scrollStart: this.geometry.scrollStart,
      scrollDistance: this.geometry.scrollDistance,
      slackPx: RANGE_SLACK_PX,
    });

  private nearestIndex = (): number =>
    getSlideIndexFromProgress(
      computeProgress(
        window.scrollY,
        this.geometry.scrollStart,
        this.geometry.scrollDistance
      ),
      this.geometry.slideStops
    );

  private stopPosition = (index: number): number =>
    getStepScrollPosition(
      index,
      this.geometry.scrollStart,
      this.geometry.scrollDistance,
      this.geometry.slideStops
    );

  private isAtStop = (index: number): boolean =>
    Math.abs(window.scrollY - this.stopPosition(index)) < STOP_EPSILON_PX;

  private prefersReducedMotion = (): boolean => {
    if (typeof window.matchMedia !== 'function') return false;
    return window.matchMedia(REDUCED_MOTION_QUERY).matches;
  };

  private effectiveStepDurationMs = (): number => {
    if (this.prefersReducedMotion()) return 0;
    return Math.max(0, this.geometry.stepDurationMs);
  };

  private onWheel = (event: WheelEvent): void => {
    if (this.destroyed || this.abortController.signal.aborted) return;
    // Another instance (or handler) already claimed this gesture.
    if (event.defaultPrevented) return;
    if (isEditableTarget(event.target)) return;
    if (Math.abs(event.deltaX) > Math.abs(event.deltaY)) return;

    const deltaY = normalizeWheelDeltaY(event, window.innerHeight);
    if (deltaY === 0) return;

    const direction: 1 | -1 = deltaY > 0 ? 1 : -1;

    if (this.animating) {
      event.preventDefault();
      if (Math.abs(deltaY) >= WHEEL_INTENT_PX) {
        this.pendingDirection = direction;
      }
      return;
    }

    if (!this.owning) {
      if (this.exitLatch) {
        // Let the page scroll out; clear the latch once we're truly outside.
        if (!this.isInRange()) this.exitLatch = false;
        return;
      }
      if (!this.isInRange()) return;
      // Take ownership and seat — this gesture is the entry, not a dead zone.
      this.takeOwnership(direction);
      event.preventDefault();
      return;
    }

    if (this.coast.consume(direction)) {
      if (this.canStep(direction)) {
        event.preventDefault();
        this.scheduleClamp();
        return;
      }
      // Coasting at the end → release and let the page continue.
      this.releaseOwnership();
      return;
    }

    if (Math.abs(deltaY) < WHEEL_INTENT_PX) {
      event.preventDefault();
      this.scheduleClamp();
      return;
    }

    if (this.stepInDirection(direction)) {
      event.preventDefault();
      return;
    }

    // First/last slide + same direction: leave the gallery cleanly.
    this.releaseOwnership();
  };

  private onTouchStart = (event: TouchEvent): void => {
    this.touchHandled = false;
    this.touchStartY = event.touches[0]?.clientY ?? 0;
  };

  private onTouchMove = (event: TouchEvent): void => {
    if (this.animating) {
      event.preventDefault();
      return;
    }

    if (this.touchHandled) return;

    const currentY = event.touches[0]?.clientY ?? this.touchStartY;
    const delta = this.touchStartY - currentY;
    const direction: 1 | -1 = delta >= 0 ? 1 : -1;

    if (!this.owning) {
      if (this.exitLatch) {
        if (!this.isInRange()) this.exitLatch = false;
        return;
      }
      if (!this.isInRange()) return;
      if (Math.abs(delta) < TOUCH_STEP_THRESHOLD_PX) {
        event.preventDefault();
        return;
      }
      this.takeOwnership(direction);
      event.preventDefault();
      this.touchHandled = true;
      return;
    }

    if (Math.abs(delta) < TOUCH_STEP_THRESHOLD_PX) {
      event.preventDefault();
      return;
    }

    if (this.coast.consume(direction)) {
      if (this.canStep(direction)) {
        event.preventDefault();
        return;
      }
      this.releaseOwnership();
      return;
    }

    if (this.stepInDirection(direction)) {
      event.preventDefault();
      this.touchHandled = true;
      return;
    }

    this.releaseOwnership();
  };

  private onTouchEnd = (): void => {
    this.touchHandled = false;
  };

  private onScroll = (): void => {
    if (!this.animating) {
      const inRange = this.isInRange();

      if (!inRange) {
        this.exitLatch = false;
        if (this.owning) this.releaseOwnership(false);
      } else if (!this.owning) {
        // Don't reclaim while the user is scrolling out through the band.
        if (!this.exitLatch) {
          this.takeOwnership(0);
        }
      } else {
        this.scheduleClamp();
      }
    }

    this.syncWindowWheel();
  };

  /**
   * Become the active paging region. Seats on the nearest slide with no coast
   * lock so the next gesture is live immediately.
   */
  private takeOwnership = (entryDirection: 1 | -1 | 0): void => {
    this.owning = true;
    this.exitLatch = false;
    this.coast.clear();
    this.pendingDirection = 0;

    if (this.geometry.slides.length === 0) {
      this.reflectState();
      return;
    }

    const seated = resolveEntrySlideIndex({
      entryDirection,
      nearestIndex: this.nearestIndex(),
      scrollY: window.scrollY,
      scrollStart: this.geometry.scrollStart,
      scrollDistance: this.geometry.scrollDistance,
      slideCount: this.geometry.slides.length,
      slackPx: RANGE_SLACK_PX,
    });

    if (seated !== this.settledIndex || !this.isAtStop(seated)) {
      // Entry settle — no coast afterward (armCoast: false).
      this.startStep(seated, { armCoast: false });
      return;
    }

    // Already seated — quiet (no live-region spam on reclaim).
    this.seatNow(seated, { announce: false });
    this.syncWindowWheel();
  };

  /**
   * @param latch - When true (intentional exit at a boundary), stay released
   * until scrollY leaves the range so we do not immediately reclaim.
   */
  private releaseOwnership = (latch = true): void => {
    this.owning = false;
    this.exitLatch = latch;
    this.coast.clear();
    this.pendingDirection = 0;
    this.cancelClamp();
    this.reflectState();
    this.syncWindowWheel();
  };

  private seatNow = (
    index: number,
    options: { announce?: boolean } = {}
  ): void => {
    this.settledIndex = clamp(index, 0, this.geometry.slides.length - 1);
    const exact = this.stopPosition(this.settledIndex);
    window.scrollTo(0, exact);
    this.paint(exact);
    this.presentation.setActive(this.settledIndex, {
      announce: options.announce ?? false,
    });
    this.animating = false;
    this.reflectState();
  };

  private scheduleClamp = (): void => {
    if (this.clampFrame || this.animating || !this.owning) return;
    this.clampFrame = window.requestAnimationFrame(() => {
      this.clampFrame = 0;
      if (this.animating || !this.owning) return;
      const exact = this.stopPosition(this.settledIndex);
      if (Math.abs(window.scrollY - exact) > CLAMP_DRIFT_PX) {
        window.scrollTo(0, exact);
      }
      this.paint(exact);
    });
  };

  private cancelClamp = (): void => {
    window.cancelAnimationFrame(this.clampFrame);
    this.clampFrame = 0;
  };

  private canStep = (direction: 1 | -1): boolean => {
    const next = this.settledIndex + direction;
    return next >= 0 && next <= this.geometry.slides.length - 1;
  };

  private stepInDirection = (direction: 1 | -1): boolean => {
    if (!this.canStep(direction)) return false;
    this.startStep(this.settledIndex + direction);
    return true;
  };

  private startStep = (
    targetIndex: number,
    options: { armCoast?: boolean } = {}
  ): void => {
    if (this.geometry.slides.length === 0) return;

    const shouldArmCoast = options.armCoast !== false;
    const target = clamp(targetIndex, 0, this.geometry.slides.length - 1);
    const from = window.scrollY;
    const to = this.stopPosition(target);

    const stepDirection: 1 | -1 | 0 =
      target > this.settledIndex ? 1 : target < this.settledIndex ? -1 : 0;
    this.coast.direction = stepDirection;
    this.coast.clear();
    this.cancelClamp();
    this.pendingDirection = 0;
    this.owning = true;
    this.animating = true;
    this.reflectState();
    this.syncWindowWheel();

    this.tweenGeneration += 1;
    const generation = this.tweenGeneration;
    window.cancelAnimationFrame(this.tweenFrame);
    this.tweenFrame = 0;

    const frame = startScrollTween({
      from,
      to,
      durationMs: this.effectiveStepDurationMs(),
      callbacks: {
        isCurrent: () => generation === this.tweenGeneration,
        onFrame: position => {
          window.scrollTo(0, position);
          this.paint(position);
        },
        onComplete: () => {
          this.finishStep(target, generation, shouldArmCoast);
        },
      },
    });

    // Sync completion may have already started a pending step — don't clobber.
    if (generation === this.tweenGeneration) {
      this.tweenFrame = frame;
    }
  };

  private finishStep = (
    target: number,
    generation: number,
    armCoast: boolean
  ): void => {
    if (generation !== this.tweenGeneration) return;

    this.tweenFrame = 0;
    this.settledIndex = target;
    this.owning = true;
    this.animating = false;

    const exact = this.stopPosition(target);
    window.scrollTo(0, exact);
    this.paint(exact);
    this.presentation.setActive(target, { announce: true });

    if (this.pendingDirection !== 0 && this.canStep(this.pendingDirection)) {
      const next = this.settledIndex + this.pendingDirection;
      this.pendingDirection = 0;
      this.startStep(next);
      return;
    }

    this.pendingDirection = 0;
    if (armCoast) {
      this.coast.arm(this.coast.direction);
    } else {
      this.coast.clear();
    }
    this.reflectState();
    this.syncWindowWheel();
  };

  private cancelTween = (): void => {
    this.tweenGeneration += 1;
    window.cancelAnimationFrame(this.tweenFrame);
    this.tweenFrame = 0;
    this.animating = false;
    this.pendingDirection = 0;
    this.coast.reset();
    this.reflectState();
    this.syncWindowWheel();
  };

  private reflectState = (): void => {
    this.elements.ref.dataset.aaHscrollStepState = this.animating
      ? 'locked'
      : 'ready';
  };

  private render = (): void => {
    this.paint(this.stopPosition(this.settledIndex));
  };

  private paint = (position: number): void => {
    paintScrollPosition(
      this.elements.ref,
      this.presentation,
      this.geometry,
      position
    );
  };
}
