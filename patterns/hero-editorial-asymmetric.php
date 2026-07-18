<?php
/**
 * Title: Hero Editorial Asymmetric
 * Description: Magazine-style asymmetric hero with large cover image left and stacked eyebrow, headline, body, and CTA right.
 * Slug: aggressive-apparel/hero-editorial-asymmetric
 * Categories: aggressive, aggressive-apparel, aggressive-homepage
 * Keywords: hero, editorial, asymmetric, magazine, cover, layout
 * Viewport Width: 1400
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"0","bottom":"0"},"margin":{"top":"0","bottom":"0"}},"dimensions":{"minHeight":"85vh"}},"backgroundColor":"surface","layout":{"type":"default"}} -->
<div class="wp-block-group alignfull has-surface-background-color has-background" style="min-height:85vh;margin-top:0;margin-bottom:0;padding-top:0;padding-bottom:0">
	<!-- wp:columns {"verticalAlignment":"stretch","isStackedOnMobile":true,"style":{"spacing":{"blockGap":{"left":"0"}}}} -->
	<div class="wp-block-columns are-vertically-aligned-stretch is-stacked-on-mobile">
		<!-- wp:column {"verticalAlignment":"stretch","width":"60%"} -->
		<div class="wp-block-column is-vertically-aligned-stretch" style="flex-basis:60%">
			<!-- wp:cover {"dimRatio":20,"overlayColor":"black","minHeight":85,"minHeightUnit":"vh","contentPosition":"bottom left","isDark":true,"style":{"color":{"background":"var:preset|color|black"}}} -->
			<div class="wp-block-cover is-dark has-custom-content-position is-position-bottom-left" style="background-color:var(--wp--preset--color--black);min-height:85vh"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-20 has-background-dim"></span><div class="wp-block-cover__inner-container">
				<!-- wp:paragraph {"align":"center","fontSize":"large"} -->
				<p class="has-text-align-center has-large-font-size"></p>
				<!-- /wp:paragraph -->
			</div></div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"verticalAlignment":"center","width":"40%","style":{"spacing":{"padding":{"top":"var:preset|spacing|16","bottom":"var:preset|spacing|16","left":"var:preset|spacing|16","right":"var:preset|spacing|16"}}}} -->
		<div class="wp-block-column is-vertically-aligned-center" style="padding-top:var(--wp--preset--spacing--16);padding-right:var(--wp--preset--spacing--16);padding-bottom:var(--wp--preset--spacing--16);padding-left:var(--wp--preset--spacing--16);flex-basis:40%">
			<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.15em","fontStyle":"normal","fontWeight":"600"}},"textColor":"accent","fontSize":"x-small"} -->
			<p class="has-accent-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.15em;text-transform:uppercase"><?php echo esc_html__( 'SS26 &middot; Issue 04', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"level":1,"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}},"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"800","lineHeight":"1"}},"fontSize":"fluid-xxxxxxx-large"} -->
			<h1 class="wp-block-heading has-fluid-xxxxxxx-large-font-size" style="margin-top:var(--wp--preset--spacing--6);font-style:normal;font-weight:800;line-height:1;text-transform:uppercase">Concrete<br>Confidence</h1>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|8"}},"typography":{"lineHeight":"1.8"}},"textColor":"foreground-muted","fontSize":"medium"} -->
			<p class="has-foreground-muted-color has-text-color has-medium-font-size" style="margin-top:var(--wp--preset--spacing--8);line-height:1.8"><?php echo esc_html__( 'Shot on location in downtown LA, the SS26 editorial pairs raw urban textures with precision-cut streetwear. Every frame is a study in controlled aggression.', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|10"}}}} -->
			<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--10)">
				<!-- wp:button {"backgroundColor":"black","textColor":"white","style":{"typography":{"fontStyle":"normal","fontWeight":"600","textTransform":"uppercase","letterSpacing":"0.05em"},"spacing":{"padding":{"top":"var:preset|spacing|4","bottom":"var:preset|spacing|4","left":"var:preset|spacing|10","right":"var:preset|spacing|10"}}}} -->
				<div class="wp-block-button"><a class="wp-block-button__link has-white-color has-black-background-color has-text-color has-background wp-element-button" style="padding-top:var(--wp--preset--spacing--4);padding-right:var(--wp--preset--spacing--10);padding-bottom:var(--wp--preset--spacing--4);padding-left:var(--wp--preset--spacing--10);font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase" href="/shop"><?php echo esc_html__( 'Explore the Collection', 'aggressive-apparel' ); ?></a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
