/**
 * Shared jsdom helpers for hero-carousel store tests.
 *
 * @package Aggressive_Apparel
 */

/**
 * jsdom has no Element.scrollTo. Install a stub that updates scrollLeft and
 * optionally dispatches `scroll` (needed when autoplay/goTo must look like a
 * real track scroll).
 */
export function stubElementScrollTo(options?: {
  dispatchScroll?: boolean;
}): void {
  const dispatchScroll = options?.dispatchScroll ?? false;
  HTMLElement.prototype.scrollTo = function (
    this: HTMLElement,
    arg?: ScrollToOptions | number
  ) {
    const left = typeof arg === 'number' ? arg : (arg?.left ?? 0);
    Object.defineProperty(this, 'scrollLeft', {
      value: left,
      configurable: true,
      writable: true,
    });
    if (dispatchScroll) {
      this.dispatchEvent(new Event('scroll'));
    }
  };
}

/** Remove the scrollTo stub installed by {@link stubElementScrollTo}. */
export function restoreElementScrollTo(): void {
  Reflect.deleteProperty(HTMLElement.prototype, 'scrollTo');
}
