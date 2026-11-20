<?php
/**
 * Title: Editorial — Best Sellers
 * Description: Products ranked by real sales. Without sales data it shows the store's Featured products as Staff Picks, and with neither it renders nothing — popularity is never invented.
 * Slug: aggressive-apparel/editorial-best-sellers
 * Categories: aggressive-editorial, aggressive-homepage, aggressive-products
 * Keywords: best sellers, popular, top, products, woocommerce, homepage
 * Viewport Width: 1440
 *
 * @package Aggressive_Apparel
 */

use Aggressive_Apparel\WooCommerce\Editorial_Catalog;
use Aggressive_Apparel\WooCommerce\Editorial_Product_Grid;

$aa_ranked   = Editorial_Catalog::has_sales_data();
$aa_featured = ! $aa_ranked && Editorial_Catalog::has_featured_products();

if ( ! $aa_ranked && ! $aa_featured ) {
	return;
}

?><!-- wp:group {"metadata":{"name":"05 Best Sellers"},"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-standard","bottom":"var:preset|spacing|section-standard","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"},"blockGap":"var:preset|spacing|fluid-12"}},"backgroundColor":"surface-muted","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-surface-muted-background-color has-background" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-standard);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-standard);padding-left:var(--wp--preset--spacing--gutter)"><!-- wp:group {"align":"wide","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group alignwide"><!-- wp:group {"layout":{"type":"constrained","contentSize":"640px","justifyContent":"left"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow"><?php echo esc_html( $aa_ranked ? __( 'Most ordered', 'aggressive-apparel' ) : __( 'Chosen by the studio', 'aggressive-apparel' ) ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textColor":"foreground"} -->
<h2 class="wp-block-heading has-foreground-color has-text-color"><?php echo esc_html( $aa_ranked ? __( 'Best sellers', 'aggressive-apparel' ) : __( 'Staff picks', 'aggressive-apparel' ) ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-ghost"} -->
<div class="wp-block-button is-style-ghost"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $aa_ranked ? Editorial_Catalog::shop_url( array( 'orderby' => 'popularity' ) ) : Editorial_Catalog::shop_url() ); ?>"><?php echo esc_html__( 'Shop the list', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<?php
$aa_grid = Editorial_Product_Grid::markup(
	$aa_ranked
		? array(
			'query_id'   => 305,
			'per_page'   => 3,
			'columns'    => 3,
			'order_by'   => 'popularity',
			'collection' => 'best-sellers',
			'label'      => __( 'Best sellers grid', 'aggressive-apparel' ),
		)
		: array(
			'query_id'   => 305,
			'per_page'   => 3,
			'columns'    => 3,
			'featured'   => true,
			'collection' => 'featured',
			'label'      => __( 'Staff picks grid', 'aggressive-apparel' ),
		)
);
echo $aa_grid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; attributes encoded by core's serializer, classes escaped.
?>
</div>
<!-- /wp:group -->
