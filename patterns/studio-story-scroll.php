<?php
/**
 * Title: Studio Story Scroll
 * Description: Sticky media column with a long scrolling editorial narrative — uses the Split Story block for scroll-driven storytelling.
 * Slug: aggressive-apparel/studio-story-scroll
 * Categories: aggressive, aggressive-apparel, aggressive-informational, aggressive-drops
 * Keywords: split story, sticky, editorial, studio, scroll, narrative
 * Viewport Width: 1400
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|16","bottom":"var:preset|spacing|16"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--16);padding-bottom:var(--wp--preset--spacing--16)">
	<!-- wp:aggressive-apparel/split-story {"mediaPosition":"left","mediaWidth":48,"mediaHeight":"viewport","sticky":true,"stackOrder":"media-first","align":"wide","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|10","left":"var:preset|spacing|14"}}}} -->
	<div class="wp-block-aggressive-apparel-split-story aa-split-story aa-split-story--media-left aa-split-story--viewport aa-split-story--sticky aa-split-story--stack-media-first alignwide" style="--aa-split-media-width:48%;--aa-split-gap:var(--wp--preset--spacing--10) var(--wp--preset--spacing--14)">
		<!-- wp:aggressive-apparel/split-story-media -->
		<div class="wp-block-aggressive-apparel-split-story-media aa-split-story__media">
			<!-- wp:cover {"dimRatio":25,"overlayColor":"black","minHeight":100,"minHeightUnit":"%","isDark":true,"style":{"color":{"background":"var:preset|color|black"}}} -->
			<div class="wp-block-cover is-dark" style="background-color:var(--wp--preset--color--black);min-height:100%"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-25 has-background-dim"></span><div class="wp-block-cover__inner-container">
				<!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.16em","fontStyle":"normal","fontWeight":"600"}},"textColor":"white","fontSize":"x-small"} -->
				<p class="has-text-align-center has-white-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.16em;text-transform:uppercase"><?php echo esc_html__( 'Studio still', 'aggressive-apparel' ); ?></p>
				<!-- /wp:paragraph -->
			</div></div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:aggressive-apparel/split-story-media -->

		<!-- wp:aggressive-apparel/split-story-content {"layout":{"type":"constrained"}} -->
		<div class="wp-block-aggressive-apparel-split-story-content aa-split-story__content">
			<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.14em","fontStyle":"normal","fontWeight":"600"}},"textColor":"accent","fontSize":"x-small"} -->
			<p class="has-accent-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.14em;text-transform:uppercase"><?php echo esc_html__( 'Inside the room', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}},"typography":{"fontStyle":"normal","fontWeight":"800","lineHeight":"1.1"}},"fontSize":"fluid-xxxx-large"} -->
			<h2 class="wp-block-heading has-fluid-xxxx-large-font-size" style="margin-top:var(--wp--preset--spacing--4);font-style:normal;font-weight:800;line-height:1.1"><?php echo esc_html__( 'Midnight fittings, last-call dye runs', 'aggressive-apparel' ); ?></h2>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|8"}},"typography":{"lineHeight":"1.75"}},"textColor":"foreground-muted","fontSize":"medium"} -->
			<p class="has-foreground-muted-color has-text-color has-medium-font-size" style="margin-top:var(--wp--preset--spacing--8);line-height:1.75"><?php echo esc_html__( 'Most of what you see on the product page started as a pile of rejected samples on a folding table. We keep the rejects nearby on purpose — they remind the room what almost shipped.', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}},"typography":{"lineHeight":"1.75"}},"textColor":"foreground-muted","fontSize":"medium"} -->
			<p class="has-foreground-muted-color has-text-color has-medium-font-size" style="margin-top:var(--wp--preset--spacing--6);line-height:1.75"><?php echo esc_html__( 'Heavyweight fleece only stays in the line if it survives a wash cycle without losing the print edge. If the hand-feel goes soft in the wrong way, the blank is out.', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}},"typography":{"lineHeight":"1.75"}},"textColor":"foreground-muted","fontSize":"medium"} -->
			<p class="has-foreground-muted-color has-text-color has-medium-font-size" style="margin-top:var(--wp--preset--spacing--6);line-height:1.75"><?php echo esc_html__( 'When a drop finally clears, we photograph it the same night. No lifestyle set dressing — just the piece, the light we have, and the deadline.', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|12"}}}} -->
			<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--12)">
				<!-- wp:button {"backgroundColor":"foreground","textColor":"surface","style":{"typography":{"fontStyle":"normal","fontWeight":"700","textTransform":"uppercase","letterSpacing":"0.06em"},"spacing":{"padding":{"top":"var:preset|spacing|3","bottom":"var:preset|spacing|3","left":"var:preset|spacing|8","right":"var:preset|spacing|8"}}}} -->
				<div class="wp-block-button"><a class="wp-block-button__link has-surface-color has-foreground-background-color has-text-color has-background wp-element-button" style="padding-top:var(--wp--preset--spacing--3);padding-right:var(--wp--preset--spacing--8);padding-bottom:var(--wp--preset--spacing--3);padding-left:var(--wp--preset--spacing--8);font-style:normal;font-weight:700;letter-spacing:0.06em;text-transform:uppercase" href="/journal"><?php echo esc_html__( 'More from the journal', 'aggressive-apparel' ); ?></a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:aggressive-apparel/split-story-content -->
	</div>
	<!-- /wp:aggressive-apparel/split-story -->
</div>
<!-- /wp:group -->
