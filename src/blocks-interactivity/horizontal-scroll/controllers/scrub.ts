import {
  computeProgress,
  getSlideIndexFromProgress,
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

/**
 * Continuous "scrub" mode: the pinned track glides horizontally in lock-step
 * with vertical scroll.
 *
 * The track's horizontal position is a pure function of the document scroll
 * offset, applied once per animation frame via the `--aa-hscroll-x` custom
 * property (a `translate3d` on its own GPU layer). Reads are coalesced to a
 * single rAF per frame, and the presentation layer (progress bar + active-slide
 * announcement) is updated in the same pass.
 */
export class ScrubController implements Controller {
  private geometry: Geometry;
  private readonly abortController = new AbortController();
  private frame = 0;

  constructor(
    private readonly elements: ControllerElements,
    private readonly presentation: Presentation,
    geometry: Geometry
  ) {
    this.geometry = geometry;

    window.addEventListener('scroll', this.scheduleRender, {
      passive: true,
      signal: this.abortController.signal,
    });

    this.render();
  }

  updateGeometry = (geometry: Geometry): void => {
    this.geometry = geometry;
    this.render();
  };

  keydown = (event: KeyboardEvent): boolean => {
    if (isEditableTarget(event.target)) return false;

    const progress = computeProgress(
      window.scrollY,
      this.geometry.scrollStart,
      this.geometry.scrollDistance
    );
    const target = resolveKeyboardTarget({
      key: event.key,
      currentIndex: getSlideIndexFromProgress(
        progress,
        this.geometry.slideStops
      ),
      slideCount: this.geometry.slides.length,
      rtl: this.geometry.rtl,
    });
    if (target === null) return false;

    const targetProgress = this.geometry.slideStops[target] ?? 0;
    window.scrollTo({
      top:
        this.geometry.scrollStart +
        targetProgress * this.geometry.scrollDistance,
      behavior: 'smooth',
    });
    // Announce immediately; the smooth scroll (and per-frame render) catch up.
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
    paintScrollPosition(
      this.elements.ref,
      this.presentation,
      this.geometry,
      window.scrollY
    );
  };
}
