<?php
/**
 * Title: Editorial — New Arrivals
 * Description: One scrolling rail of the newest in-stock products after the cover's garment, from the live catalog. Omitted when nothing follows the cover.
 * Slug: aggressive-apparel/editorial-new-arrivals
 * Categories: aggressive-editorial, aggressive-homepage, aggressive-products
 * Keywords: new arrivals, latest, products, woocommerce, homepage
 * Viewport Width: 1440
 *
 * @package Aggressive_Apparel
 */

use Aggressive_Apparel\WooCommerce\Editorial_Catalog;
use Aggressive_Apparel\WooCommerce\Editorial_Product_Grid;

// The cover shows the newest product; with nothing after it there is no rail.
$aa_count = Editorial_Catalog::visible_count();
if ( $aa_count < 2 ) {
	return;
}

?><!-- wp:group {"metadata":{"name":"02 New Arrivals"},"align":"full","className":"aa-shop-section","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-compact","bottom":"var:preset|spacing|section-compact","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"},"blockGap":"var:preset|spacing|fluid-12"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull aa-shop-section" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-compact);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-compact);padding-left:var(--wp--preset--spacing--gutter)"><!-- wp:group {"align":"wide","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group alignwide"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|3"}},"layout":{"type":"constrained","contentSize":"640px","justifyContent":"left"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"is-style-eyebrow aa-chapter-index"} -->
<p class="is-style-eyebrow aa-chapter-index"><?php echo esc_html__( 'New in', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"aa-chapter-title","textColor":"foreground"} -->
<h2 class="wp-block-heading aa-chapter-title has-foreground-color has-text-color"><?php echo esc_html__( 'New arrivals', 'aggressive-apparel' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-text"} -->
<div class="wp-block-button is-style-text"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( Editorial_Catalog::shop_url( array( 'orderby' => 'date' ) ) ); ?>"><?php echo esc_html__( 'Shop new arrivals', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<?php
// One scrolling row of the newest pieces after the cover's garment, which
// opens the page directly above. A rail shows a longer range as more,
// without a second grid repeating the categories' rhythm.
$aa_rail = Editorial_Product_Grid::markup(
	array(
		'query_id'   => 302,
		'per_page'   => min( 8, $aa_count - 1 ),
		'offset'     => 1,
		'columns'    => 4,
		'collection' => 'new-arrivals',
		'rail'       => true,
		'label'      => __( 'New arrivals rail', 'aggressive-apparel' ),
	)
);
echo $aa_rail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; attributes encoded by core's serializer, classes escaped.
?>
</div>
<!-- /wp:group -->
