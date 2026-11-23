<?php
/**
 * Title: Editorial — Featured Looks
 * Description: The lookbook on a light studio band: three to five approved photos in the Aggressive Blocks horizontal scroll (pinned and scrubbed by scrolling on desktop, a swipe carousel with controls on touch), each linked to and named for its product. Needs three photos, else it is omitted.
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
$aa_leads = Editorial_Imagery::leads( 5, null, true );
if ( count( $aa_leads ) < 3 ) {
	return;
}

?><!-- wp:group {"metadata":{"name":"11 Lookbook"},"align":"full","className":"aa-shop-section aa-lookbook aa-scheme-light","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-compact","bottom":"var:preset|spacing|section-compact","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"},"blockGap":"var:preset|spacing|fluid-12"}},"backgroundColor":"product-media","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull aa-shop-section aa-lookbook aa-scheme-light has-product-media-background-color has-background" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-compact);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-compact);padding-left:var(--wp--preset--spacing--gutter)"><!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|3"}},"layout":{"type":"constrained","contentSize":"640px","justifyContent":"left"}} -->
<div class="wp-block-group alignwide"><!-- wp:paragraph {"className":"is-style-eyebrow aa-chapter-index"} -->
<p class="is-style-eyebrow aa-chapter-index"><?php echo esc_html__( 'Lookbook', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"aa-chapter-title","textColor":"foreground"} -->
<h2 class="wp-block-heading aa-chapter-title has-foreground-color has-text-color"><?php echo esc_html__( 'How it’s worn', 'aggressive-apparel' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:aggressive-blocks/horizontal-scroll {"align":"wide","ariaLabel":"<?php echo esc_attr__( 'How it’s worn', 'aggressive-apparel' ); ?>","itemWidth":"34vw","speed":0.6,"showProgress":true,"showControls":true,"activation":"center","desktopBehavior":"pinned","snapBehavior":"off","swipeHintStyle":"cue","className":"aa-lookbook-scroll"} -->
<?php foreach ( $aa_leads as $aa_look ) : ?>
<!-- wp:group {"className":"aa-photo-rail__look","style":{"spacing":{"blockGap":"var:preset|spacing|3"}},"layout":{"type":"default"}} -->
<div class="wp-block-group aa-photo-rail__look"><?php echo Editorial_Imagery::image_markup( $aa_look, '1' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; URLs and alt escaped in image_markup(). ?>

<!-- wp:paragraph {"className":"aa-photo-rail__caption"} -->
<p class="aa-photo-rail__caption"><a href="<?php echo esc_url( (string) get_permalink( $aa_look['product'] ) ); ?>"><?php echo esc_html( get_the_title( $aa_look['product'] ) ); ?></a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<?php endforeach; ?>
<!-- /wp:aggressive-blocks/horizontal-scroll --></div>
<!-- /wp:group -->
