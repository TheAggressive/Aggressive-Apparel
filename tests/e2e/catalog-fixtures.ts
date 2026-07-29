import { wpCli } from './wp-cli';

/**
 * Ensure the wp-env shop catalogue can exercise Load More / infinite scroll.
 *
 * Soft-skips in catalog-cursor-pagination.spec.ts hide real regressions when
 * the env has too few products or is stuck in button mode. Run from global
 * setup so every catalog e2e starts from a known floor.
 */

const MIN_PRODUCTS = 24;
const LOAD_MORE_MODE_OPTION = 'aggressive_apparel_load_more_mode';

function publishedProductCount(): number {
  try {
    const out = wpCli([
      'post',
      'list',
      '--post_type=product',
      '--post_status=publish',
      '--format=count',
    ]);
    const n = Number.parseInt(out, 10);
    return Number.isFinite(n) ? n : 0;
  } catch {
    return 0;
  }
}

function ensureInfiniteScrollMode(): void {
  wpCli(['option', 'update', LOAD_MORE_MODE_OPTION, 'infinite_scroll']);
}

function createSimpleProducts(startIndex: number, count: number): void {
  wpCli([
    'eval',
    `if (!function_exists('wc_get_product')) {
	throw new RuntimeException('WooCommerce product APIs are unavailable.');
}

for ($offset = 0; $offset < ${count}; $offset++) {
	$index = ${startIndex} + $offset;
	$product = new WC_Product_Simple();
	$product->set_name('E2E Catalog Product ' . $index);
	$product->set_regular_price((string) (10 + ($index % 50)));
	$product->set_status('publish');
	$product->set_catalog_visibility('visible');
	$product->save();
}`,
  ]);
}

function ensureProductFloor(): void {
  const count = publishedProductCount();
  if (count >= MIN_PRODUCTS) {
    return;
  }

  createSimpleProducts(count + 1, MIN_PRODUCTS - count);

  const finalCount = publishedProductCount();
  if (finalCount < MIN_PRODUCTS) {
    throw new Error(
      `Expected at least ${MIN_PRODUCTS} published products, found ${finalCount}.`
    );
  }
}

/** Idempotent catalogue floor for cursor-pagination e2e. */
export function ensureCatalogCursorFixtures(): void {
  ensureInfiniteScrollMode();
  ensureProductFloor();
}
