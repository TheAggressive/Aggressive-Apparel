<?php
/**
 * Title: Editorial — Women's Collection
 * Description: A tall editorial portrait (replace the image) beside a two-up grid of real women's products and a path to the full collection.
 * Slug: aggressive-apparel/editorial-womens-collection
 * Categories: aggressive-editorial, aggressive-homepage, aggressive-products
 * Keywords: women, womens, collection, editorial, products, homepage
 * Viewport Width: 1440
 *
 * @package Aggressive_Apparel
 */

use Aggressive_Apparel\WooCommerce\Editorial_Catalog;
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

?><!-- wp:group {"metadata":{"name":"07 Women's Collection"},"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-editorial","bottom":"var:preset|spacing|section-editorial","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-editorial);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-editorial);padding-left:var(--wp--preset--spacing--gutter)"><!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|fluid-12","left":"var:preset|spacing|fluid-16"}}}} -->
<div class="wp-block-columns alignwide"><!-- wp:column {"width":"45%"} -->
<div class="wp-block-column" style="flex-basis:45%"><!-- wp:cover {"dimRatio":100,"overlayColor":"surface-sunken","isUserOverlayColor":true,"isDark":false,"metadata":{"name":"Women's editorial portrait (replace image)"},"style":{"dimensions":{"aspectRatio":"4/5"}}} -->
<div class="wp-block-cover is-light"><span aria-hidden="true" class="wp-block-cover__background has-surface-sunken-background-color has-background-dim-100 has-background-dim"></span><div class="wp-block-cover__inner-container"></div></div>
<!-- /wp:cover --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"55%","style":{"spacing":{"blockGap":"var:preset|spacing|fluid-12"}}} -->
<div class="wp-block-column" style="flex-basis:55%"><!-- wp:group {"layout":{"type":"constrained","contentSize":"560px","justifyContent":"left"}} -->
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
$aa_grid = Editorial_Product_Grid::markup(
	array(
		'query_id' => 307,
		'per_page' => 4,
		'columns'  => 2,
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
