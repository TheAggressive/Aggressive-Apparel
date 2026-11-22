<?php
/**
 * Title: Editorial — Best Sellers
 * Description: Products ranked by real sales. Without sales data it shows the store's Featured products, and with neither it renders nothing — popularity is never invented.
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
// Two or three large cards beside the intro, sized to the picks that exist:
// two featured products fill the column instead of leaving a third of it empty.
$aa_columns = $aa_ranked ? 3 : max( 2, min( 3, Editorial_Catalog::visible_count( null, true ) ) );

?><!-- wp:group {"metadata":{"name":"05 Best Sellers"},"align":"full","className":"aa-shop-section","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-compact","bottom":"var:preset|spacing|section-compact","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"},"blockGap":"var:preset|spacing|fluid-12"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull aa-shop-section" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-compact);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-compact);padding-left:var(--wp--preset--spacing--gutter)"><!-- wp:columns {"verticalAlignment":"center","align":"wide","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|fluid-12","left":"var:preset|spacing|fluid-16"}}}} -->
<div class="wp-block-columns alignwide are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center","width":"30%","style":{"spacing":{"blockGap":"var:preset|spacing|6"}}} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:30%"><!-- wp:aggressive-blocks/animate-on-scroll {"animation":"slide","direction":"up","slideDistance":24,"duration":0.7,"threshold":"0.1","detectionBoundary":{"top":"100%","right":"0%","bottom":"0%","left":"0%"},"staggerChildren":true,"staggerDelay":0.08,"style":{"spacing":{"blockGap":"var:preset|spacing|6"}}} -->
<!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow"><?php echo esc_html( $aa_ranked ? __( 'Most wanted', 'aggressive-apparel' ) : __( 'Start here', 'aggressive-apparel' ) ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textColor":"foreground"} -->
<h2 class="wp-block-heading has-foreground-color has-text-color"><?php echo esc_html( $aa_ranked ? __( 'Best sellers', 'aggressive-apparel' ) : __( 'Featured', 'aggressive-apparel' ) ); ?></h2>
<!-- /wp:heading -->

<?php if ( $aa_ranked ) : ?>
<!-- wp:paragraph {"textColor":"foreground-muted"} -->
<p class="has-foreground-muted-color has-text-color"><?php echo esc_html__( 'Ranked by real orders, nothing else.', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->
<?php endif; ?>

<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}}}} -->
<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--4)"><!-- wp:button {"className":"is-style-ghost"} -->
<div class="wp-block-button is-style-ghost"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $aa_ranked ? Editorial_Catalog::shop_url( array( 'orderby' => 'popularity' ) ) : Editorial_Catalog::shop_url() ); ?>"><?php echo esc_html__( 'Shop the list', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
<!-- /wp:aggressive-blocks/animate-on-scroll --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"70%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:70%">
<?php
$aa_grid = Editorial_Product_Grid::markup(
	$aa_ranked
		? array(
			'query_id'   => 305,
			'per_page'   => $aa_columns,
			'columns'    => $aa_columns,
			'order_by'   => 'popularity',
			'collection' => 'best-sellers',
			'align'      => '',
			'label'      => __( 'Best sellers grid', 'aggressive-apparel' ),
		)
		: array(
			'query_id'   => 305,
			'per_page'   => $aa_columns,
			'columns'    => $aa_columns,
			'featured'   => true,
			'collection' => 'featured',
			'align'      => '',
			'label'      => __( 'Featured grid', 'aggressive-apparel' ),
		)
);
echo $aa_grid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; attributes encoded by core's serializer, classes escaped.
?>
</div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
