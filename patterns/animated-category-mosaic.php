<?php
/**
 * Title: Animated Category Mosaic
 * Description: Two-by-two category mosaic with scroll-triggered stagger animation linking to product category archives.
 * Slug: aggressive-apparel/animated-category-mosaic
 * Categories: aggressive, aggressive-apparel, aggressive-homepage, aggressive-products
 * Keywords: categories, mosaic, grid, animate, scroll, hoodies, tees
 * Viewport Width: 1200
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|24","bottom":"var:preset|spacing|24"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--24);padding-bottom:var(--wp--preset--spacing--24)">
	<!-- wp:heading {"textAlign":"center","fontSize":"fluid-xxxx-large"} -->
	<h2 class="wp-block-heading has-text-align-center has-fluid-xxxx-large-font-size">Shop the Lineup</h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|4","bottom":"var:preset|spacing|14"}}},"textColor":"foreground-muted","fontSize":"medium"} -->
	<p class="has-text-align-center has-foreground-muted-color has-text-color has-medium-font-size" style="margin-top:var(--wp--preset--spacing--4);margin-bottom:var(--wp--preset--spacing--14)">Four pillars of the Aggressive wardrobe</p>
	<!-- /wp:paragraph -->

	<!-- wp:aggressive-apparel/animate-on-scroll {"staggerChildren":true,"animation":"fade","direction":"up","staggerDelay":0.15} -->
	<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|6","top":"var:preset|spacing|6"}}}} -->
	<div class="wp-block-columns">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:cover {"dimRatio":50,"overlayColor":"black","minHeight":320,"minHeightUnit":"px","contentPosition":"bottom left","isDark":true,"style":{"color":{"background":"var:preset|color|black"},"border":{"radius":"var(--wp--custom--radius--card)"}}} -->
			<div class="wp-block-cover is-dark has-custom-content-position is-position-bottom-left" style="border-radius:var(--wp--custom--radius--card);background-color:var(--wp--preset--color--black);min-height:320px"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-50 has-background-dim"></span><div class="wp-block-cover__inner-container">
				<!-- wp:heading {"level":3,"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"800"}},"textColor":"white","fontSize":"fluid-xx-large"} -->
				<h3 class="wp-block-heading has-white-color has-text-color has-fluid-xx-large-font-size" style="font-style:normal;font-weight:800;text-transform:uppercase"><a href="/product-category/hoodies">Hoodies</a></h3>
				<!-- /wp:heading -->
			</div></div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:cover {"dimRatio":50,"overlayColor":"black","minHeight":320,"minHeightUnit":"px","contentPosition":"bottom left","isDark":true,"style":{"color":{"background":"var:preset|color|black"},"border":{"radius":"var(--wp--custom--radius--card)"}}} -->
			<div class="wp-block-cover is-dark has-custom-content-position is-position-bottom-left" style="border-radius:var(--wp--custom--radius--card);background-color:var(--wp--preset--color--black);min-height:320px"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-50 has-background-dim"></span><div class="wp-block-cover__inner-container">
				<!-- wp:heading {"level":3,"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"800"}},"textColor":"white","fontSize":"fluid-xx-large"} -->
				<h3 class="wp-block-heading has-white-color has-text-color has-fluid-xx-large-font-size" style="font-style:normal;font-weight:800;text-transform:uppercase"><a href="/product-category/tees">Tees</a></h3>
				<!-- /wp:heading -->
			</div></div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->

	<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|6","top":"var:preset|spacing|6"},"margin":{"top":"var:preset|spacing|6"}}}} -->
	<div class="wp-block-columns" style="margin-top:var(--wp--preset--spacing--6)">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:cover {"dimRatio":50,"overlayColor":"black","minHeight":320,"minHeightUnit":"px","contentPosition":"bottom left","isDark":true,"style":{"color":{"background":"var:preset|color|black"},"border":{"radius":"var(--wp--custom--radius--card)"}}} -->
			<div class="wp-block-cover is-dark has-custom-content-position is-position-bottom-left" style="border-radius:var(--wp--custom--radius--card);background-color:var(--wp--preset--color--black);min-height:320px"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-50 has-background-dim"></span><div class="wp-block-cover__inner-container">
				<!-- wp:heading {"level":3,"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"800"}},"textColor":"white","fontSize":"fluid-xx-large"} -->
				<h3 class="wp-block-heading has-white-color has-text-color has-fluid-xx-large-font-size" style="font-style:normal;font-weight:800;text-transform:uppercase"><a href="/product-category/outerwear">Outerwear</a></h3>
				<!-- /wp:heading -->
			</div></div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:cover {"dimRatio":50,"overlayColor":"black","minHeight":320,"minHeightUnit":"px","contentPosition":"bottom left","isDark":true,"style":{"color":{"background":"var:preset|color|black"},"border":{"radius":"var(--wp--custom--radius--card)"}}} -->
			<div class="wp-block-cover is-dark has-custom-content-position is-position-bottom-left" style="border-radius:var(--wp--custom--radius--card);background-color:var(--wp--preset--color--black);min-height:320px"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-50 has-background-dim"></span><div class="wp-block-cover__inner-container">
				<!-- wp:heading {"level":3,"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"800"}},"textColor":"white","fontSize":"fluid-xx-large"} -->
				<h3 class="wp-block-heading has-white-color has-text-color has-fluid-xx-large-font-size" style="font-style:normal;font-weight:800;text-transform:uppercase"><a href="/product-category/accessories">Accessories</a></h3>
				<!-- /wp:heading -->
			</div></div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
	<!-- /wp:aggressive-apparel/animate-on-scroll -->
</div>
<!-- /wp:group -->
