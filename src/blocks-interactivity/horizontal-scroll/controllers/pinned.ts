import {
  clamp,
  computeProgress,
  getProximitySnapTarget,
  getSnapStrengthPreset,
  getSlideIndexFromProgress,
  isEditableTarget,
} from '../logic';
import type {
  Controller,
  ControllerElements,
  ControllerOptions,
  Geometry,
  Presentation,
} from './types';

const SETTLE_DEBOUNCE_MS = 160;

/** Native vertical document progress driving a sticky horizontal track. */
export class PinnedController implements Controller {
  private geometry: Geometry;
  private readonly abortController = new AbortController();
  private frame = 0;
  private settleTimer = 0;
  private snapping = false;
  private snapTargetIndex: number | null = null;
  private suppressNextSettle = false;
  private readonly supportsScrollEnd = 'onscrollend' in window;

  constructor(
    private readonly elements: ControllerElements,
    private readonly presentation: Presentation,
    geometry: Geometry,
    private readonly options: ControllerOptions
  ) {
    this.geometry = geometry;
    window.addEventListener('scroll', this.onScroll, {
      passive: true,
      signal: this.abortController.signal,
    });

    if (options.snapBehavior === 'proximity') {
      if (this.supportsScrollEnd) {
        window.addEventListener('scrollend', this.onScrollSettled, {
          signal: this.abortController.signal,
        });
      }

      window.addEventListener('wheel', this.onUserInput, {
        passive: true,
        signal: this.abortController.signal,
      });
      window.addEventListener('touchstart', this.onUserInput, {
        passive: true,
        signal: this.abortController.signal,
      });
      window.addEventListener('pointerdown', this.onUserInput, {
        passive: true,
        signal: this.abortController.signal,
      });
      window.addEventListener('keydown', this.onUserInput, {
        signal: this.abortController.signal,
      });
    }

    this.render();
  }

  updateGeometry = (geometry: Geometry): void => {
    this.suppressNextSettle = false;
    this.cancelSettle();
    const previous = this.geometry;
    const previousProgress = computeProgress(
      window.scrollY,
      previous.scrollStart,
      previous.scrollDistance
    );
    const wasInside =
      window.scrollY > previous.scrollStart &&
      window.scrollY < previous.scrollStart + previous.scrollDistance;

    this.geometry = geometry;

    // A resize can alter the track distance. Preserve the reader's horizontal
    // position without introducing an animated page movement.
    if (wasInside) {
      window.scrollTo({
        top: geometry.scrollStart + previousProgress * geometry.scrollDistance,
        behavior: 'auto',
      });
    }

    this.render();
  };

  keydown = (event: KeyboardEvent): boolean => {
    if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') return false;
    if (isEditableTarget(event.target)) return false;

    const progress = computeProgress(
      window.scrollY,
      this.geometry.scrollStart,
      this.geometry.scrollDistance
    );
    const current = getSlideIndexFromProgress(
      progress,
      this.geometry.slideStops
    );
    const target = clamp(
      current + (event.key === 'ArrowRight' ? 1 : -1),
      0,
      this.geometry.slides.length - 1
    );
    const targetProgress = this.geometry.slideStops[target] ?? 0;

    window.scrollTo({
      top:
        this.geometry.scrollStart +
        targetProgress * this.geometry.scrollDistance,
      behavior: 'smooth',
    });
    return true;
  };

  destroy = (): void => {
    this.abortController.abort();
    window.cancelAnimationFrame(this.frame);
    window.clearTimeout(this.settleTimer);
  };

  private cancelSettle = (): void => {
    window.clearTimeout(this.settleTimer);
    this.settleTimer = 0;
    this.snapping = false;
    this.snapTargetIndex = null;
  };

  private onUserInput = (): void => {
    if (this.snapping) {
      this.suppressNextSettle = true;
      const currentPosition = window.scrollY;
      this.cancelSettle();
      window.scrollTo({ top: currentPosition, behavior: 'auto' });
      return;
    }
    this.cancelSettle();
  };

  private onScroll = (): void => {
    this.scheduleRender();

    if (this.options.snapBehavior !== 'proximity' || this.supportsScrollEnd) {
      return;
    }

    window.clearTimeout(this.settleTimer);
    this.settleTimer = window.setTimeout(
      this.onScrollSettled,
      SETTLE_DEBOUNCE_MS
    );
  };

  private onScrollSettled = (): void => {
    window.clearTimeout(this.settleTimer);
    this.settleTimer = 0;

    if (this.suppressNextSettle) {
      this.suppressNextSettle = false;
      return;
    }

    if (this.snapping) {
      this.snapping = false;
      if (this.snapTargetIndex !== null) {
        this.presentation.setActive(this.snapTargetIndex, { announce: true });
      }
      this.snapTargetIndex = null;
      return;
    }

    if (this.options.snapBehavior !== 'proximity') return;

    const preset = getSnapStrengthPreset(this.options.snapStrength);

    const target = getProximitySnapTarget({
      scrollPosition: window.scrollY,
      scrollStart: this.geometry.scrollStart,
      scrollDistance: this.geometry.scrollDistance,
      slideStops: this.geometry.slideStops,
      maxDistance: preset.maxDistance,
      maxSegmentRatio: preset.maxSegmentRatio,
    });
    if (!target) return;

    this.snapping = true;
    this.snapTargetIndex = target.index;
    window.scrollTo({
      top: target.scrollPosition,
      behavior: 'smooth',
    });
  };

  private scheduleRender = (): void => {
    if (this.frame) return;
    this.frame = window.requestAnimationFrame(this.render);
  };

  private render = (): void => {
    this.frame = 0;
    const progress = computeProgress(
      window.scrollY,
      this.geometry.scrollStart,
      this.geometry.scrollDistance
    );
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
