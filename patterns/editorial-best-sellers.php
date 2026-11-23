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
// Three large numbered cards: a ranking reads 01-03; featured picks, in order.
$aa_columns = $aa_ranked ? 3 : max( 2, min( 3, Editorial_Catalog::visible_count( null, true ) ) );

?><!-- wp:group {"metadata":{"name":"05 Best Sellers"},"align":"full","className":"aa-shop-section","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-compact","bottom":"var:preset|spacing|section-compact","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"},"blockGap":"var:preset|spacing|fluid-12"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull aa-shop-section" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-compact);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-compact);padding-left:var(--wp--preset--spacing--gutter)"><!-- wp:group {"align":"wide","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group alignwide"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|3"}},"layout":{"type":"constrained","contentSize":"640px","justifyContent":"left"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"is-style-eyebrow aa-chapter-index"} -->
<p class="is-style-eyebrow aa-chapter-index"><?php echo esc_html( $aa_ranked ? __( 'Most wanted', 'aggressive-apparel' ) : __( 'Start here', 'aggressive-apparel' ) ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"aa-chapter-title","textColor":"foreground"} -->
<h2 class="wp-block-heading aa-chapter-title has-foreground-color has-text-color"><?php echo esc_html( $aa_ranked ? __( 'Best sellers', 'aggressive-apparel' ) : __( 'Featured', 'aggressive-apparel' ) ); ?></h2>
<!-- /wp:heading -->
<?php if ( $aa_ranked ) : ?>

<!-- wp:paragraph {"textColor":"foreground-muted"} -->
<p class="has-foreground-muted-color has-text-color"><?php echo esc_html__( 'Ranked by real orders, nothing else.', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->
<?php endif; ?>
</div>
<!-- /wp:group -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-text"} -->
<div class="wp-block-button is-style-text"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $aa_ranked ? Editorial_Catalog::shop_url( array( 'orderby' => 'popularity' ) ) : Editorial_Catalog::shop_url() ); ?>"><?php echo esc_html__( 'Shop the list', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<?php
$aa_grid = Editorial_Product_Grid::markup(
	$aa_ranked
		? array(
			'query_id'   => 305,
			'per_page'   => $aa_columns,
			'columns'    => $aa_columns,
			'order_by'   => 'popularity',
			'collection' => 'best-sellers',
			'numbered'   => true,
			'label'      => __( 'Best sellers grid', 'aggressive-apparel' ),
		)
		: array(
			'query_id'   => 305,
			'per_page'   => $aa_columns,
			'columns'    => $aa_columns,
			'featured'   => true,
			'collection' => 'featured',
			'numbered'   => true,
			'label'      => __( 'Featured grid', 'aggressive-apparel' ),
		)
);
echo $aa_grid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; attributes encoded by core's serializer, classes escaped.
?>
</div>
<!-- /wp:group -->
