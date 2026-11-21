<?php
/**
 * Title: Editorial — Original Work
 * Description: Dark, immersive split: a large portrait image (replace the image) beside a statement about original design and creative independence.
 * Slug: aggressive-apparel/editorial-artwork-spotlight
 * Categories: aggressive-editorial, aggressive-homepage
 * Keywords: artwork, design, original, detail, editorial, homepage
 * Viewport Width: 1440
 *
 * @package Aggressive_Apparel
 */

use Aggressive_Apparel\WooCommerce\Editorial_Catalog;

?><!-- wp:group {"metadata":{"name":"04 Original Work"},"anchor":"the-label","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-editorial","bottom":"var:preset|spacing|section-editorial","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"}},"elements":{"link":{"color":{"text":"var:preset|color|white"}}}},"backgroundColor":"black","textColor":"white","layout":{"type":"constrained"}} -->
<div id="the-label" class="wp-block-group alignfull has-white-color has-black-background-color has-text-color has-background has-link-color" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-editorial);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-editorial);padding-left:var(--wp--preset--spacing--gutter)"><!-- wp:columns {"verticalAlignment":"center","align":"wide","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|fluid-12","left":"var:preset|spacing|fluid-24"}}}} -->
<div class="wp-block-columns alignwide are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center","width":"58%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:58%"><!-- wp:cover {"dimRatio":100,"overlayColor":"surface-muted","isUserOverlayColor":true,"isDark":false,"metadata":{"name":"Label portrait (replace image)"},"style":{"dimensions":{"aspectRatio":"4/5"}}} -->
<div class="wp-block-cover is-light"><span aria-hidden="true" class="wp-block-cover__background has-surface-muted-background-color has-background-dim-100 has-background-dim"></span><div class="wp-block-cover__inner-container"></div></div>
<!-- /wp:cover --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"42%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:42%"><!-- wp:aggressive-blocks/animate-on-scroll {"animation":"slide","direction":"up","slideDistance":24,"duration":0.7,"staggerChildren":true,"staggerDelay":0.08} -->
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
<!-- /wp:columns --></div>
<!-- /wp:group -->
