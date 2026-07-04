import {
  clamp,
  getSlideIndexFromProgress,
  getSlideTarget,
  isEditableTarget,
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

  updateGeometry = (geometry: Geometry): void => {
    const previousProgress =
      this.geometry.maxTranslate > 0
        ? this.elements.viewport.scrollLeft / this.geometry.maxTranslate
        : 0;
    this.geometry = geometry;

    if (previousProgress > 0) {
      this.elements.viewport.scrollLeft =
        previousProgress * geometry.maxTranslate;
    }
    this.render();
  };

  keydown = (event: KeyboardEvent): boolean => {
    if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') return false;
    if (isEditableTarget(event.target)) return false;

    const target = clamp(
      this.presentation.getIndex() + (event.key === 'ArrowRight' ? 1 : -1),
      0,
      this.geometry.slides.length - 1
    );

    this.elements.viewport.scrollTo({
      left: getSlideTarget(
        target,
        this.geometry.slideStops,
        this.geometry.maxTranslate
      ),
      behavior: 'smooth',
    });
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
        ? clamp(
            this.elements.viewport.scrollLeft / this.geometry.maxTranslate,
            0,
            1
          )
        : 0;

    this.presentation.setActive(
      getSlideIndexFromProgress(progress, this.geometry.slideStops)
    );
    this.presentation.setProgress(progress);

    if (this.elements.viewport.scrollLeft > 1) {
      this.presentation.dismissSwipeHint();
    }
  };
}
