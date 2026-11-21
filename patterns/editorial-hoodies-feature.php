<?php
/**
 * Title: Editorial — Hoodies Feature
 * Description: Oversized centered category headline over a three-up grid of real hoodies, with a single shopping CTA.
 * Slug: aggressive-apparel/editorial-hoodies-feature
 * Categories: aggressive-editorial, aggressive-homepage, aggressive-products
 * Keywords: hoodies, sweatshirts, category, products, homepage
 * Viewport Width: 1440
 *
 * @package Aggressive_Apparel
 */

use Aggressive_Apparel\WooCommerce\Editorial_Catalog;
use Aggressive_Apparel\WooCommerce\Editorial_Product_Grid;

$aa_term = Editorial_Catalog::stocked_term( array( 'product_cat' ), array( 'hoodies', 'hoodie', 'sweatshirts' ) );

// "Shop all hoodies" must lead to hoodies, not the whole catalog.
if ( null === $aa_term ) {
	return;
}

?><!-- wp:group {"metadata":{"name":"08 Hoodies"},"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-standard","bottom":"var:preset|spacing|section-standard","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"},"blockGap":"var:preset|spacing|fluid-12"}},"backgroundColor":"surface-elevated","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-surface-elevated-background-color has-background" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-standard);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-standard);padding-left:var(--wp--preset--spacing--gutter)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|4"}},"layout":{"type":"constrained","contentSize":"760px"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"align":"center","className":"is-style-eyebrow"} -->
<p class="has-text-align-center is-style-eyebrow"><?php echo esc_html__( 'Layers', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textAlign":"center","className":"is-style-campaign"} -->
<h2 class="wp-block-heading has-text-align-center is-style-campaign"><?php echo esc_html__( 'Hoodies', 'aggressive-apparel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","className":"is-style-lead","textColor":"foreground-muted"} -->
<p class="has-text-align-center is-style-lead has-foreground-muted-color has-text-color"><?php echo esc_html__( 'The artwork, in its warmest form.', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<?php
$aa_grid = Editorial_Product_Grid::markup(
	array(
		'query_id' => 308,
		'per_page' => 3,
		'columns'  => 3,
		'term'     => $aa_term,
		'label'    => __( 'Hoodies grid', 'aggressive-apparel' ),
		'centered' => true,
	)
);
echo $aa_grid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; attributes encoded by core's serializer, classes escaped.
?>

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-cta"} -->
<div class="wp-block-button is-style-cta"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( Editorial_Catalog::url( $aa_term ) ); ?>"><?php echo esc_html__( 'Shop all hoodies', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
