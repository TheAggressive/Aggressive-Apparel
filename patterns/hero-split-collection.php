<?php
/**
 * Title: Hero Split Collection
 * Description: Full-width 50/50 hero with product image and bold text CTA for collection launches.
 * Slug: aggressive-apparel/hero-split-collection
 * Categories: aggressive, aggressive-apparel, aggressive-homepage
 * Keywords: hero, split, collection, featured, banner, image
 * Viewport Width: 1400
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"0","bottom":"0"},"margin":{"top":"0","bottom":"0"}},"dimensions":{"minHeight":"80vh"}},"backgroundColor":"black","layout":{"type":"default"}} -->
<div class="wp-block-group alignfull has-black-background-color has-background" style="min-height:80vh;margin-top:0;margin-bottom:0;padding-top:0;padding-bottom:0">
	<!-- wp:columns {"verticalAlignment":"center","isStackedOnMobile":true,"style":{"spacing":{"blockGap":{"left":"0"}}}} -->
	<div class="wp-block-columns are-vertically-aligned-center is-stacked-on-mobile">
		<!-- wp:column {"verticalAlignment":"center","width":"50%"} -->
		<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:50%">
			<!-- wp:cover {"dimRatio":0,"minHeight":80,"minHeightUnit":"vh","isDark":false,"style":{"color":{"background":"#1a1a1a"}}} -->
			<div class="wp-block-cover is-light" style="background-color:#1a1a1a;min-height:80vh">
				<span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim"></span>
				<div class="wp-block-cover__inner-container">
					<!-- wp:paragraph {"align":"center","textColor":"white","fontSize":"large"} -->
					<p class="has-text-align-center has-white-color has-text-color has-large-font-size">Collection image placeholder</p>
					<!-- /wp:paragraph -->
				</div>
			</div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"verticalAlignment":"center","width":"50%","style":{"spacing":{"padding":{"top":"var:preset|spacing|16","bottom":"var:preset|spacing|16","left":"var:preset|spacing|16","right":"var:preset|spacing|16"}}}} -->
		<div class="wp-block-column is-vertically-aligned-center" style="padding-top:var(--wp--preset--spacing--16);padding-right:var(--wp--preset--spacing--16);padding-bottom:var(--wp--preset--spacing--16);padding-left:var(--wp--preset--spacing--16);flex-basis:50%">
			<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.1em","fontStyle":"normal","fontWeight":"600"}},"textColor":"red","fontSize":"x-small"} -->
			<p class="has-red-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.1em;text-transform:uppercase">New Season</p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"level":1,"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}},"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"700"}},"textColor":"white","fontSize":"fluid-xxxxxxx-large"} -->
			<h1 class="wp-block-heading has-white-color has-text-color has-fluid-xxxxxxx-large-font-size" style="margin-top:var(--wp--preset--spacing--4);font-style:normal;font-weight:700;text-transform:uppercase">SS26 Collection</h1>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}},"typography":{"lineHeight":"1.6"}},"textColor":"white","fontSize":"large"} -->
			<p class="has-white-color has-text-color has-large-font-size" style="margin-top:var(--wp--preset--spacing--6);line-height:1.6">Engineered for performance. Designed for the streets. Our latest collection pushes the boundaries of what athletic wear can be.</p>
			<!-- /wp:paragraph -->

			<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|10"}}}} -->
			<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--10)">
				<!-- wp:button {"backgroundColor":"red","textColor":"white","style":{"typography":{"fontStyle":"normal","fontWeight":"600","textTransform":"uppercase","letterSpacing":"0.05em"},"spacing":{"padding":{"top":"var:preset|spacing|4","bottom":"var:preset|spacing|4","left":"var:preset|spacing|10","right":"var:preset|spacing|10"}}}} -->
				<div class="wp-block-button"><a class="wp-block-button__link has-white-color has-red-background-color has-text-color has-background wp-element-button" style="padding-top:var(--wp--preset--spacing--4);padding-right:var(--wp--preset--spacing--10);padding-bottom:var(--wp--preset--spacing--4);padding-left:var(--wp--preset--spacing--10);font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase" href="/shop">Shop the Collection</a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
