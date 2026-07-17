export interface Geometry {
  slides: HTMLElement[];
  slideStops: number[];
  maxTranslate: number;
  scrollDistance: number;
  scrollStart: number;
  /** Whether the block renders in a right-to-left writing direction. */
  rtl: boolean;
  /** Directional snap glide duration in milliseconds. */
  stepDurationMs: number;
}

export interface Controller {
  updateGeometry: (geometry: Geometry) => void;
  keydown: (event: KeyboardEvent) => boolean;
  /**
   * Jump to a slide by index (prev/next buttons, etc.). Returns false when the
   * index is unchanged or out of range.
   */
  goToIndex: (index: number) => boolean;
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
  /** Enable/disable prev/next controls for the current slide. */
  syncControls: (index: number, slideCount: number) => void;
}
