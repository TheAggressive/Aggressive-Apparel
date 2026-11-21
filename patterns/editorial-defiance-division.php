<?php
/**
 * Title: Editorial — Defiance Division
 * Description: The motocross capsule's own treatment: a wide 16:9 action image (replace the image), racing-stripe accent and the capsule's products. Renders nothing when the store has no Defiance Division products.
 * Slug: aggressive-apparel/editorial-defiance-division
 * Categories: aggressive-editorial, aggressive-homepage, aggressive-drops
 * Keywords: defiance division, motocross, moto, capsule, collection, homepage
 * Viewport Width: 1440
 *
 * @package Aggressive_Apparel
 */

use Aggressive_Apparel\WooCommerce\Editorial_Catalog;
use Aggressive_Apparel\WooCommerce\Editorial_Product_Grid;

$aa_term = Editorial_Catalog::stocked_term( array( 'pa_collection', 'product_cat', 'product_tag' ), array( 'defiance-division' ) );

// The section sells the capsule; without it in the store there is nothing to
// link "Shop Defiance Division" to.
if ( null === $aa_term ) {
	return;
}

?><!-- wp:group {"metadata":{"name":"09 Defiance Division"},"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-editorial","bottom":"var:preset|spacing|section-editorial","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"},"blockGap":"var:preset|spacing|fluid-16"},"elements":{"link":{"color":{"text":"var:preset|color|white"}}}},"backgroundColor":"black","textColor":"white","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-white-color has-black-background-color has-text-color has-background has-link-color" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-editorial);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-editorial);padding-left:var(--wp--preset--spacing--gutter)"><!-- wp:columns {"verticalAlignment":"bottom","align":"wide","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|8","left":"var:preset|spacing|fluid-16"}}}} -->
<div class="wp-block-columns alignwide are-vertically-aligned-bottom"><!-- wp:column {"verticalAlignment":"bottom","width":"60%"} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:60%"><!-- wp:separator {"className":"is-style-brand-stripe"} -->
<hr class="wp-block-separator has-alpha-channel-opacity is-style-brand-stripe"/>
<!-- /wp:separator -->

<!-- wp:paragraph {"className":"is-style-meta","textColor":"white"} -->
<p class="is-style-meta has-white-color has-text-color"><?php echo esc_html__( 'Capsule collection', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"is-style-campaign","textColor":"white"} -->
<h2 class="wp-block-heading is-style-campaign has-white-color has-text-color"><?php echo esc_html__( 'Defiance Division', 'aggressive-apparel' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"bottom","width":"40%"} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:40%"><!-- wp:paragraph {"className":"is-style-lead","textColor":"white"} -->
<p class="is-style-lead has-white-color has-text-color"><?php echo esc_html__( 'A capsule drawn from moto culture.', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-outline-on-dark"} -->
<div class="wp-block-button is-style-outline-on-dark"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( Editorial_Catalog::url( $aa_term ) ); ?>"><?php echo esc_html__( 'Shop Defiance Division', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:cover {"dimRatio":100,"overlayColor":"surface-muted","isUserOverlayColor":true,"isDark":false,"metadata":{"name":"Defiance Division action image (replace image)"},"align":"wide","style":{"dimensions":{"aspectRatio":"16/9"}}} -->
<div class="wp-block-cover alignwide is-light"><span aria-hidden="true" class="wp-block-cover__background has-surface-muted-background-color has-background-dim-100 has-background-dim"></span><div class="wp-block-cover__inner-container"></div></div>
<!-- /wp:cover -->
<?php
$aa_grid = Editorial_Product_Grid::markup(
	array(
		'query_id' => 309,
		'per_page' => 4,
		'columns'  => 4,
		'term'     => $aa_term,
		'label'    => __( 'Defiance Division products', 'aggressive-apparel' ),
	)
);
echo $aa_grid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; attributes encoded by core's serializer, classes escaped.
?>
</div>
<!-- /wp:group -->
