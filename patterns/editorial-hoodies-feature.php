<?php
/**
 * Title: Editorial — Hoodies Feature
 * Description: The hoodies as a lineup: up to four side by side at garment scale on one studio band, each named with its live price below, then one link to the category. Five or more become a rail.
 * Slug: aggressive-apparel/editorial-hoodies-feature
 * Categories: aggressive-editorial, aggressive-homepage, aggressive-products
 * Keywords: hoodies, sweatshirts, category, products, homepage
 * Viewport Width: 1440
 *
 * @package Aggressive_Apparel
 */

use Aggressive_Apparel\WooCommerce\Editorial_Catalog;
use Aggressive_Apparel\WooCommerce\Editorial_Product_Grid;

$aa_term = Editorial_Catalog::stocked_term( array( 'product_cat' ), array( 'hoodies', 'hoodie', 'sweatshirts' ) );

// "Shop all hoodies" must lead to hoodies, not the whole catalog.
if ( null === $aa_term ) {
	return;
}
$aa_count = Editorial_Catalog::visible_count( $aa_term );
if ( 0 === $aa_count ) {
	return;
}

?><!-- wp:group {"metadata":{"name":"08 Hoodies"},"align":"full","className":"aa-shop-section aa-lineup","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-compact","bottom":"var:preset|spacing|section-compact","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"},"blockGap":"var:preset|spacing|fluid-12"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull aa-shop-section aa-lineup" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-compact);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-compact);padding-left:var(--wp--preset--spacing--gutter)"><!-- wp:group {"align":"wide","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group alignwide"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|3"}},"layout":{"type":"constrained","contentSize":"640px","justifyContent":"left"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"is-style-eyebrow aa-chapter-index"} -->
<p class="is-style-eyebrow aa-chapter-index"><?php echo esc_html__( 'Heavy rotation', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"aa-chapter-title","textColor":"foreground"} -->
<h2 class="wp-block-heading aa-chapter-title has-foreground-color has-text-color"><?php echo esc_html__( 'Hoodies', 'aggressive-apparel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"foreground-muted"} -->
<p class="has-foreground-muted-color has-text-color"><?php echo esc_html__( 'For the cold walk home.', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-text"} -->
<div class="wp-block-button is-style-text"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( Editorial_Catalog::url( $aa_term ) ); ?>"><?php echo esc_html__( 'Shop all hoodies', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<?php
// Garments side by side on one studio band (aa-grid-lineup): a range read
// as one uniform. More than four scroll as a rail.
$aa_grid = Editorial_Product_Grid::markup(
	array(
		'query_id' => 308,
		'per_page' => min( 8, $aa_count ),
		'columns'  => max( 3, min( 4, $aa_count ) ),
		'centered' => $aa_count < 3,
		'term'     => $aa_term,
		'rail'     => $aa_count > 4,
		'lineup'   => true,
		'label'    => __( 'Hoodies lineup', 'aggressive-apparel' ),
	)
);
echo $aa_grid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; attributes encoded by core's serializer, classes escaped.
?>
</div>
<!-- /wp:group -->
