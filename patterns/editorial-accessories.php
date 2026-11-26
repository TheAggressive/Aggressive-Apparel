<?php
/**
 * Title: Editorial — Accessories
 * Description: The accessories actually in stock. With an on-figure accessory photo: the photo beside two products and a tile counting the rest, linked to the category. Without one: a row of up to four, or a rail of up to eight.
 * Slug: aggressive-apparel/editorial-accessories
 * Categories: aggressive-editorial, aggressive-homepage, aggressive-products
 * Keywords: accessories, hats, bags, products, homepage
 * Viewport Width: 1440
 *
 * @package Aggressive_Apparel
 */

use Aggressive_Apparel\WooCommerce\Editorial_Catalog;
use Aggressive_Apparel\WooCommerce\Editorial_Imagery;
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
// An accessory worn: the photo leads, two products beside it, and the rest
// counted on one tile rather than another row of cards.
$aa_photo = $aa_count >= 2 ? ( Editorial_Imagery::leads( 1, $aa_term )[0] ?? null ) : null;
$aa_more  = $aa_count - 2;

?><!-- wp:group {"metadata":{"name":"10 Accessories"},"align":"full","className":"aa-shop-section","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-compact","bottom":"var:preset|spacing|section-compact","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"},"blockGap":"var:preset|spacing|fluid-12"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull aa-shop-section" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-compact);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-compact);padding-left:var(--wp--preset--spacing--gutter)">
<?php if ( null !== $aa_photo ) : ?>
<!-- wp:columns {"align":"wide","className":"aa-tablet-split","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|8","left":"var:preset|spacing|fluid-12"}}}} -->
<div class="wp-block-columns alignwide aa-tablet-split"><!-- wp:column {"width":"40%"} -->
<div class="wp-block-column" style="flex-basis:40%"><?php echo Editorial_Imagery::image_markup( $aa_photo, '1' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; URLs and alt escaped in image_markup(). ?></div>
<!-- /wp:column -->

<!-- wp:column {"width":"60%","style":{"spacing":{"blockGap":"var:preset|spacing|fluid-12"}}} -->
<div class="wp-block-column" style="flex-basis:60%">
<?php endif; ?>
<!-- wp:group {"align":"wide","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group alignwide"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|3"}},"layout":{"type":"constrained","contentSize":"640px","justifyContent":"left"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow"><?php echo esc_html__( 'The small stuff', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"aa-chapter-title","textColor":"foreground"} -->
<h2 class="wp-block-heading aa-chapter-title has-foreground-color has-text-color"><?php echo esc_html__( 'Accessories', 'aggressive-apparel' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-text"} -->
<div class="wp-block-button is-style-text"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( Editorial_Catalog::url( $aa_term ) ); ?>"><?php echo esc_html__( 'Shop accessories', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
<?php if ( null !== $aa_photo ) : ?>

<!-- wp:group {"className":"aa-acc-grid","style":{"spacing":{"blockGap":"var:preset|spacing|8"}},"layout":{"type":"grid","columnCount":3}} -->
<div class="wp-block-group aa-acc-grid">
	<?php
	// Two cards and the count tile on three equal tracks (the collection
	// spans two, editorial-chapters.css), so the tile is exactly one card
	// wide and every gap is the grid's.
	$aa_grid = Editorial_Product_Grid::markup(
		array(
			'query_id' => 310,
			'per_page' => 2,
			'columns'  => 2,
			'term'     => $aa_term,
			'align'    => '',
			'label'    => __( 'Accessories row', 'aggressive-apparel' ),
		)
	);
	echo $aa_grid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; attributes encoded by core's serializer, classes escaped.
	?>
	<?php if ( $aa_more > 0 ) : ?>

<!-- wp:group {"className":"aa-more-tile","style":{"spacing":{"padding":{"top":"var:preset|spacing|5","bottom":"var:preset|spacing|5","left":"var:preset|spacing|5","right":"var:preset|spacing|5"}}},"backgroundColor":"foreground","textColor":"surface","layout":{"type":"flex","orientation":"vertical","justifyContent":"left","verticalAlignment":"space-between"}} -->
<div class="wp-block-group aa-more-tile has-surface-color has-foreground-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--5);padding-right:var(--wp--preset--spacing--5);padding-bottom:var(--wp--preset--spacing--5);padding-left:var(--wp--preset--spacing--5)"><!-- wp:paragraph {"className":"aa-more-tile__count"} -->
<p class="aa-more-tile__count"><?php echo esc_html( '+' . number_format_i18n( $aa_more ) ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"is-style-meta"} -->
<p class="is-style-meta"><a href="<?php echo esc_url( Editorial_Catalog::url( $aa_term ) ); ?>"><?php echo esc_html__( 'Shop accessories', 'aggressive-apparel' ); ?></a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
	<?php endif; ?>
</div>
<!-- /wp:group -->
</div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<?php else : ?>

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
<?php endif; ?>
</div>
<!-- /wp:group -->
