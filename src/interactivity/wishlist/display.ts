/** Shared formatting for account and browser wishlist items. */
import {
  decodeEntities,
  formatVariablePriceRange,
} from '@aggressive-apparel/helpers';
import type { StoreApiPrices } from '@aggressive-apparel/helpers';
import type { WishlistProduct } from './account';

export interface WishlistI18n {
  addLabel?: string;
  removeLabel?: string;
  removeItemLabel?: string;
  errorLabel?: string;
  chooseOptionsLabel?: string;
  viewProductLabel?: string;
  localSaveLabel?: string;
}

export interface WishlistDisplayItem {
  id: number;
  key?: string;
  canAddToCart: boolean;
  hasProductLink: boolean;
  hasProductFallback: boolean;
  viewProductLabel: string;
  storageNote: string;
  removeLabel: string;
  name: string;
  image: string;
  price: string;
  permalink: string;
  addToCartUrl: string;
}

/**
 * Format a Store API raw price (minor units) into a display string.
 */
function formatPrice(prices: StoreApiPrices | null): string {
  if (!prices || !prices.price) {
    return '';
  }
  const minorUnit = prices.currency_minor_unit ?? 2;
  const divisor = Math.pow(10, minorUnit);
  const value = (parseInt(prices.price, 10) / divisor).toFixed(minorUnit);
  const prefix = prices.currency_prefix ?? '$';
  const suffix = prices.currency_suffix ?? '';
  return `${prefix}${value}${suffix}`;
}

/**
 * Transform a Store API product into a simple object for template rendering.
 *
 * Variable products collapse to a shared "From $X" starting price when Smart
 * Price Display is on (matching every other surface); otherwise they keep the
 * existing single-value (minimum) formatting.
 */
export function formatWishlistItem(
  product: WishlistProduct,
  priceCfg: { collapseVariablePrice?: boolean; priceStartingPrefix?: string },
  account: boolean,
  getWishlistLabel: (key: keyof WishlistI18n, fallback: string) => string
): WishlistDisplayItem {
  const fromPrice =
    priceCfg.collapseVariablePrice &&
    product.type === 'variable' &&
    product.prices
      ? formatVariablePriceRange(product.prices, {
          collapse: true,
          prefix: priceCfg.priceStartingPrefix ?? 'From',
        })
      : null;

  let addToCartUrl = product.add_to_cart?.url || product.permalink || '';
  if (product.key && product.is_purchasable && product.permalink) {
    const url = new URL(product.permalink, location.href);
    url.searchParams.set(
      'add-to-cart',
      String(product.product_id || product.id)
    );
    if (product.variation_id) {
      url.searchParams.set('variation_id', String(product.variation_id));
      for (const attribute of product.variation || []) {
        url.searchParams.set(attribute.raw_attribute, attribute.value);
      }
    }
    addToCartUrl = url.href;
  }
  const canAddToCart =
    product.is_purchasable !== false &&
    product.is_in_stock !== false &&
    product.type !== 'variable';
  return {
    id: product.id,
    key: product.key,
    canAddToCart,
    hasProductFallback: Boolean(product.permalink) && !canAddToCart,
    viewProductLabel:
      product.type === 'variable'
        ? getWishlistLabel('chooseOptionsLabel', 'Choose options')
        : getWishlistLabel('viewProductLabel', 'View product'),
    storageNote:
      account && !product.key
        ? getWishlistLabel('localSaveLabel', 'Saved on this device')
        : '',
    hasProductLink: Boolean(product.permalink),
    removeLabel: getWishlistLabel(
      'removeItemLabel',
      'Remove %s from wishlist'
    ).replace('%s', decodeEntities(product.name)),

    name: [
      decodeEntities(product.name) || '',
      ...(product.variation || []).map(attribute =>
        decodeEntities(attribute.value)
      ),
    ]
      .filter(Boolean)
      .join(' / '),
    image:
      product.images && product.images.length > 0 ? product.images[0].src : '',
    price: fromPrice ?? formatPrice(product.prices),
    permalink: product.permalink || '#',
    addToCartUrl,
  };
}
