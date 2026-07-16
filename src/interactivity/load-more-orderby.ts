/**
 * Resolve the catalog orderby sent with Load More / infinite-scroll continuations.
 *
 * Prefer the SSR-seeded value over the sorting dropdown's "Default sorting"
 * option. That dropdown often reports `menu_order` even when the Product
 * Collection was rendered (and cursor-seeded) under a different sort — pairing
 * the wrong orderby with a valid-shaped cursor yields mostly duplicate cards
 * after client-side dedupe (often a single new product).
 *
 * An explicit `?orderby=` in the URL means the shopper chose a sort via the
 * WooCommerce form (full navigation) and must win.
 *
 * @param seededOrderby Orderby from interactivity state (SSR or filter sync).
 * @param selectValue   Current `.woocommerce-ordering` select value, if any.
 * @param urlOrderby    `orderby` query param from the current location, if any.
 * @return Catalog orderby slug safe to send to the rendered-products endpoint.
 */
export function resolveContinuationOrderby(
  seededOrderby: string,
  selectValue = '',
  urlOrderby = ''
): string {
  const fromUrl = urlOrderby.trim();
  if (
    fromUrl &&
    fromUrl !== 'featured' &&
    fromUrl !== 'savings' &&
    fromUrl !== 'relevance'
  ) {
    return fromUrl;
  }

  const seeded = seededOrderby.trim();
  if (seeded && seeded !== 'featured' && seeded !== 'savings') {
    return seeded;
  }

  const fromSelect = selectValue.trim();
  if (fromSelect && fromSelect !== 'featured' && fromSelect !== 'savings') {
    return fromSelect;
  }

  return 'menu_order';
}

/**
 * Whether the Load More button should be hidden for the current UI mode.
 *
 * Infinite scroll uses the sentinel + spinner; the button is only for
 * explicit `load_more` mode (or when the store falls back to it).
 */
export function shouldHideLoadMoreButton(
  mode: string,
  allLoaded: boolean
): boolean {
  return mode === 'infinite_scroll' || allLoaded;
}
