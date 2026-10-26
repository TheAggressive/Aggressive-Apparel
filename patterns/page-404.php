<?php
/**
 * Title: 404 Page
 * Description: Branded 404 error page with search, popular products link, and back-to-home CTA.
 * Slug: aggressive-apparel/page-404
 * Categories: aggressive
 * Keywords: 404, error, not found, missing, search
 * Viewport Width: 1200
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|fluid-24","bottom":"var:preset|spacing|fluid-24"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained","contentSize":"540px"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--fluid-24);padding-bottom:var(--wp--preset--spacing--fluid-24)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|0"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"center"}} -->
<div class="wp-block-group"><!-- wp:aggressive-apparel/icon {"icon":"broken-chain-link","iconSize":64,"textAlign":"center","style":{"elements":{"link":{"color":{"text":"var:preset|color|accent"}}},"spacing":{"margin":{"top":"var:preset|spacing|0","bottom":"var:preset|spacing|0"}}},"textColor":"accent"} /-->

<!-- wp:heading {"level":1,"style":{"typography":{"fontWeight":"800","lineHeight":"1","letterSpacing":"-0.04em","textAlign":"center"}},"fontSize":"fluid-xxxxxxx-large"} -->
<h1 class="wp-block-heading has-text-align-center has-fluid-xxxxxxx-large-font-size" style="font-weight:800;letter-spacing:-0.04em;line-height:1">404</h1>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:heading {"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}},"typography":{"textTransform":"uppercase","letterSpacing":"0.05em","textAlign":"center"}},"fontSize":"large"} -->
<h2 class="wp-block-heading has-text-align-center has-large-font-size" style="margin-top:var(--wp--preset--spacing--4);letter-spacing:0.05em;text-transform:uppercase"><?php echo esc_html__( 'Page Not Found', 'aggressive-apparel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}},"typography":{"textAlign":"center"}},"fontSize":"medium"} -->
<p class="has-text-align-center has-medium-font-size" style="margin-top:var(--wp--preset--spacing--6)"><?php echo esc_html__( 'Looks like this page has disappeared...', 'aggressive-apparel' ); ?><br><?php echo esc_html__( 'Lets get you back to the good stuff.', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:search {"label":"Search","showLabel":false,"placeholder":"Search products, collections...","width":100,"widthUnit":"%","buttonText":"Search","buttonUseIcon":true,"style":{"spacing":{"margin":{"top":"var:preset|spacing|fluid-16"}}}} /-->

<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|fluid-12"}}},"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--fluid-12)"><!-- wp:button {"backgroundColor":"black","textColor":"white","className":"is-style-cta"} -->
<div class="wp-block-button is-style-cta"><a class="wp-block-button__link has-white-color has-black-background-color has-text-color has-background wp-element-button" href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><?php echo esc_html__( 'Browse Shop', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-cta-ghost"} -->
<div class="wp-block-button is-style-cta-ghost"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html__( 'Go Home', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
