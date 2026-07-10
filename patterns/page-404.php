<?php
/**
 * Title: 404 Page
 * Description: Branded 404 error page with search, popular products link, and back-to-home CTA.
 * Slug: aggressive-apparel/page-404
 * Categories: aggressive, aggressive-apparel
 * Keywords: 404, error, not found, missing, search
 * Viewport Width: 1200
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|24","bottom":"var:preset|spacing|24"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained","contentSize":"540px"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--24);padding-bottom:var(--wp--preset--spacing--24)">
	<!-- wp:heading {"textAlign":"center","level":1,"style":{"typography":{"fontWeight":"800","lineHeight":"1","letterSpacing":"-0.04em"}},"fontSize":"fluid-xxxxxxx-large"} -->
	<h1 class="wp-block-heading has-text-align-center has-fluid-xxxxxxx-large-font-size" style="font-weight:800;line-height:1;letter-spacing:-0.04em">404</h1>
	<!-- /wp:heading -->

	<!-- wp:heading {"textAlign":"center","level":2,"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}},"typography":{"textTransform":"uppercase","letterSpacing":"0.05em"}},"fontSize":"large"} -->
	<h2 class="wp-block-heading has-text-align-center has-large-font-size" style="margin-top:var(--wp--preset--spacing--4);letter-spacing:0.05em;text-transform:uppercase">Page Not Found</h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}}},"fontSize":"medium"} -->
	<p class="has-text-align-center has-medium-font-size" style="margin-top:var(--wp--preset--spacing--6)">The page you're looking for doesn't exist or has been moved. Try searching or head back to the shop.</p>
	<!-- /wp:paragraph -->

	<!-- wp:search {"label":"Search","showLabel":false,"placeholder":"Search products, collections...","buttonText":"Search","buttonUseIcon":true,"style":{"spacing":{"margin":{"top":"var:preset|spacing|10"}}}} /-->

	<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|10"}}}} -->
	<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--10)">
		<!-- wp:button {"backgroundColor":"black","textColor":"white","style":{"typography":{"fontStyle":"normal","fontWeight":"600","textTransform":"uppercase","letterSpacing":"0.05em"},"spacing":{"padding":{"top":"var:preset|spacing|4","bottom":"var:preset|spacing|4","left":"var:preset|spacing|10","right":"var:preset|spacing|10"}}}} -->
		<div class="wp-block-button"><a class="wp-block-button__link has-white-color has-black-background-color has-text-color has-background wp-element-button" style="padding-top:var(--wp--preset--spacing--4);padding-right:var(--wp--preset--spacing--10);padding-bottom:var(--wp--preset--spacing--4);padding-left:var(--wp--preset--spacing--10);font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase" href="/shop">Browse Shop</a></div>
		<!-- /wp:button -->

		<!-- wp:button {"className":"is-style-outline","style":{"typography":{"fontStyle":"normal","fontWeight":"600","textTransform":"uppercase","letterSpacing":"0.05em"},"spacing":{"padding":{"top":"var:preset|spacing|4","bottom":"var:preset|spacing|4","left":"var:preset|spacing|10","right":"var:preset|spacing|10"}}}} -->
		<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" style="padding-top:var(--wp--preset--spacing--4);padding-right:var(--wp--preset--spacing--10);padding-bottom:var(--wp--preset--spacing--4);padding-left:var(--wp--preset--spacing--10);font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase" href="/">Go Home</a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
