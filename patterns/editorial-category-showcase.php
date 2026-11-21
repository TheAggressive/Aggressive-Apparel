<?php
/**
 * Title: Editorial — Category Showcase
 * Description: Portrait category tiles, one per stocked category, showing the category's own image (or its newest product's); the whole tile links to the category.
 * Slug: aggressive-apparel/editorial-category-showcase
 * Categories: aggressive-editorial, aggressive-homepage
 * Keywords: categories, shop, tiles, editorial, homepage
 * Viewport Width: 1440
 *
 * @package Aggressive_Apparel
 */

use Aggressive_Apparel\WooCommerce\Editorial_Catalog;
use Aggressive_Apparel\WooCommerce\Editorial_Imagery;

// Candidate slugs per tile, first match wins; a tile is left out when the
// store has no such category or it has no products, so every tile links to
// a real, non-empty archive.
$aa_tiles = array(
	array(
		'slugs' => array( 't-shirts', 'tshirts', 'tees', 'shirts' ),
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

?><!-- wp:group {"metadata":{"name":"02 Shop Categories"},"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-standard","bottom":"var:preset|spacing|section-compact","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"},"blockGap":"var:preset|spacing|fluid-12"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-standard);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-compact);padding-left:var(--wp--preset--spacing--gutter)"><!-- wp:group {"align":"wide","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group alignwide"><!-- wp:group {"layout":{"type":"constrained","contentSize":"640px","justifyContent":"left"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow"><?php echo esc_html__( 'Shop by category', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"is-style-headline"} -->
<h2 class="wp-block-heading is-style-headline"><?php echo esc_html__( 'Pick your issue.', 'aggressive-apparel' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-text"} -->
<div class="wp-block-button is-style-text"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( Editorial_Catalog::shop_url() ); ?>"><?php echo esc_html__( 'All products', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<?php
$aa_found = array();
foreach ( $aa_tiles as $aa_tile ) {
	$aa_term = Editorial_Catalog::stocked_term( array( 'product_cat' ), $aa_tile['slugs'] );
	if ( null !== $aa_term ) {
		$aa_found[] = $aa_term;
	}
}
// One row of however many categories exist: four tiles never wrap 3 + 1.
// The gap is explicit because core sizes responsive grid tracks from the
// block's own gap and assumes 0.5em when none is set.
$aa_grid_attributes = array(
	'align'     => 'wide',
	'className' => 'aa-editorial-tiles',
	'style'     => array( 'spacing' => array( 'blockGap' => 'var:preset|spacing|2' ) ),
	'layout'    => array(
		'type'               => 'grid',
		'columnCount'        => max( 1, count( $aa_found ) ),
		'minimumColumnWidth' => '9rem',
	),
);
?>
<!-- wp:group <?php echo serialize_block_attributes( $aa_grid_attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block attributes JSON-encoded by core's serializer. ?> -->
<div class="wp-block-group alignwide aa-editorial-tiles">
<?php
foreach ( $aa_found as $aa_term ) {
	$aa_attributes = array(
		'contentAlign'    => 'left',
		'dimRatio'        => 100,
		// A CSS value, not a slug: WooCommerce prints it straight into
		// `background-image`. Darkens only the lower half, where the name sits.
		'overlayGradient' => 'var(--wp--preset--gradient--dark-overlay)',
		'categoryId'      => $aa_term->term_id,
		'backgroundColor' => 'black',
		// Square, the shape the product images are designed in, so the
		// tile's cover fit never crops the product it shows.
		'style'           => array( 'dimensions' => array( 'aspectRatio' => '1/1' ) ),
	);
	// The category's own image, else its newest product photo not already
	// shown higher on the page (the hero's); claimed so later slots skip it.
	$aa_image = Editorial_Imagery::term_image( $aa_term );
	if ( $aa_image > 0 ) {
		$aa_attributes['mediaId']  = $aa_image;
		$aa_attributes['imageFit'] = 'cover';
	}
	?>
<!-- wp:woocommerce/featured-category <?php echo serialize_block_attributes( $aa_attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block attributes JSON-encoded by core's serializer. ?> -->
<!-- wp:heading {"level":3,"textColor":"white","fontSize":"fluid-xxxxx-large"} -->
<h3 class="wp-block-heading has-white-color has-text-color has-fluid-xxxxx-large-font-size"><a href="<?php echo esc_url( Editorial_Catalog::url( $aa_term ) ); ?>"><?php echo esc_html( $aa_term->name ); ?></a></h3>
<!-- /wp:heading -->
	<?php
	$aa_count = Editorial_Catalog::visible_count( $aa_term );
	if ( $aa_count > 0 ) :
		?>

<!-- wp:paragraph {"className":"is-style-meta","textColor":"white"} -->
<p class="is-style-meta has-white-color has-text-color">
		<?php
		/* translators: %d: number of in-stock products in a category. */
		echo esc_html( sprintf( _n( '%d piece', '%d pieces', $aa_count, 'aggressive-apparel' ), $aa_count ) );
		?>
</p>
<!-- /wp:paragraph -->
	<?php endif; ?>
<!-- /wp:woocommerce/featured-category -->
	<?php
}
?>
</div>
<!-- /wp:group --></div>
<!-- /wp:group -->
