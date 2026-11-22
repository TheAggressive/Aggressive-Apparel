<?php
/**
 * Title: Editorial — New Arrivals
 * Description: The newest in-stock product as a large lead card beside a two-by-two of the next four, from the live catalog. With fewer than five in stock it is one row sized to the count; with none it is omitted.
 * Slug: aggressive-apparel/editorial-new-arrivals
 * Categories: aggressive-editorial, aggressive-homepage, aggressive-products
 * Keywords: new arrivals, latest, products, woocommerce, homepage
 * Viewport Width: 1440
 *
 * @package Aggressive_Apparel
 */

use Aggressive_Apparel\WooCommerce\Editorial_Catalog;
use Aggressive_Apparel\WooCommerce\Editorial_Product_Grid;

// A lead card needs four beside it; fewer would leave holes in the 2 x 2,
// so a short catalog shows one row of what exists, and none shows nothing.
$aa_count = Editorial_Catalog::visible_count();
if ( 0 === $aa_count ) {
	return;
}
$aa_split = $aa_count >= 5;

?><!-- wp:group {"metadata":{"name":"03 New Arrivals"},"align":"full","className":"aa-shop-section","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-compact","bottom":"var:preset|spacing|section-compact","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"},"blockGap":"var:preset|spacing|fluid-12"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull aa-shop-section" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-compact);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-compact);padding-left:var(--wp--preset--spacing--gutter)"><!-- wp:group {"align":"wide","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group alignwide"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|3"}},"layout":{"type":"constrained","contentSize":"640px","justifyContent":"left"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow"><?php echo esc_html__( 'New in', 'aggressive-apparel' ); ?></p>
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

<?php if ( ! $aa_split ) : ?>
	<?php
	$aa_row = Editorial_Product_Grid::markup(
		array(
			'query_id'   => 302,
			'per_page'   => $aa_count,
			'columns'    => max( 2, $aa_count ),
			'collection' => 'new-arrivals',
			'label'      => __( 'New arrivals grid', 'aggressive-apparel' ),
		)
	);
	echo $aa_row; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; attributes encoded by core's serializer, classes escaped.
	?>
<?php else : ?>
<!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|8","left":"var:preset|spacing|8"}}}} -->
<div class="wp-block-columns alignwide"><!-- wp:column {"width":"50%"} -->
<div class="wp-block-column" style="flex-basis:50%">
	<?php
	// The newest piece, large; the next four beside it. Two Product
	// Collections over the same query (the second offset by one), so each
	// stays a native, retunable block. Always visible: products are never
	// held behind a scroll reveal.
	$aa_lead = Editorial_Product_Grid::markup(
		array(
			'query_id'   => 301,
			'per_page'   => 1,
			'columns'    => 1,
			'collection' => 'new-arrivals',
			'align'      => '',
			'label'      => __( 'Newest arrival', 'aggressive-apparel' ),
			'lead'       => true,
		)
	);
	echo $aa_lead; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; attributes encoded by core's serializer, classes escaped.
	?>
</div>
<!-- /wp:column -->

<!-- wp:column {"width":"50%"} -->
<div class="wp-block-column" style="flex-basis:50%">
	<?php
	$aa_grid = Editorial_Product_Grid::markup(
		array(
			'query_id'   => 302,
			'per_page'   => 4,
			'offset'     => 1,
			'columns'    => 2,
			'collection' => 'new-arrivals',
			'align'      => '',
			'label'      => __( 'New arrivals grid', 'aggressive-apparel' ),
		)
	);
	echo $aa_grid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; attributes encoded by core's serializer, classes escaped.
	?>
</div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<?php endif; ?>
</div>
<!-- /wp:group -->
