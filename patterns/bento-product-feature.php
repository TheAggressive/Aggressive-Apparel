<?php
/**
 * Title: Bento Grid Product Feature
 * Description: Asymmetric bento-style tile grid showcasing featured products and collections in a modern 2026 layout.
 * Slug: aggressive-apparel/bento-product-feature
 * Categories: aggressive, aggressive-apparel, aggressive-homepage, aggressive-products
 * Keywords: bento, grid, tiles, asymmetric, featured, products, modern
 * Viewport Width: 1400
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|24","bottom":"var:preset|spacing|24"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--24);padding-bottom:var(--wp--preset--spacing--24)">
	<!-- wp:heading {"textAlign":"center","fontSize":"fluid-xxxx-large"} -->
	<h2 class="wp-block-heading has-text-align-center has-fluid-xxxx-large-font-size"><?php echo esc_html__( 'Shop the Edit', 'aggressive-apparel' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|4","bottom":"var:preset|spacing|14"}}},"textColor":"foreground-muted","fontSize":"medium"} -->
	<p class="has-text-align-center has-foreground-muted-color has-text-color has-medium-font-size" style="margin-top:var(--wp--preset--spacing--4);margin-bottom:var(--wp--preset--spacing--14)"><?php echo esc_html__( 'Heavyweight staples and limited collabs — pick your lane', 'aggressive-apparel' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:aggressive-apparel/animate-on-scroll {"animation":"fade","staggerChildren":true,"staggerDelay":0.12} -->
	<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|4"}}}} -->
	<div class="wp-block-columns">
		<!-- wp:column {"width":"66.66%"} -->
		<div class="wp-block-column" style="flex-basis:66.66%">
			<!-- wp:cover {"dimRatio":40,"overlayColor":"black","minHeight":500,"minHeightUnit":"px","contentPosition":"bottom left","isDark":true,"style":{"color":{"background":"var:preset|color|black"},"spacing":{"padding":{"top":"var:preset|spacing|8","bottom":"var:preset|spacing|8","left":"var:preset|spacing|8","right":"var:preset|spacing|8"}}}} -->
			<div class="wp-block-cover is-dark has-custom-content-position is-position-bottom-left" style="background-color:var(--wp--preset--color--black);padding-top:var(--wp--preset--spacing--8);padding-right:var(--wp--preset--spacing--8);padding-bottom:var(--wp--preset--spacing--8);padding-left:var(--wp--preset--spacing--8);min-height:500px"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-40 has-background-dim"></span><div class="wp-block-cover__inner-container">
				<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.1em","fontStyle":"normal","fontWeight":"600"}},"textColor":"accent","fontSize":"x-small"} -->
				<p class="has-accent-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.1em;text-transform:uppercase"><?php echo esc_html__( 'Featured Collection', 'aggressive-apparel' ); ?></p>
				<!-- /wp:paragraph -->

				<!-- wp:heading {"level":3,"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"700"}},"textColor":"white","fontSize":"fluid-xxx-large"} -->
				<h3 class="wp-block-heading has-white-color has-text-color has-fluid-xxx-large-font-size" style="font-style:normal;font-weight:700;text-transform:uppercase"><?php echo esc_html__( 'Streetwear Essentials', 'aggressive-apparel' ); ?></h3>
				<!-- /wp:heading -->

				<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}}}} -->
				<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--6)">
					<!-- wp:button {"backgroundColor":"accent","textColor":"white","style":{"typography":{"fontStyle":"normal","fontWeight":"600","textTransform":"uppercase","letterSpacing":"0.05em"},"spacing":{"padding":{"top":"var:preset|spacing|3","bottom":"var:preset|spacing|3","left":"var:preset|spacing|6","right":"var:preset|spacing|6"}}}} -->
					<div class="wp-block-button"><a class="wp-block-button__link has-white-color has-accent-background-color has-text-color has-background wp-element-button" style="padding-top:var(--wp--preset--spacing--3);padding-right:var(--wp--preset--spacing--6);padding-bottom:var(--wp--preset--spacing--3);padding-left:var(--wp--preset--spacing--6);font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase" href="/collections/streetwear"><?php echo esc_html__( 'Shop Now', 'aggressive-apparel' ); ?></a></div>
					<!-- /wp:button -->
				</div>
				<!-- /wp:buttons -->
			</div></div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"width":"33.33%"} -->
		<div class="wp-block-column" style="flex-basis:33.33%">
			<!-- wp:cover {"dimRatio":40,"overlayColor":"black","minHeight":244,"minHeightUnit":"px","contentPosition":"bottom left","isDark":true,"style":{"color":{"background":"var:preset|color|black"},"spacing":{"padding":{"top":"var:preset|spacing|6","bottom":"var:preset|spacing|6","left":"var:preset|spacing|6","right":"var:preset|spacing|6"},"margin":{"bottom":"var:preset|spacing|4"}}}} -->
			<div class="wp-block-cover is-dark has-custom-content-position is-position-bottom-left" style="background-color:var(--wp--preset--color--black);margin-bottom:var(--wp--preset--spacing--4);padding-top:var(--wp--preset--spacing--6);padding-right:var(--wp--preset--spacing--6);padding-bottom:var(--wp--preset--spacing--6);padding-left:var(--wp--preset--spacing--6);min-height:244px"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-40 has-background-dim"></span><div class="wp-block-cover__inner-container">
				<!-- wp:heading {"level":4,"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"700"}},"textColor":"white","fontSize":"x-large"} -->
				<h4 class="wp-block-heading has-white-color has-text-color has-x-large-font-size" style="font-style:normal;font-weight:700;text-transform:uppercase"><?php echo esc_html__( 'New Hoodies', 'aggressive-apparel' ); ?></h4>
				<!-- /wp:heading -->

				<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|3"}}}} -->
				<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--3)">
					<!-- wp:button {"className":"is-style-cta-small is-style-outline-on-dark"} -->
					<div class="wp-block-button is-style-cta-small is-style-outline-on-dark"><a class="wp-block-button__link wp-element-button" href="/product-category/hoodies"><?php echo esc_html__( 'Shop Hoodies', 'aggressive-apparel' ); ?></a></div>
					<!-- /wp:button -->
				</div>
				<!-- /wp:buttons -->
			</div></div>
			<!-- /wp:cover -->

			<!-- wp:cover {"dimRatio":40,"overlayColor":"black","minHeight":244,"minHeightUnit":"px","contentPosition":"bottom left","isDark":true,"style":{"color":{"background":"var:preset|color|black"},"spacing":{"padding":{"top":"var:preset|spacing|6","bottom":"var:preset|spacing|6","left":"var:preset|spacing|6","right":"var:preset|spacing|6"}}}} -->
			<div class="wp-block-cover is-dark has-custom-content-position is-position-bottom-left" style="background-color:var(--wp--preset--color--black);padding-top:var(--wp--preset--spacing--6);padding-right:var(--wp--preset--spacing--6);padding-bottom:var(--wp--preset--spacing--6);padding-left:var(--wp--preset--spacing--6);min-height:244px"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-40 has-background-dim"></span><div class="wp-block-cover__inner-container">
				<!-- wp:heading {"level":4,"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"700"}},"textColor":"white","fontSize":"x-large"} -->
				<h4 class="wp-block-heading has-white-color has-text-color has-x-large-font-size" style="font-style:normal;font-weight:700;text-transform:uppercase"><?php echo esc_html__( 'Graphic Tees', 'aggressive-apparel' ); ?></h4>
				<!-- /wp:heading -->

				<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|3"}}}} -->
				<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--3)">
					<!-- wp:button {"className":"is-style-cta-small is-style-outline-on-dark"} -->
					<div class="wp-block-button is-style-cta-small is-style-outline-on-dark"><a class="wp-block-button__link wp-element-button" href="/product-category/tees"><?php echo esc_html__( 'Shop Tees', 'aggressive-apparel' ); ?></a></div>
					<!-- /wp:button -->
				</div>
				<!-- /wp:buttons -->
			</div></div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->

	<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|4"},"margin":{"top":"var:preset|spacing|4"}}}} -->
	<div class="wp-block-columns" style="margin-top:var(--wp--preset--spacing--4)">
		<!-- wp:column {"width":"33.33%"} -->
		<div class="wp-block-column" style="flex-basis:33.33%">
			<!-- wp:cover {"dimRatio":40,"overlayColor":"black","minHeight":244,"minHeightUnit":"px","contentPosition":"bottom left","isDark":true,"style":{"color":{"background":"var:preset|color|black"},"spacing":{"padding":{"top":"var:preset|spacing|6","bottom":"var:preset|spacing|6","left":"var:preset|spacing|6","right":"var:preset|spacing|6"},"margin":{"bottom":"var:preset|spacing|4"}}}} -->
			<div class="wp-block-cover is-dark has-custom-content-position is-position-bottom-left" style="background-color:var(--wp--preset--color--black);margin-bottom:var(--wp--preset--spacing--4);padding-top:var(--wp--preset--spacing--6);padding-right:var(--wp--preset--spacing--6);padding-bottom:var(--wp--preset--spacing--6);padding-left:var(--wp--preset--spacing--6);min-height:244px"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-40 has-background-dim"></span><div class="wp-block-cover__inner-container">
				<!-- wp:heading {"level":4,"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"700"}},"textColor":"white","fontSize":"x-large"} -->
				<h4 class="wp-block-heading has-white-color has-text-color has-x-large-font-size" style="font-style:normal;font-weight:700;text-transform:uppercase"><?php echo esc_html__( 'Joggers', 'aggressive-apparel' ); ?></h4>
				<!-- /wp:heading -->

				<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|3"}}}} -->
				<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--3)">
					<!-- wp:button {"className":"is-style-cta-small is-style-outline-on-dark"} -->
					<div class="wp-block-button is-style-cta-small is-style-outline-on-dark"><a class="wp-block-button__link wp-element-button" href="/product-category/joggers"><?php echo esc_html__( 'Shop Joggers', 'aggressive-apparel' ); ?></a></div>
					<!-- /wp:button -->
				</div>
				<!-- /wp:buttons -->
			</div></div>
			<!-- /wp:cover -->

			<!-- wp:cover {"dimRatio":40,"overlayColor":"black","minHeight":244,"minHeightUnit":"px","contentPosition":"bottom left","isDark":true,"style":{"color":{"background":"var:preset|color|black"},"spacing":{"padding":{"top":"var:preset|spacing|6","bottom":"var:preset|spacing|6","left":"var:preset|spacing|6","right":"var:preset|spacing|6"}}}} -->
			<div class="wp-block-cover is-dark has-custom-content-position is-position-bottom-left" style="background-color:var(--wp--preset--color--black);padding-top:var(--wp--preset--spacing--6);padding-right:var(--wp--preset--spacing--6);padding-bottom:var(--wp--preset--spacing--6);padding-left:var(--wp--preset--spacing--6);min-height:244px"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-40 has-background-dim"></span><div class="wp-block-cover__inner-container">
				<!-- wp:heading {"level":4,"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"700"}},"textColor":"white","fontSize":"x-large"} -->
				<h4 class="wp-block-heading has-white-color has-text-color has-x-large-font-size" style="font-style:normal;font-weight:700;text-transform:uppercase"><?php echo esc_html__( 'Caps & Hats', 'aggressive-apparel' ); ?></h4>
				<!-- /wp:heading -->

				<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|3"}}}} -->
				<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--3)">
					<!-- wp:button {"className":"is-style-cta-small is-style-outline-on-dark"} -->
					<div class="wp-block-button is-style-cta-small is-style-outline-on-dark"><a class="wp-block-button__link wp-element-button" href="/product-category/caps"><?php echo esc_html__( 'Shop Caps', 'aggressive-apparel' ); ?></a></div>
					<!-- /wp:button -->
				</div>
				<!-- /wp:buttons -->
			</div></div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"width":"66.66%"} -->
		<div class="wp-block-column" style="flex-basis:66.66%">
			<!-- wp:cover {"dimRatio":40,"overlayColor":"black","minHeight":500,"minHeightUnit":"px","contentPosition":"bottom left","isDark":true,"style":{"color":{"background":"var:preset|color|black"},"spacing":{"padding":{"top":"var:preset|spacing|8","bottom":"var:preset|spacing|8","left":"var:preset|spacing|8","right":"var:preset|spacing|8"}}}} -->
			<div class="wp-block-cover is-dark has-custom-content-position is-position-bottom-left" style="background-color:var(--wp--preset--color--black);padding-top:var(--wp--preset--spacing--8);padding-right:var(--wp--preset--spacing--8);padding-bottom:var(--wp--preset--spacing--8);padding-left:var(--wp--preset--spacing--8);min-height:500px"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-40 has-background-dim"></span><div class="wp-block-cover__inner-container">
				<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.1em","fontStyle":"normal","fontWeight":"600"}},"textColor":"accent","fontSize":"x-small"} -->
				<p class="has-accent-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.1em;text-transform:uppercase"><?php echo esc_html__( 'Limited Edition', 'aggressive-apparel' ); ?></p>
				<!-- /wp:paragraph -->

				<!-- wp:heading {"level":3,"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"700"}},"textColor":"white","fontSize":"fluid-xxx-large"} -->
				<h3 class="wp-block-heading has-white-color has-text-color has-fluid-xxx-large-font-size" style="font-style:normal;font-weight:700;text-transform:uppercase"><?php echo esc_html__( 'Collab Drop 001', 'aggressive-apparel' ); ?></h3>
				<!-- /wp:heading -->

				<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}}}} -->
				<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--6)">
					<!-- wp:button {"backgroundColor":"white","textColor":"black","style":{"typography":{"fontStyle":"normal","fontWeight":"600","textTransform":"uppercase","letterSpacing":"0.05em"},"spacing":{"padding":{"top":"var:preset|spacing|3","bottom":"var:preset|spacing|3","left":"var:preset|spacing|6","right":"var:preset|spacing|6"}}}} -->
					<div class="wp-block-button"><a class="wp-block-button__link has-black-color has-white-background-color has-text-color has-background wp-element-button" style="padding-top:var(--wp--preset--spacing--3);padding-right:var(--wp--preset--spacing--6);padding-bottom:var(--wp--preset--spacing--3);padding-left:var(--wp--preset--spacing--6);font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase" href="/collections/limited"><?php echo esc_html__( 'View Collection', 'aggressive-apparel' ); ?></a></div>
					<!-- /wp:button -->
				</div>
				<!-- /wp:buttons -->
			</div></div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
	<!-- /wp:aggressive-apparel/animate-on-scroll -->
</div>
<!-- /wp:group -->
