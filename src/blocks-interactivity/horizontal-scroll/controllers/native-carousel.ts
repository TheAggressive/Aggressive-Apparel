import {
  clamp,
  getSlideIndexFromProgress,
  getSlideTarget,
  isEditableTarget,
  resolveKeyboardTarget,
} from '../logic';
import type {
  Controller,
  ControllerElements,
  Geometry,
  Presentation,
} from './types';

export class NativeCarouselController implements Controller {
  private geometry: Geometry;
  private readonly abortController = new AbortController();
  private frame = 0;

  constructor(
    private readonly elements: ControllerElements,
    private readonly presentation: Presentation,
    geometry: Geometry
  ) {
    this.geometry = geometry;
    this.elements.viewport.addEventListener('scroll', this.scheduleRender, {
      passive: true,
      signal: this.abortController.signal,
    });
    this.render();
  }

  /** Logical scroll distance from the inline-start edge (RTL-safe). */
  private getScrolled = (): number =>
    Math.abs(this.elements.viewport.scrollLeft);

  /** Scroll to a logical offset — RTL viewports scroll into negative left. */
  private scrollToOffset = (offset: number, behavior: ScrollBehavior): void => {
    this.elements.viewport.scrollTo({
      left: this.geometry.rtl ? -offset : offset,
      behavior,
    });
  };

  updateGeometry = (geometry: Geometry): void => {
    const previousProgress =
      this.geometry.maxTranslate > 0
        ? this.getScrolled() / this.geometry.maxTranslate
        : 0;
    this.geometry = geometry;

    if (previousProgress > 0) {
      this.scrollToOffset(previousProgress * geometry.maxTranslate, 'auto');
    }
    this.render();
  };

  keydown = (event: KeyboardEvent): boolean => {
    if (isEditableTarget(event.target)) return false;

    const target = resolveKeyboardTarget({
      key: event.key,
      currentIndex: this.presentation.getIndex(),
      slideCount: this.geometry.slides.length,
      rtl: this.geometry.rtl,
    });
    if (target === null) return false;

    this.scrollToOffset(
      getSlideTarget(
        target,
        this.geometry.slideStops,
        this.geometry.maxTranslate
      ),
      'smooth'
    );
    // Announce immediately so keyboard users hear the new position.
    this.presentation.setActive(target, { announce: true });
    return true;
  };

  destroy = (): void => {
    this.abortController.abort();
    window.cancelAnimationFrame(this.frame);
  };

  private scheduleRender = (): void => {
    if (this.frame) return;
    this.frame = window.requestAnimationFrame(this.render);
  };

  private render = (): void => {
    this.frame = 0;
    const progress =
      this.geometry.maxTranslate > 0
        ? clamp(this.getScrolled() / this.geometry.maxTranslate, 0, 1)
        : 0;

    this.presentation.setActive(
      getSlideIndexFromProgress(progress, this.geometry.slideStops)
    );
    this.presentation.setProgress(progress);

    if (this.getScrolled() > 1) {
      this.presentation.dismissSwipeHint();
    }
  };
}
