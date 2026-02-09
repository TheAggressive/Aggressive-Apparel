<?php
/**
 * Title: Contact Location
 * Description: Three-column contact section with address, contact details, social links, and a placeholder image.
 * Slug: aggressive-apparel/contact-location
 * Categories: aggressive, aggressive-apparel, aggressive-informational
 * Keywords: contact, location, store, address, hours, map
 * Viewport Width: 1200
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|24","bottom":"var:preset|spacing|24"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--24);padding-bottom:var(--wp--preset--spacing--24)">
	<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.1em","fontStyle":"normal","fontWeight":"600"}},"textColor":"red","fontSize":"x-small"} -->
	<p class="has-red-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.1em;text-transform:uppercase">Get in Touch</p>
	<!-- /wp:paragraph -->

	<!-- wp:heading {"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}}},"fontSize":"fluid-xxxx-large"} -->
	<h2 class="wp-block-heading has-fluid-xxxx-large-font-size" style="margin-top:var(--wp--preset--spacing--4)">Visit Us</h2>
	<!-- /wp:heading -->

	<!-- wp:spacer {"height":"var:preset|spacing|8"} -->
	<div style="height:var(--wp--preset--spacing--8)" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->

	<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|10"}}}} -->
	<div class="wp-block-columns">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":4,"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"700"}},"fontSize":"x-large"} -->
			<h4 class="wp-block-heading has-x-large-font-size" style="font-style:normal;font-weight:700;text-transform:uppercase">Flagship Store</h4>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}},"typography":{"lineHeight":"1.8"}},"fontSize":"medium"} -->
			<p class="has-medium-font-size" style="margin-top:var(--wp--preset--spacing--4);line-height:1.8">123 Street Name<br>City, State 12345</p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"level":5,"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"700"},"spacing":{"margin":{"top":"var:preset|spacing|8"}}},"fontSize":"large"} -->
			<h5 class="wp-block-heading has-large-font-size" style="margin-top:var(--wp--preset--spacing--8);font-style:normal;font-weight:700;text-transform:uppercase">Hours</h5>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}},"typography":{"lineHeight":"1.8"}},"fontSize":"medium"} -->
			<p class="has-medium-font-size" style="margin-top:var(--wp--preset--spacing--4);line-height:1.8">Mon–Sat: 10AM – 8PM<br>Sun: 12PM – 6PM</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":4,"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"700"}},"fontSize":"x-large"} -->
			<h4 class="wp-block-heading has-x-large-font-size" style="font-style:normal;font-weight:700;text-transform:uppercase">Contact</h4>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}},"typography":{"lineHeight":"1.8"}},"fontSize":"medium"} -->
			<p class="has-medium-font-size" style="margin-top:var(--wp--preset--spacing--4);line-height:1.8">info@aggressiveapparel.com<br>(555) 123-4567</p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"level":5,"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"700"},"spacing":{"margin":{"top":"var:preset|spacing|8"}}},"fontSize":"large"} -->
			<h5 class="wp-block-heading has-large-font-size" style="margin-top:var(--wp--preset--spacing--8);font-style:normal;font-weight:700;text-transform:uppercase">Follow Us</h5>
			<!-- /wp:heading -->

			<!-- wp:social-links {"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}}},"layout":{"type":"flex"}} -->
			<ul class="wp-block-social-links" style="margin-top:var(--wp--preset--spacing--4)">
				<!-- wp:social-link {"url":"https://instagram.com","service":"instagram"} /-->
				<!-- wp:social-link {"url":"https://twitter.com","service":"x"} /-->
				<!-- wp:social-link {"url":"https://facebook.com","service":"facebook"} /-->
				<!-- wp:social-link {"url":"https://tiktok.com","service":"tiktok"} /-->
			</ul>
			<!-- /wp:social-links -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:cover {"dimRatio":0,"minHeight":350,"minHeightUnit":"px","isDark":false,"style":{"color":{"background":"#e5e7eb"}}} -->
			<div class="wp-block-cover is-light" style="background-color:#e5e7eb;min-height:350px">
				<span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim"></span>
				<div class="wp-block-cover__inner-container">
					<!-- wp:paragraph {"align":"center","fontSize":"medium"} -->
					<p class="has-text-align-center has-medium-font-size">Map or storefront image</p>
					<!-- /wp:paragraph -->
				</div>
			</div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
