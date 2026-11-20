<?php
/**
 * Title: Editorial — Category Showcase
 * Description: Portrait category tiles that read each category's own image from the store; the whole tile links to the category.
 * Slug: aggressive-apparel/editorial-category-showcase
 * Categories: aggressive-editorial, aggressive-homepage
 * Keywords: categories, shop, tiles, editorial, homepage
 * Viewport Width: 1440
 *
 * @package Aggressive_Apparel
 */

use Aggressive_Apparel\WooCommerce\Editorial_Catalog;

// Candidate slugs per tile, first match wins; a tile is left out when the
// store has no such category, so every tile links somewhere real.
$aa_tiles = array(
	array(
		'slugs' => array( 't-shirts', 'tshirts', 'tees' ),
	),
	array(
		'slugs' => array( 'hoodies', 'hoodie', 'sweatshirts' ),
	),
	array(
		'slugs' => array( 'outerwear', 'jackets' ),
	),
	array(
		'slugs' => array( 'accessories' ),
	),
);

?><!-- wp:group {"metadata":{"name":"02 Shop Categories"},"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-standard","bottom":"var:preset|spacing|section-standard","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"},"blockGap":"var:preset|spacing|fluid-12"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-standard);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-standard);padding-left:var(--wp--preset--spacing--gutter)"><!-- wp:group {"align":"wide","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group alignwide"><!-- wp:group {"layout":{"type":"constrained","contentSize":"640px","justifyContent":"left"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow"><?php echo esc_html__( 'Shop by category', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"is-style-headline"} -->
<h2 class="wp-block-heading is-style-headline"><?php echo esc_html__( 'Find your uniform.', 'aggressive-apparel' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-text"} -->
<div class="wp-block-button is-style-text"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( Editorial_Catalog::shop_url() ); ?>"><?php echo esc_html__( 'All products', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"aa-editorial-tiles","layout":{"type":"grid","columnCount":4,"minimumColumnWidth":"9rem"}} -->
<div class="wp-block-group alignwide aa-editorial-tiles">
<?php
foreach ( $aa_tiles as $aa_tile ) {
	$aa_term = Editorial_Catalog::term( array( 'product_cat' ), $aa_tile['slugs'] );
	if ( null === $aa_term ) {
		continue;
	}
	$aa_attributes = array(
		'contentAlign'    => 'left',
		'dimRatio'        => 100,
		'overlayGradient' => 'dark-overlay-strong',
		'categoryId'      => $aa_term->term_id,
		'backgroundColor' => 'black',
		'style'           => array( 'dimensions' => array( 'aspectRatio' => '3/4' ) ),
	);
	?>
<!-- wp:woocommerce/featured-category <?php echo serialize_block_attributes( $aa_attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block attributes JSON-encoded by core's serializer. ?> -->
<!-- wp:heading {"level":3,"textColor":"white"} -->
<h3 class="wp-block-heading has-white-color has-text-color"><a href="<?php echo esc_url( Editorial_Catalog::url( $aa_term ) ); ?>"><?php echo esc_html( $aa_term->name ); ?></a></h3>
<!-- /wp:heading -->
<!-- /wp:woocommerce/featured-category -->
	<?php
}
?>
</div>
<!-- /wp:group --></div>
<!-- /wp:group -->
