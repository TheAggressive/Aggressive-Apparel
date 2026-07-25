import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * Deterministic fixture for variation-availability.spec.ts.
 *
 * Creates (idempotently, by SKU) one variable product with a `pa_color`
 * attribute whose variations are:
 *   - Red   $12  in stock
 *   - Blue  $15  in stock
 *   - Green $14  OUT OF STOCK
 *
 * The $12–$15 spread exercises the Smart Price "From $X" collapse, and the
 * sold-out Green variation exercises the option-dimming. The relevant store
 * features are enabled here too so the spec never depends on env toggle state.
 */

const THEME_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  '../..'
);

export const VARIATION_FIXTURE_SKU = 'e2e-variation-availability';

function wp(args: string[]): string {
  return execFileSync('pnpm', ['exec', 'wp-env', 'run', 'cli', 'wp', ...args], {
    cwd: THEME_ROOT,
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  }).trim();
}

const FIXTURE_PHP = `
if (!function_exists('wc_get_product')) { echo 0; return; }

// Enable the store features the spec drives (merge, don't clobber).
$feat = get_option('aggressive_apparel_wc_features', array());
if (!is_array($feat)) { $feat = array(); }
foreach (array('quick_view','sticky_add_to_cart','price_display','stock_status') as $f) { $feat[$f] = 1; }
update_option('aggressive_apparel_wc_features', $feat);

$tax = 'pa_color';
if (!taxonomy_exists($tax)) { echo 0; return; }

// Ensure the colour terms exist.
$slugs = array('red' => 'Red', 'blue' => 'Blue', 'green' => 'Green');
$term_ids = array();
foreach ($slugs as $slug => $name) {
  $t = get_term_by('slug', $slug, $tax);
  if (!$t) {
    $res = wp_insert_term($name, $tax, array('slug' => $slug));
    if (!is_wp_error($res)) { $term_ids[$slug] = (int) $res['term_id']; }
  } else {
    $term_ids[$slug] = (int) $t->term_id;
  }
}

$sku = '${VARIATION_FIXTURE_SKU}';
$product_id = wc_get_product_id_by_sku($sku);
if (!$product_id) {
  $product = new WC_Product_Variable();
  $product->set_name('E2E Variation Availability');
  $product->set_sku($sku);
  $product->set_status('publish');
  $product->set_catalog_visibility('visible');
  $attr = new WC_Product_Attribute();
  $attr->set_id(wc_attribute_taxonomy_id_by_name('color'));
  $attr->set_name($tax);
  $attr->set_options(array_values($term_ids));
  $attr->set_visible(true);
  $attr->set_variation(true);
  $product->set_attributes(array($attr));
  $product_id = $product->save();
}

// (Re)assert variation prices + stock so the fixture converges every run.
$spec = array('red' => array(12, 'instock'), 'blue' => array(15, 'instock'), 'green' => array(14, 'outofstock'));
$parent = wc_get_product($product_id);
$have = array();
foreach ($parent->get_children() as $cid) {
  $cv = wc_get_product($cid);
  if (!$cv) { continue; }
  $a = $cv->get_attributes();
  $have[$a['pa_color'] ?? ''] = $cv;
}
foreach ($spec as $slug => $ps) {
  $v = isset($have[$slug]) ? $have[$slug] : new WC_Product_Variation();
  if (!isset($have[$slug])) {
    $v->set_parent_id($product_id);
    $v->set_attributes(array('pa_color' => $slug));
  }
  $v->set_regular_price((string) $ps[0]);
  $v->set_stock_status($ps[1]);
  $v->save();
}
WC_Product_Variable::sync($product_id);
$parent->save();

echo wp_json_encode(array(
  'id'        => (int) $product_id,
  'permalink' => get_permalink($product_id),
));
`;

export interface VariationFixture {
  /** Product id, or 0 when the fixture could not be built. */
  id: number;
  /** Single-product permalink (empty when the fixture could not be built). */
  permalink: string;
}

/** Idempotent fixture; returns the product id + its single-product permalink. */
export function ensureVariationAvailabilityFixtures(): VariationFixture {
  try {
    const data = JSON.parse(wp(['eval', FIXTURE_PHP])) as {
      id?: number;
      permalink?: string;
    };
    return {
      id: Number(data.id) || 0,
      permalink: String(data.permalink || ''),
    };
  } catch (error) {
    // Keep auth setup succeeding when wp-env is mid-boot; the spec soft-skips.
    console.warn(
      '[e2e] variation-availability fixture skipped:',
      error instanceof Error ? error.message : error
    );
    return { id: 0, permalink: '' };
  }
}
