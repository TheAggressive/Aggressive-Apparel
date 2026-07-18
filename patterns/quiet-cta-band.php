<?php
/**
 * Title: Quiet CTA Band
 * Description: Thin full-bleed strip with one sentence and one text link — intentional contrast to loud flash-sale sections.
 * Slug: aggressive-apparel/quiet-cta-band
 * Categories: aggressive, aggressive-apparel, aggressive-homepage, aggressive-conversion
 * Keywords: cta, band, strip, quiet, newsletter, soft sell
 * Viewport Width: 1400
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|10","bottom":"var:preset|spacing|10","left":"var:preset|spacing|8","right":"var:preset|spacing|8"},"margin":{"top":"0","bottom":"0"}},"border":{"top":{"color":"var:preset|color|border","width":"1px"},"bottom":{"color":"var:preset|color|border","width":"1px"}}},"backgroundColor":"surface-elevated","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-surface-elevated-background-color has-background" style="border-top-color:var(--wp--preset--color--border);border-top-width:1px;border-bottom-color:var(--wp--preset--color--border);border-bottom-width:1px;margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--10);padding-right:var(--wp--preset--spacing--8);padding-bottom:var(--wp--preset--spacing--10);padding-left:var(--wp--preset--spacing--8)">
	<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"},"style":{"spacing":{"blockGap":"var:preset|spacing|8"}}} -->
	<div class="wp-block-group">
		<!-- wp:paragraph {"style":{"typography":{"lineHeight":"1.5"}},"fontSize":"large"} -->
		<p class="has-large-font-size" style="line-height:1.5"><?php echo esc_html__( 'New drops land first in the inbox — no noise, just the release.', 'aggressive-apparel' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"700","textTransform":"uppercase","letterSpacing":"0.1em"}},"textColor":"accent","fontSize":"x-small"} -->
		<p class="has-accent-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:700;letter-spacing:0.1em;text-transform:uppercase"><a href="/newsletter"><?php echo esc_html__( 'Get on the list →', 'aggressive-apparel' ); ?></a></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
