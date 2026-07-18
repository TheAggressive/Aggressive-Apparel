<?php
/**
 * Title: Drop Calendar Strip
 * Description: Horizontal timeline strip of three upcoming drops with date, name, and live status badges.
 * Slug: aggressive-apparel/drop-calendar-strip
 * Categories: aggressive, aggressive-apparel, aggressive-drops, aggressive-homepage
 * Keywords: drop, calendar, timeline, upcoming, release, schedule, strip
 * Viewport Width: 1400
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|12","bottom":"var:preset|spacing|12"},"margin":{"top":"0","bottom":"0"}},"border":{"top":{"color":"var:preset|color|border","width":"1px"},"bottom":{"color":"var:preset|color|border","width":"1px"}}},"backgroundColor":"surface-elevated","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-surface-elevated-background-color has-background" style="border-top-color:var(--wp--preset--color--border);border-top-width:1px;border-bottom-color:var(--wp--preset--color--border);border-bottom-width:1px;margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--12);padding-bottom:var(--wp--preset--spacing--12)">
	<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"},"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|8"}}}} -->
	<div class="wp-block-group" style="margin-bottom:var(--wp--preset--spacing--8)">
		<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.12em","fontStyle":"normal","fontWeight":"700"}},"fontSize":"x-small"} -->
		<p class="has-x-small-font-size" style="font-style:normal;font-weight:700;letter-spacing:0.12em;text-transform:uppercase"><?php echo esc_html__( 'Drop Calendar', 'aggressive-apparel' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"600","textTransform":"uppercase","letterSpacing":"0.05em"}},"fontSize":"x-small"} -->
		<p class="has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase"><a href="/shop"><?php echo esc_html__( 'View all drops &rarr;', 'aggressive-apparel' ); ?></a></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|6","top":"var:preset|spacing|6"}}}} -->
	<div class="wp-block-columns">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|6","bottom":"var:preset|spacing|6","left":"var:preset|spacing|6","right":"var:preset|spacing|6"},"blockGap":"var:preset|spacing|3"},"border":{"width":"1px","color":"var:preset|color|border","radius":"0px"}},"layout":{"type":"flex","orientation":"vertical"}} -->
			<div class="wp-block-group has-border-color" style="border-color:var(--wp--preset--color--border);border-width:1px;border-radius:0px;padding-top:var(--wp--preset--spacing--6);padding-right:var(--wp--preset--spacing--6);padding-bottom:var(--wp--preset--spacing--6);padding-left:var(--wp--preset--spacing--6)">
				<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.1em","fontStyle":"normal","fontWeight":"600"}},"textColor":"foreground-muted","fontSize":"x-small"} -->
				<p class="has-foreground-muted-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.1em;text-transform:uppercase"><?php echo esc_html__( 'Mar 22', 'aggressive-apparel' ); ?></p>
				<!-- /wp:paragraph -->

				<!-- wp:heading {"level":4,"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"800"}},"fontSize":"large"} -->
				<h4 class="wp-block-heading has-large-font-size" style="font-style:normal;font-weight:800;text-transform:uppercase"><?php echo esc_html__( 'Concrete Heat', 'aggressive-apparel' ); ?></h4>
				<!-- /wp:heading -->

				<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.08em","fontStyle":"normal","fontWeight":"700"}},"textColor":"warning","fontSize":"x-small"} -->
				<p class="has-warning-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:700;letter-spacing:0.08em;text-transform:uppercase"><?php echo esc_html__( 'Soon', 'aggressive-apparel' ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|6","bottom":"var:preset|spacing|6","left":"var:preset|spacing|6","right":"var:preset|spacing|6"},"blockGap":"var:preset|spacing|3"},"border":{"width":"2px","color":"var:preset|color|accent","radius":"0px"}},"backgroundColor":"black","textColor":"white","layout":{"type":"flex","orientation":"vertical"}} -->
			<div class="wp-block-group has-border-color has-white-color has-black-background-color has-text-color has-background" style="border-color:var(--wp--preset--color--accent);border-width:2px;border-radius:0px;padding-top:var(--wp--preset--spacing--6);padding-right:var(--wp--preset--spacing--6);padding-bottom:var(--wp--preset--spacing--6);padding-left:var(--wp--preset--spacing--6)">
				<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.1em","fontStyle":"normal","fontWeight":"600"}},"textColor":"accent","fontSize":"x-small"} -->
				<p class="has-accent-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.1em;text-transform:uppercase"><?php echo esc_html__( 'Mar 15', 'aggressive-apparel' ); ?></p>
				<!-- /wp:paragraph -->

				<!-- wp:heading {"level":4,"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"800"}},"textColor":"white","fontSize":"large"} -->
				<h4 class="wp-block-heading has-white-color has-text-color has-large-font-size" style="font-style:normal;font-weight:800;text-transform:uppercase"><?php echo esc_html__( 'Nightfall Capsule', 'aggressive-apparel' ); ?></h4>
				<!-- /wp:heading -->

				<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.08em","fontStyle":"normal","fontWeight":"700"}},"textColor":"success","fontSize":"x-small"} -->
				<p class="has-success-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:700;letter-spacing:0.08em;text-transform:uppercase"><?php echo esc_html__( 'Live', 'aggressive-apparel' ); ?></p>
				<!-- /wp:paragraph -->

				<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|2"}},"typography":{"fontStyle":"normal","fontWeight":"600","textTransform":"uppercase","letterSpacing":"0.05em"}},"textColor":"white","fontSize":"x-small"} -->
				<p class="has-white-color has-text-color has-x-small-font-size" style="margin-top:var(--wp--preset--spacing--2);font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase"><a href="/shop"><?php echo esc_html__( 'Shop now &rarr;', 'aggressive-apparel' ); ?></a></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|6","bottom":"var:preset|spacing|6","left":"var:preset|spacing|6","right":"var:preset|spacing|6"},"blockGap":"var:preset|spacing|3"},"border":{"width":"1px","color":"var:preset|color|border","radius":"0px"}},"layout":{"type":"flex","orientation":"vertical"}} -->
			<div class="wp-block-group has-border-color" style="border-color:var(--wp--preset--color--border);border-width:1px;border-radius:0px;padding-top:var(--wp--preset--spacing--6);padding-right:var(--wp--preset--spacing--6);padding-bottom:var(--wp--preset--spacing--6);padding-left:var(--wp--preset--spacing--6)">
				<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.1em","fontStyle":"normal","fontWeight":"600"}},"textColor":"foreground-muted","fontSize":"x-small"} -->
				<p class="has-foreground-muted-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.1em;text-transform:uppercase"><?php echo esc_html__( 'Mar 08', 'aggressive-apparel' ); ?></p>
				<!-- /wp:paragraph -->

				<!-- wp:heading {"level":4,"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"800"}},"fontSize":"large"} -->
				<h4 class="wp-block-heading has-large-font-size" style="font-style:normal;font-weight:800;text-transform:uppercase"><?php echo esc_html__( 'Steel City Pack', 'aggressive-apparel' ); ?></h4>
				<!-- /wp:heading -->

				<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.08em","fontStyle":"normal","fontWeight":"700"}},"textColor":"error","fontSize":"x-small"} -->
				<p class="has-error-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:700;letter-spacing:0.08em;text-transform:uppercase"><?php echo esc_html__( 'Sold Out', 'aggressive-apparel' ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
