import { wpCli } from './wp-cli';

/**
 * Transactional fixture for product-card visual contracts and variation
 * availability. Every suite receives a fresh owned product and restores the
 * feature option it found, so a persistent local wp-env converges to its
 * original state after the tests finish.
 */

const FEATURE_OPTION = 'aggressive_apparel_wc_features';
const FEATURE_BACKUP_OPTION = 'e2e_variation_feature_backup';
const FIXTURE_CATEGORY_SLUG = 'e2e-browser-fixtures';

export const VARIATION_FIXTURE_SKU = 'e2e-variation-availability';

const FIXTURE_PHP = `
if (!function_exists('wc_get_product')) {
  throw new RuntimeException('WooCommerce product APIs are unavailable.');
}
if (!taxonomy_exists('pa_color')) {
  throw new RuntimeException('The pa_color taxonomy is unavailable.');
}

$feature_key = '${FEATURE_OPTION}';
$backup_key = '${FEATURE_BACKUP_OPTION}';

// Recover state left by an interrupted prior run before opening a new
// transaction.
$stale_backup = get_option($backup_key, null);
if (is_array($stale_backup)) {
  if (!empty($stale_backup['existed'])) {
    update_option($feature_key, $stale_backup['value'], false);
  } else {
    delete_option($feature_key);
  }
  delete_option($backup_key);
}

$sentinel    = new stdClass();
$original    = get_option($feature_key, $sentinel);
$backup_open = add_option(
  $backup_key,
  array(
    'existed' => $original !== $sentinel,
    'value'   => $original !== $sentinel ? $original : null,
  ),
  '',
  false
);
if (!$backup_open) {
  throw new RuntimeException('Variation feature option backup could not be created.');
}

$old_product_id = wc_get_product_id_by_sku('${VARIATION_FIXTURE_SKU}');
if ($old_product_id > 0) {
  foreach (get_children(array('post_parent' => $old_product_id, 'post_type' => 'product_variation')) as $child) {
    wp_delete_post($child->ID, true);
  }
  wp_delete_post($old_product_id, true);
}

$taxonomy = 'pa_color';
$colors = array(
  'red'   => array('name' => 'Red', 'value' => '#ff0000'),
  'blue'  => array('name' => 'Blue', 'value' => '#0000ff'),
  'green' => array('name' => 'Green', 'value' => '#008000'),
);
$term_ids = array();
$created_term_ids = array();

foreach ($colors as $slug => $color) {
  $term = get_term_by('slug', $slug, $taxonomy);
  if (!$term) {
    $created = wp_insert_term($color['name'], $taxonomy, array('slug' => $slug));
    if (is_wp_error($created)) {
      throw new RuntimeException($created->get_error_message());
    }
    $term_id = (int) $created['term_id'];
    $created_term_ids[] = $term_id;
    update_term_meta($term_id, 'color_type', 'solid');
    update_term_meta($term_id, 'color_value', $color['value']);
    update_term_meta($term_id, 'color_format', 'hex');
  } else {
    $term_id = (int) $term->term_id;
  }
  $term_ids[$slug] = $term_id;
}

$category = get_term_by('slug', '${FIXTURE_CATEGORY_SLUG}', 'product_cat');
$created_category_id = 0;
if (!$category) {
  $created = wp_insert_term(
    'E2E Browser Fixtures',
    'product_cat',
    array('slug' => '${FIXTURE_CATEGORY_SLUG}')
  );
  if (is_wp_error($created)) {
    throw new RuntimeException($created->get_error_message());
  }
  $category_id = (int) $created['term_id'];
  $created_category_id = $category_id;
} else {
  $category_id = (int) $category->term_id;
}

$product = new WC_Product_Variable();
$product->set_name('E2E Variation Availability');
$product->set_sku('${VARIATION_FIXTURE_SKU}');
$product->set_status('publish');
$product->set_catalog_visibility('visible');
$product->set_category_ids(array($category_id));

$attribute = new WC_Product_Attribute();
$attribute->set_id(wc_attribute_taxonomy_id_by_name('color'));
$attribute->set_name($taxonomy);
$attribute->set_options(array_values($term_ids));
$attribute->set_visible(true);
$attribute->set_variation(true);
$product->set_attributes(array($attribute));
$product_id = $product->save();

$variations = array(
  'red'   => array(12, 'instock'),
  'blue'  => array(15, 'instock'),
  'green' => array(14, 'outofstock'),
);
foreach ($variations as $slug => $spec) {
  $variation = new WC_Product_Variation();
  $variation->set_parent_id($product_id);
  $variation->set_attributes(array('pa_color' => $slug));
  $variation->set_regular_price((string) $spec[0]);
  $variation->set_stock_status($spec[1]);
  $variation->save();
}
WC_Product_Variable::sync($product_id);

// Delay the option mutation until every fixture record exists. A setup
// exception before this point cannot leak feature flags into persistent wp-env.
$features = is_array($original) ? $original : array();
foreach (array('quick_view', 'sticky_add_to_cart', 'price_display', 'stock_status') as $feature) {
  $features[$feature] = 1;
}
update_option($feature_key, $features, false);

echo wp_json_encode(
  array(
    'id'                => (int) $product_id,
    'permalink'         => get_permalink($product_id),
    'createdTermIds'    => $created_term_ids,
    'createdCategoryId' => $created_category_id,
  )
);
`;

export interface VariationFixture {
  id: number;
  permalink: string;
  createdTermIds: number[];
  createdCategoryId: number;
}

/** Create a fresh fixture or fail the suite with an actionable setup error. */
export function createVariationAvailabilityFixture(): VariationFixture {
  const output = wpCli(['eval', FIXTURE_PHP]);
  const data = JSON.parse(output) as Partial<VariationFixture>;
  const fixture: VariationFixture = {
    id: Number(data.id) || 0,
    permalink: String(data.permalink || ''),
    createdTermIds: Array.isArray(data.createdTermIds)
      ? data.createdTermIds.map(Number).filter(Number.isInteger)
      : [],
    createdCategoryId: Number(data.createdCategoryId) || 0,
  };

  if (!fixture.id || !fixture.permalink) {
    throw new Error(`Invalid variation fixture response: ${output}`);
  }

  return fixture;
}

/** Remove fixture-owned records and restore the original feature option. */
export function deleteVariationAvailabilityFixture(
  fixture: VariationFixture
): void {
  const termIds = JSON.stringify(fixture.createdTermIds);
  const script = `
$product_id = ${Number(fixture.id)};
if ($product_id > 0) {
  foreach (get_children(array('post_parent' => $product_id, 'post_type' => 'product_variation')) as $child) {
    wp_delete_post($child->ID, true);
  }
  wp_delete_post($product_id, true);
}

foreach (${termIds} as $term_id) {
  if ((int) $term_id > 0) {
    wp_delete_term((int) $term_id, 'pa_color');
  }
}

$category_id = ${Number(fixture.createdCategoryId)};
if ($category_id > 0) {
  wp_delete_term($category_id, 'product_cat');
}

$backup = get_option('${FEATURE_BACKUP_OPTION}', null);
if (!is_array($backup)) {
  throw new RuntimeException('Variation fixture feature backup is missing.');
}
if (!empty($backup['existed'])) {
  update_option('${FEATURE_OPTION}', $backup['value'], false);
} else {
  delete_option('${FEATURE_OPTION}');
}
delete_option('${FEATURE_BACKUP_OPTION}');
echo 'ok';
`;

  const output = wpCli(['eval', script]);
  if (!output.endsWith('ok')) {
    throw new Error(`Variation fixture cleanup failed: ${output}`);
  }
}
