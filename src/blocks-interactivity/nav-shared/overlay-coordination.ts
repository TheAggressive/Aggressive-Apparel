/**
 * Overlay coordination between the mobile nav panel and higher z-index overlays.
 *
 * Overlays that sit above the nav (search, future modals) mark their root with
 * `data-aa-yields-nav-focus` and toggle `is-open` while active so the nav focus
 * trap yields instead of pulling focus back into the drawer.
 *
 * @package Aggressive_Apparel
 */

/** Search modal element id (portaled in wp_footer). */
export const SEARCH_MODAL_ID = 'aa-search-modal';

/** Body class while search is open — disables nav pointer-events via CSS. */
export const SEARCH_OPEN_BODY_CLASS = 'has-search-modal-open';

/** Attribute marking an overlay that keeps focus above the nav panel. */
export const YIELDS_NAV_FOCUS_ATTR = 'data-aa-yields-nav-focus';

/** Selector for overlays that yield nav focus while open. */
export const YIELDS_NAV_FOCUS_SELECTOR = `[${YIELDS_NAV_FOCUS_ATTR}]`;

/**
 * Whether focus may live in an overlay above the nav panel instead of the drawer.
 */
export function isWithinOverlayThatYieldsNavFocus(
  target: HTMLElement
): boolean {
  const overlay = target.closest<HTMLElement>(YIELDS_NAV_FOCUS_SELECTOR);

  return Boolean(
    overlay &&
    !overlay.hasAttribute('hidden') &&
    overlay.classList.contains('is-open')
  );
}
