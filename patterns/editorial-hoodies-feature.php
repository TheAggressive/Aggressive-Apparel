<?php
/**
 * Title: Editorial — Hoodies Feature
 * Description: Oversized category headline with real hoodies and a single shopping CTA. Three or more hoodies sit in a centred row under the headline; one or two become large cards beside it.
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
$aa_count = Editorial_Catalog::visible_count( $aa_term );
if ( 0 === $aa_count ) {
	return;
}
// A row needs three; fewer read as a gap under a centred headline, so one
// or two hoodies become large cards beside it instead.
$aa_row     = $aa_count >= 3;
$aa_columns = $aa_row ? min( 4, $aa_count ) : $aa_count;
$aa_grid    = Editorial_Product_Grid::markup(
	array(
		'query_id' => 308,
		'per_page' => $aa_columns,
		'columns'  => $aa_columns,
		'term'     => $aa_term,
		'align'    => $aa_row ? 'wide' : '',
		'label'    => __( 'Hoodies grid', 'aggressive-apparel' ),
		'centered' => $aa_row,
	)
);
$aa_align   = $aa_row ? 'center' : 'left';
// Beside the headline (which comes first, so phones read it before the cards).
$aa_grid_width = 1 === $aa_count ? '50%' : '60%';
$aa_text_width = 1 === $aa_count ? '50%' : '40%';

?><!-- wp:group {"metadata":{"name":"08 Hoodies"},"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-standard","bottom":"var:preset|spacing|section-standard","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"},"blockGap":"var:preset|spacing|fluid-12"}},"backgroundColor":"surface-muted","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-surface-muted-background-color has-background" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-standard);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-standard);padding-left:var(--wp--preset--spacing--gutter)">
<?php if ( ! $aa_row ) : ?>
<!-- wp:columns {"verticalAlignment":"center","align":"wide","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|fluid-12","left":"var:preset|spacing|fluid-16"}}}} -->
<div class="wp-block-columns alignwide are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center","width":"<?php echo esc_attr( $aa_text_width ); ?>"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:<?php echo esc_attr( $aa_text_width ); ?>">
<?php endif; ?>
<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|4"}},"layout":{"type":"constrained","contentSize":"760px"<?php echo $aa_row ? '' : ',"justifyContent":"left"'; ?>}} -->
<div class="wp-block-group"><!-- wp:paragraph {"align":"<?php echo esc_attr( $aa_align ); ?>","className":"is-style-eyebrow"} -->
<p class="has-text-align-<?php echo esc_attr( $aa_align ); ?> is-style-eyebrow"><?php echo esc_html__( 'Heavy rotation', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textAlign":"<?php echo esc_attr( $aa_align ); ?>","className":"is-style-campaign"} -->
<h2 class="wp-block-heading has-text-align-<?php echo esc_attr( $aa_align ); ?> is-style-campaign"><?php echo esc_html__( 'Hoodies', 'aggressive-apparel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"<?php echo esc_attr( $aa_align ); ?>","className":"is-style-lead","textColor":"foreground-muted"} -->
<p class="has-text-align-<?php echo esc_attr( $aa_align ); ?> is-style-lead has-foreground-muted-color has-text-color"><?php echo esc_html__( 'For the cold walk home.', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<?php
if ( $aa_row ) {
	echo $aa_grid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; attributes encoded by core's serializer, classes escaped.
}
?>

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"<?php echo esc_attr( $aa_align ); ?>"}} -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-cta"} -->
<div class="wp-block-button is-style-cta"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( Editorial_Catalog::url( $aa_term ) ); ?>"><?php echo esc_html__( 'Shop all hoodies', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
<?php if ( ! $aa_row ) : ?>
</div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"<?php echo esc_attr( $aa_grid_width ); ?>"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:<?php echo esc_attr( $aa_grid_width ); ?>">
	<?php echo $aa_grid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; attributes encoded by core's serializer, classes escaped. ?>
</div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<?php endif; ?>
</div>
<!-- /wp:group -->
