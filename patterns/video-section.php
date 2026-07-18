<?php
/**
 * Title: Video Section
 * Description: Full-width video embed section with heading, description, CTA, and embedded video player.
 * Slug: aggressive-apparel/video-section
 * Categories: aggressive, aggressive-apparel, aggressive-homepage
 * Keywords: video, embed, youtube, vimeo, media, film, campaign
 * Viewport Width: 1400
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"},"margin":{"top":"0","bottom":"0"}}},"backgroundColor":"black","textColor":"white","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-white-color has-black-background-color has-text-color has-background" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20)">
	<!-- wp:group {"layout":{"type":"constrained","contentSize":"600px"},"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|14"}}}} -->
	<div class="wp-block-group" style="margin-bottom:var(--wp--preset--spacing--14)">
		<!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.1em","fontStyle":"normal","fontWeight":"600"}},"textColor":"accent","fontSize":"x-small"} -->
		<p class="has-text-align-center has-accent-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.1em;text-transform:uppercase"><?php echo esc_html__( 'Behind the Brand', 'aggressive-apparel' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:heading {"textAlign":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}}},"textColor":"white","fontSize":"fluid-xxxxx-large"} -->
		<h2 class="wp-block-heading has-text-align-center has-white-color has-text-color has-fluid-xxxxx-large-font-size" style="margin-top:var(--wp--preset--spacing--4)"><?php echo esc_html__( 'Watch the Film', 'aggressive-apparel' ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}}},"textColor":"white","fontSize":"medium"} -->
		<p class="has-text-align-center has-white-color has-text-color has-medium-font-size" style="margin-top:var(--wp--preset--spacing--4)"><?php echo esc_html__( 'From concept to collection — see how we build every piece from the ground up.', 'aggressive-apparel' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|8"}}}} -->
		<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--8)">
			<!-- wp:button {"textColor":"white","className":"is-style-outline","style":{"typography":{"fontStyle":"normal","fontWeight":"600","textTransform":"uppercase","letterSpacing":"0.05em"},"spacing":{"padding":{"top":"var:preset|spacing|3","bottom":"var:preset|spacing|3","left":"var:preset|spacing|8","right":"var:preset|spacing|8"}}}} -->
			<div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-white-color has-text-color wp-element-button" style="padding-top:var(--wp--preset--spacing--3);padding-right:var(--wp--preset--spacing--8);padding-bottom:var(--wp--preset--spacing--3);padding-left:var(--wp--preset--spacing--8);font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase" href="/shop"><?php echo esc_html__( 'Shop the Collection', 'aggressive-apparel' ); ?></a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"layout":{"type":"constrained","contentSize":"960px"}} -->
	<div class="wp-block-group">
		<!-- wp:video {"style":{"border":{"radius":"var(--wp--custom--radius--card)"}}} -->
		<figure class="wp-block-video" style="border-radius:var(--wp--custom--radius--card)"></figure>
		<!-- /wp:video -->

		<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}}},"textColor":"foreground-muted","fontSize":"small"} -->
		<p class="has-text-align-center has-foreground-muted-color has-text-color has-small-font-size" style="margin-top:var(--wp--preset--spacing--4)"><?php echo esc_html__( 'Select this video block and upload a file, or replace it with a YouTube/Vimeo embed.', 'aggressive-apparel' ); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
