<?php
/**
 * Title: Editorial — Original Work
 * Description: Dark split: a large portrait photo beside a statement about original design and creative independence. Shows the newest product photo until campaign photography replaces it; without a portrait photo the statement stands alone.
 * Slug: aggressive-apparel/editorial-artwork-spotlight
 * Categories: aggressive-editorial, aggressive-homepage
 * Keywords: artwork, design, original, detail, editorial, homepage
 * Viewport Width: 1440
 *
 * @package Aggressive_Apparel
 */

use Aggressive_Apparel\WooCommerce\Editorial_Catalog;
use Aggressive_Apparel\WooCommerce\Editorial_Imagery;

// Store photography holds the slot until campaign photography exists; an
// empty frame on a black band read as a missing image.
$aa_lead = Editorial_Imagery::leads( 1 )[0] ?? null;

?><!-- wp:group {"metadata":{"name":"04 Original Work"},"anchor":"the-label","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-editorial","bottom":"var:preset|spacing|section-editorial","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"}},"elements":{"link":{"color":{"text":"var:preset|color|white"}}}},"backgroundColor":"black","textColor":"white","layout":{"type":"constrained"}} -->
<div id="the-label" class="wp-block-group alignfull has-white-color has-black-background-color has-text-color has-background has-link-color" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-editorial);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-editorial);padding-left:var(--wp--preset--spacing--gutter)"><?php if ( null !== $aa_lead ) : ?>
<!-- wp:columns {"verticalAlignment":"center","align":"wide","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|fluid-12","left":"var:preset|spacing|fluid-24"}}}} -->
<div class="wp-block-columns alignwide are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center","width":"50%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:50%">
	<?php echo Editorial_Imagery::image_markup( $aa_lead, '4/5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; URLs and alt escaped in image_markup(). ?>
</div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"50%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:50%"><!-- wp:aggressive-blocks/animate-on-scroll {"animation":"slide","direction":"up","slideDistance":24,"duration":0.7,"staggerChildren":true,"staggerDelay":0.08} -->
<!-- wp:paragraph {"className":"is-style-meta","textColor":"white"} -->
<p class="is-style-meta has-white-color has-text-color"><?php echo esc_html__( 'Original work', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"is-style-headline","textColor":"white"} -->
<h2 class="wp-block-heading is-style-headline has-white-color has-text-color"><?php echo esc_html__( 'Nothing here is borrowed.', 'aggressive-apparel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"is-style-lead","textColor":"white"} -->
<p class="is-style-lead has-white-color has-text-color"><?php echo esc_html__( 'Original design, our own creative direction, no trend report to answer to. If it carries our name, we made the call.', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|8"}}}} -->
<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--8)"><!-- wp:button {"className":"is-style-outline-on-dark"} -->
<div class="wp-block-button is-style-outline-on-dark"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( Editorial_Catalog::shop_url() ); ?>"><?php echo esc_html__( 'Shop the collection', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
<!-- /wp:aggressive-blocks/animate-on-scroll --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<?php else : ?>
<!-- wp:group {"align":"wide","layout":{"type":"constrained","contentSize":"760px","justifyContent":"left"}} -->
<div class="wp-block-group alignwide"><!-- wp:aggressive-blocks/animate-on-scroll {"animation":"slide","direction":"up","slideDistance":24,"duration":0.7,"staggerChildren":true,"staggerDelay":0.08} -->
<!-- wp:paragraph {"className":"is-style-meta","textColor":"white"} -->
<p class="is-style-meta has-white-color has-text-color"><?php echo esc_html__( 'Original work', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"is-style-headline","textColor":"white"} -->
<h2 class="wp-block-heading is-style-headline has-white-color has-text-color"><?php echo esc_html__( 'Nothing here is borrowed.', 'aggressive-apparel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"is-style-lead","textColor":"white"} -->
<p class="is-style-lead has-white-color has-text-color"><?php echo esc_html__( 'Original design, our own creative direction, no trend report to answer to. If it carries our name, we made the call.', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|8"}}}} -->
<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--8)"><!-- wp:button {"className":"is-style-outline-on-dark"} -->
<div class="wp-block-button is-style-outline-on-dark"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( Editorial_Catalog::shop_url() ); ?>"><?php echo esc_html__( 'Shop the collection', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
<!-- /wp:aggressive-blocks/animate-on-scroll --></div>
<!-- /wp:group -->
<?php endif; ?>
</div>
<!-- /wp:group -->
