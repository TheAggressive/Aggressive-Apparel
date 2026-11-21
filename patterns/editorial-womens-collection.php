<?php
/**
 * Title: Editorial — Women's Collection
 * Description: A tall portrait photo of the collection beside the statement and a two-up grid of real women's products. Shows the newest women's product photo until campaign photography replaces it; with one product the photo carries the section alone, without a portrait photo the grid takes its place.
 * Slug: aggressive-apparel/editorial-womens-collection
 * Categories: aggressive-editorial, aggressive-homepage, aggressive-products
 * Keywords: women, womens, collection, editorial, products, homepage
 * Viewport Width: 1440
 *
 * @package Aggressive_Apparel
 */

use Aggressive_Apparel\WooCommerce\Editorial_Catalog;
use Aggressive_Apparel\WooCommerce\Editorial_Imagery;
use Aggressive_Apparel\WooCommerce\Editorial_Product_Grid;

// The Collection attribute drives the /shop/womens/ landing page; a
// category of the same name is the fallback.
$aa_term = Editorial_Catalog::stocked_term( array( 'pa_collection', 'product_cat' ), array( 'womens', 'women', 'womens-2' ) );

// "Shop women's" must lead to women's products, not the whole catalog.
if ( null === $aa_term ) {
	return;
}
$aa_url = 'pa_collection' === $aa_term->taxonomy
	? Editorial_Catalog::audience_url( $aa_term->slug )
	: Editorial_Catalog::url( $aa_term );

// Sized to what the collection can actually show: no half-empty grid.
$aa_count = Editorial_Catalog::visible_count( $aa_term );
if ( 0 === $aa_count ) {
	return;
}
$aa_lead = Editorial_Imagery::leads( 1, $aa_term )[0] ?? null;
if ( null !== $aa_lead ) {
	// Beside the photo: a 2 x 2 or a single row of two, never one lone card
	// (the photo already shows a one-product collection).
	$aa_columns  = 2;
	$aa_per_page = $aa_count >= 4 ? 4 : ( $aa_count >= 2 ? 2 : 0 );
} else {
	$aa_columns  = max( 2, min( 3, $aa_count ) );
	$aa_per_page = min( 3, $aa_count );
}
$aa_text_width = null !== $aa_lead ? '55%' : '40%';

?><!-- wp:group {"metadata":{"name":"07 Women's Collection"},"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-editorial","bottom":"var:preset|spacing|section-editorial","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-editorial);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-editorial);padding-left:var(--wp--preset--spacing--gutter)"><!-- wp:columns {"verticalAlignment":"center","align":"wide","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|fluid-12","left":"var:preset|spacing|fluid-16"}}}} -->
<div class="wp-block-columns alignwide are-vertically-aligned-center">
<?php if ( null !== $aa_lead ) : ?>
<!-- wp:column {"verticalAlignment":"center","width":"45%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:45%">
	<?php echo Editorial_Imagery::image_markup( $aa_lead, '4/5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; URLs and alt escaped in image_markup(). ?>
</div>
<!-- /wp:column -->
<?php endif; ?>

<!-- wp:column {"verticalAlignment":"center","width":"<?php echo esc_attr( $aa_text_width ); ?>","style":{"spacing":{"blockGap":"var:preset|spacing|fluid-12"}}} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:<?php echo esc_attr( $aa_text_width ); ?>"><!-- wp:group {"layout":{"type":"constrained","contentSize":"560px","justifyContent":"left"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow"><?php echo esc_html__( "Women's", 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"is-style-headline"} -->
<h2 class="wp-block-heading is-style-headline"><?php echo esc_html__( 'Cut for women, not shrunk for them.', 'aggressive-apparel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"is-style-lead","textColor":"foreground-muted"} -->
<p class="is-style-lead has-foreground-muted-color has-text-color"><?php echo esc_html__( 'Women’s fits, nothing toned down.', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-ghost"} -->
<div class="wp-block-button is-style-ghost"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $aa_url ); ?>"><?php echo esc_html__( "Shop women's", 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
<?php
if ( null !== $aa_lead && $aa_per_page > 0 ) {
	$aa_grid = Editorial_Product_Grid::markup(
		array(
			'query_id' => 307,
			'per_page' => $aa_per_page,
			'columns'  => $aa_columns,
			'term'     => $aa_term,
			'align'    => '',
			'label'    => __( "Women's products", 'aggressive-apparel' ),
		)
	);
	echo $aa_grid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; attributes encoded by core's serializer, classes escaped.
}
?>
</div>
<!-- /wp:column -->
<?php if ( null === $aa_lead ) : ?>

<!-- wp:column {"width":"60%"} -->
<div class="wp-block-column" style="flex-basis:60%">
	<?php
	$aa_grid = Editorial_Product_Grid::markup(
		array(
			'query_id' => 307,
			'per_page' => $aa_per_page,
			'columns'  => $aa_columns,
			'term'     => $aa_term,
			'align'    => '',
			'label'    => __( "Women's products", 'aggressive-apparel' ),
		)
	);
	echo $aa_grid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; attributes encoded by core's serializer, classes escaped.
	?>
</div>
<!-- /wp:column -->
<?php endif; ?>
</div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
