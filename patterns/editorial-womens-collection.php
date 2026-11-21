<?php
/**
 * Title: Editorial — Women's Collection
 * Description: A light editorial spread: a full-height portrait photo of the collection running off the right edge, beside the collection name and a two-up grid of real women's products. Shows the newest women's product photo until campaign photography replaces it; with one product the photo carries the section alone, without a portrait photo the grid takes its place.
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
	// Beside the photo: one row of two, never one lone card (the photo
	// already shows a one-product collection). A 2 x 2 made the copy column
	// so tall the full-height photo stretched far past its own size.
	$aa_columns  = 2;
	$aa_per_page = $aa_count >= 2 ? 2 : 0;
} else {
	$aa_columns  = max( 2, min( 3, $aa_count ) );
	$aa_per_page = min( 3, $aa_count );
}

// With a photo: an edge-to-edge spread, copy first, the photo running the
// full height off the right edge (the drop band above puts its photo left,
// so on phones photo and copy alternate). The copy's left padding lines it
// up with the wide column on very wide screens.
?><!-- wp:group {"metadata":{"name":"07 Women's Collection"},"align":"full","style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0">
<?php if ( null !== $aa_lead ) : ?>
<!-- wp:columns {"className":"aa-tablet-split","align":"full","style":{"spacing":{"blockGap":{"top":"0","left":"0"},"margin":{"top":"0","bottom":"0"}}}} -->
<div class="wp-block-columns aa-tablet-split alignfull" style="margin-top:0;margin-bottom:0"><!-- wp:column {"verticalAlignment":"center","width":"55%","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-standard","right":"var:preset|spacing|gutter","bottom":"var:preset|spacing|section-standard","left":"max(var(--wp--preset--spacing--gutter), calc((100% - var(--wp--style--global--wide-size)) / 2))"},"blockGap":"var:preset|spacing|fluid-12"}}} -->
<div class="wp-block-column is-vertically-aligned-center" style="padding-top:var(--wp--preset--spacing--section-standard);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-standard);padding-left:max(var(--wp--preset--spacing--gutter), calc((100% - var(--wp--style--global--wide-size)) / 2));flex-basis:55%"><!-- wp:group {"layout":{"type":"constrained","contentSize":"560px","justifyContent":"left"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow"><?php echo esc_html__( 'Collection', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"is-style-campaign"} -->
<h2 class="wp-block-heading is-style-campaign"><?php echo esc_html__( 'Women’s', 'aggressive-apparel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-ghost"} -->
<div class="wp-block-button is-style-ghost"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $aa_url ); ?>"><?php echo esc_html__( "Shop women's", 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
	<?php if ( $aa_per_page > 0 ) : ?>
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
	<?php endif; ?>
</div>
<!-- /wp:column -->

<!-- wp:column {"width":"45%","className":"aa-bleed-photo"} -->
<div class="wp-block-column aa-bleed-photo" style="flex-basis:45%">
	<?php echo Editorial_Imagery::image_markup( $aa_lead, '4/5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; URLs and alt escaped in image_markup(). ?>
</div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<?php else : ?>
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|section-standard","right":"var:preset|spacing|gutter","bottom":"var:preset|spacing|section-standard","left":"var:preset|spacing|gutter"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--section-standard);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-standard);padding-left:var(--wp--preset--spacing--gutter)"><!-- wp:columns {"verticalAlignment":"center","align":"wide","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|fluid-12","left":"var:preset|spacing|fluid-16"}}}} -->
<div class="wp-block-columns alignwide are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center","width":"40%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:40%"><!-- wp:group {"layout":{"type":"constrained","contentSize":"560px","justifyContent":"left"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow"><?php echo esc_html__( 'Collection', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"is-style-campaign"} -->
<h2 class="wp-block-heading is-style-campaign"><?php echo esc_html__( 'Women’s', 'aggressive-apparel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-ghost"} -->
<div class="wp-block-button is-style-ghost"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $aa_url ); ?>"><?php echo esc_html__( "Shop women's", 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
</div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"60%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:60%">
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
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
<?php endif; ?>
</div>
<!-- /wp:group -->
