/**
 * Automatic-rules disclosure on the badge taxonomy screen.
 *
 * The rules card is server-rendered outside the React root (it posts to
 * admin-post.php on its own), so it keeps this small toggle instead of being
 * re-rendered by the studio.
 *
 * @package Aggressive_Apparel
 */

/** Wire the Configure/Close disclosure, if the card is on screen. */
export function initRulesPanel(): void {
  const toggle = document.querySelector<HTMLButtonElement>(
    '[data-badge-rules-toggle]'
  );
  const form = document.querySelector<HTMLFormElement>(
    '[data-badge-rules-form]'
  );
  if (!toggle || !form) return;

  toggle.addEventListener('click', () => {
    const open = form.hidden;
    form.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));

    const label = open ? toggle.dataset.closeLabel : toggle.dataset.openLabel;
    if (label) toggle.textContent = label;
  });
}
