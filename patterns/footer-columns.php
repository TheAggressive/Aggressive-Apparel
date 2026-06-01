<?php
/**
 * Title: Footer with Columns
 * Description: Multi-column footer with navigation links, contact info, newsletter signup, and social icons.
 * Slug: aggressive-apparel/footer-columns
 * Categories: aggressive, aggressive-apparel, footer
 * Keywords: footer, columns, navigation, newsletter, contact, social
 * Block Types: core/template-part/footer
 * Viewport Width: 1400
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|14"},"margin":{"top":"0","bottom":"0"}}},"backgroundColor":"black","textColor":"white","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-white-color has-black-background-color has-text-color has-background" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--14)">
	<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|12"}}}} -->
	<div class="wp-block-columns">
		<!-- wp:column {"width":"35%"} -->
		<div class="wp-block-column" style="flex-basis:35%">
			<!-- wp:site-title {"level":0,"style":{"typography":{"fontStyle":"normal","fontWeight":"700","textTransform":"uppercase","letterSpacing":"0.05em"}},"textColor":"white","fontSize":"large"} /-->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}},"typography":{"lineHeight":"1.8"}},"textColor":"white","fontSize":"small"} -->
			<p class="has-white-color has-text-color has-small-font-size" style="margin-top:var(--wp--preset--spacing--6);line-height:1.8">Premium streetwear crafted for those who refuse to blend in. Built tough, designed bold.</p>
			<!-- /wp:paragraph -->

			<!-- wp:social-links {"iconColor":"white","iconColorValue":"var(--wp--preset--color--white)","size":"has-small-icon-size","style":{"spacing":{"margin":{"top":"var:preset|spacing|8"},"blockGap":{"left":"var:preset|spacing|4"}}},"className":"is-style-logos-only"} -->
			<ul class="wp-block-social-links has-small-icon-size has-icon-color is-style-logos-only" style="margin-top:var(--wp--preset--spacing--8)">
				<!-- wp:social-link {"url":"#","service":"instagram"} /-->
				<!-- wp:social-link {"url":"#","service":"x"} /-->
				<!-- wp:social-link {"url":"#","service":"tiktok"} /-->
				<!-- wp:social-link {"url":"#","service":"facebook"} /-->
				<!-- wp:social-link {"url":"#","service":"youtube"} /-->
			</ul>
			<!-- /wp:social-links -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"style":{"typography":{"fontStyle":"normal","fontWeight":"700","textTransform":"uppercase","letterSpacing":"0.05em"}},"textColor":"white","fontSize":"x-small"} -->
			<h3 class="wp-block-heading has-white-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:700;letter-spacing:0.05em;text-transform:uppercase">Shop</h3>
			<!-- /wp:heading -->

			<!-- wp:list {"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}},"typography":{"lineHeight":"2.2"}},"fontSize":"small"} -->
			<ul class="has-small-font-size" style="margin-top:var(--wp--preset--spacing--6);line-height:2.2">
				<li><a href="/shop">All Products</a></li>
				<li><a href="#">New Arrivals</a></li>
				<li><a href="#">Best Sellers</a></li>
				<li><a href="#">Sale</a></li>
				<li><a href="#">Gift Cards</a></li>
			</ul>
			<!-- /wp:list -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"style":{"typography":{"fontStyle":"normal","fontWeight":"700","textTransform":"uppercase","letterSpacing":"0.05em"}},"textColor":"white","fontSize":"x-small"} -->
			<h3 class="wp-block-heading has-white-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:700;letter-spacing:0.05em;text-transform:uppercase">Company</h3>
			<!-- /wp:heading -->

			<!-- wp:list {"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}},"typography":{"lineHeight":"2.2"}},"fontSize":"small"} -->
			<ul class="has-small-font-size" style="margin-top:var(--wp--preset--spacing--6);line-height:2.2">
				<li><a href="#">About Us</a></li>
				<li><a href="#">Sustainability</a></li>
				<li><a href="#">Careers</a></li>
				<li><a href="#">Press</a></li>
				<li><a href="/contact">Contact</a></li>
			</ul>
			<!-- /wp:list -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"style":{"typography":{"fontStyle":"normal","fontWeight":"700","textTransform":"uppercase","letterSpacing":"0.05em"}},"textColor":"white","fontSize":"x-small"} -->
			<h3 class="wp-block-heading has-white-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:700;letter-spacing:0.05em;text-transform:uppercase">Help</h3>
			<!-- /wp:heading -->

			<!-- wp:list {"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}},"typography":{"lineHeight":"2.2"}},"fontSize":"small"} -->
			<ul class="has-small-font-size" style="margin-top:var(--wp--preset--spacing--6);line-height:2.2">
				<li><a href="#">Shipping &amp; Returns</a></li>
				<li><a href="#">Size Guide</a></li>
				<li><a href="#">FAQ</a></li>
				<li><a href="#">Track Order</a></li>
				<li><a href="#">Privacy Policy</a></li>
			</ul>
			<!-- /wp:list -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->

	<!-- wp:separator {"style":{"spacing":{"margin":{"top":"var:preset|spacing|14","bottom":"var:preset|spacing|8"}}},"backgroundColor":"white","className":"is-style-wide"} -->
	<hr class="wp-block-separator has-text-color has-white-color has-alpha-channel-opacity has-white-background-color has-background is-style-wide" style="margin-top:var(--wp--preset--spacing--14);margin-bottom:var(--wp--preset--spacing--8)"/>
	<!-- /wp:separator -->

	<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
	<div class="wp-block-group">
		<!-- wp:paragraph {"style":{"typography":{"fontSize":"0.75rem"}},"textColor":"white"} -->
		<p class="has-white-color has-text-color" style="font-size:0.75rem">&copy; 2026 Aggressive Apparel. All rights reserved.</p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"style":{"typography":{"fontSize":"0.75rem"}},"textColor":"white"} -->
		<p class="has-white-color has-text-color" style="font-size:0.75rem"><a href="#">Terms</a> &middot; <a href="#">Privacy</a> &middot; <a href="#">Cookies</a></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
