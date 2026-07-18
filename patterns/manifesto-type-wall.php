<?php
/**
 * Title: Manifesto Type Wall
 * Description: Typography-led brand statement with stacked lines and a single quiet CTA — no cover image, no card grid.
 * Slug: aggressive-apparel/manifesto-type-wall
 * Categories: aggressive, aggressive-apparel, aggressive-homepage, aggressive-informational
 * Keywords: manifesto, typography, statement, brand, type wall, editorial
 * Viewport Width: 1400
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|28","bottom":"var:preset|spacing|28","left":"var:preset|spacing|8","right":"var:preset|spacing|8"},"margin":{"top":"0","bottom":"0"}}},"backgroundColor":"surface","layout":{"type":"constrained","contentSize":"960px"}} -->
<div class="wp-block-group alignfull has-surface-background-color has-background" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--28);padding-right:var(--wp--preset--spacing--8);padding-bottom:var(--wp--preset--spacing--28);padding-left:var(--wp--preset--spacing--8)">
	<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.18em","fontStyle":"normal","fontWeight":"600"}},"textColor":"accent","fontSize":"x-small"} -->
	<p class="has-accent-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.18em;text-transform:uppercase"><?php echo esc_html__( 'Manifesto', 'aggressive-apparel' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:heading {"level":2,"style":{"spacing":{"margin":{"top":"var:preset|spacing|10"}},"typography":{"fontStyle":"normal","fontWeight":"900","lineHeight":"0.92","textTransform":"uppercase"}},"fontSize":"fluid-xxxxxxxxx-large"} -->
	<h2 class="wp-block-heading has-fluid-xxxxxxxxx-large-font-size" style="margin-top:var(--wp--preset--spacing--10);font-style:normal;font-weight:900;line-height:0.92;text-transform:uppercase"><?php echo esc_html__( 'We don\'t do soft.', 'aggressive-apparel' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:heading {"level":2,"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}},"typography":{"fontStyle":"normal","fontWeight":"300","lineHeight":"0.95"}},"textColor":"foreground-muted","fontSize":"fluid-xxxxxx-large"} -->
	<h2 class="wp-block-heading has-foreground-muted-color has-text-color has-fluid-xxxxxx-large-font-size" style="margin-top:var(--wp--preset--spacing--4);font-style:normal;font-weight:300;line-height:0.95"><?php echo esc_html__( 'We cut heavyweight blanks, print what we mean,', 'aggressive-apparel' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:heading {"level":2,"style":{"spacing":{"margin":{"top":"var:preset|spacing|2"}},"typography":{"fontStyle":"normal","fontWeight":"800","lineHeight":"0.95","textTransform":"uppercase"}},"fontSize":"fluid-xxxxxx-large"} -->
	<h2 class="wp-block-heading has-fluid-xxxxxx-large-font-size" style="margin-top:var(--wp--preset--spacing--2);font-style:normal;font-weight:800;line-height:0.95;text-transform:uppercase"><?php echo esc_html__( 'and ship it before the hype cools.', 'aggressive-apparel' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:separator {"style":{"spacing":{"margin":{"top":"var:preset|spacing|14","bottom":"var:preset|spacing|10"}}},"backgroundColor":"border"} -->
	<hr class="wp-block-separator has-text-color has-border-color has-alpha-channel-opacity has-border-background-color has-background" style="margin-top:var(--wp--preset--spacing--14);margin-bottom:var(--wp--preset--spacing--10)"/>
	<!-- /wp:separator -->

	<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"},"style":{"spacing":{"blockGap":"var:preset|spacing|8"}}} -->
	<div class="wp-block-group">
		<!-- wp:paragraph {"style":{"typography":{"lineHeight":"1.6"}},"textColor":"foreground-muted","fontSize":"medium"} -->
		<p class="has-foreground-muted-color has-text-color has-medium-font-size" style="line-height:1.6"><?php echo esc_html__( 'Street-tested fits. Limited runs. No filler drops.', 'aggressive-apparel' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"700","textTransform":"uppercase","letterSpacing":"0.08em"}},"fontSize":"x-small"} -->
		<p class="has-x-small-font-size" style="font-style:normal;font-weight:700;letter-spacing:0.08em;text-transform:uppercase"><a href="/about"><?php echo esc_html__( 'Read the story →', 'aggressive-apparel' ); ?></a></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
