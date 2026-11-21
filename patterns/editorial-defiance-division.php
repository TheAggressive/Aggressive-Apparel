<?php
/**
 * Title: Editorial — Defiance Division
 * Description: Black band for the motocross capsule: its title, line and CTA beside a full-height portrait photo of the collection and a two-up grid, or the title band above a grid sized to the collection's stock. Renders nothing without stocked Defiance Division products.
 * Slug: aggressive-apparel/editorial-defiance-division
 * Categories: aggressive-editorial, aggressive-homepage, aggressive-drops
 * Keywords: defiance division, motocross, moto, capsule, collection, homepage
 * Viewport Width: 1440
 *
 * @package Aggressive_Apparel
 */

use Aggressive_Apparel\WooCommerce\Editorial_Catalog;
use Aggressive_Apparel\WooCommerce\Editorial_Collection_Band;

$aa_term = Editorial_Catalog::stocked_term( array( 'pa_collection', 'product_cat', 'product_tag' ), array( 'defiance-division' ) );

// The section sells the capsule; without it in the store there is nothing to
// link "Shop Defiance Division" to.
if ( null === $aa_term ) {
	return;
}

$aa_band = Editorial_Collection_Band::markup(
	array(
		'term'       => $aa_term,
		'query_id'   => 309,
		'name'       => '09 Defiance Division',
		'eyebrow'    => __( 'Capsule collection', 'aggressive-apparel' ),
		'title'      => __( 'Defiance Division', 'aggressive-apparel' ),
		'line'       => __( 'Drawn from the pit lane, not the showroom.', 'aggressive-apparel' ),
		'cta'        => __( 'Shop Defiance Division', 'aggressive-apparel' ),
		'grid_label' => __( 'Defiance Division products', 'aggressive-apparel' ),
	)
);
echo $aa_band; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; text escaped in the helper.
