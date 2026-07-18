<?php
/**
 * Title: PDP Delivery Estimate
 * Description: Slim horizontal strip with same-day shipping cutoff, free shipping threshold, and returns messaging for product pages.
 * Slug: aggressive-apparel/pdp-delivery-estimate
 * Categories: aggressive, aggressive-apparel, aggressive-pdp, aggressive-conversion
 * Keywords: delivery, shipping, estimate, returns, pdp, urgency, free shipping
 * Viewport Width: 1200
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|5","bottom":"var:preset|spacing|5","left":"var:preset|spacing|6","right":"var:preset|spacing|6"},"margin":{"top":"0","bottom":"0"}},"border":{"width":"1px","color":"var:preset|color|border","radius":"0px"}},"backgroundColor":"surface-elevated","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-border-color alignfull has-surface-elevated-background-color has-background" style="border-color:var(--wp--preset--color--border);border-width:1px;border-radius:0px;margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--5);padding-right:var(--wp--preset--spacing--6);padding-bottom:var(--wp--preset--spacing--5);padding-left:var(--wp--preset--spacing--6)">
	<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"},"style":{"spacing":{"blockGap":"var:preset|spacing|6"}}} -->
	<div class="wp-block-group">
		<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"},"style":{"spacing":{"blockGap":"var:preset|spacing|3"}}} -->
		<div class="wp-block-group">
			<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"700","textTransform":"uppercase","letterSpacing":"0.05em"}},"fontSize":"x-small"} -->
			<p class="has-x-small-font-size" style="font-style:normal;font-weight:700;letter-spacing:0.05em;text-transform:uppercase"><?php echo esc_html__( '🚚 Order by 2pm — ships today', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:paragraph {"textColor":"foreground-muted","fontSize":"x-small"} -->
		<p class="has-foreground-muted-color has-text-color has-x-small-font-size"><?php echo esc_html__( 'Free shipping on orders over $100', 'aggressive-apparel' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"textColor":"foreground-muted","fontSize":"x-small"} -->
		<p class="has-foreground-muted-color has-text-color has-x-small-font-size"><?php echo esc_html__( '30-day hassle-free returns', 'aggressive-apparel' ); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
