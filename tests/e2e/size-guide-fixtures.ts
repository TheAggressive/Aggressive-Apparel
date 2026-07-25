import { wpCli } from './wp-cli';

/**
 * Deterministic product + assigned guide for the Size Guide browser contract.
 */

const FEATURE_OPTION = 'aggressive_apparel_wc_features';
const FEATURE_BACKUP_OPTION = 'e2e_size_guide_feature_backup';
const FIXTURE_SKU = 'e2e-size-guide';

export interface SizeGuideFixture {
  productId: number;
  guideId: number;
  permalink: string;
}

const SETUP_PHP = `
if (!function_exists('wc_get_product') || !post_type_exists('aa_size_guide')) {
  echo wp_json_encode(array('error' => 'WooCommerce or Size Guide post type unavailable.'));
  return;
}

$feature_key = '${FEATURE_OPTION}';
$backup_key = '${FEATURE_BACKUP_OPTION}';

if (false === get_option($backup_key, false)) {
  $sentinel = new stdClass();
  $original = get_option($feature_key, $sentinel);
  add_option(
    $backup_key,
    array(
      'existed' => $original !== $sentinel,
      'value'   => $original !== $sentinel ? $original : null,
    ),
    '',
    false
  );
}

$features = get_option($feature_key, array());
if (!is_array($features)) {
  $features = array();
}
$features['size_guide'] = 1;
update_option($feature_key, $features, false);

$product_id = wc_get_product_id_by_sku('${FIXTURE_SKU}');
$product = $product_id ? wc_get_product($product_id) : new WC_Product_Simple();
if (!$product) {
  echo wp_json_encode(array('error' => 'Could not create the fixture product.'));
  return;
}

$product->set_name('E2E Size Guide Product');
$product->set_sku('${FIXTURE_SKU}');
$product->set_regular_price('29.99');
$product->set_status('publish');
$product->set_catalog_visibility('visible');
$product->set_short_description('<p>Size Guide browser fixture.</p>');
$product_id = $product->save();

$guide_id = (int) get_post_meta($product_id, '_aggressive_apparel_size_guide_id', true);
if ($guide_id <= 0 || 'aa_size_guide' !== get_post_type($guide_id)) {
  $guide_id = wp_insert_post(
    array(
      'post_type'   => 'aa_size_guide',
      'post_status' => 'publish',
      'post_title'  => 'E2E Size Guide',
    )
  );
}

if (is_wp_error($guide_id) || $guide_id <= 0) {
  echo wp_json_encode(array('error' => 'Could not create the fixture guide.'));
  return;
}

wp_update_post(
  array(
    'ID'           => $guide_id,
    'post_status'  => 'publish',
    'post_content' =>
      '<p id="e2e-fit-notes">E2E measurement 38–40 inches.</p>' .
      '<p><a href="#e2e-fit-notes">Fit notes</a></p>' .
      '<table><thead><tr><th>Size</th><th>Chest</th></tr></thead>' .
      '<tbody><tr><td>M</td><td>38–40</td></tr></tbody></table>',
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

export function createSizeGuideFixture(): SizeGuideFixture {
  const output = wpCli(['eval', SETUP_PHP]);
  const result = JSON.parse(output) as Partial<SizeGuideFixture> & {
    error?: string;
  };

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
}

export function deleteSizeGuideFixture(fixture: SizeGuideFixture): void {
  const cleanup = `
$product_id = ${Number(fixture.productId)};
$guide_id = ${Number(fixture.guideId)};

if ($product_id > 0) {
  wp_delete_post($product_id, true);
}
if ($guide_id > 0) {
  wp_delete_post($guide_id, true);
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

echo 'ok';
`;

  try {
    wpCli(['eval', cleanup]);
  } catch {
    // Best-effort cleanup keeps the original Playwright failure visible.
  }
}
