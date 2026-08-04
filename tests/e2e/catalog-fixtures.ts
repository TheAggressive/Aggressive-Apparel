import { wpCli } from './wp-cli';

/**
 * Ensure the wp-env shop catalogue can exercise Load More / infinite scroll.
 *
 * Soft-skips in catalog-cursor-pagination.spec.ts hide real regressions when
 * the env has too few products or is stuck in button mode. Run from global
 * setup so every catalog e2e starts from a known floor.
 */

// The shop archive renders 24 products per page, so a 24-product floor is the
// single worst value available: the first page shows everything, nothing is
// left to paginate, and the load-more sentinel stays hidden. That is exactly
// how catalog-cursor-pagination timed out in CI while passing locally, where a
// reused database had accumulated extras above the floor and produced a second
// page by accident.
//
// Seed comfortably past one page so a second page always exists on a clean
// install. Keep this ABOVE the archive's per-page count if that ever changes.
const PRODUCTS_PER_PAGE = 24;
const MIN_PRODUCTS = PRODUCTS_PER_PAGE + 12;
const FEATURE_OPTION = 'aggressive_apparel_wc_features';
const LOAD_MORE_MODE_OPTION = 'aggressive_apparel_load_more_mode';
const COMING_SOON_OPTION = 'woocommerce_coming_soon';
const PRETTY_PERMALINK_STRUCTURE = '/%postname%/';

export interface CatalogCursorFixtures {
  shopPageId: number;
}

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
  const state = JSON.parse(
    wpCli([
      'eval',
      `$features = get_option('${FEATURE_OPTION}', array());
if (!is_array($features)) {
	$features = array();
}

$features['load_more'] = 1;
update_option('${FEATURE_OPTION}', $features, false);
update_option('${LOAD_MORE_MODE_OPTION}', 'infinite_scroll', false);

echo wp_json_encode(
	array(
		'loadMoreEnabled' => !empty(get_option('${FEATURE_OPTION}', array())['load_more']),
		'mode'            => get_option('${LOAD_MORE_MODE_OPTION}'),
	)
);`,
    ])
  ) as {
    loadMoreEnabled?: boolean;
    mode?: string;
  };

  if (state.loadMoreEnabled !== true || state.mode !== 'infinite_scroll') {
    throw new Error(
      `Expected the E2E Load More feature to use infinite_scroll, found ${JSON.stringify(state)}.`
    );
  }
}

function ensurePublicStore(): void {
  wpCli(['option', 'update', COMING_SOON_OPTION, 'no']);

  const comingSoon = wpCli(['option', 'get', COMING_SOON_OPTION]);
  if (comingSoon !== 'no') {
    throw new Error(
      `Expected the E2E WooCommerce store to be public, found ${comingSoon}.`
    );
  }
}

/**
 * Provision the WooCommerce archive and its rewrite contract.
 *
 * Persistent local wp-env installations normally already contain both, while a
 * fresh CI database may have neither. In that state Apache handles `/shop/`
 * directly and returns a raw 404 before WordPress or the theme can run.
 */
function ensureStorefrontInfrastructure(): number {
  const shopPageId = Number.parseInt(
    wpCli([
      'eval',
      `if (!class_exists('WC_Install')) {
	throw new RuntimeException('WooCommerce installation APIs are unavailable.');
}

WC_Install::create_pages();

$shop_page_id = wc_get_page_id('shop');
$shop_page    = $shop_page_id > 0 ? get_post($shop_page_id) : null;

if (
	!$shop_page instanceof WP_Post
	|| 'page' !== $shop_page->post_type
	|| 'publish' !== $shop_page->post_status
	|| 'shop' !== $shop_page->post_name
) {
	throw new RuntimeException('WooCommerce did not provision a published /shop/ page.');
}

echo (string) $shop_page_id;`,
    ]),
    10
  );

  if (!Number.isInteger(shopPageId) || shopPageId <= 0) {
    throw new Error(
      `Expected a valid WooCommerce Shop page ID, found ${shopPageId}.`
    );
  }

  wpCli(['rewrite', 'structure', PRETTY_PERMALINK_STRUCTURE, '--hard']);

  const permalinkStructure = wpCli(['option', 'get', 'permalink_structure']);
  if (permalinkStructure !== PRETTY_PERMALINK_STRUCTURE) {
    throw new Error(
      `Expected E2E permalinks to use ${PRETTY_PERMALINK_STRUCTURE}, found ${permalinkStructure}.`
    );
  }

  return shopPageId;
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

  // The floor exists to guarantee a SECOND page, not merely some products.
  // Asserting the count alone is what let a 24-product floor sit against a
  // 24-per-page archive: the fixture reported success while leaving nothing to
  // paginate, and the failure surfaced 60 seconds later as an unrelated-looking
  // locator timeout in a spec that never mentions page size.
  if (finalCount <= PRODUCTS_PER_PAGE) {
    throw new Error(
      `Catalog pagination needs more than one page: found ${finalCount} ` +
        `products against ${PRODUCTS_PER_PAGE} per page, so the load-more ` +
        'sentinel stays hidden and every pagination spec times out.'
    );
  }
}

/** Idempotent catalogue floor for cursor-pagination e2e. */
export function ensureCatalogCursorFixtures(): CatalogCursorFixtures {
  ensurePublicStore();
  const shopPageId = ensureStorefrontInfrastructure();
  ensureInfiniteScrollMode();
  ensureProductFloor();

  return { shopPageId };
}
