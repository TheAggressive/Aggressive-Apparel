import {
  clamp,
  computeProgress,
  getPagedSnapTarget,
  getSlideIndexFromProgress,
  isEditableTarget,
  PAGED_COMMIT_RATIO,
} from '../logic';
import type {
  Controller,
  ControllerElements,
  Geometry,
  Presentation,
} from './types';

const GESTURE_IDLE_MS = 320;
const SNAP_FALLBACK_MS = 900;

/** One-adjacent-panel paging layered on top of the native sticky scroll range. */
export class PagedController implements Controller {
  private geometry: Geometry;
  private readonly abortController = new AbortController();
  private frame = 0;
  private gestureTimer = 0;
  private snapTimer = 0;
  private settledIndex: number;
  private targetIndex: number | null = null;
  private snapDirection: 1 | -1 | null = null;
  private awaitingGestureEnd = false;
  private snapping = false;
  private readonly supportsScrollEnd = 'onscrollend' in window;

  constructor(
    private readonly elements: ControllerElements,
    private readonly presentation: Presentation,
    geometry: Geometry
  ) {
    this.geometry = geometry;
    this.settledIndex = this.getNearestIndex();

    window.addEventListener('scroll', this.onScroll, {
      passive: true,
      signal: this.abortController.signal,
    });
    window.addEventListener('wheel', this.onWheel, {
      passive: false,
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

    this.render();
  };

  keydown = (event: KeyboardEvent): boolean => {
    if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') return false;
    if (isEditableTarget(event.target)) return false;

    const direction: 1 | -1 = event.key === 'ArrowRight' ? 1 : -1;
    const target = clamp(
      this.settledIndex + direction,
      0,
      this.geometry.slides.length - 1
    );

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
    this.snapDirection = delta > 0 ? 1 : -1;
    this.awaitingGestureEnd = true;
    this.scheduleGestureEnd();
    window.clearTimeout(this.snapTimer);
    this.snapTimer = window.setTimeout(this.finishSnap, SNAP_FALLBACK_MS);

    window.scrollTo({ top: targetPosition, behavior: 'smooth' });
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

    if (!this.isInsideRange()) return;

    const predictedProgress = this.getProgress(
      window.scrollY + this.getWheelPixelDelta(event)
    );
    const target = getPagedSnapTarget({
      progress: predictedProgress,
      settledIndex: this.settledIndex,
      slideStops: this.geometry.slideStops,
      commitRatio: PAGED_COMMIT_RATIO,
    });

    if (target === null) return;

    event.preventDefault();
    this.startSnap(target);
  };

  private onScroll = (): void => {
    this.scheduleRender();
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

    const target = getPagedSnapTarget({
      progress: rawProgress,
      settledIndex: this.settledIndex,
      slideStops: this.geometry.slideStops,
      commitRatio: PAGED_COMMIT_RATIO,
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
      `${Math.round(-target * 100) / 100}px`
    );
    this.presentation.setActive(
      getSlideIndexFromProgress(progress, this.geometry.slideStops)
    );
    this.presentation.setProgress(progress);
  };
}
