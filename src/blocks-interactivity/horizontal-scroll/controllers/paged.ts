import {
  clamp,
  computeProgress,
  getPagedSnapTarget,
  getSlideIndexFromProgress,
  isEditableTarget,
  toSignedTranslate,
} from '../logic';
import type {
  Controller,
  ControllerElements,
  ControllerOptions,
  Geometry,
  Presentation,
} from './types';

const GESTURE_IDLE_MS = 320;
const SNAP_FALLBACK_MS = 900;

/**
 * Grace period after the document scrolls into the pinned range before
 * paging may commit. Without it, the same flick that carries the user into
 * the section can immediately cross the commit threshold and snap to the
 * next slide — reading as the section "grabbing" the page on arrival.
 */
const ENTRY_COMMIT_GRACE_MS = 250;

/**
 * Maximum fallback-timer re-arms while a smooth scroll is still travelling.
 * Guards Safari (no scrollend) against declaring a long snap finished early —
 * and against re-arming forever if the scroll never arrives.
 */
const SNAP_FALLBACK_MAX_ATTEMPTS = 6;

/** One-adjacent-panel paging layered on top of the native sticky scroll range. */
export class PagedController implements Controller {
  private geometry: Geometry;
  private readonly abortController = new AbortController();
  private frame = 0;
  private gestureTimer = 0;
  private snapTimer = 0;
  private settledIndex: number;
  private targetIndex: number | null = null;
  private snapTargetPosition = 0;
  private snapFallbackAttempts = 0;
  private snapDirection: 1 | -1 | null = null;
  private awaitingGestureEnd = false;
  private snapping = false;
  private insideRange: boolean;
  private entryGraceUntil = 0;
  private readonly supportsScrollEnd = 'onscrollend' in window;

  constructor(
    private readonly elements: ControllerElements,
    private readonly presentation: Presentation,
    geometry: Geometry,
    private readonly options: ControllerOptions
  ) {
    this.geometry = geometry;
    this.settledIndex = this.getNearestIndex();
    // No grace on initial load (deep links can land inside the range with no
    // gesture in flight); grace arms only on outside → inside transitions.
    this.insideRange = this.isInsideRange();

    window.addEventListener('scroll', this.onScroll, {
      passive: true,
      signal: this.abortController.signal,
    });

    // The cancelable listener is scoped to the block: a page-wide non-passive
    // wheel listener would put every scroll on the slow path for the whole
    // document (and compound per block instance). While pinned, the section
    // spans the viewport, so gesture wheels land here. A passive window
    // listener below covers bookkeeping for wheels outside the section.
    this.elements.ref.addEventListener('wheel', this.onWheel, {
      passive: false,
      signal: this.abortController.signal,
    });
    window.addEventListener('wheel', this.onWindowWheel, {
      passive: true,
      signal: this.abortController.signal,
    });

    if (this.supportsScrollEnd) {
      window.addEventListener('scrollend', this.finishSnap, {
        signal: this.abortController.signal,
      });
    }

    this.render();
  }

  updateGeometry = (geometry: Geometry): void => {
    const previous = this.geometry;
    const previousProgress = computeProgress(
      window.scrollY,
      previous.scrollStart,
      previous.scrollDistance
    );
    const wasInside = this.isInsideRange(previous);

    this.cancelSnap();
    this.geometry = geometry;
    this.settledIndex = clamp(
      this.settledIndex,
      0,
      Math.max(0, geometry.slides.length - 1)
    );

    if (wasInside) {
      window.scrollTo({
        top: geometry.scrollStart + previousProgress * geometry.scrollDistance,
        behavior: 'auto',
      });
    } else {
      this.settledIndex = this.getNearestIndex();
    }

    // Resize/layout shifts are not an entry gesture — refresh presence
    // against the new geometry without arming the grace window.
    this.insideRange = this.isInsideRange();

    this.render();
  };

  keydown = (event: KeyboardEvent): boolean => {
    if (isEditableTarget(event.target)) return false;

    const lastIndex = this.geometry.slides.length - 1;
    let target: number;

    if (event.key === 'Home') {
      target = 0;
    } else if (event.key === 'End') {
      target = lastIndex;
    } else if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
      // In RTL, ArrowRight moves toward the previous (visually right) slide.
      const direction =
        (event.key === 'ArrowRight' ? 1 : -1) * (this.geometry.rtl ? -1 : 1);
      target = clamp(this.settledIndex + direction, 0, lastIndex);
    } else {
      return false;
    }

    if (target !== this.settledIndex) {
      this.startSnap(target);
    }
    return true;
  };

  destroy = (): void => {
    this.abortController.abort();
    window.cancelAnimationFrame(this.frame);
    window.clearTimeout(this.gestureTimer);
    window.clearTimeout(this.snapTimer);
  };

  private isInsideRange = (geometry: Geometry = this.geometry): boolean =>
    window.scrollY >= geometry.scrollStart &&
    window.scrollY <= geometry.scrollStart + geometry.scrollDistance;

  /**
   * Track outside → inside transitions of the pinned range and arm the
   * entry-commit grace window on each one.
   */
  private updateRangePresence = (): void => {
    const inside = this.isInsideRange();

    if (inside && !this.insideRange) {
      this.entryGraceUntil = performance.now() + ENTRY_COMMIT_GRACE_MS;
    }

    this.insideRange = inside;
  };

  private isEntryGraceActive = (): boolean =>
    performance.now() < this.entryGraceUntil;

  private getProgress = (scrollPosition: number = window.scrollY): number =>
    computeProgress(
      scrollPosition,
      this.geometry.scrollStart,
      this.geometry.scrollDistance
    );

  private getNearestIndex = (): number =>
    getSlideIndexFromProgress(this.getProgress(), this.geometry.slideStops);

  private getWheelPixelDelta = (event: WheelEvent): number => {
    if (event.deltaMode === WheelEvent.DOM_DELTA_LINE) {
      return event.deltaY * 16;
    }
    if (event.deltaMode === WheelEvent.DOM_DELTA_PAGE) {
      return event.deltaY * window.innerHeight;
    }
    return event.deltaY;
  };

  private scheduleGestureEnd = (): void => {
    window.clearTimeout(this.gestureTimer);
    this.gestureTimer = window.setTimeout(() => {
      this.awaitingGestureEnd = false;
    }, GESTURE_IDLE_MS);
  };

  private cancelSnap = (): void => {
    window.clearTimeout(this.snapTimer);
    this.snapTimer = 0;
    this.snapping = false;
    this.targetIndex = null;
    this.snapDirection = null;
  };

  private cancelSnapAtCurrentPosition = (wheelPixelDelta: number): void => {
    const currentPosition = window.scrollY;
    this.cancelSnap();
    this.awaitingGestureEnd = false;
    window.clearTimeout(this.gestureTimer);
    window.scrollTo({
      top: currentPosition + wheelPixelDelta,
      behavior: 'auto',
    });
  };

  private startSnap = (targetIndex: number): void => {
    const target = clamp(targetIndex, 0, this.geometry.slides.length - 1);
    const targetProgress = this.geometry.slideStops[target] ?? 0;
    const targetPosition =
      this.geometry.scrollStart + targetProgress * this.geometry.scrollDistance;
    const delta = targetPosition - window.scrollY;

    if (Math.abs(delta) < 1) {
      this.settledIndex = target;
      this.presentation.setActive(target, { announce: true });
      return;
    }

    this.snapping = true;
    this.targetIndex = target;
    this.snapTargetPosition = targetPosition;
    this.snapFallbackAttempts = 0;
    this.snapDirection = delta > 0 ? 1 : -1;
    this.awaitingGestureEnd = true;
    this.scheduleGestureEnd();
    window.clearTimeout(this.snapTimer);
    this.snapTimer = window.setTimeout(this.onSnapFallback, SNAP_FALLBACK_MS);

    window.scrollTo({ top: targetPosition, behavior: 'smooth' });
  };

  /**
   * Fallback completion for browsers without scrollend (Safari). Finishing
   * on a fixed timer alone would settle long smooth scrolls mid-flight and
   * bounce the index backwards, so re-arm while still travelling; if the
   * scroll never arrives (interrupted some other way), give up and settle to
   * wherever the document actually is.
   */
  private onSnapFallback = (): void => {
    if (!this.snapping || this.targetIndex === null) return;

    const stillTravelling =
      Math.abs(window.scrollY - this.snapTargetPosition) > 2;

    if (
      stillTravelling &&
      this.snapFallbackAttempts < SNAP_FALLBACK_MAX_ATTEMPTS
    ) {
      this.snapFallbackAttempts += 1;
      this.snapTimer = window.setTimeout(this.onSnapFallback, SNAP_FALLBACK_MS);
      return;
    }

    if (stillTravelling) {
      this.cancelSnap();
      this.settledIndex = this.getNearestIndex();
      this.presentation.setActive(this.settledIndex, { announce: true });
      return;
    }

    this.finishSnap();
  };

  private finishSnap = (): void => {
    if (!this.snapping || this.targetIndex === null) return;

    window.clearTimeout(this.snapTimer);
    this.snapTimer = 0;
    this.settledIndex = this.targetIndex;
    this.presentation.setActive(this.settledIndex, { announce: true });
    this.snapping = false;
    this.targetIndex = null;
  };

  private onWheel = (event: WheelEvent): void => {
    if (
      event.deltaY === 0 ||
      Math.abs(event.deltaX) > Math.abs(event.deltaY) ||
      isEditableTarget(event.target)
    ) {
      return;
    }

    const direction: 1 | -1 = event.deltaY > 0 ? 1 : -1;

    if (this.snapping) {
      if (direction === this.snapDirection) {
        event.preventDefault();
        this.scheduleGestureEnd();
      } else {
        event.preventDefault();
        this.cancelSnapAtCurrentPosition(this.getWheelPixelDelta(event));
      }
      return;
    }

    if (this.awaitingGestureEnd) {
      if (direction === this.snapDirection) {
        event.preventDefault();
        this.scheduleGestureEnd();
        return;
      }

      this.awaitingGestureEnd = false;
      window.clearTimeout(this.gestureTimer);
    }

    this.updateRangePresence();
    if (!this.insideRange || this.isEntryGraceActive()) return;

    const predictedProgress = this.getProgress(
      window.scrollY + this.getWheelPixelDelta(event)
    );
    const target = getPagedSnapTarget({
      progress: predictedProgress,
      settledIndex: this.settledIndex,
      slideStops: this.geometry.slideStops,
      commitRatio: this.options.commitRatio,
    });

    if (target === null) return;

    event.preventDefault();
    this.startSnap(target);
  };

  /**
   * Passive bookkeeping for wheels landing outside the section (possible
   * with center/bottom activation, where the pinned panel does not span the
   * full viewport). Cannot preventDefault; opposite-direction input simply
   * releases the snap and lets native scrolling win.
   */
  private onWindowWheel = (event: WheelEvent): void => {
    if (
      this.elements.ref.contains(event.target as Node) ||
      event.deltaY === 0 ||
      Math.abs(event.deltaX) > Math.abs(event.deltaY)
    ) {
      return;
    }

    const direction: 1 | -1 = event.deltaY > 0 ? 1 : -1;

    if (this.snapping) {
      if (direction === this.snapDirection) {
        this.scheduleGestureEnd();
      } else {
        this.cancelSnap();
        this.awaitingGestureEnd = false;
        window.clearTimeout(this.gestureTimer);
      }
      return;
    }

    if (this.awaitingGestureEnd && direction !== this.snapDirection) {
      this.awaitingGestureEnd = false;
      window.clearTimeout(this.gestureTimer);
    }
  };

  private onScroll = (): void => {
    this.scheduleRender();
    this.updateRangePresence();
    if (this.snapping || this.awaitingGestureEnd) return;

    const rawProgress =
      (window.scrollY - this.geometry.scrollStart) /
      this.geometry.scrollDistance;
    if (rawProgress < 0) {
      this.settledIndex = 0;
      return;
    }
    if (rawProgress > 1) {
      this.settledIndex = Math.max(0, this.geometry.slides.length - 1);
      return;
    }

    if (this.isEntryGraceActive()) return;

    const target = getPagedSnapTarget({
      progress: rawProgress,
      settledIndex: this.settledIndex,
      slideStops: this.geometry.slideStops,
      commitRatio: this.options.commitRatio,
    });
    if (target !== null) {
      this.startSnap(target);
    }
  };

  private scheduleRender = (): void => {
    if (this.frame) return;
    this.frame = window.requestAnimationFrame(this.render);
  };

  private render = (): void => {
    this.frame = 0;
    const progress = this.getProgress();
    const target = progress * this.geometry.maxTranslate;

    this.elements.ref.style.setProperty(
      '--aa-hscroll-x',
      `${toSignedTranslate(target, this.geometry.rtl)}px`
    );
    this.presentation.setActive(
      getSlideIndexFromProgress(progress, this.geometry.slideStops)
    );
    this.presentation.setProgress(progress);
  };
}
