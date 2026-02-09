<?php
/**
 * Title: Split-Screen Collection
 * Description: Two-column full-bleed split layout for showcasing dual collections like Men's and Women's.
 * Slug: aggressive-apparel/split-screen-collection
 * Categories: aggressive, aggressive-apparel, aggressive-homepage, aggressive-products
 * Keywords: split, screen, collection, mens, womens, dual, fullwidth
 * Viewport Width: 1400
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:columns {"isStackedOnMobile":true,"align":"full","style":{"spacing":{"blockGap":{"left":"0","top":"0"},"margin":{"top":"0","bottom":"0"}}}} -->
<div class="wp-block-columns alignfull" style="margin-top:0;margin-bottom:0">
	<!-- wp:column -->
	<div class="wp-block-column">
		<!-- wp:cover {"dimRatio":50,"overlayColor":"black","minHeight":60,"minHeightUnit":"vh","contentPosition":"center center","isDark":true,"style":{"color":{"background":"#1a1a1a"}}} -->
		<div class="wp-block-cover is-dark" style="background-color:#1a1a1a;min-height:60vh"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim"></span><div class="wp-block-cover__inner-container">
			<!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.15em","fontStyle":"normal","fontWeight":"600"}},"textColor":"red","fontSize":"x-small"} -->
			<p class="has-text-align-center has-red-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.15em;text-transform:uppercase">Collection</p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"textAlign":"center","style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"800"}},"textColor":"white","fontSize":"fluid-xxxxx-large"} -->
			<h2 class="wp-block-heading has-text-align-center has-white-color has-text-color has-fluid-xxxxx-large-font-size" style="font-style:normal;font-weight:800;text-transform:uppercase">Men's</h2>
			<!-- /wp:heading -->

			<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|8"}}}} -->
			<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--8)">
				<!-- wp:button {"textColor":"white","className":"is-style-outline","style":{"typography":{"fontStyle":"normal","fontWeight":"600","textTransform":"uppercase","letterSpacing":"0.05em"},"spacing":{"padding":{"top":"var:preset|spacing|3","bottom":"var:preset|spacing|3","left":"var:preset|spacing|8","right":"var:preset|spacing|8"}}}} -->
				<div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-white-color has-text-color wp-element-button" style="padding-top:var(--wp--preset--spacing--3);padding-right:var(--wp--preset--spacing--8);padding-bottom:var(--wp--preset--spacing--3);padding-left:var(--wp--preset--spacing--8);font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase" href="/shop/men">Shop Men's</a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div></div>
		<!-- /wp:cover -->
	</div>
	<!-- /wp:column -->

	<!-- wp:column -->
	<div class="wp-block-column">
		<!-- wp:cover {"dimRatio":50,"overlayColor":"black","minHeight":60,"minHeightUnit":"vh","contentPosition":"center center","isDark":true,"style":{"color":{"background":"#2a2a2a"}}} -->
		<div class="wp-block-cover is-dark" style="background-color:#2a2a2a;min-height:60vh"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim"></span><div class="wp-block-cover__inner-container">
			<!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.15em","fontStyle":"normal","fontWeight":"600"}},"textColor":"red","fontSize":"x-small"} -->
			<p class="has-text-align-center has-red-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.15em;text-transform:uppercase">Collection</p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"textAlign":"center","style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"800"}},"textColor":"white","fontSize":"fluid-xxxxx-large"} -->
			<h2 class="wp-block-heading has-text-align-center has-white-color has-text-color has-fluid-xxxxx-large-font-size" style="font-style:normal;font-weight:800;text-transform:uppercase">Women's</h2>
			<!-- /wp:heading -->

			<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|8"}}}} -->
			<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--8)">
				<!-- wp:button {"textColor":"white","className":"is-style-outline","style":{"typography":{"fontStyle":"normal","fontWeight":"600","textTransform":"uppercase","letterSpacing":"0.05em"},"spacing":{"padding":{"top":"var:preset|spacing|3","bottom":"var:preset|spacing|3","left":"var:preset|spacing|8","right":"var:preset|spacing|8"}}}} -->
				<div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-white-color has-text-color wp-element-button" style="padding-top:var(--wp--preset--spacing--3);padding-right:var(--wp--preset--spacing--8);padding-bottom:var(--wp--preset--spacing--3);padding-left:var(--wp--preset--spacing--8);font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase" href="/shop/women">Shop Women's</a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div></div>
		<!-- /wp:cover -->
	</div>
	<!-- /wp:column -->
</div>
<!-- /wp:columns -->
