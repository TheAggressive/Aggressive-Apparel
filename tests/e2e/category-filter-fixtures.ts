import {
  backupOptions,
  FIXTURE_OWNER_META,
  ownAttributePhp,
  ownPostPhp,
  ownTermPhp,
} from './fixture-cleanup';
import { wpCli } from './wp-cli';

/**
 * A parent category whose only product sits in a child category and carries
 * its own Collection term. A parent archive lists child-category products, so
 * the filter panel must treat that collection as available there.
 *
 * Idempotent; created through WooCommerce APIs only. Everything it creates is
 * marked for deleteCategoryFilterFixtures() and the suite's global cleanup.
 */

const FIXTURE_OWNER = 'category-filter';
const FEATURE_OPTION = 'aggressive_apparel_wc_features';

export const PARENT_CATEGORY = 'e2e-filter-parent';
export const PARENT_NAME = 'E2E Filter Parent';
export const CHILD_COLLECTION = 'e2e-child-only';

export interface CategoryFilterFixtures {
  parentUrl: string;
}

export function ensureCategoryFilterFixtures(): CategoryFilterFixtures {
  backupOptions([FEATURE_OPTION]);
  const parentUrl = wpCli([
    'eval',
    `$features = get_option('${FEATURE_OPTION}', array());
$features = is_array($features) ? $features : array();
$features['product_filters'] = 1;
update_option('${FEATURE_OPTION}', $features, false);

if (!wc_attribute_taxonomy_id_by_name('collection')) {
	$attribute_id = wc_create_attribute(array('name' => 'Collection', 'slug' => 'collection'));
	if (!is_wp_error($attribute_id)) {
		${ownAttributePhp('$attribute_id')}
	}
}
// WooCommerce registers new attribute taxonomies on the next request.
if (!taxonomy_exists('pa_collection')) {
	register_taxonomy('pa_collection', array('product'), array('hierarchical' => false));
}
if (!term_exists('${CHILD_COLLECTION}', 'pa_collection')) {
	$created = wp_insert_term('E2E Child Only', 'pa_collection', array('slug' => '${CHILD_COLLECTION}'));
	${ownTermPhp('$created["term_id"]', FIXTURE_OWNER)}
}
$collection = get_term_by('slug', '${CHILD_COLLECTION}', 'pa_collection');

$parent = term_exists('${PARENT_CATEGORY}', 'product_cat');
if (!$parent) {
	$parent = wp_insert_term('${PARENT_NAME}', 'product_cat', array('slug' => '${PARENT_CATEGORY}'));
	${ownTermPhp('$parent["term_id"]', FIXTURE_OWNER)}
}
$parent_id = (int) (is_array($parent) ? $parent['term_id'] : $parent);

$child = term_exists('e2e-filter-child', 'product_cat');
if (!$child) {
	$child = wp_insert_term('E2E Filter Child', 'product_cat', array('slug' => 'e2e-filter-child', 'parent' => $parent_id));
	${ownTermPhp('$child["term_id"]', FIXTURE_OWNER)}
}
$child_id = (int) (is_array($child) ? $child['term_id'] : $child);

if (!wc_get_product_id_by_sku('e2e-filter-child-product')) {
	$attribute = new WC_Product_Attribute();
	$attribute->set_id(wc_attribute_taxonomy_id_by_name('collection'));
	$attribute->set_name('pa_collection');
	$attribute->set_options(array($collection->term_id));
	$attribute->set_visible(true);

	$product = new WC_Product_Simple();
	$product->set_name('E2E Filter Child Product');
	$product->set_sku('e2e-filter-child-product');
	$product->set_regular_price('25');
	$product->set_status('publish');
	$product->set_catalog_visibility('visible');
	$product->set_category_ids(array($child_id));
	$product->set_attributes(array($attribute));
	$id = $product->save();
	${ownPostPhp('$id', FIXTURE_OWNER)}
	wp_set_object_terms($id, array($collection->slug), 'pa_collection');

	// Facets read WooCommerce's attribute lookup table; write its rows now
	// rather than through the queued regeneration job.
	$lookup = 'Automattic\\\\WooCommerce\\\\Internal\\\\ProductAttributesLookup\\\\LookupDataStore';
	if (class_exists($lookup)) {
		wc_get_container()->get($lookup)->create_data_for_product(wc_get_product($id));
	}
}

echo get_term_link($parent_id, 'product_cat');`,
  ]);

  return { parentUrl };
}

/** Remove the product and terms ensureCategoryFilterFixtures() created. */
export function deleteCategoryFilterFixtures(): void {
  wpCli([
    'eval',
    `foreach (get_posts(array('post_type' => 'product', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '${FIXTURE_OWNER_META}', 'meta_value' => '${FIXTURE_OWNER}')) as $id) {
	wc_get_product($id)?->delete(true);
}
foreach (get_terms(array('taxonomy' => array('product_cat', 'pa_collection'), 'hide_empty' => false, 'meta_key' => '${FIXTURE_OWNER_META}', 'meta_value' => '${FIXTURE_OWNER}')) as $term) {
	wp_delete_term($term->term_id, $term->taxonomy);
}`,
  ]);
}
