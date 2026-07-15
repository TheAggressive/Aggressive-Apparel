import {
  clamp,
  computeProgress,
  easeInOutCubic,
  getSlideIndexFromProgress,
  getStepScrollPosition,
  isEditableTarget,
  resolveKeyboardTarget,
} from '../logic';
import { paintScrollPosition } from './paint';
import type {
  Controller,
  ControllerElements,
  Geometry,
  Presentation,
} from './types';

/** Duration of one slide-to-slide glide. */
const STEP_DURATION_MS = 620;

/**
 * Idle window after a glide lands before the next gesture is accepted. Absorbs
 * the trailing wheel/trackpad momentum that follows a flick so it cannot queue
 * a second, unwanted step.
 */
const STEP_COOLDOWN_MS = 90;

/** Minimum touch travel (px) that counts as a deliberate swipe. */
const TOUCH_STEP_THRESHOLD_PX = 24;

/** Tolerance for treating the document as "within" the pinned range. */
const RANGE_EPSILON_PX = 2;

/**
 * Paged "step" mode: one deliberate gesture advances exactly one slide, which
 * glides left/right into place while further scroll input is ignored until it
 * lands.
 *
 * On a gesture it eases the document scroll from the current slide's stop to the
 * adjacent one with its own {@link easeInOutCubic} tween — never the browser's
 * untunable `scrollTo({ behavior: 'smooth' })` — and locks out all input for the
 * duration plus a short cooldown. The track transform (`--aa-hscroll-x`) is
 * updated in the same frames, so the slide glides in sync with the scroll.
 *
 * At the first/last slide a gesture in the outward direction is released (not
 * intercepted), so the page scrolls past the section normally.
 */
export class StepController implements Controller {
  private geometry: Geometry;
  private readonly abortController = new AbortController();

  private settledIndex: number;
  private insideRange: boolean;

  private renderFrame = 0;
  private tweenFrame = 0;
  private cooldownTimer = 0;

  /** A glide is currently animating the scroll position. */
  private animating = false;
  /** Input is suppressed (animating or cooling down). */
  private locked = false;

  private touchStartY = 0;
  private touchHandled = false;

  constructor(
    private readonly elements: ControllerElements,
    private readonly presentation: Presentation,
    geometry: Geometry
  ) {
    this.geometry = geometry;
    this.settledIndex = this.nearestIndex();
    this.insideRange = this.isInRange();
    this.reflectLockState();

    const { signal } = this.abortController;

    // Wheel is captured on the section only (non-passive so gestures can be
    // preventDefault-ed). A page-wide non-passive wheel listener would put the
    // whole document's scrolling on the slow path; while pinned the section
    // spans the viewport, so gesture wheels land here.
    this.elements.ref.addEventListener('wheel', this.onWheel, {
      passive: false,
      signal,
    });
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

    // Passive bookkeeping: our own tween, scrollbar drags, and find-in-page all
    // move the document; keep the presentation and settled index in sync.
    window.addEventListener('scroll', this.onScroll, { passive: true, signal });

    this.render();
  }

  updateGeometry = (geometry: Geometry): void => {
    const wasInside = this.isInRange();
    const previousProgress = computeProgress(
      window.scrollY,
      this.geometry.scrollStart,
      this.geometry.scrollDistance
    );

    this.cancelTween();
    this.geometry = geometry;
    this.settledIndex = clamp(
      this.settledIndex,
      0,
      Math.max(0, geometry.slides.length - 1)
    );

    // A resize can change the track distance; preserve the reader's position
    // without an animated jump.
    if (wasInside) {
      window.scrollTo({
        top: geometry.scrollStart + previousProgress * geometry.scrollDistance,
        behavior: 'auto',
      });
    } else {
      this.settledIndex = this.nearestIndex();
    }

    this.insideRange = this.isInRange();
    this.render();
  };

  keydown = (event: KeyboardEvent): boolean => {
    // Editable targets and non-navigation keys (Tab, typing) pass through.
    if (isEditableTarget(event.target)) return false;

    const target = resolveKeyboardTarget({
      key: event.key,
      currentIndex: this.settledIndex,
      slideCount: this.geometry.slides.length,
      rtl: this.geometry.rtl,
    });
    if (target === null) return false;

    // Swallow navigation keys mid-glide so they cannot queue another step, but
    // still report them as handled so the page does not also scroll.
    if (this.locked) return true;

    if (target !== this.settledIndex) {
      this.startStep(target);
    }
    return true;
  };

  destroy = (): void => {
    this.abortController.abort();
    window.cancelAnimationFrame(this.renderFrame);
    window.cancelAnimationFrame(this.tweenFrame);
    window.clearTimeout(this.cooldownTimer);
    this.elements.ref.removeAttribute('data-aa-hscroll-step-state');
  };

  private isInRange = (): boolean => {
    const { scrollStart, scrollDistance } = this.geometry;
    return (
      window.scrollY >= scrollStart - RANGE_EPSILON_PX &&
      window.scrollY <= scrollStart + scrollDistance + RANGE_EPSILON_PX
    );
  };

  private nearestIndex = (): number =>
    getSlideIndexFromProgress(
      computeProgress(
        window.scrollY,
        this.geometry.scrollStart,
        this.geometry.scrollDistance
      ),
      this.geometry.slideStops
    );

  private onWheel = (event: WheelEvent): void => {
    if (
      event.deltaY === 0 ||
      Math.abs(event.deltaX) > Math.abs(event.deltaY) ||
      isEditableTarget(event.target)
    ) {
      return;
    }

    // Swallow everything mid-glide/cooldown so momentum cannot queue steps.
    if (this.locked) {
      event.preventDefault();
      return;
    }

    if (!this.isInRange()) return;

    // Step in the wheel's direction; at a boundary, release to native scroll.
    if (this.stepInDirection(event.deltaY > 0 ? 1 : -1)) {
      event.preventDefault();
    }
  };

  private onTouchStart = (event: TouchEvent): void => {
    this.touchHandled = false;
    this.touchStartY = event.touches[0]?.clientY ?? 0;
  };

  private onTouchMove = (event: TouchEvent): void => {
    if (this.locked) {
      event.preventDefault();
      return;
    }

    if (this.touchHandled || !this.isInRange()) return;

    const currentY = event.touches[0]?.clientY ?? this.touchStartY;
    const delta = this.touchStartY - currentY;
    if (Math.abs(delta) < TOUCH_STEP_THRESHOLD_PX) return;

    // Step in the swipe's direction; at a boundary, release to native scroll.
    if (this.stepInDirection(delta > 0 ? 1 : -1)) {
      event.preventDefault();
      this.touchHandled = true;
    }
  };

  private onTouchEnd = (): void => {
    this.touchHandled = false;
  };

  private onScroll = (): void => {
    // Our tween owns the scroll while animating; ignore the events it emits.
    if (this.animating) return;

    this.scheduleRender();

    if (this.locked) return;

    const inside = this.isInRange();

    // On entering the pinned range, settle onto the nearest slide. This locks
    // in a clean starting position and absorbs the momentum of the flick that
    // carried the reader in, so the section never "grabs" on arrival.
    if (inside && !this.insideRange) {
      this.insideRange = true;
      this.startStep(this.nearestIndex());
      return;
    }

    this.insideRange = inside;
    if (inside) {
      this.settledIndex = this.nearestIndex();
    }
  };

  /**
   * Advance one slide in the given direction. Returns true when a glide was
   * started, or false at the first/last slide — in which case the caller leaves
   * the gesture alone so the page scrolls past the section.
   */
  private stepInDirection = (direction: 1 | -1): boolean => {
    const next = this.settledIndex + direction;
    if (next < 0 || next > this.geometry.slides.length - 1) return false;
    this.startStep(next);
    return true;
  };

  private startStep = (targetIndex: number): void => {
    const target = clamp(targetIndex, 0, this.geometry.slides.length - 1);
    const from = window.scrollY;
    const to = getStepScrollPosition(
      target,
      this.geometry.scrollStart,
      this.geometry.scrollDistance,
      this.geometry.slideStops
    );
    const distance = to - from;

    this.animating = true;
    this.setLocked(true);
    window.cancelAnimationFrame(this.tweenFrame);
    window.clearTimeout(this.cooldownTimer);

    if (Math.abs(distance) < 1) {
      this.finishStep(target);
      return;
    }

    const start = performance.now();
    const tick = (now: number): void => {
      const t = clamp((now - start) / STEP_DURATION_MS, 0, 1);
      const position = from + distance * easeInOutCubic(t);
      window.scrollTo(0, position);
      this.paint(position);

      if (t < 1) {
        this.tweenFrame = window.requestAnimationFrame(tick);
      } else {
        this.finishStep(target);
      }
    };
    this.tweenFrame = window.requestAnimationFrame(tick);
  };

  private finishStep = (target: number): void => {
    this.animating = false;
    this.tweenFrame = 0;
    this.settledIndex = target;
    this.presentation.setActive(target, { announce: true });

    // Hold the lock through a brief cooldown so trailing momentum is ignored.
    window.clearTimeout(this.cooldownTimer);
    this.cooldownTimer = window.setTimeout(() => {
      this.setLocked(false);
    }, STEP_COOLDOWN_MS);
  };

  private cancelTween = (): void => {
    window.cancelAnimationFrame(this.tweenFrame);
    window.clearTimeout(this.cooldownTimer);
    this.tweenFrame = 0;
    this.cooldownTimer = 0;
    this.animating = false;
    this.setLocked(false);
  };

  /** Keep the controller's input readiness observable for diagnostics and tests. */
  private setLocked = (locked: boolean): void => {
    this.locked = locked;
    this.reflectLockState();
  };

  private reflectLockState = (): void => {
    this.elements.ref.dataset.aaHscrollStepState = this.locked
      ? 'locked'
      : 'ready';
  };

  private scheduleRender = (): void => {
    if (this.renderFrame) return;
    this.renderFrame = window.requestAnimationFrame(() => {
      this.renderFrame = 0;
      this.paint(window.scrollY);
    });
  };

  private render = (): void => {
    this.paint(window.scrollY);
  };

  /**
   * Reflect a scroll position into the track transform and the presentation
   * layer. Active-slide changes here are silent; the settled announcement is
   * made once per landed step in {@link finishStep}.
   */
  private paint = (position: number): void => {
    paintScrollPosition(
      this.elements.ref,
      this.presentation,
      this.geometry,
      position
    );
  };
}
