<?php
/**
 * Title: Footer Centered
 * Description: Centered footer with stacked logo, navigation links, social icons, and payment methods.
 * Slug: aggressive-apparel/footer-centered
 * Categories: aggressive, aggressive-apparel, footer
 * Keywords: footer, centered, navigation, social, payment
 * Block Types: core/template-part/footer
 * Viewport Width: 1400
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|14"},"margin":{"top":"0","bottom":"0"}},"border":{"top":{"color":"var:preset|color|border","width":"1px"}}},"layout":{"type":"constrained","contentSize":"600px"}} -->
<div class="wp-block-group alignfull" style="border-top-color:var(--wp--preset--color--border);border-top-width:1px;margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--14)">
	<!-- wp:group {"layout":{"type":"flex","justifyContent":"center"}} -->
	<div class="wp-block-group">
		<!-- wp:aggressive-apparel/aggressive-apparel-logo {"lightColor":"var:preset|color|foreground","lightHoverColor":"var:preset|color|accent","darkColor":"var:preset|color|foreground","darkHoverColor":"var:preset|color|accent","largeWidth":220,"smallWidth":44} /-->
	</div>
	<!-- /wp:group -->

	<!-- wp:navigation {"overlayMenu":"never","style":{"spacing":{"margin":{"top":"var:preset|spacing|10"},"blockGap":"var:preset|spacing|8"},"typography":{"textTransform":"uppercase","letterSpacing":"0.05em","fontStyle":"normal","fontWeight":"600"}},"fontSize":"x-small","layout":{"type":"flex","justifyContent":"center","flexWrap":"wrap"}} -->
		<!-- wp:navigation-link {"label":"Shop","url":"/shop","kind":"custom","isTopLevelLink":true} /-->
		<!-- wp:navigation-link {"label":"Collections","url":"#","kind":"custom","isTopLevelLink":true} /-->
		<!-- wp:navigation-link {"label":"About","url":"#","kind":"custom","isTopLevelLink":true} /-->
		<!-- wp:navigation-link {"label":"Contact","url":"/contact","kind":"custom","isTopLevelLink":true} /-->
		<!-- wp:navigation-link {"label":"FAQ","url":"#","kind":"custom","isTopLevelLink":true} /-->
	<!-- /wp:navigation -->

	<!-- wp:social-links {"iconColor":"foreground","iconColorValue":"currentColor","size":"has-small-icon-size","style":{"spacing":{"margin":{"top":"var:preset|spacing|10"},"blockGap":{"left":"var:preset|spacing|5"}}},"className":"is-style-logos-only","layout":{"type":"flex","justifyContent":"center"}} -->
	<ul class="wp-block-social-links has-small-icon-size has-icon-color is-style-logos-only" style="margin-top:var(--wp--preset--spacing--10)">
		<!-- wp:social-link {"url":"#","service":"instagram"} /-->
		<!-- wp:social-link {"url":"#","service":"x"} /-->
		<!-- wp:social-link {"url":"#","service":"tiktok"} /-->
		<!-- wp:social-link {"url":"#","service":"facebook"} /-->
		<!-- wp:social-link {"url":"#","service":"youtube"} /-->
		<!-- wp:social-link {"url":"#","service":"pinterest"} /-->
	</ul>
	<!-- /wp:social-links -->

	<!-- wp:separator {"style":{"spacing":{"margin":{"top":"var:preset|spacing|10","bottom":"var:preset|spacing|10"}}},"className":"is-style-wide"} -->
	<hr class="wp-block-separator has-alpha-channel-opacity is-style-wide" style="margin-top:var(--wp--preset--spacing--10);margin-bottom:var(--wp--preset--spacing--10)"/>
	<!-- /wp:separator -->

	<!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.08em","fontStyle":"normal","fontWeight":"600"},"spacing":{"margin":{"bottom":"var:preset|spacing|5"}}},"textColor":"foreground-muted","fontSize":"x-small"} -->
	<p class="has-text-align-center has-foreground-muted-color has-text-color has-x-small-font-size" style="margin-bottom:var(--wp--preset--spacing--5);font-style:normal;font-weight:600;letter-spacing:0.08em;text-transform:uppercase">Payment methods accepted</p>
	<!-- /wp:paragraph -->

	<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|3","margin":{"bottom":"var:preset|spacing|10"}}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"center"}} -->
	<div class="wp-block-group" style="margin-bottom:var(--wp--preset--spacing--10)">
		<!-- wp:paragraph {"className":"is-style-badge-muted"} -->
		<p class="is-style-badge-muted">VISA</p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"className":"is-style-badge-muted"} -->
		<p class="is-style-badge-muted">MC</p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"className":"is-style-badge-muted"} -->
		<p class="is-style-badge-muted">AMEX</p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"className":"is-style-badge-muted"} -->
		<p class="is-style-badge-muted">PAYPAL</p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"className":"is-style-badge-muted"} -->
		<p class="is-style-badge-muted">SHOP PAY</p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"className":"is-style-badge-muted"} -->
		<p class="is-style-badge-muted">APPLE PAY</p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:paragraph {"align":"center","textColor":"foreground-muted","fontSize":"x-small"} -->
	<p class="has-text-align-center has-foreground-muted-color has-text-color has-x-small-font-size">&copy; 2026 Aggressive Apparel. All rights reserved. <a href="#">Terms</a> &middot; <a href="#">Privacy</a></p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
