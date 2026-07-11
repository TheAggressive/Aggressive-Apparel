/**
 * Debug-only IntersectionObserver probe.
 *
 * The production observers register only the thresholds they need to
 * act on, so their callbacks fire sparsely and the raw ratio is stale in
 * between. The probe watches the same element with the same rootMargin
 * but a dense threshold list, giving the debug UI a live, accurate ratio
 * WITHOUT touching production observer behavior.
 *
 * @package Aggressive Apparel
 */

export interface ProbeSample {
  ratio: number;
  /** Raw isIntersecting — NOT pre-thresholded by block logic. */
  isIntersecting: boolean;
}

export interface IntersectionProbe {
  disconnect: () => void;
}

/** 0, 0.01 … 1 — dense enough to look continuous while scrolling. */
const DENSE_THRESHOLDS = Array.from({ length: 101 }, (_, i) => i / 100);

export const createIntersectionProbe = (
  element: HTMLElement,
  rootMargin: string,
  onSample: (sample: ProbeSample) => void
): IntersectionProbe => {
  const observer = new IntersectionObserver(
    entries => {
      entries.forEach(entry => {
        onSample({
          ratio: entry.intersectionRatio,
          isIntersecting: entry.isIntersecting,
        });
      });
    },
    { threshold: DENSE_THRESHOLDS, rootMargin }
  );

  observer.observe(element);

  return {
    disconnect: () => observer.disconnect(),
  };
};
