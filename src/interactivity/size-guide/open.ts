/**
 * Size Guide — opening the guide from elsewhere on the page.
 *
 * Other controls (the "Size guide" link beside the size options, the Product
 * Specs footer link) open the guide by clicking its own trigger, so the store
 * keeps one code path and returns focus to whatever opened it. Plain DOM
 * listeners, not data-wp-on directives: these links can sit in kses-filtered
 * content, which strips directives.
 *
 * @package Aggressive_Apparel
 */

export const TRIGGER = '.aggressive-apparel-size-guide__trigger';
const LABEL_LINK = '.aa-size-label__link';
const PENDING = 'is-pending';

/** Open the size guide; false when the page has none. */
export function openSizeGuide(from: HTMLElement | null): boolean {
  const trigger = document.querySelector<HTMLElement>(TRIGGER);
  if (!trigger) {
    return false;
  }
  // The guide returns focus to whatever was focused when it opened; Safari
  // doesn't focus buttons on click, so make that the control used.
  from?.focus({ preventScroll: true });
  trigger.click();
  return true;
}

/** Reveal the size label links and handle their clicks (one listener). */
export function initLabelLinks(): void {
  const links = document.querySelectorAll<HTMLElement>(LABEL_LINK);
  if (links.length === 0 || !document.querySelector(TRIGGER)) {
    return;
  }
  links.forEach(link => link.classList.remove(PENDING));
  document.addEventListener('click', event => {
    const link = (event.target as Element | null)?.closest<HTMLElement>(
      LABEL_LINK
    );
    if (link) {
      openSizeGuide(link);
    }
  });
}
