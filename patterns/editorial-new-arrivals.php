<?php
/**
 * Title: Editorial — New Arrivals
 * Description: The newest in-stock products from the live catalog in a spacious four-up grid.
 * Slug: aggressive-apparel/editorial-new-arrivals
 * Categories: aggressive-editorial, aggressive-homepage, aggressive-products
 * Keywords: new arrivals, latest, products, woocommerce, homepage
 * Viewport Width: 1440
 *
 * @package Aggressive_Apparel
 */

use Aggressive_Apparel\WooCommerce\Editorial_Catalog;
use Aggressive_Apparel\WooCommerce\Editorial_Product_Grid;

?><!-- wp:group {"metadata":{"name":"03 New Arrivals"},"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-compact","bottom":"var:preset|spacing|section-standard","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"},"blockGap":"var:preset|spacing|fluid-12"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-compact);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-standard);padding-left:var(--wp--preset--spacing--gutter)"><!-- wp:group {"align":"wide","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group alignwide"><!-- wp:group {"layout":{"type":"constrained","contentSize":"640px","justifyContent":"left"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow"><?php echo esc_html__( 'Newest first', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textColor":"foreground"} -->
<h2 class="wp-block-heading has-foreground-color has-text-color"><?php echo esc_html__( 'New arrivals', 'aggressive-apparel' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-ghost"} -->
<div class="wp-block-button is-style-ghost"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( Editorial_Catalog::shop_url( array( 'orderby' => 'date' ) ) ); ?>"><?php echo esc_html__( 'Shop new arrivals', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<!-- wp:aggressive-blocks/animate-on-scroll {"animation":"slide","direction":"up","slideDistance":24,"duration":0.7,"align":"wide"} -->
<?php
$aa_grid = Editorial_Product_Grid::markup(
	array(
		'query_id'   => 301,
		'per_page'   => 8,
		'columns'    => 4,
		'collection' => 'new-arrivals',
		'label'      => __( 'New arrivals grid', 'aggressive-apparel' ),
	)
);
echo $aa_grid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; attributes encoded by core's serializer, classes escaped.
?>
<!-- /wp:aggressive-blocks/animate-on-scroll -->
</div>
<!-- /wp:group -->
