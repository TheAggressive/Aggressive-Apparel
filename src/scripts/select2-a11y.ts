/**
 * selectWoo (WooCommerce's select2) accessibility.
 *
 * selectWoo names the `role="combobox"` element after the field label, then
 * nests a `role="textbox" aria-readonly` span inside it that has no name
 * (axe: aria-input-field-name). A read-only textbox inside a combobox is
 * noise to assistive tech — the combobox already exposes the value and
 * carries aria-required — so the nested role and its ARIA state are dropped.
 * selectWoo builds a fresh container whenever a field is re-initialised (the
 * State field after a country change), hence the observer on each form that
 * holds a select.
 *
 * @package Aggressive_Apparel
 */

const RENDERED_SELECTOR =
  '.select2-selection[role="combobox"] .select2-selection__rendered[role="textbox"]';

/**
 * Strip the nameless nested textbox role inside `root`.
 */
export function stripNestedTextboxRoles(root: ParentNode = document): void {
  root.querySelectorAll(RENDERED_SELECTOR).forEach(node => {
    node.removeAttribute('role');
    node.removeAttribute('aria-readonly');
    node.removeAttribute('aria-required');
  });
}

/**
 * Fix existing selects and watch forms for re-initialised ones.
 */
export function initSelect2A11y(): void {
  stripNestedTextboxRoles();

  document.querySelectorAll('form').forEach(form => {
    if (!form.querySelector('select')) {
      return;
    }

    new MutationObserver(() => stripNestedTextboxRoles(form)).observe(form, {
      childList: true,
      subtree: true,
    });
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initSelect2A11y);
} else {
  initSelect2A11y();
}
