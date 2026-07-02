/** Escape untrusted labels before inserting generated markup. */
export function escapeHtml(value: string): string {
  const element = document.createElement('div');
  element.textContent = value;
  return element.innerHTML;
}

/** Fade and disable filter controls rejected by a predicate. */
export function setFilterVisibility(
  selector: string,
  predicate: (element: HTMLElement) => boolean
): void {
  document.querySelectorAll<HTMLElement>(selector).forEach(element => {
    const visible = predicate(element);
    const unavailable = element.classList.contains('is-unavailable');
    if (!visible && !unavailable) {
      element.classList.add('is-unavailable');
      element.setAttribute('aria-hidden', 'true');
      element.tabIndex = -1;
    } else if (visible && unavailable) {
      element.classList.remove('is-unavailable');
      element.removeAttribute('aria-hidden');
      element.removeAttribute('tabindex');
    }
  });
}
