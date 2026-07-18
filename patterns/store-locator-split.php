<?php
/**
 * Title: Store Locator Split
 * Description: Flagship store section with address, hours, directions CTA on the left and map cover on the right.
 * Slug: aggressive-apparel/store-locator-split
 * Categories: aggressive, aggressive-apparel, aggressive-informational
 * Keywords: store, locator, flagship, address, hours, map, directions
 * Viewport Width: 1200
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|24","bottom":"var:preset|spacing|24"},"margin":{"top":"0","bottom":"0"}}},"backgroundColor":"surface","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-surface-background-color has-background" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--24);padding-bottom:var(--wp--preset--spacing--24)">
	<!-- wp:columns {"verticalAlignment":"center","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|14"}}}} -->
	<div class="wp-block-columns are-vertically-aligned-center">
		<!-- wp:column {"verticalAlignment":"center","width":"42%","style":{"spacing":{"padding":{"top":"var:preset|spacing|8","bottom":"var:preset|spacing|8","left":"var:preset|spacing|4","right":"var:preset|spacing|8"}}}} -->
		<div class="wp-block-column is-vertically-aligned-center" style="padding-top:var(--wp--preset--spacing--8);padding-right:var(--wp--preset--spacing--8);padding-bottom:var(--wp--preset--spacing--8);padding-left:var(--wp--preset--spacing--4);flex-basis:42%">
			<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.12em","fontStyle":"normal","fontWeight":"600"}},"textColor":"accent","fontSize":"x-small"} -->
			<p class="has-accent-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.12em;text-transform:uppercase"><?php echo esc_html__( 'Visit Us', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}}},"fontSize":"fluid-xxxx-large"} -->
			<h2 class="wp-block-heading has-fluid-xxxx-large-font-size" style="margin-top:var(--wp--preset--spacing--4)"><?php echo esc_html__( 'Flagship Store', 'aggressive-apparel' ); ?></h2>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|8"}},"typography":{"lineHeight":"1.8"}},"fontSize":"medium"} -->
			<p class="has-medium-font-size" style="margin-top:var(--wp--preset--spacing--8);line-height:1.8"><strong><?php echo esc_html__( '1247 Melrose Ave', 'aggressive-apparel' ); ?></strong><br>Los Angeles, CA 90038<br>United States</p>
			<!-- /wp:paragraph -->

			<!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|8"},"blockGap":"var:preset|spacing|3"}},"layout":{"type":"constrained"}} -->
			<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--8)">
				<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"700","textTransform":"uppercase","letterSpacing":"0.05em"}},"fontSize":"x-small"} -->
				<p class="has-x-small-font-size" style="font-style:normal;font-weight:700;letter-spacing:0.05em;text-transform:uppercase"><?php echo esc_html__( 'Hours', 'aggressive-apparel' ); ?></p>
				<!-- /wp:paragraph -->

				<!-- wp:paragraph {"textColor":"foreground-muted","fontSize":"small"} -->
				<p class="has-foreground-muted-color has-text-color has-small-font-size">Mon–Fri: 11am – 8pm<br>Sat–Sun: 10am – 9pm</p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|10"}}}} -->
			<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--10)">
				<!-- wp:button {"backgroundColor":"black","textColor":"white","style":{"typography":{"fontStyle":"normal","fontWeight":"600","textTransform":"uppercase","letterSpacing":"0.05em"},"spacing":{"padding":{"top":"var:preset|spacing|3","bottom":"var:preset|spacing|3","left":"var:preset|spacing|8","right":"var:preset|spacing|8"}}}} -->
				<div class="wp-block-button"><a class="wp-block-button__link has-white-color has-black-background-color has-text-color has-background wp-element-button" style="padding-top:var(--wp--preset--spacing--3);padding-right:var(--wp--preset--spacing--8);padding-bottom:var(--wp--preset--spacing--3);padding-left:var(--wp--preset--spacing--8);font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase" href="https://maps.google.com/?q=1247+Melrose+Ave+Los+Angeles+CA" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Get Directions', 'aggressive-apparel' ); ?></a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"verticalAlignment":"center","width":"58%"} -->
		<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:58%">
			<!-- wp:cover {"dimRatio":0,"minHeight":480,"minHeightUnit":"px","isDark":false,"style":{"color":{"background":"var:preset|color|surface-elevated"},"border":{"radius":"var(--wp--custom--radius--card)"}}} -->
			<div class="wp-block-cover is-light" style="border-radius:var(--wp--custom--radius--card);background-color:var(--wp--preset--color--surface-elevated);min-height:480px"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim"></span><div class="wp-block-cover__inner-container">
				<!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.12em","fontStyle":"normal","fontWeight":"600"}},"textColor":"foreground-muted","fontSize":"x-small"} -->
				<p class="has-text-align-center has-foreground-muted-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.12em;text-transform:uppercase"><?php echo esc_html__( 'Flagship', 'aggressive-apparel' ); ?></p>
				<!-- /wp:paragraph -->
			</div></div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
