import type { SnapBehavior, SnapStrength } from '../logic';

export interface Geometry {
  slides: HTMLElement[];
  slideStops: number[];
  maxTranslate: number;
  scrollDistance: number;
  scrollStart: number;
}

export interface Controller {
  updateGeometry: (geometry: Geometry) => void;
  keydown: (event: KeyboardEvent) => boolean;
  destroy: () => void;
}

export interface ControllerElements {
  ref: HTMLElement;
  viewport: HTMLElement;
}

export interface ControllerOptions {
  snapBehavior: SnapBehavior;
  snapStrength: SnapStrength;
}

export interface Presentation {
  getIndex: () => number;
  setActive: (index: number, options?: { announce?: boolean }) => number;
  setProgress: (progress: number) => void;
  dismissSwipeHint: () => void;
}
