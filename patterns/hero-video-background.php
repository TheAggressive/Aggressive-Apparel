<?php
/**
 * Title: Video Hero Section
 * Description: Full-bleed cinematic hero. Insert as image cover, then set Cover → Media type to Video and upload your film in the editor.
 * Slug: aggressive-apparel/hero-video-background
 * Categories: aggressive, aggressive-apparel, aggressive-homepage
 * Keywords: hero, video, background, cinematic, fullscreen, cta
 * Viewport Width: 1400
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:cover {"dimRatio":60,"overlayColor":"black","isUserOverlayColor":true,"minHeight":100,"minHeightUnit":"vh","contentPosition":"center center","align":"full","isDark":true,"style":{"spacing":{"margin":{"top":"0","bottom":"0"}},"color":{"background":"var:preset|color|black"}}} -->
<div class="wp-block-cover alignfull is-dark has-black-overlay-color" style="background-color:var(--wp--preset--color--black);margin-top:0;margin-bottom:0;min-height:100vh"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-60 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:group {"layout":{"type":"constrained","contentSize":"900px"}} -->
<div class="wp-block-group">
	<!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.2em","fontStyle":"normal","fontWeight":"600"},"spacing":{"margin":{"bottom":"var:preset|spacing|6"}}},"textColor":"accent","fontSize":"small"} -->
	<p class="has-text-align-center has-accent-color has-text-color has-small-font-size" style="margin-bottom:var(--wp--preset--spacing--6);font-style:normal;font-weight:600;letter-spacing:0.2em;text-transform:uppercase"><?php echo esc_html__( 'Campaign Film', 'aggressive-apparel' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:heading {"textAlign":"center","level":1,"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"800","lineHeight":"1.05"}},"textColor":"white","fontSize":"fluid-xxxxxxxx-large"} -->
	<h1 class="wp-block-heading has-text-align-center has-white-color has-text-color has-fluid-xxxxxxxx-large-font-size" style="font-style:normal;font-weight:800;line-height:1.05;text-transform:uppercase"><?php echo esc_html__( 'Redefine Your Limits', 'aggressive-apparel' ); ?></h1>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}}},"textColor":"white","fontSize":"large"} -->
	<p class="has-text-align-center has-white-color has-text-color has-large-font-size" style="margin-top:var(--wp--preset--spacing--6)"><?php echo esc_html__( 'Attach your campaign video to this cover in the editor. Performance meets street style.', 'aggressive-apparel' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|10"}}}} -->
	<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--10)">
		<!-- wp:button {"backgroundColor":"accent","textColor":"white","style":{"typography":{"fontStyle":"normal","fontWeight":"600","textTransform":"uppercase","letterSpacing":"0.05em"},"spacing":{"padding":{"top":"var:preset|spacing|4","bottom":"var:preset|spacing|4","left":"var:preset|spacing|10","right":"var:preset|spacing|10"}}}} -->
		<div class="wp-block-button"><a class="wp-block-button__link has-white-color has-accent-background-color has-text-color has-background wp-element-button" style="padding-top:var(--wp--preset--spacing--4);padding-right:var(--wp--preset--spacing--10);padding-bottom:var(--wp--preset--spacing--4);padding-left:var(--wp--preset--spacing--10);font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase" href="/shop"><?php echo esc_html__( 'Shop the Collection', 'aggressive-apparel' ); ?></a></div>
		<!-- /wp:button -->

		<!-- wp:button {"textColor":"white","className":"is-style-outline","style":{"typography":{"fontStyle":"normal","fontWeight":"600","textTransform":"uppercase","letterSpacing":"0.05em"},"spacing":{"padding":{"top":"var:preset|spacing|4","bottom":"var:preset|spacing|4","left":"var:preset|spacing|10","right":"var:preset|spacing|10"}}}} -->
		<div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-white-color has-text-color wp-element-button" style="padding-top:var(--wp--preset--spacing--4);padding-right:var(--wp--preset--spacing--10);padding-bottom:var(--wp--preset--spacing--4);padding-left:var(--wp--preset--spacing--10);font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase" href="/lookbook"><?php echo esc_html__( 'Watch the Film', 'aggressive-apparel' ); ?></a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group --></div></div>
<!-- /wp:cover -->
