export interface Geometry {
  slides: HTMLElement[];
  slideStops: number[];
  maxTranslate: number;
  scrollDistance: number;
  scrollStart: number;
  /** Whether the block renders in a right-to-left writing direction. */
  rtl: boolean;
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

export interface Presentation {
  getIndex: () => number;
  setActive: (index: number, options?: { announce?: boolean }) => number;
  setProgress: (progress: number) => void;
  dismissSwipeHint: () => void;
}
