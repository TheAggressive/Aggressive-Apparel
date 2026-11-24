<?php
/**
 * Title: Editorial — Shop by Line
 * Description: A typographic index of the store's lines: the categories marked "Show in Shop by line", in WooCommerce's category order (drag to reorder in Products → Categories), each with its name at display scale, a live count of in-stock pieces and three of its newest products. Until any are marked, the theme's standard lines in a fixed order. The whole row links to the category.
 * Slug: aggressive-apparel/editorial-category-showcase
 * Categories: aggressive-editorial, aggressive-homepage
 * Keywords: categories, shop, index, lines, editorial, homepage
 * Viewport Width: 1440
 *
 * @package Aggressive_Apparel
 */

use Aggressive_Apparel\WooCommerce\Editorial_Catalog;
use Aggressive_Apparel\WooCommerce\Editorial_Controls;
use Aggressive_Apparel\WooCommerce\Editorial_Imagery;

// The merchant's lines: categories marked "Show in Shop by line", in
// WooCommerce's own category order. A stable order, never re-sorted by stock.
$aa_terms = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
		'orderby'    => 'menu_order',
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- A handful of categories, once per page.
		'meta_query' => array(
			array(
				'key'   => Editorial_Controls::LINE_META,
				'value' => 'yes',
			),
		),
	)
);
$aa_terms = is_array( $aa_terms ) ? array_values( array_filter( $aa_terms, static fn( $term ): bool => $term instanceof \WP_Term ) ) : array();

// None marked yet: the theme's standard lines, the first stocked slug of
// each, in this fixed order.
if ( array() === $aa_terms ) {
	foreach ( array(
		array( 't-shirts', 'tshirts', 'tees', 'shirts' ),
		array( 'hoodies', 'hoodie', 'sweatshirts' ),
		array( 'outerwear', 'jackets' ),
		array( 'swimwear', 'swim', 'swimsuits' ),
		array( 'leggings' ),
		array( 'accessories' ),
	) as $aa_slugs ) {
		$aa_term = Editorial_Catalog::stocked_term( array( 'product_cat' ), $aa_slugs );
		if ( null !== $aa_term ) {
			$aa_terms[] = $aa_term;
		}
	}
}

// A line with nothing in stock is left out, so every row links to a real,
// non-empty archive; the count stays live.
$aa_found = array();
foreach ( $aa_terms as $aa_term ) {
	$aa_count = Editorial_Catalog::visible_count( $aa_term );
	if ( $aa_count > 0 ) {
		$aa_found[] = array(
			'term'  => $aa_term,
			'count' => $aa_count,
		);
	}
}
// No stocked line, no section: a heading over an empty index reads as broken.
if ( array() === $aa_found ) {
	return;
}

?><!-- wp:group {"metadata":{"name":"03 Shop by Line"},"align":"full","className":"aa-shop-section aa-line-index","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-compact","bottom":"var:preset|spacing|section-compact","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"},"blockGap":"var:preset|spacing|8"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull aa-shop-section aa-line-index" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-compact);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-compact);padding-left:var(--wp--preset--spacing--gutter)"><!-- wp:group {"align":"wide","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<div class="wp-block-group alignwide"><!-- wp:paragraph {"className":"is-style-eyebrow aa-chapter-index"} -->
<p class="is-style-eyebrow aa-chapter-index"><?php echo esc_html__( 'Shop by line', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-text"} -->
<div class="wp-block-button is-style-text"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( Editorial_Catalog::shop_url() ); ?>"><?php echo esc_html__( 'All products', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"aa-line-index__rows","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide aa-line-index__rows">
<?php
/*
 * Only the name rises in (the house reveal, staggered down the index); the
 * row's rule draws in with it. The thumbnails sit outside the reveal, so
 * they are visible at rest.
 */
foreach ( array_values( $aa_found ) as $aa_index => $aa_line ) {
	$aa_term = $aa_line['term'];
	?>
<!-- wp:group {"className":"aa-line-index__row","layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<div class="wp-block-group aa-line-index__row"><!-- wp:aggressive-blocks/animate-on-scroll {"animation":"slide","direction":"up","slideDistance":24,"duration":0.7,"initialDelay":<?php echo esc_html( sprintf( '%.2F', 0.08 * $aa_index ) ); ?>,"threshold":"0.1","detectionBoundary":{"top":"100%","right":"0%","bottom":"0%","left":"0%"},"className":"aa-line-index__reveal"} -->
<!-- wp:heading {"level":3,"className":"aa-line-index__name","textColor":"foreground"} -->
<h3 class="wp-block-heading aa-line-index__name has-foreground-color has-text-color"><a href="<?php echo esc_url( Editorial_Catalog::url( $aa_term ) ); ?>"><?php echo esc_html( $aa_term->name ); ?></a></h3>
<!-- /wp:heading -->
<!-- /wp:aggressive-blocks/animate-on-scroll -->

<!-- wp:paragraph {"className":"is-style-meta aa-line-index__count"} -->
<p class="is-style-meta aa-line-index__count">
	<?php
	/* translators: %d: number of in-stock products in a category. */
	echo esc_html( sprintf( _n( '%d piece', '%d pieces', $aa_line['count'], 'aggressive-apparel' ), $aa_line['count'] ) );
	?>
</p>
<!-- /wp:paragraph -->

<!-- wp:group {"className":"aa-line-index__thumbs","layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group aa-line-index__thumbs">
	<?php
	foreach ( Editorial_Imagery::term_images( $aa_term, 3 ) as $aa_image ) {
		$aa_src = wp_get_attachment_image_url( $aa_image, 'thumbnail' );
		if ( false === $aa_src ) {
			continue;
		}
		?>
<!-- wp:image {"id":<?php echo (int) $aa_image; ?>,"sizeSlug":"thumbnail","linkDestination":"none","className":"aa-line-index__thumb"} -->
<figure class="wp-block-image size-thumbnail aa-line-index__thumb"><img src="<?php echo esc_url( $aa_src ); ?>" alt="" class="wp-image-<?php echo (int) $aa_image; ?>"/></figure>
<!-- /wp:image -->
		<?php
	}
	?>
</div>
<!-- /wp:group --></div>
<!-- /wp:group -->
	<?php
}
?>
</div>
<!-- /wp:group --></div>
<!-- /wp:group -->
