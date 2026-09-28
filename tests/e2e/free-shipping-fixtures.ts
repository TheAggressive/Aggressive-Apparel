import { wpCli } from './wp-cli';

/**
 * Deterministic international free-shipping setup: two country zones placed
 * ahead of the site's own, a EUR-formatted store currency, a page carrying
 * the free-shipping message block, and one product. Every touched option is
 * backed up and restored, including after an interrupted prior run.
 */

const BACKUP_OPTION = 'e2e_free_shipping_backup';
const FIXTURE_OWNER_META = '_aa_e2e_fixture_owner';
const FIXTURE_OWNER = 'free-shipping';

/** Zone with free shipping from €200. */
export const FREE_SHIPPING_COUNTRY = 'IS';
/** Zone with flat-rate shipping only. */
export const NO_FREE_SHIPPING_COUNTRY = 'NZ';
export const THRESHOLD = 200;
export const PRODUCT_PRICE = 29.99;

export interface FreeShippingFixture {
  pageUrl: string;
  productId: number;
}

const OPTIONS = [
  'woocommerce_currency',
  'woocommerce_currency_pos',
  'woocommerce_price_decimal_sep',
  'woocommerce_price_thousand_sep',
  'woocommerce_price_num_decimals',
  'woocommerce_default_country',
  'woocommerce_default_customer_address',
  // Store Copy wording; restored so a wording test never leaks into the site.
  'aggressive_apparel_free_shipping_progress_text',
  'aggressive_apparel_free_shipping_unlocked_text',
];

const RESTORE_PHP = `
$backup = get_option('${BACKUP_OPTION}', null);
if (is_array($backup)) {
  foreach ((array) ($backup['options'] ?? array()) as $name => $entry) {
    if (!empty($entry['existed'])) {
      update_option($name, $entry['value']);
    } else {
      delete_option($name);
    }
  }
  foreach ((array) ($backup['zones'] ?? array()) as $zone_id) {
    $zone = new WC_Shipping_Zone((int) $zone_id);
    if ($zone->get_id()) {
      $zone->delete();
    }
  }
  delete_option('${BACKUP_OPTION}');
}
foreach (array('page', 'product') as $post_type) {
  $ids = get_posts(
    array(
      'post_type'      => $post_type,
      'post_status'    => 'any',
      'fields'         => 'ids',
      'meta_key'       => '${FIXTURE_OWNER_META}',
      'meta_value'     => '${FIXTURE_OWNER}',
      'posts_per_page' => 10,
      'no_found_rows'  => true,
    )
  );
  foreach ($ids as $id) {
    wp_delete_post((int) $id, true);
  }
}
delete_transient('aggressive_apparel_free_shipping_threshold');
`;

const CREATE_PHP = `
if (!class_exists('WC_Shipping_Zone')) {
  echo wp_json_encode(array('error' => 'WooCommerce shipping APIs are unavailable.'));
  return;
}
${RESTORE_PHP}
$options = array();
foreach (array(${OPTIONS.map(name => `'${name}'`).join(', ')}) as $name) {
  $sentinel = new stdClass();
  $value = get_option($name, $sentinel);
  $options[$name] = array('existed' => $value !== $sentinel, 'value' => $value !== $sentinel ? $value : null);
}
$backup = array('options' => $options, 'zones' => array());
add_option('${BACKUP_OPTION}', $backup, '', false);

update_option('woocommerce_currency', 'EUR');
update_option('woocommerce_currency_pos', 'right_space');
update_option('woocommerce_price_decimal_sep', ',');
update_option('woocommerce_price_thousand_sep', '.');
update_option('woocommerce_price_num_decimals', '2');
update_option('woocommerce_default_customer_address', 'base');
update_option('woocommerce_default_country', '${FREE_SHIPPING_COUNTRY}');

$zones = array(
  array('${FREE_SHIPPING_COUNTRY}', 'free_shipping'),
  array('${NO_FREE_SHIPPING_COUNTRY}', 'flat_rate'),
);
foreach ($zones as $index => $definition) {
  $zone = new WC_Shipping_Zone();
  $zone->set_zone_name('E2E ' . $definition[0]);
  $zone->set_zone_order(-1000 + $index);
  $zone->add_location($definition[0], 'country');
  $zone->save();
  $instance_id = $zone->add_shipping_method($definition[1]);
  if ('free_shipping' === $definition[1]) {
    update_option(
      'woocommerce_free_shipping_' . $instance_id . '_settings',
      array(
        'title'            => 'Free shipping',
        'requires'         => 'min_amount',
        'min_amount'       => '${THRESHOLD}',
        'ignore_discounts' => 'no',
      )
    );
  }
  $backup['zones'][] = $zone->get_id();
  update_option('${BACKUP_OPTION}', $backup, false);
}
delete_transient('aggressive_apparel_free_shipping_threshold');

$product = new WC_Product_Simple();
$product->set_name('E2E Free Shipping Product');
$product->set_regular_price('${PRODUCT_PRICE}');
$product->set_status('publish');
$product_id = $product->save();
update_post_meta($product_id, '${FIXTURE_OWNER_META}', '${FIXTURE_OWNER}');

$page_id = wp_insert_post(
  array(
    'post_type'    => 'page',
    'post_status'  => 'publish',
    'post_title'   => 'E2E Free Shipping',
    'post_content' => '<!-- wp:aggressive-apparel/free-shipping-message /-->',
  )
);
if (is_wp_error($page_id) || $page_id <= 0) {
  echo wp_json_encode(array('error' => 'Could not create the fixture page.'));
  return;
}
update_post_meta($page_id, '${FIXTURE_OWNER_META}', '${FIXTURE_OWNER}');

echo wp_json_encode(
  array(
    'pageUrl'   => get_permalink($page_id),
    'productId' => (int) $product_id,
  )
);
`;

function run(php: string, label: string): Record<string, unknown> {
  const output = wpCli(['eval', php]);
  try {
    return JSON.parse(output || '{}') as Record<string, unknown>;
  } catch {
    throw new Error(`${label} returned invalid JSON: ${output}`);
  }
}

export function createFreeShippingFixture(): FreeShippingFixture {
  try {
    const result = run(CREATE_PHP, 'Free shipping fixture setup');
    if (result.error || !result.pageUrl || !result.productId) {
      throw new Error(
        String(result.error ?? 'Invalid free shipping fixture response.')
      );
    }

    return {
      pageUrl: String(result.pageUrl),
      productId: Number(result.productId),
    };
  } catch (error) {
    deleteFreeShippingFixture();
    throw error;
  }
}

/** Set the site-wide Store Copy wording for the in-progress message. */
export function setProgressWording(wording: string): void {
  wpCli([
    'option',
    'update',
    'aggressive_apparel_free_shipping_progress_text',
    wording,
  ]);
}

/** Point the guest default location (store base) at a country. */
export function setGuestCountry(country: string): void {
  wpCli(['option', 'update', 'woocommerce_default_country', country]);
}

export function deleteFreeShippingFixture(): void {
  run(`${RESTORE_PHP} echo wp_json_encode(array('clean' => true));`, 'cleanup');
}
