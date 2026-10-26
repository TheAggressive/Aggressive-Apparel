import { wpCli } from './wp-cli';

/**
 * Cleanup for everything the E2E suite puts in the site.
 *
 * Fixtures mark what they create (posts and terms carry FIXTURE_OWNER_META,
 * attribute taxonomies are recorded) and back up each option before they
 * change it. cleanUpFixtures() deletes the marked records and restores the
 * options. Global teardown runs it after the suite, and global setup runs it
 * first, so a run that crashed or was interrupted is cleaned up too.
 *
 * Specs still clean up their own fixtures; this is the net under them.
 */

/** Post and term meta marking a record an E2E fixture created. */
export const FIXTURE_OWNER_META = '_aa_e2e_fixture_owner';

/** Original values of the options fixtures changed. */
const OPTION_BACKUP = 'aa_e2e_option_backup';

/** IDs of the WooCommerce attributes fixtures created. */
const CREATED_ATTRIBUTES = 'aa_e2e_created_attributes';

/** Instance IDs of the shipping methods fixtures added. */
const CREATED_SHIPPING_METHODS = 'aa_e2e_created_shipping_methods';

/** PHP that marks a post as fixture-created. */
export function ownPostPhp(idExpr: string, owner: string): string {
  return `update_post_meta((int) (${idExpr}), '${FIXTURE_OWNER_META}', '${owner}');`;
}

/** PHP that marks a term as fixture-created. */
export function ownTermPhp(idExpr: string, owner: string): string {
  return `update_term_meta((int) (${idExpr}), '${FIXTURE_OWNER_META}', '${owner}');`;
}

/** PHP that records a WooCommerce attribute a fixture created. */
export function ownAttributePhp(idExpr: string): string {
  return `update_option('${CREATED_ATTRIBUTES}', array_values(array_unique(array_merge((array) get_option('${CREATED_ATTRIBUTES}', array()), array((int) (${idExpr}))))), false);`;
}

/** PHP that records a shipping method instance a fixture added. */
export function ownShippingMethodPhp(idExpr: string): string {
  return `update_option('${CREATED_SHIPPING_METHODS}', array_values(array_unique(array_merge((array) get_option('${CREATED_SHIPPING_METHODS}', array()), array((int) (${idExpr}))))), false);`;
}

/**
 * Back up options before a fixture changes them. The first backup of each
 * option wins, so the value restored is the one from before the suite ran.
 */
export function backupOptions(names: string[]): void {
  wpCli([
    'eval',
    `global $wpdb;
$backup = get_option('${OPTION_BACKUP}', array());
$backup = is_array($backup) ? $backup : array();
foreach (${JSON.stringify(names)} as $name) {
  if (array_key_exists($name, $backup)) {
    continue;
  }
  $row = $wpdb->get_row($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name));
  $backup[$name] = $row
    ? array('existed' => true, 'value' => maybe_unserialize($row->option_value))
    : array('existed' => false, 'value' => null);
}
update_option('${OPTION_BACKUP}', $backup, false);`,
  ]);
}

export interface CleanupReport {
  posts: number;
  terms: number;
  attributes: number;
  shippingMethods: number;
  options: number;
}

/** Delete every fixture-created record and restore every backed-up option. */
export function cleanUpFixtures(): CleanupReport {
  const output = wpCli([
    'eval',
    `$owner = '${FIXTURE_OWNER_META}';
$report = array('posts' => 0, 'terms' => 0, 'attributes' => 0, 'shippingMethods' => 0, 'options' => 0);

$ids = get_posts(array(
  'post_type'        => array_values(get_post_types()),
  'post_status'      => array_keys(get_post_stati()),
  'numberposts'      => -1,
  'fields'           => 'ids',
  'meta_key'         => $owner,
  'suppress_filters' => true,
));
foreach ($ids as $id) {
  $type = get_post_type($id);
  if (!$type) {
    continue; // Deleted with its parent.
  }
  if ('attachment' === $type) {
    wp_delete_attachment($id, true);
  } elseif (in_array($type, array('product', 'product_variation'), true) && function_exists('wc_get_product') && ($product = wc_get_product($id))) {
    $product->delete(true); // Variations and lookup rows too.
  } else {
    wp_delete_post($id, true);
  }
  $report['posts']++;
}

$terms = get_terms(array(
  'taxonomy'   => array_values(get_taxonomies()),
  'hide_empty' => false,
  'meta_key'   => $owner,
));
if (is_array($terms)) {
  foreach ($terms as $term) {
    if (true === wp_delete_term($term->term_id, $term->taxonomy)) {
      $report['terms']++;
    }
  }
}

foreach ((array) get_option('${CREATED_ATTRIBUTES}', array()) as $attribute_id) {
  if (function_exists('wc_delete_attribute') && wc_delete_attribute((int) $attribute_id)) {
    $report['attributes']++;
  }
}
delete_option('${CREATED_ATTRIBUTES}');

foreach ((array) get_option('${CREATED_SHIPPING_METHODS}', array()) as $instance_id) {
  $zone = class_exists('WC_Shipping_Zones') ? WC_Shipping_Zones::get_zone_by('instance_id', (int) $instance_id) : null;
  if ($zone && $zone->delete_shipping_method((int) $instance_id)) {
    $report['shippingMethods']++;
  }
}
delete_option('${CREATED_SHIPPING_METHODS}');

$backup = get_option('${OPTION_BACKUP}', array());
if (is_array($backup)) {
  foreach ($backup as $name => $saved) {
    if (!empty($saved['existed'])) {
      update_option($name, $saved['value']);
    } else {
      delete_option($name);
    }
    $report['options']++;
  }
  if (array_key_exists('permalink_structure', $backup)) {
    flush_rewrite_rules(true);
  }
}
delete_option('${OPTION_BACKUP}');

echo wp_json_encode($report);`,
  ]);

  return JSON.parse(output.slice(output.lastIndexOf('{'))) as CleanupReport;
}
