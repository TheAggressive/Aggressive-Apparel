/**
 * Cart Order Meta — extra lines in the cart and checkout totals.
 *
 * Fills WooCommerce's ExperimentalOrderMeta slot (inside the order-summary
 * card; blocks.css moves the footer total after it) with:
 *
 * - Free-shipping progress (cart only), with the free-shipping blocks' own
 *   wording and math, read from the theme's Store API cart extension. On
 *   desktop it shows in the card beside the total; on phones the card sits
 *   below every item, so the same component portals into a strip above the
 *   cart (CSS shows one or the other at WooCommerce's 920px breakpoint).
 * - "You save": the sum of the line items' sale savings (cart and checkout).
 *
 * Both render from the live cart the slot passes in, so they follow quantity
 * changes without fetching anything.
 *
 * @package Aggressive_Apparel
 */

import { registerPlugin } from '@wordpress/plugins';
import { createPortal, useEffect, useState } from '@wordpress/element';
import type { ComponentType, ReactNode } from 'react';
import { getCartSavings, type SavingsCartItem } from './_cart-savings-total';
import { watchCheckoutBar } from './_wc-checkout-bar';
import {
  buildFreeShippingSegments,
  parseCartTotals,
  progressPercent,
  type CartResponse,
  type CartTotals,
  type CurrencyFormat,
  type FreeShippingMessageI18n,
} from '../blocks-interactivity/free-shipping-message/cart-data';

/** Props the slot injects into its fill (absent on the element we create). */
interface SlotFillProps {
  cart?: {
    cartItems: SavingsCartItem[];
    cartTotals: CartTotals & Record<string, unknown>;
  };
  extensions?: CartResponse['extensions'];
  context?: string;
}

/** Server data printed before the script (Cart_Order_Meta). */
interface CartOrderMetaData {
  i18n: {
    youSave: string;
    shippingProgress: string;
  };
  shipping: CurrencyFormat & {
    emphasisText: string;
    i18n: FreeShippingMessageI18n;
  };
}

interface WooGlobals {
  blocksCheckout?: {
    ExperimentalOrderMeta?: ComponentType<{ children: ReactNode }>;
  };
  priceFormat?: {
    formatPrice: (price: number, currency: unknown) => string;
    getCurrencyFromPriceResponse: (response: unknown) => unknown;
  };
}

const STRIP_CLASS = 'aa-cart-shipping-strip';

const globals = window as unknown as {
  wc?: WooGlobals;
  aaCartOrderMeta?: CartOrderMetaData;
};
const OrderMeta = globals.wc?.blocksCheckout?.ExperimentalOrderMeta;
const priceFormat = globals.wc?.priceFormat;
const data = globals.aaCartOrderMeta;

/**
 * The strip host above the cart block: a sibling of WooCommerce's React root,
 * so its re-renders never touch it. Created once, wide-aligned like the cart.
 *
 * @param enabled Whether a strip is wanted (cart context only).
 */
function useStripHost(enabled: boolean): HTMLElement | null {
  const [host, setHost] = useState<HTMLElement | null>(null);

  useEffect(() => {
    if (!enabled) {
      return;
    }

    const cart = document.querySelector('.wp-block-woocommerce-cart');
    if (!cart) {
      return;
    }

    let strip = cart.previousElementSibling;
    if (
      !(strip instanceof HTMLElement) ||
      !strip.classList.contains(STRIP_CLASS)
    ) {
      strip = document.createElement('div');
      strip.className = `${STRIP_CLASS} alignwide`;
      cart.before(strip);
    }

    setHost(strip as HTMLElement);
  }, [enabled]);

  return host;
}

/**
 * Free-shipping progress: message, then bar. Hidden when this customer's zone
 * has no free-shipping threshold.
 *
 * @param props            Slot fill props.
 * @param props.cart       Live Store API cart.
 * @param props.extensions Store API cart extensions.
 * @param props.context    'woocommerce/cart' or 'woocommerce/checkout'.
 */
function CartShippingProgress({ cart, extensions, context }: SlotFillProps) {
  const isCart = context === 'woocommerce/cart';
  const host = useStripHost(isCart);

  if (!cart || !data || !isCart) {
    return null;
  }

  const parsed = parseCartTotals(
    { totals: cart.cartTotals, extensions },
    0,
    data.shipping
  );

  if (!parsed || parsed.threshold <= 0) {
    return null;
  }

  const percent = progressPercent(parsed);
  const segments = buildFreeShippingSegments({
    ...parsed,
    emphasisText: data.shipping.emphasisText,
    i18n: data.shipping.i18n,
  });
  const state = parsed.complete ? ' is-complete' : '';
  const body = (
    <>
      <p className='aa-cart-shipping-meta__message' aria-live='polite'>
        {segments.map(segment => (
          <span
            key={segment.key}
            className={
              segment.amount || segment.emphasis
                ? 'aa-cart-shipping-meta__strong'
                : undefined
            }
          >
            {segment.text}
          </span>
        ))}
      </p>
      <div
        className='aa-cart-shipping-meta__track'
        role='progressbar'
        aria-valuemin={0}
        aria-valuemax={100}
        aria-valuenow={Math.round(percent)}
        aria-label={data.i18n.shippingProgress}
      >
        <div
          className='aa-cart-shipping-meta__progress'
          style={{ width: `${percent}%` }}
        />
      </div>
    </>
  );

  return (
    <>
      <div
        className={`wc-block-components-totals-wrapper aa-cart-shipping-meta aa-cart-shipping-meta--card${state}`}
      >
        {body}
      </div>
      {host &&
        createPortal(
          <div
            className={`aa-cart-shipping-meta aa-cart-shipping-meta--strip${state}`}
          >
            {body}
          </div>,
          host
        )}
    </>
  );
}

/**
 * Savings row, styled as one of WooCommerce's own totals items.
 *
 * @param props      Slot fill props.
 * @param props.cart Live Store API cart.
 */
function CartSavings({ cart }: SlotFillProps) {
  if (!cart || !priceFormat || !data) {
    return null;
  }

  const savings = getCartSavings(cart.cartItems);

  if (savings <= 0) {
    return null;
  }

  const currency = priceFormat.getCurrencyFromPriceResponse(cart.cartTotals);

  return (
    <div className='wc-block-components-totals-wrapper aa-cart-savings'>
      <div className='wc-block-components-totals-item'>
        <span className='wc-block-components-totals-item__label'>
          {data.i18n.youSave}
        </span>
        <span className='wc-block-components-totals-item__value'>
          {priceFormat.formatPrice(savings, currency)}
        </span>
      </div>
    </div>
  );
}

if (OrderMeta) {
  registerPlugin('aggressive-apparel-cart-order-meta', {
    render: () => (
      <OrderMeta>
        <CartShippingProgress />
        <CartSavings />
      </OrderMeta>
    ),
    scope: 'woocommerce-checkout',
  });
}

watchCheckoutBar();
