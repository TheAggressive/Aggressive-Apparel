<?php
/**
 * Title: Editorial — Accessories
 * Description: The accessories actually in stock, with a short header and one link to the category: a row of up to four, or a sideways-scrolling rail of up to eight that shows three and a half.
 * Slug: aggressive-apparel/editorial-accessories
 * Categories: aggressive-editorial, aggressive-homepage, aggressive-products
 * Keywords: accessories, hats, bags, products, homepage
 * Viewport Width: 1440
 *
 * @package Aggressive_Apparel
 */

use Aggressive_Apparel\WooCommerce\Editorial_Catalog;
use Aggressive_Apparel\WooCommerce\Editorial_Product_Grid;

$aa_term = Editorial_Catalog::stocked_term( array( 'product_cat' ), array( 'accessories' ) );
if ( null === $aa_term ) {
	return;
}
$aa_count = Editorial_Catalog::visible_count( $aa_term );
if ( 0 === $aa_count ) {
	return;
}
// Four across at most: five made the smallest cards on the page. More than
// four become a rail of up to eight that shows three and a half, so the
// range reads as a range rather than another row of four.
$aa_columns = max( 2, min( 4, $aa_count ) );
$aa_rail    = $aa_count > 4;

?><!-- wp:group {"metadata":{"name":"10 Accessories"},"align":"full","className":"aa-shop-section","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-compact","bottom":"var:preset|spacing|section-compact","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"},"blockGap":"var:preset|spacing|fluid-12"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull aa-shop-section" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-compact);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-compact);padding-left:var(--wp--preset--spacing--gutter)"><!-- wp:group {"align":"wide","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group alignwide"><!-- wp:group {"layout":{"type":"constrained","contentSize":"640px","justifyContent":"left"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow"><?php echo esc_html__( 'The small stuff', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textColor":"foreground"} -->
<h2 class="wp-block-heading has-foreground-color has-text-color"><?php echo esc_html__( 'Accessories', 'aggressive-apparel' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-text"} -->
<div class="wp-block-button is-style-text"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( Editorial_Catalog::url( $aa_term ) ); ?>"><?php echo esc_html__( 'Shop accessories', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<?php
$aa_grid = Editorial_Product_Grid::markup(
	array(
		'query_id' => 310,
		'per_page' => $aa_rail ? min( 8, $aa_count ) : $aa_columns,
		'columns'  => $aa_columns,
		'rail'     => $aa_rail,
		'term'     => $aa_term,
		'label'    => __( 'Accessories row', 'aggressive-apparel' ),
	)
);
echo $aa_grid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; attributes encoded by core's serializer, classes escaped.
?>
</div>
<!-- /wp:group -->
