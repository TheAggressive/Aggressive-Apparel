/**
 * Product Gallery lightbox stacking fix.
 *
 * WooCommerce's block product gallery renders a NON-modal `<dialog>` inline in
 * the page (it toggles the `open` attribute via the Interactivity API rather
 * than calling `showModal()`, so it never enters the browser top layer). On
 * this theme's single-product template the gallery sits inside a
 * `position: sticky` split-story column, and that sticky ancestor's stacking
 * context caps the dialog's z-index — so fixed commerce chrome (sticky
 * add-to-cart, bottom nav, free-shipping bar, mini-cart badge) and the sibling
 * summary column bleed through the open lightbox.
 *
 * We fix this WITHOUT touching the dialog (WooCommerce owns its open/close):
 * while the dialog is open we add an elevation class to its nearest positioned
 * ancestor, lifting that whole subtree — including the fixed dialog — above the
 * rest of the page. The class is removed the moment WooCommerce closes it. This
 * stays in sync through every close path (button, ESC, backdrop) because we
 * only ever read the dialog's `open` attribute.
 *
 * @package Aggressive_Apparel
 */

const DIALOG_SELECTOR = 'dialog.wc-block-product-gallery-dialog';
const ELEVATED_CLASS = 'aa-gallery-dialog-elevated';

/**
 * The dialog is `position: fixed`, but its stacking order is capped by the
 * nearest ancestor that forms a stacking context. Lifting the nearest
 * *positioned* ancestor (so a plain `z-index` actually applies) raises the
 * dialog's whole subtree above that ancestor's siblings, which is enough to
 * clear the page content and the fixed chrome.
 */
const POSITIONED = new Set(['relative', 'absolute', 'sticky', 'fixed']);

export function nearestPositionedAncestor(el: Element): HTMLElement | null {
  let node = el.parentElement;
  while (node && node !== document.body) {
    if (POSITIONED.has(getComputedStyle(node).position)) {
      return node;
    }
    node = node.parentElement;
  }
  return null;
}

export function syncDialog(dialog: HTMLElement): void {
  const ancestor = nearestPositionedAncestor(dialog);
  if (ancestor) {
    ancestor.classList.toggle(ELEVATED_CLASS, dialog.hasAttribute('open'));
  }
}

const watched = new WeakSet<HTMLElement>();

function watchDialog(dialog: HTMLElement): void {
  if (watched.has(dialog)) return;
  watched.add(dialog);
  new MutationObserver(() => syncDialog(dialog)).observe(dialog, {
    attributes: true,
    attributeFilter: ['open'],
  });
  syncDialog(dialog);
}

function boot(): void {
  // The dialog is server-rendered inside the gallery block, and every product
  // navigation is a full page load (native MPA View Transitions), so it always
  // exists by the time this footer script runs — no need to watch the document
  // for dynamically inserted dialogs.
  document.querySelectorAll<HTMLElement>(DIALOG_SELECTOR).forEach(watchDialog);
}

if (document.readyState !== 'loading') {
  boot();
} else {
  document.addEventListener('DOMContentLoaded', boot);
}
