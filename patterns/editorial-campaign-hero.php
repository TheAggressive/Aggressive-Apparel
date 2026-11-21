<?php
/**
 * Title: Editorial — Campaign Hero
 * Description: Full-bleed campaign Cover (replace the image) with a display headline, lead copy and two CTAs into the live catalog.
 * Slug: aggressive-apparel/editorial-campaign-hero
 * Categories: aggressive-editorial, aggressive-homepage
 * Keywords: hero, campaign, editorial, cover, homepage
 * Viewport Width: 1440
 *
 * @package Aggressive_Apparel
 */

use Aggressive_Apparel\WooCommerce\Editorial_Catalog;

$aa_new_arrivals = Editorial_Catalog::shop_url( array( 'orderby' => 'date' ) );

?><!-- wp:cover {"dimRatio":40,"overlayColor":"black","isUserOverlayColor":true,"minHeight":88,"minHeightUnit":"vh","contentPosition":"bottom center","isDark":true,"metadata":{"name":"01 Campaign Hero (replace image)"},"align":"full","className":"aa-campaign-hero","style":{"color":{"background":"var:preset|color|black"},"spacing":{"padding":{"top":"var:preset|spacing|section-editorial","bottom":"var:preset|spacing|section-standard","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-cover alignfull is-dark has-custom-content-position is-position-bottom-center has-background aa-campaign-hero" style="background-color:var(--wp--preset--color--black);margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-editorial);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-standard);padding-left:var(--wp--preset--spacing--gutter);min-height:88vh"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-40 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:group {"align":"wide","layout":{"type":"constrained","contentSize":"880px","justifyContent":"left"}} -->
<div class="wp-block-group alignwide"><!-- wp:paragraph {"className":"is-style-meta","textColor":"white"} -->
<p class="is-style-meta has-white-color has-text-color"><?php echo esc_html__( 'Est. 2010', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"is-style-campaign","textColor":"white"} -->
<h1 class="wp-block-heading is-style-campaign has-white-color has-text-color"><?php echo esc_html__( 'Rebellion has a uniform.', 'aggressive-apparel' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"is-style-lead","textColor":"white"} -->
<p class="is-style-lead has-white-color has-text-color"><?php echo esc_html__( 'Uniforms are usually issued. This one you choose.', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|8"}}}} -->
<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--8)"><!-- wp:button {"className":"is-style-cta"} -->
<div class="wp-block-button is-style-cta"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $aa_new_arrivals ); ?>"><?php echo esc_html__( 'Shop new arrivals', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-cta-outline-on-dark"} -->
<div class="wp-block-button is-style-cta-outline-on-dark"><a class="wp-block-button__link wp-element-button" href="#the-label"><?php echo esc_html__( 'Inside the label', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div></div>
<!-- /wp:cover -->
