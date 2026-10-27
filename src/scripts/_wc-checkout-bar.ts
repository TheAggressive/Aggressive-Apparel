/**
 * WooCommerce's sticky mobile "Proceed to Checkout" bar, published for CSS.
 *
 * Below 783px WooCommerce pins the cart's checkout button to the bottom of
 * the viewport (`.wc-block-cart__submit-container--sticky`, z-index 9999)
 * once the in-flow button scrolls away. Bottom-anchored toasts must clear it,
 * so this mirrors its state the way the bottom nav and sticky add-to-cart do:
 * a body class plus a height variable (body-level `:has()` is banned).
 *
 * @package Aggressive_Apparel
 */

export const CHECKOUT_BAR_CLASS = 'aa-wc-checkout-bar-visible';
export const CHECKOUT_BAR_HEIGHT_VAR = '--aa-wc-checkout-bar-height';

/**
 * Mirror the sticky bar's visibility and height onto <body>.
 *
 * WooCommerce keeps the `--sticky` class above 782px but hides the bar there,
 * so a rendered height is what counts as visible.
 *
 * @param doc Document to sync.
 */
export function syncCheckoutBar(doc: Document = document): void {
  const bar = doc.querySelector<HTMLElement>(
    '.wc-block-cart__submit-container--sticky'
  );
  const height = bar ? Math.round(bar.getBoundingClientRect().height) : 0;

  doc.body.classList.toggle(CHECKOUT_BAR_CLASS, height > 0);

  if (height > 0) {
    doc.body.style.setProperty(CHECKOUT_BAR_HEIGHT_VAR, `${height}px`);
  } else {
    doc.body.style.removeProperty(CHECKOUT_BAR_HEIGHT_VAR);
  }
}

/**
 * Keep the body state in sync while the cart block renders and the shopper
 * scrolls or resizes. WooCommerce toggles the class from a scroll observer,
 * so class changes inside the cart block are the signal; syncs are batched to
 * one per frame.
 *
 * @param doc Document to watch.
 * @return Stop watching.
 */
export function watchCheckoutBar(doc: Document = document): () => void {
  const cart = doc.querySelector('.wp-block-woocommerce-cart');
  const view = doc.defaultView;

  if (!cart || !view) {
    return () => {};
  }

  let frame = 0;
  const schedule = () => {
    if (frame) {
      return;
    }
    frame = view.requestAnimationFrame(() => {
      frame = 0;
      syncCheckoutBar(doc);
    });
  };

  const observer = new view.MutationObserver(schedule);
  observer.observe(cart, {
    subtree: true,
    childList: true,
    attributes: true,
    attributeFilter: ['class'],
  });
  view.addEventListener('resize', schedule);
  schedule();

  return () => {
    observer.disconnect();
    view.removeEventListener('resize', schedule);
    if (frame) {
      view.cancelAnimationFrame(frame);
    }
  };
}
