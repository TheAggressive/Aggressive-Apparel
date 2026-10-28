/**
 * @jest-environment jsdom
 */

import {
  CHECKOUT_BAR_CLASS,
  CHECKOUT_BAR_HEIGHT_VAR,
  syncCheckoutBar,
} from '../_wc-checkout-bar';

const setBar = (height: number | null) => {
  document.body.innerHTML =
    height === null
      ? '<div class="wp-block-woocommerce-cart"></div>'
      : '<div class="wp-block-woocommerce-cart"><div class="wc-block-cart__submit-container wc-block-cart__submit-container--sticky"></div></div>';

  const bar = document.querySelector<HTMLElement>(
    '.wc-block-cart__submit-container--sticky'
  );
  if (bar && height !== null) {
    bar.getBoundingClientRect = () => ({ height }) as DOMRect;
  }
};

afterEach(() => {
  document.body.className = '';
  document.body.removeAttribute('style');
});

describe('syncCheckoutBar', () => {
  it('publishes the class and height while the sticky bar shows', () => {
    setBar(78.4);
    syncCheckoutBar();

    expect(document.body.classList.contains(CHECKOUT_BAR_CLASS)).toBe(true);
    expect(document.body.style.getPropertyValue(CHECKOUT_BAR_HEIGHT_VAR)).toBe(
      '78px'
    );
  });

  it('clears both when WooCommerce hides the bar (wide screens)', () => {
    setBar(78);
    syncCheckoutBar();
    setBar(0);
    syncCheckoutBar();

    expect(document.body.classList.contains(CHECKOUT_BAR_CLASS)).toBe(false);
    expect(document.body.style.getPropertyValue(CHECKOUT_BAR_HEIGHT_VAR)).toBe(
      ''
    );
  });

  it('clears both when the button is in view (no sticky class)', () => {
    setBar(78);
    syncCheckoutBar();
    setBar(null);
    syncCheckoutBar();

    expect(document.body.classList.contains(CHECKOUT_BAR_CLASS)).toBe(false);
  });
});
