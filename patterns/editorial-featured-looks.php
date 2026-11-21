<?php
/**
 * Title: Editorial — Featured Looks
 * Description: Asymmetric lookbook: one lead 4:5 portrait and two smaller 2:3 looks on its baseline, each captioned and linked to its product. Shows the store's newest portrait product photos not used higher on the page until lookbook photography replaces them, and renders nothing without three.
 * Slug: aggressive-apparel/editorial-featured-looks
 * Categories: aggressive-editorial, aggressive-homepage, aggressive-drops
 * Keywords: lookbook, looks, outfits, editorial, style, homepage
 * Viewport Width: 1440
 *
 * @package Aggressive_Apparel
 */

use Aggressive_Apparel\WooCommerce\Editorial_Imagery;

// Three real photos or no lookbook: an empty frame reads as missing content.
// Fresh photos first; a small catalog may repeat one shown higher up.
$aa_leads = Editorial_Imagery::leads( 3, null, true );
if ( count( $aa_leads ) < 3 ) {
	return;
}
$aa_looks = array(
	array(
		/* translators: Lookbook figure number, as in a catalogue caption. */
		'label' => __( 'Fig. 01', 'aggressive-apparel' ),
		'width' => '50%',
		'ratio' => '4/5',
	),
	array(
		/* translators: Lookbook figure number, as in a catalogue caption. */
		'label' => __( 'Fig. 02', 'aggressive-apparel' ),
		'width' => '25%',
		'ratio' => '2/3',
	),
	array(
		/* translators: Lookbook figure number, as in a catalogue caption. */
		'label' => __( 'Fig. 03', 'aggressive-apparel' ),
		'width' => '25%',
		'ratio' => '2/3',
	),
);

?><!-- wp:group {"metadata":{"name":"11 Featured Looks"},"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-standard","bottom":"var:preset|spacing|section-standard","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"},"blockGap":"var:preset|spacing|fluid-16"}},"backgroundColor":"surface-muted","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-surface-muted-background-color has-background" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-standard);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-standard);padding-left:var(--wp--preset--spacing--gutter)"><!-- wp:group {"layout":{"type":"constrained","contentSize":"720px"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"align":"center","className":"is-style-eyebrow"} -->
<p class="has-text-align-center is-style-eyebrow"><?php echo esc_html__( 'Lookbook', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textAlign":"center","textColor":"foreground"} -->
<h2 class="wp-block-heading has-text-align-center has-foreground-color has-text-color"><?php echo esc_html__( 'How it’s worn', 'aggressive-apparel' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:columns {"verticalAlignment":"bottom","align":"wide","className":"aa-editorial-looks aa-tablet-split","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|fluid-12","left":"var:preset|spacing|fluid-8"}}}} -->
<div class="wp-block-columns alignwide are-vertically-aligned-bottom aa-editorial-looks aa-tablet-split">
<?php foreach ( $aa_looks as $aa_index => $aa_look ) : ?>
<!-- wp:column {"verticalAlignment":"bottom","width":"<?php echo esc_attr( $aa_look['width'] ); ?>","style":{"spacing":{"blockGap":"var:preset|spacing|4"}}} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:<?php echo esc_attr( $aa_look['width'] ); ?>"><?php echo Editorial_Imagery::image_markup( $aa_leads[ $aa_index ], $aa_look['ratio'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; URLs and alt escaped in image_markup(). ?>

<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"is-style-meta"} -->
<p class="is-style-meta"><?php echo esc_html( $aa_look['label'] ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a href="<?php echo esc_url( (string) get_permalink( $aa_leads[ $aa_index ]['product'] ) ); ?>"><?php echo esc_html( get_the_title( $aa_leads[ $aa_index ]['product'] ) ); ?></a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->
<?php endforeach; ?>
</div>
<!-- /wp:columns -->
</div>
<!-- /wp:group -->
