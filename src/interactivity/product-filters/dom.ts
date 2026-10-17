/** Escape untrusted labels before inserting generated markup. */
export function escapeHtml(value: string): string {
  const element = document.createElement('div');
  element.textContent = value;
  return element.innerHTML;
}

/**
 * Fade and disable filter options rejected by a predicate.
 *
 * Unavailable options stay perceivable — announced as unavailable
 * (`aria-disabled`), not hidden from assistive tech — and leave the tab order.
 */
export function setFilterVisibility(
  selector: string,
  predicate: (element: HTMLElement) => boolean
): void {
  document.querySelectorAll<HTMLElement>(selector).forEach(element => {
    const visible = predicate(element);
    const unavailable = element.classList.contains('is-unavailable');
    if (!visible && !unavailable) {
      element.classList.add('is-unavailable');
      element.setAttribute('aria-disabled', 'true');
      element.tabIndex = -1;
    } else if (visible && unavailable) {
      element.classList.remove('is-unavailable');
      element.removeAttribute('aria-disabled');
      element.removeAttribute('tabindex');
    }
  });
}
