import type { Controller, ControllerElements, Presentation } from './types';

export class StaticController implements Controller {
  constructor(
    private readonly elements: ControllerElements,
    private readonly presentation: Presentation
  ) {
    this.elements.ref.style.removeProperty('--aa-hscroll-x');
    this.elements.viewport.scrollLeft = 0;
    this.presentation.setActive(0);
    this.presentation.setProgress(0);
  }

  updateGeometry = (): void => {};
  keydown = (): boolean => false;
  destroy = (): void => {};
}
