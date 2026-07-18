<?php
/**
 * Title: Drop Process Track
 * Description: Four-step drop pipeline (design → sample → produce → release) connected by a top rule — sequential storytelling, not a feature grid.
 * Slug: aggressive-apparel/drop-process-track
 * Categories: aggressive, aggressive-apparel, aggressive-drops, aggressive-informational
 * Keywords: process, drop, pipeline, steps, how we make, timeline
 * Viewport Width: 1400
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|24","bottom":"var:preset|spacing|24"},"margin":{"top":"0","bottom":"0"}}},"backgroundColor":"black","textColor":"white","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-white-color has-black-background-color has-text-color has-background" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--24);padding-bottom:var(--wp--preset--spacing--24)">
	<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"},"style":{"spacing":{"blockGap":"var:preset|spacing|8","margin":{"bottom":"var:preset|spacing|16"}}}} -->
	<div class="wp-block-group" style="margin-bottom:var(--wp--preset--spacing--16)">
		<!-- wp:group {"layout":{"type":"constrained","contentSize":"520px"},"style":{"spacing":{"blockGap":"var:preset|spacing|4"}}} -->
		<div class="wp-block-group">
			<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.16em","fontStyle":"normal","fontWeight":"700"}},"textColor":"accent","fontSize":"x-small"} -->
			<p class="has-accent-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:700;letter-spacing:0.16em;text-transform:uppercase"><?php echo esc_html__( 'From sketch to street', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"style":{"typography":{"fontStyle":"normal","fontWeight":"800","lineHeight":"1.05"}},"textColor":"white","fontSize":"fluid-xxxx-large"} -->
			<h2 class="wp-block-heading has-white-color has-text-color has-fluid-xxxx-large-font-size" style="font-style:normal;font-weight:800;line-height:1.05"><?php echo esc_html__( 'How a drop gets made', 'aggressive-apparel' ); ?></h2>
			<!-- /wp:heading -->
		</div>
		<!-- /wp:group -->

		<!-- wp:paragraph {"textColor":"white","fontSize":"small"} -->
		<p class="has-white-color has-text-color has-small-font-size"><?php echo esc_html__( 'Every piece moves through the same four gates.', 'aggressive-apparel' ); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:aggressive-apparel/animate-on-scroll {"animation":"fade","direction":"up","staggerChildren":true,"staggerDelay":0.1} -->
	<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|6","top":"var:preset|spacing|10"}}}} -->
	<div class="wp-block-columns">
		<!-- wp:column {"style":{"spacing":{"padding":{"top":"var:preset|spacing|8"}},"border":{"top":{"color":"var:preset|color|accent","width":"3px"}}}} -->
		<div class="wp-block-column" style="border-top-color:var(--wp--preset--color--accent);border-top-width:3px;padding-top:var(--wp--preset--spacing--8)">
			<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"800","letterSpacing":"0.06em"}},"textColor":"accent","fontSize":"small"} -->
			<p class="has-accent-color has-text-color has-small-font-size" style="font-style:normal;font-weight:800;letter-spacing:0.06em"><?php echo esc_html__( '01 — Design', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}},"typography":{"fontStyle":"normal","fontWeight":"700"}},"textColor":"white","fontSize":"x-large"} -->
			<h3 class="wp-block-heading has-white-color has-text-color has-x-large-font-size" style="margin-top:var(--wp--preset--spacing--4);font-style:normal;font-weight:700"><?php echo esc_html__( 'Lock the silhouette', 'aggressive-apparel' ); ?></h3>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|3"}},"typography":{"lineHeight":"1.65"}},"textColor":"white","fontSize":"small"} -->
			<p class="has-white-color has-text-color has-small-font-size" style="margin-top:var(--wp--preset--spacing--3);line-height:1.65"><?php echo esc_html__( 'Fit, placement, and type get sketched until the piece reads from across the room.', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"style":{"spacing":{"padding":{"top":"var:preset|spacing|8"}},"border":{"top":{"color":"var:preset|color|border","width":"3px"}}}} -->
		<div class="wp-block-column" style="border-top-color:var(--wp--preset--color--border);border-top-width:3px;padding-top:var(--wp--preset--spacing--8)">
			<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"800","letterSpacing":"0.06em"}},"textColor":"white","fontSize":"small"} -->
			<p class="has-white-color has-text-color has-small-font-size" style="font-style:normal;font-weight:800;letter-spacing:0.06em"><?php echo esc_html__( '02 — Sample', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}},"typography":{"fontStyle":"normal","fontWeight":"700"}},"textColor":"white","fontSize":"x-large"} -->
			<h3 class="wp-block-heading has-white-color has-text-color has-x-large-font-size" style="margin-top:var(--wp--preset--spacing--4);font-style:normal;font-weight:700"><?php echo esc_html__( 'Wear-test the blank', 'aggressive-apparel' ); ?></h3>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|3"}},"typography":{"lineHeight":"1.65"}},"textColor":"white","fontSize":"small"} -->
			<p class="has-white-color has-text-color has-small-font-size" style="margin-top:var(--wp--preset--spacing--3);line-height:1.65"><?php echo esc_html__( 'First pulls hit the floor. Shrink, hand-feel, and print adhesion either pass or restart.', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"style":{"spacing":{"padding":{"top":"var:preset|spacing|8"}},"border":{"top":{"color":"var:preset|color|border","width":"3px"}}}} -->
		<div class="wp-block-column" style="border-top-color:var(--wp--preset--color--border);border-top-width:3px;padding-top:var(--wp--preset--spacing--8)">
			<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"800","letterSpacing":"0.06em"}},"textColor":"white","fontSize":"small"} -->
			<p class="has-white-color has-text-color has-small-font-size" style="font-style:normal;font-weight:800;letter-spacing:0.06em"><?php echo esc_html__( '03 — Produce', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}},"typography":{"fontStyle":"normal","fontWeight":"700"}},"textColor":"white","fontSize":"x-large"} -->
			<h3 class="wp-block-heading has-white-color has-text-color has-x-large-font-size" style="margin-top:var(--wp--preset--spacing--4);font-style:normal;font-weight:700"><?php echo esc_html__( 'Run a tight batch', 'aggressive-apparel' ); ?></h3>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|3"}},"typography":{"lineHeight":"1.65"}},"textColor":"white","fontSize":"small"} -->
			<p class="has-white-color has-text-color has-small-font-size" style="margin-top:var(--wp--preset--spacing--3);line-height:1.65"><?php echo esc_html__( 'Limited units keep quality high and the drop scarce. No endless restocks by default.', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"style":{"spacing":{"padding":{"top":"var:preset|spacing|8"}},"border":{"top":{"color":"var:preset|color|border","width":"3px"}}}} -->
		<div class="wp-block-column" style="border-top-color:var(--wp--preset--color--border);border-top-width:3px;padding-top:var(--wp--preset--spacing--8)">
			<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"800","letterSpacing":"0.06em"}},"textColor":"white","fontSize":"small"} -->
			<p class="has-white-color has-text-color has-small-font-size" style="font-style:normal;font-weight:800;letter-spacing:0.06em"><?php echo esc_html__( '04 — Release', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}},"typography":{"fontStyle":"normal","fontWeight":"700"}},"textColor":"white","fontSize":"x-large"} -->
			<h3 class="wp-block-heading has-white-color has-text-color has-x-large-font-size" style="margin-top:var(--wp--preset--spacing--4);font-style:normal;font-weight:700"><?php echo esc_html__( 'Drop on the clock', 'aggressive-apparel' ); ?></h3>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|3"}},"typography":{"lineHeight":"1.65"}},"textColor":"white","fontSize":"small"} -->
			<p class="has-white-color has-text-color has-small-font-size" style="margin-top:var(--wp--preset--spacing--3);line-height:1.65"><?php echo esc_html__( 'Live countdown, waitlist first, then open cart. When it\'s gone, it\'s gone.', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
	<!-- /wp:aggressive-apparel/animate-on-scroll -->
</div>
<!-- /wp:group -->
