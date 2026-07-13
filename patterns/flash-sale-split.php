<?php
/**
 * Title: Flash Sale Split
 * Description: Loud two-column flash sale banner with oversized type, countdown copy, and full-height shop CTA.
 * Slug: aggressive-apparel/flash-sale-split
 * Categories: aggressive, aggressive-apparel, aggressive-conversion, aggressive-homepage
 * Keywords: flash sale, countdown, split, urgent, promotion, sale, loud
 * Viewport Width: 1400
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0">
	<!-- wp:columns {"isStackedOnMobile":true,"style":{"spacing":{"blockGap":{"left":"0","top":"0"}}}} -->
	<div class="wp-block-columns is-stacked-on-mobile">
		<!-- wp:column {"width":"50%","style":{"spacing":{"padding":{"top":"var:preset|spacing|16","bottom":"var:preset|spacing|16","left":"var:preset|spacing|12","right":"var:preset|spacing|12"}}},"backgroundColor":"black"} -->
		<div class="wp-block-column has-black-background-color has-background" style="padding-top:var(--wp--preset--spacing--16);padding-right:var(--wp--preset--spacing--12);padding-bottom:var(--wp--preset--spacing--16);padding-left:var(--wp--preset--spacing--12);flex-basis:50%">
			<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.2em","fontStyle":"normal","fontWeight":"700"}},"textColor":"accent","fontSize":"x-small"} -->
			<p class="has-accent-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:700;letter-spacing:0.2em;text-transform:uppercase">Ends Tonight</p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"level":1,"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}},"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"900","lineHeight":"0.85"}},"textColor":"white","fontSize":"fluid-xxxxxxxxx-large"} -->
			<h1 class="wp-block-heading has-white-color has-text-color has-fluid-xxxxxxxxx-large-font-size" style="margin-top:var(--wp--preset--spacing--4);font-style:normal;font-weight:900;line-height:0.85;text-transform:uppercase">Flash Sale</h1>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|8"}},"typography":{"textTransform":"uppercase","letterSpacing":"0.1em","fontStyle":"normal","fontWeight":"600"}},"textColor":"white","fontSize":"large"} -->
			<p class="has-white-color has-text-color has-large-font-size" style="margin-top:var(--wp--preset--spacing--8);font-style:normal;font-weight:600;letter-spacing:0.1em;text-transform:uppercase">Ends at midnight</p>
			<!-- /wp:paragraph -->

			<!-- wp:aggressive-apparel/countdown-timer {"displayStyle":"strip","endDateTime":"2026-12-31T23:59:59","saleLabelColor":"var:preset|color|accent","timeValueColor":"var:preset|color|white","unitLabelColor":"var:preset|color|white","timerBorderColor":"var:preset|color|border","style":{"spacing":{"margin":{"top":"var:preset|spacing|8"}}}} /-->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}}},"textColor":"white","fontSize":"medium"} -->
			<p class="has-white-color has-text-color has-medium-font-size" style="margin-top:var(--wp--preset--spacing--4)">Up to 40% off select streetwear. No code needed — prices already slashed.</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"verticalAlignment":"center","width":"50%","style":{"spacing":{"padding":{"top":"var:preset|spacing|16","bottom":"var:preset|spacing|16","left":"var:preset|spacing|12","right":"var:preset|spacing|12"}}},"backgroundColor":"accent"} -->
		<div class="wp-block-column is-vertically-aligned-center has-accent-background-color has-background" style="padding-top:var(--wp--preset--spacing--16);padding-right:var(--wp--preset--spacing--12);padding-bottom:var(--wp--preset--spacing--16);padding-left:var(--wp--preset--spacing--12);flex-basis:50%">
			<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
			<div class="wp-block-buttons">
				<!-- wp:button {"backgroundColor":"white","textColor":"accent","width":100,"style":{"typography":{"fontStyle":"normal","fontWeight":"800","textTransform":"uppercase","letterSpacing":"0.08em"},"spacing":{"padding":{"top":"var:preset|spacing|8","bottom":"var:preset|spacing|8","left":"var:preset|spacing|12","right":"var:preset|spacing|12"}}},"fontSize":"x-large"} -->
				<div class="wp-block-button has-custom-width wp-block-button__width-100"><a class="wp-block-button__link has-accent-color has-white-background-color has-text-color has-background has-x-large-font-size wp-element-button" style="padding-top:var(--wp--preset--spacing--8);padding-right:var(--wp--preset--spacing--12);padding-bottom:var(--wp--preset--spacing--8);padding-left:var(--wp--preset--spacing--12);font-style:normal;font-weight:800;letter-spacing:0.08em;text-transform:uppercase" href="/shop?on_sale=true">Shop Sale</a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
