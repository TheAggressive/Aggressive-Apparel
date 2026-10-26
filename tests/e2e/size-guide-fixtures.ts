import { wpCli } from './wp-cli';

/**
 * Deterministic product + assigned guide for the Size Guide browser contract.
 *
 * Two shapes: `legacy` (a simple product whose guide is free-form content,
 * the pre-chart format that must keep rendering) and `chart` (a variable
 * product with a Size attribute and a structured chart, S/M/L chest in
 * inches, for the unit toggle and selected-size highlight).
 */

export type SizeGuideFixtureKind = 'legacy' | 'chart';

const FEATURE_OPTION = 'aggressive_apparel_wc_features';
const FEATURE_BACKUP_OPTION = 'e2e_size_guide_feature_backup';
const FIXTURE_SKU = 'e2e-size-guide';
const FIXTURE_OWNER_META = '_aa_e2e_fixture_owner';
const FIXTURE_OWNER = 'size-guide';

export interface SizeGuideFixture {
  productId: number;
  guideId: number;
  permalink: string;
}

const BEGIN_TRANSACTION_PHP = `
if (!function_exists('wc_get_product')) {
  echo wp_json_encode(array('error' => 'WooCommerce product APIs are unavailable.'));
  return;
}

$feature_key = '${FEATURE_OPTION}';
$backup_key = '${FEATURE_BACKUP_OPTION}';

// Recover the original option after an interrupted prior run. At this point
// WordPress has already booted with the leaked enabled value, so the fixture
// post type remains available for stale-record cleanup in this process.
$stale_backup = get_option($backup_key, null);
if (is_array($stale_backup)) {
  if (!empty($stale_backup['existed'])) {
    update_option($feature_key, $stale_backup['value'], false);
  } else {
    delete_option($feature_key);
  }
  delete_option($backup_key);
}

$stale_product_ids = get_posts(
  array(
    'post_type'      => 'product',
    'post_status'    => 'any',
    'fields'         => 'ids',
    'meta_key'       => '${FIXTURE_OWNER_META}',
    'meta_value'     => '${FIXTURE_OWNER}',
    'posts_per_page' => 10,
    'no_found_rows'  => true,
  )
);
foreach ($stale_product_ids as $stale_product_id) {
  foreach (get_children(array('post_parent' => (int) $stale_product_id, 'post_type' => 'product_variation')) as $child) {
    wp_delete_post($child->ID, true);
  }
  wp_delete_post((int) $stale_product_id, true);
}

if (post_type_exists('aa_size_guide')) {
  $stale_guide_ids = get_posts(
    array(
      'post_type'      => 'aa_size_guide',
      'post_status'    => 'any',
      'fields'         => 'ids',
      'meta_key'       => '${FIXTURE_OWNER_META}',
      'meta_value'     => '${FIXTURE_OWNER}',
      'posts_per_page' => 10,
      'no_found_rows'  => true,
    )
  );
  foreach ($stale_guide_ids as $stale_guide_id) {
    wp_delete_post((int) $stale_guide_id, true);
  }
}

$sentinel = new stdClass();
$original = get_option($feature_key, $sentinel);
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
  echo wp_json_encode(array('error' => 'Size Guide feature backup could not be created.'));
  return;
}

$features = $original !== $sentinel ? $original : array();
if (!is_array($features)) {
  $features = array();
}
$features['size_guide'] = 1;
update_option($feature_key, $features, false);
$stored_features = get_option($feature_key, array());
if (!is_array($stored_features) || empty($stored_features['size_guide'])) {
  echo wp_json_encode(array('error' => 'The Size Guide feature could not be enabled.'));
  return;
}

echo wp_json_encode(array('ready' => true));
`;

const CREATE_FIXTURE_PHP = `
if (!function_exists('wc_get_product')) {
  echo wp_json_encode(array('error' => 'WooCommerce product APIs are unavailable.'));
  return;
}
if (!post_type_exists('aa_size_guide')) {
  echo wp_json_encode(
    array(
      'error' => 'The Size Guide post type was not registered after enabling its feature flag.',
    )
  );
  return;
}

// Sized like the store's apparel: the guide opens from the "(Guide)" link
// beside the Size options.
$product = new WC_Product_Variable();
$product->set_name('E2E Size Guide Product');
$product->set_sku('${FIXTURE_SKU}');
$product->set_status('publish');
$product->set_catalog_visibility('visible');
$product->set_short_description('<p>Size Guide browser fixture.</p>');
$attribute = new WC_Product_Attribute();
$attribute->set_name('Size');
$attribute->set_options(array('Small', 'Medium', 'Large'));
$attribute->set_visible(true);
$attribute->set_variation(true);
$product->set_attributes(array($attribute));
$product_id = $product->save();
if ($product_id <= 0) {
  echo wp_json_encode(array('error' => 'Could not create the fixture product.'));
  return;
}
foreach (array('Small', 'Medium', 'Large') as $size) {
  $variation = new WC_Product_Variation();
  $variation->set_parent_id($product_id);
  $variation->set_attributes(array('size' => $size));
  $variation->set_regular_price('29.99');
  $variation->set_stock_status('instock');
  $variation->save();
}
WC_Product_Variable::sync($product_id);
if (false === update_post_meta($product_id, '${FIXTURE_OWNER_META}', '${FIXTURE_OWNER}')) {
  echo wp_json_encode(array('error' => 'Could not mark the fixture product as test-owned.'));
  return;
}

$guide_id = wp_insert_post(
  array(
    'post_type'   => 'aa_size_guide',
    'post_status' => 'publish',
    'post_title'  => 'E2E Size Guide',
  )
);

if (is_wp_error($guide_id) || $guide_id <= 0) {
  echo wp_json_encode(array('error' => 'Could not create the fixture guide.'));
  return;
}
if (false === update_post_meta($guide_id, '${FIXTURE_OWNER_META}', '${FIXTURE_OWNER}')) {
  echo wp_json_encode(array('error' => 'Could not mark the fixture guide as test-owned.'));
  return;
}

$updated_guide_id = wp_update_post(
  array(
    'ID'           => $guide_id,
    'post_status'  => 'publish',
    'post_content' =>
      '<p id="e2e-fit-notes">E2E measurement 38–40 inches.</p>' .
      '<p><a href="#e2e-fit-notes">Fit notes</a></p>' .
      '<table><thead><tr><th>Size</th><th>Chest</th></tr></thead>' .
      '<tbody><tr><td>M</td><td>38–40</td></tr></tbody></table>',
  ),
  true
);
if (is_wp_error($updated_guide_id)) {
  echo wp_json_encode(array('error' => $updated_guide_id->get_error_message()));
  return;
}

update_post_meta($product_id, '_aggressive_apparel_size_guide_id', $guide_id);

echo wp_json_encode(
  array(
    'productId' => (int) $product_id,
    'guideId'   => (int) $guide_id,
    'permalink' => get_permalink($product_id),
  )
);
`;

const CREATE_CHART_FIXTURE_PHP = `
if (!function_exists('wc_get_product') || !post_type_exists('aa_size_guide')) {
  echo wp_json_encode(array('error' => 'WooCommerce or the Size Guide post type is unavailable.'));
  return;
}

$product = new WC_Product_Variable();
$product->set_name('E2E Chart Product');
// The Product Specs block (compact) shows Fit and the picked size's line.
$product->set_description('<!-- wp:aggressive-apparel/product-specs {"layout":"compact","scales":["fit"]} /-->');
$product->set_sku('${FIXTURE_SKU}-chart');
$product->set_status('publish');
$product->set_catalog_visibility('visible');
$attribute = new WC_Product_Attribute();
$attribute->set_name('Size');
$attribute->set_options(array('Small', 'Medium', 'Large'));
$attribute->set_visible(true);
$attribute->set_variation(true);
$product->set_attributes(array($attribute));
$product_id = $product->save();
if ($product_id <= 0) {
  echo wp_json_encode(array('error' => 'Could not create the fixture product.'));
  return;
}
update_post_meta($product_id, '${FIXTURE_OWNER_META}', '${FIXTURE_OWNER}');
update_post_meta($product_id, '_aa_spec_fit', '2');

foreach (array('Small', 'Medium', 'Large') as $size) {
  $variation = new WC_Product_Variation();
  $variation->set_parent_id($product_id);
  $variation->set_attributes(array('size' => $size));
  $variation->set_regular_price('29.99');
  $variation->set_stock_status('instock');
  $variation->save();
}
WC_Product_Variable::sync($product_id);

$guide_id = wp_insert_post(
  array(
    'post_type'   => 'aa_size_guide',
    'post_status' => 'publish',
    'post_title'  => 'E2E Size Chart',
  ),
  true
);
if (is_wp_error($guide_id) || $guide_id <= 0) {
  echo wp_json_encode(array('error' => 'Could not create the fixture guide.'));
  return;
}
update_post_meta($guide_id, '${FIXTURE_OWNER_META}', '${FIXTURE_OWNER}');
update_post_meta(
  $guide_id,
  '_aa_size_chart',
  array(
    'unit'    => 'in',
    'columns' => array(array('label' => 'Chest', 'type' => 'measure')),
    'rows'    => array(
      array('size' => 'S', 'values' => array('19')),
      array('size' => 'M', 'values' => array('21')),
      array('size' => 'L', 'values' => array('23')),
    ),
  )
);
update_post_meta($product_id, '_aggressive_apparel_size_guide_id', $guide_id);

echo wp_json_encode(
  array(
    'productId' => (int) $product_id,
    'guideId'   => (int) $guide_id,
    'permalink' => get_permalink($product_id),
  )
);
`;

const ROLLBACK_PHP = `
$product_ids = get_posts(
  array(
    'post_type'      => 'product',
    'post_status'    => 'any',
    'fields'         => 'ids',
    'meta_key'       => '${FIXTURE_OWNER_META}',
    'meta_value'     => '${FIXTURE_OWNER}',
    'posts_per_page' => 10,
    'no_found_rows'  => true,
  )
);
foreach ($product_ids as $product_id) {
  foreach (get_children(array('post_parent' => (int) $product_id, 'post_type' => 'product_variation')) as $child) {
    wp_delete_post($child->ID, true);
  }
  wp_delete_post((int) $product_id, true);
}

if (post_type_exists('aa_size_guide')) {
  $guide_ids = get_posts(
    array(
      'post_type'      => 'aa_size_guide',
      'post_status'    => 'any',
      'fields'         => 'ids',
      'meta_key'       => '${FIXTURE_OWNER_META}',
      'meta_value'     => '${FIXTURE_OWNER}',
      'posts_per_page' => 10,
      'no_found_rows'  => true,
    )
  );
  foreach ($guide_ids as $guide_id) {
    wp_delete_post((int) $guide_id, true);
  }
}

$backup = get_option('${FEATURE_BACKUP_OPTION}', null);
if (is_array($backup)) {
  if (!empty($backup['existed'])) {
    update_option('${FEATURE_OPTION}', $backup['value'], false);
  } else {
    delete_option('${FEATURE_OPTION}');
  }
  delete_option('${FEATURE_BACKUP_OPTION}');
}

echo wp_json_encode(array('clean' => true));
`;

function parseResponse<T extends object>(output: string, label: string): T {
  try {
    return JSON.parse(output) as T;
  } catch {
    throw new Error(`${label} returned invalid JSON: ${output}`);
  }
}

function rollbackSizeGuideFixture(): void {
  const output = wpCli(['eval', ROLLBACK_PHP]);
  const result = parseResponse<{ clean?: boolean }>(
    output,
    'Size Guide fixture cleanup'
  );

  if (!result.clean) {
    throw new Error(`Invalid Size Guide cleanup response: ${output}`);
  }
}

export function createSizeGuideFixture(
  kind: SizeGuideFixtureKind = 'legacy'
): SizeGuideFixture {
  try {
    const beginOutput = wpCli(['eval', BEGIN_TRANSACTION_PHP]);
    const beginResult = parseResponse<{ ready?: boolean; error?: string }>(
      beginOutput,
      'Size Guide fixture transaction'
    );

    if (beginResult.error || !beginResult.ready) {
      throw new Error(
        beginResult.error ??
          `Invalid Size Guide transaction response: ${beginOutput}`
      );
    }

    // A fresh WP-CLI request is intentional: production bootstrap now sees
    // the enabled flag and registers the post type through its normal hooks.
    const output = wpCli([
      'eval',
      kind === 'chart' ? CREATE_CHART_FIXTURE_PHP : CREATE_FIXTURE_PHP,
    ]);
    const result = parseResponse<
      Partial<SizeGuideFixture> & { error?: string }
    >(output, 'Size Guide fixture setup');

    if (
      result.error ||
      !result.productId ||
      !result.guideId ||
      !result.permalink
    ) {
      throw new Error(
        result.error ?? `Invalid Size Guide fixture response: ${output}`
      );
    }

    return {
      productId: Number(result.productId),
      guideId: Number(result.guideId),
      permalink: String(result.permalink),
    };
  } catch (error) {
    try {
      rollbackSizeGuideFixture();
    } catch (cleanupError) {
      const setupMessage =
        error instanceof Error ? error.message : String(error);
      const cleanupMessage =
        cleanupError instanceof Error
          ? cleanupError.message
          : String(cleanupError);
      throw new Error(
        `Size Guide fixture setup failed: ${setupMessage}. Cleanup also failed: ${cleanupMessage}`
      );
    }
    throw error;
  }
}

export function deleteSizeGuideFixture(): void {
  rollbackSizeGuideFixture();
}
