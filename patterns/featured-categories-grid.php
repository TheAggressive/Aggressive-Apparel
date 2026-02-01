<?php
/**
 * Title: Featured Categories Grid
 * Description: Three product category cards with image overlays and shop links for browsing by category.
 * Slug: aggressive-apparel/featured-categories-grid
 * Categories: aggressive-apparel, aggressive-homepage, aggressive-products
 * Keywords: categories, grid, shop, collections, featured
 * Viewport Width: 1200
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|24","bottom":"var:preset|spacing|24"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--24);padding-bottom:var(--wp--preset--spacing--24)">
	<!-- wp:heading {"textAlign":"center","fontSize":"fluid-xxxx-large"} -->
	<h2 class="wp-block-heading has-text-align-center has-fluid-xxxx-large-font-size">Shop by Category</h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|4","bottom":"var:preset|spacing|14"}}},"fontSize":"medium"} -->
	<p class="has-text-align-center has-medium-font-size" style="margin-top:var(--wp--preset--spacing--4);margin-bottom:var(--wp--preset--spacing--14)">Find your next statement piece</p>
	<!-- /wp:paragraph -->

	<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|6"}}}} -->
	<div class="wp-block-columns">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:cover {"dimRatio":40,"overlayColor":"black","minHeight":400,"minHeightUnit":"px","contentPosition":"bottom center","isDark":true,"style":{"color":{"background":"#1a1a1a"}}} -->
			<div class="wp-block-cover is-dark has-custom-content-position is-position-bottom-center" style="background-color:#1a1a1a;min-height:400px">
				<span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-40 has-background-dim"></span>
				<div class="wp-block-cover__inner-container">
					<!-- wp:heading {"textAlign":"center","level":3,"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"700"}},"textColor":"white","fontSize":"xx-large"} -->
					<h3 class="wp-block-heading has-text-align-center has-white-color has-text-color has-xx-large-font-size" style="font-style:normal;font-weight:700;text-transform:uppercase">Hoodies</h3>
					<!-- /wp:heading -->

					<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}}}} -->
					<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--4)">
						<!-- wp:button {"className":"is-style-outline","style":{"typography":{"fontStyle":"normal","fontWeight":"600","textTransform":"uppercase","letterSpacing":"0.05em","fontSize":"0.875rem"},"spacing":{"padding":{"top":"var:preset|spacing|2","bottom":"var:preset|spacing|2","left":"var:preset|spacing|6","right":"var:preset|spacing|6"}},"border":{"color":"#ffffff"}}} -->
						<div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-border-color wp-element-button" style="border-color:#ffffff;padding-top:var(--wp--preset--spacing--2);padding-right:var(--wp--preset--spacing--6);padding-bottom:var(--wp--preset--spacing--2);padding-left:var(--wp--preset--spacing--6);font-size:0.875rem;font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase" href="/product-category/hoodies">Shop Now</a></div>
						<!-- /wp:button -->
					</div>
					<!-- /wp:buttons -->
				</div>
			</div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:cover {"dimRatio":40,"overlayColor":"black","minHeight":400,"minHeightUnit":"px","contentPosition":"bottom center","isDark":true,"style":{"color":{"background":"#2a2a2a"}}} -->
			<div class="wp-block-cover is-dark has-custom-content-position is-position-bottom-center" style="background-color:#2a2a2a;min-height:400px">
				<span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-40 has-background-dim"></span>
				<div class="wp-block-cover__inner-container">
					<!-- wp:heading {"textAlign":"center","level":3,"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"700"}},"textColor":"white","fontSize":"xx-large"} -->
					<h3 class="wp-block-heading has-text-align-center has-white-color has-text-color has-xx-large-font-size" style="font-style:normal;font-weight:700;text-transform:uppercase">Tees</h3>
					<!-- /wp:heading -->

					<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}}}} -->
					<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--4)">
						<!-- wp:button {"className":"is-style-outline","style":{"typography":{"fontStyle":"normal","fontWeight":"600","textTransform":"uppercase","letterSpacing":"0.05em","fontSize":"0.875rem"},"spacing":{"padding":{"top":"var:preset|spacing|2","bottom":"var:preset|spacing|2","left":"var:preset|spacing|6","right":"var:preset|spacing|6"}},"border":{"color":"#ffffff"}}} -->
						<div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-border-color wp-element-button" style="border-color:#ffffff;padding-top:var(--wp--preset--spacing--2);padding-right:var(--wp--preset--spacing--6);padding-bottom:var(--wp--preset--spacing--2);padding-left:var(--wp--preset--spacing--6);font-size:0.875rem;font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase" href="/product-category/tees">Shop Now</a></div>
						<!-- /wp:button -->
					</div>
					<!-- /wp:buttons -->
				</div>
			</div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:cover {"dimRatio":40,"overlayColor":"black","minHeight":400,"minHeightUnit":"px","contentPosition":"bottom center","isDark":true,"style":{"color":{"background":"#3a3a3a"}}} -->
			<div class="wp-block-cover is-dark has-custom-content-position is-position-bottom-center" style="background-color:#3a3a3a;min-height:400px">
				<span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-40 has-background-dim"></span>
				<div class="wp-block-cover__inner-container">
					<!-- wp:heading {"textAlign":"center","level":3,"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"700"}},"textColor":"white","fontSize":"xx-large"} -->
					<h3 class="wp-block-heading has-text-align-center has-white-color has-text-color has-xx-large-font-size" style="font-style:normal;font-weight:700;text-transform:uppercase">Bottoms</h3>
					<!-- /wp:heading -->

					<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}}}} -->
					<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--4)">
						<!-- wp:button {"className":"is-style-outline","style":{"typography":{"fontStyle":"normal","fontWeight":"600","textTransform":"uppercase","letterSpacing":"0.05em","fontSize":"0.875rem"},"spacing":{"padding":{"top":"var:preset|spacing|2","bottom":"var:preset|spacing|2","left":"var:preset|spacing|6","right":"var:preset|spacing|6"}},"border":{"color":"#ffffff"}}} -->
						<div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-border-color wp-element-button" style="border-color:#ffffff;padding-top:var(--wp--preset--spacing--2);padding-right:var(--wp--preset--spacing--6);padding-bottom:var(--wp--preset--spacing--2);padding-left:var(--wp--preset--spacing--6);font-size:0.875rem;font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase" href="/product-category/bottoms">Shop Now</a></div>
						<!-- /wp:button -->
					</div>
					<!-- /wp:buttons -->
				</div>
			</div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
