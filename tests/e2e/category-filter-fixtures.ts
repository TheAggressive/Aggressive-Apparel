import { wpCli } from './wp-cli';

/**
 * A parent category whose only product sits in a child category and carries
 * its own Collection term. A parent archive lists child-category products, so
 * the filter panel must treat that collection as available there.
 *
 * Idempotent; created through WooCommerce APIs only.
 */

export const PARENT_CATEGORY = 'e2e-filter-parent';
export const PARENT_NAME = 'E2E Filter Parent';
export const CHILD_COLLECTION = 'e2e-child-only';

export interface CategoryFilterFixtures {
  parentUrl: string;
}

export function ensureCategoryFilterFixtures(): CategoryFilterFixtures {
  const parentUrl = wpCli([
    'eval',
    `$features = get_option('aggressive_apparel_wc_features', array());
$features = is_array($features) ? $features : array();
$features['product_filters'] = 1;
update_option('aggressive_apparel_wc_features', $features, false);

if (!wc_attribute_taxonomy_id_by_name('collection')) {
	wc_create_attribute(array('name' => 'Collection', 'slug' => 'collection'));
}
// WooCommerce registers new attribute taxonomies on the next request.
if (!taxonomy_exists('pa_collection')) {
	register_taxonomy('pa_collection', array('product'), array('hierarchical' => false));
}
if (!term_exists('${CHILD_COLLECTION}', 'pa_collection')) {
	wp_insert_term('E2E Child Only', 'pa_collection', array('slug' => '${CHILD_COLLECTION}'));
}
$collection = get_term_by('slug', '${CHILD_COLLECTION}', 'pa_collection');

$parent = term_exists('${PARENT_CATEGORY}', 'product_cat');
if (!$parent) {
	$parent = wp_insert_term('${PARENT_NAME}', 'product_cat', array('slug' => '${PARENT_CATEGORY}'));
}
$parent_id = (int) (is_array($parent) ? $parent['term_id'] : $parent);

$child = term_exists('e2e-filter-child', 'product_cat');
if (!$child) {
	$child = wp_insert_term('E2E Filter Child', 'product_cat', array('slug' => 'e2e-filter-child', 'parent' => $parent_id));
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
