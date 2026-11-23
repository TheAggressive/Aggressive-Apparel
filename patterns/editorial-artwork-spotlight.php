<?php
/**
 * Title: Editorial — The Name
 * Description: The one place the brand explains itself: the statement about the name and a short paragraph beside up to three of the store's own on-figure photos, each product named in a Shown line. With no photos it stays typographic. Anchor #the-label, the cover's second link.
 * Slug: aggressive-apparel/editorial-artwork-spotlight
 * Categories: aggressive-editorial, aggressive-homepage
 * Keywords: brand, name, statement, editorial, homepage
 * Viewport Width: 1440
 *
 * @package Aggressive_Apparel
 */

use Aggressive_Apparel\WooCommerce\Editorial_Imagery;

$aa_photos = Editorial_Imagery::leads( 3 );
$aa_shown  = array();
foreach ( $aa_photos as $aa_photo ) {
	$aa_shown[] = '<a href="' . esc_url( (string) get_permalink( $aa_photo['product'] ) ) . '">' . esc_html( get_the_title( $aa_photo['product'] ) ) . '</a>';
}
$aa_image = static function ( int $index, string $ratio ) use ( $aa_photos ): string {
	return isset( $aa_photos[ $index ] ) ? Editorial_Imagery::image_markup( $aa_photos[ $index ], $ratio ) : '';
};

?><!-- wp:group {"metadata":{"name":"04 The Name"},"anchor":"the-label","align":"full","className":"aa-statement aa-name","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-standard","bottom":"var:preset|spacing|section-standard","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"}}},"backgroundColor":"white","layout":{"type":"constrained"}} -->
<div id="the-label" class="wp-block-group alignfull aa-statement aa-name has-white-background-color has-background" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-standard);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-standard);padding-left:var(--wp--preset--spacing--gutter)"><!-- wp:columns {"align":"wide","className":"aa-tablet-split","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|fluid-12","left":"var:preset|spacing|fluid-16"}}}} -->
<div class="wp-block-columns alignwide aa-tablet-split"><!-- wp:column {"width":"<?php echo array() === $aa_photos ? '62%' : '44%'; ?>"} -->
<div class="wp-block-column" style="flex-basis:<?php echo array() === $aa_photos ? '62%' : '44%'; ?>"><!-- wp:paragraph {"className":"is-style-eyebrow aa-chapter-index"} -->
<p class="is-style-eyebrow aa-chapter-index"><?php echo esc_html__( 'The name', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"is-style-headline"} -->
<h2 class="wp-block-heading is-style-headline"><?php echo esc_html__( 'Aggressive is a compliment.', 'aggressive-apparel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"is-style-lead"} -->
<p class="is-style-lead"><?php echo esc_html__( 'It means nobody softened it. We named the label after it in 2010. Some of what we make is loud. Some of it is a black hoodie. Neither is toned down to suit everyone.', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->
<?php if ( array() !== $aa_photos ) : ?>

<!-- wp:column {"width":"56%","className":"aa-name__photos"} -->
<div class="wp-block-column aa-name__photos" style="flex-basis:56%"><!-- wp:columns {"isStackedOnMobile":false,"style":{"spacing":{"blockGap":{"top":"var:preset|spacing|4","left":"var:preset|spacing|4"}}}} -->
<div class="wp-block-columns is-not-stacked-on-mobile"><!-- wp:column {"width":"62%"} -->
<div class="wp-block-column" style="flex-basis:62%"><?php echo $aa_image( 0, '4/5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; URLs and alt escaped in image_markup(). ?></div>
<!-- /wp:column -->
	<?php if ( count( $aa_photos ) > 1 ) : ?>

<!-- wp:column {"verticalAlignment":"bottom","width":"38%","style":{"spacing":{"blockGap":"var:preset|spacing|4"}}} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:38%"><?php echo $aa_image( 1, '4/5' ) . $aa_image( 2, '1' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; URLs and alt escaped in image_markup(). ?></div>
<!-- /wp:column -->
	<?php endif; ?>
</div>
<!-- /wp:columns -->

<!-- wp:paragraph {"className":"is-style-meta"} -->
<p class="is-style-meta">
	<?php
	/* translators: %s: linked names of the products in the photos, separated by a middle dot. */
	printf( esc_html__( 'Shown: %s', 'aggressive-apparel' ), implode( ' · ', $aa_shown ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Links built above with esc_url()/esc_html().
	?>
</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->
<?php endif; ?>
</div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
