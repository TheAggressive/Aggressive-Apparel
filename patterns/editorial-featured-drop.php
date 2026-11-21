<?php
/**
 * Title: Editorial — Featured Drop
 * Description: Black band for the current drop: its name, line and CTA beside a full-height portrait photo of the drop and a two-up grid, or the title band above a grid sized to the drop's stock. Renders nothing without a stocked drop term (slugs filterable).
 * Slug: aggressive-apparel/editorial-featured-drop
 * Categories: aggressive-editorial, aggressive-homepage, aggressive-drops
 * Keywords: drop, release, collection, campaign, capsule, homepage
 * Viewport Width: 1440
 *
 * @package Aggressive_Apparel
 */

use Aggressive_Apparel\WooCommerce\Editorial_Catalog;
use Aggressive_Apparel\WooCommerce\Editorial_Collection_Band;

/**
 * Filters the store term slugs the Featured Drop section looks for.
 *
 * @param array<int, string> $slugs Candidate slugs, preferred first.
 */
$aa_slugs = (array) apply_filters( 'aggressive_apparel_featured_drop_slugs', array( 'dead-signal' ) );
$aa_drop  = Editorial_Catalog::stocked_term( array( 'pa_collection', 'product_cat', 'product_tag' ), array_map( 'strval', $aa_slugs ) );

// No drop in the store, no section: "Shop the drop" must lead to a drop, and
// New Arrivals already covers the newest products.
if ( null === $aa_drop ) {
	return;
}

$aa_band = Editorial_Collection_Band::markup(
	array(
		'term'       => $aa_drop,
		'query_id'   => 306,
		'name'       => '06 Featured Drop',
		'eyebrow'    => __( 'Featured drop', 'aggressive-apparel' ),
		'title'      => $aa_drop->name,
		'line'       => __( 'One idea, released as a set.', 'aggressive-apparel' ),
		'cta'        => __( 'Shop the drop', 'aggressive-apparel' ),
		'grid_label' => __( 'Drop products', 'aggressive-apparel' ),
	)
);
echo $aa_band; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; text escaped in the helper.
