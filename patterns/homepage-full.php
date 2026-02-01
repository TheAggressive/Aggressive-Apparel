<?php
/**
 * Title: Full Homepage
 * Description: Complete homepage composition with hero, sale banner, categories, products, brand story, values, testimonials, newsletter, and social feed.
 * Slug: aggressive-apparel/homepage-full
 * Categories: aggressive-apparel, aggressive-homepage
 * Keywords: homepage, full page, landing, storefront, complete
 * Viewport Width: 1400
 *
 * @package Aggressive_Apparel
 */

?>
<!-- HERO -->
<!-- wp:cover {"dimRatio":50,"overlayColor":"black","minHeight":80,"minHeightUnit":"vh","contentPosition":"center center","align":"full","isDark":true} -->
<div class="wp-block-cover alignfull is-dark" style="min-height:80vh">
	<span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-50 has-background-dim"></span>
	<div class="wp-block-cover__inner-container">
		<!-- wp:group {"layout":{"type":"constrained","contentSize":"800px"}} -->
		<div class="wp-block-group">
			<!-- wp:heading {"textAlign":"center","level":1,"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"700"}},"textColor":"white","fontSize":"fluid-xxxxxxx-large"} -->
			<h1 class="wp-block-heading has-text-align-center has-white-color has-text-color has-fluid-xxxxxxx-large-font-size" style="font-style:normal;font-weight:700;text-transform:uppercase">New Collection Drop</h1>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}}},"textColor":"white","fontSize":"large"} -->
			<p class="has-text-align-center has-white-color has-text-color has-large-font-size" style="margin-top:var(--wp--preset--spacing--6)">Premium apparel engineered for those who refuse to blend in.</p>
			<!-- /wp:paragraph -->

			<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|10"}}}} -->
			<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--10)">
				<!-- wp:button {"backgroundColor":"red","textColor":"white","style":{"typography":{"fontStyle":"normal","fontWeight":"600","textTransform":"uppercase","letterSpacing":"0.05em"},"spacing":{"padding":{"top":"var:preset|spacing|4","bottom":"var:preset|spacing|4","left":"var:preset|spacing|10","right":"var:preset|spacing|10"}}}} -->
				<div class="wp-block-button"><a class="wp-block-button__link has-white-color has-red-background-color has-text-color has-background wp-element-button" style="padding-top:var(--wp--preset--spacing--4);padding-right:var(--wp--preset--spacing--10);padding-bottom:var(--wp--preset--spacing--4);padding-left:var(--wp--preset--spacing--10);font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase" href="/shop">Shop New Arrivals</a></div>
				<!-- /wp:button -->

				<!-- wp:button {"className":"is-style-outline","style":{"typography":{"fontStyle":"normal","fontWeight":"600","textTransform":"uppercase","letterSpacing":"0.05em"},"spacing":{"padding":{"top":"var:preset|spacing|4","bottom":"var:preset|spacing|4","left":"var:preset|spacing|10","right":"var:preset|spacing|10"}},"border":{"color":"#ffffff"}}} -->
				<div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-border-color wp-element-button" style="border-color:#ffffff;padding-top:var(--wp--preset--spacing--4);padding-right:var(--wp--preset--spacing--10);padding-bottom:var(--wp--preset--spacing--4);padding-left:var(--wp--preset--spacing--10);font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase" href="/lookbook">View Lookbook</a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:group -->
	</div>
</div>
<!-- /wp:cover -->

<!-- SALE BANNER -->
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|10","bottom":"var:preset|spacing|10"},"margin":{"top":"0","bottom":"0"}}},"backgroundColor":"red","textColor":"white","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-white-color has-red-background-color has-text-color has-background" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--10);padding-bottom:var(--wp--preset--spacing--10)">
	<!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.05em","fontStyle":"normal","fontWeight":"700"}},"textColor":"white","fontSize":"large"} -->
	<p class="has-text-align-center has-white-color has-text-color has-large-font-size" style="font-style:normal;font-weight:700;letter-spacing:0.05em;text-transform:uppercase">Free shipping on orders over $100 — Use code <strong>AGGRESSIVE</strong> for 15% off</p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- FEATURED CATEGORIES -->
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|24","bottom":"var:preset|spacing|24"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
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
			<!-- wp:cover {"dimRatio":40,"overlayColor":"black","minHeight":350,"minHeightUnit":"px","contentPosition":"bottom center","isDark":true,"style":{"color":{"background":"#1a1a1a"}}} -->
			<div class="wp-block-cover is-dark has-custom-content-position is-position-bottom-center" style="background-color:#1a1a1a;min-height:350px"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-40 has-background-dim"></span><div class="wp-block-cover__inner-container">
				<!-- wp:heading {"textAlign":"center","level":3,"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"700"}},"textColor":"white","fontSize":"xx-large"} -->
				<h3 class="wp-block-heading has-text-align-center has-white-color has-text-color has-xx-large-font-size" style="font-style:normal;font-weight:700;text-transform:uppercase">Hoodies</h3>
				<!-- /wp:heading -->
			</div></div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:cover {"dimRatio":40,"overlayColor":"black","minHeight":350,"minHeightUnit":"px","contentPosition":"bottom center","isDark":true,"style":{"color":{"background":"#2a2a2a"}}} -->
			<div class="wp-block-cover is-dark has-custom-content-position is-position-bottom-center" style="background-color:#2a2a2a;min-height:350px"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-40 has-background-dim"></span><div class="wp-block-cover__inner-container">
				<!-- wp:heading {"textAlign":"center","level":3,"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"700"}},"textColor":"white","fontSize":"xx-large"} -->
				<h3 class="wp-block-heading has-text-align-center has-white-color has-text-color has-xx-large-font-size" style="font-style:normal;font-weight:700;text-transform:uppercase">Tees</h3>
				<!-- /wp:heading -->
			</div></div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:cover {"dimRatio":40,"overlayColor":"black","minHeight":350,"minHeightUnit":"px","contentPosition":"bottom center","isDark":true,"style":{"color":{"background":"#3a3a3a"}}} -->
			<div class="wp-block-cover is-dark has-custom-content-position is-position-bottom-center" style="background-color:#3a3a3a;min-height:350px"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-40 has-background-dim"></span><div class="wp-block-cover__inner-container">
				<!-- wp:heading {"textAlign":"center","level":3,"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"700"}},"textColor":"white","fontSize":"xx-large"} -->
				<h3 class="wp-block-heading has-text-align-center has-white-color has-text-color has-xx-large-font-size" style="font-style:normal;font-weight:700;text-transform:uppercase">Bottoms</h3>
				<!-- /wp:heading -->
			</div></div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->

<!-- NEW ARRIVALS -->
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|24","bottom":"var:preset|spacing|24"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--24);padding-bottom:var(--wp--preset--spacing--24)">
	<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
	<div class="wp-block-group">
		<!-- wp:heading {"fontSize":"fluid-xxxx-large"} -->
		<h2 class="wp-block-heading has-fluid-xxxx-large-font-size">New Arrivals</h2>
		<!-- /wp:heading -->

		<!-- wp:buttons -->
		<div class="wp-block-buttons">
			<!-- wp:button {"backgroundColor":"red","textColor":"white","style":{"typography":{"fontStyle":"normal","fontWeight":"600","textTransform":"uppercase","letterSpacing":"0.05em"},"spacing":{"padding":{"top":"var:preset|spacing|3","bottom":"var:preset|spacing|3","left":"var:preset|spacing|8","right":"var:preset|spacing|8"}}}} -->
			<div class="wp-block-button"><a class="wp-block-button__link has-white-color has-red-background-color has-text-color has-background wp-element-button" style="padding-top:var(--wp--preset--spacing--3);padding-right:var(--wp--preset--spacing--8);padding-bottom:var(--wp--preset--spacing--3);padding-left:var(--wp--preset--spacing--8);font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase" href="/shop">View All</a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
	</div>
	<!-- /wp:group -->

	<!-- wp:spacer {"height":"var:preset|spacing|8"} -->
	<div style="height:var(--wp--preset--spacing--8)" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->

	<!-- wp:woocommerce/product-collection {"queryId":9,"query":{"perPage":4,"pages":0,"offset":0,"postType":"product","order":"desc","orderBy":"date","search":"","exclude":[],"inherit":false,"taxQuery":[],"isProductCollectionBlock":true,"woocommerceOnSale":false,"woocommerceStockStatus":["instock","outofstock","onbackorder"],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[]},"tagName":"div","displayLayout":{"type":"flex","columns":4,"shrinkColumns":true},"dimensions":{"widthType":"fill"},"collection":"woocommerce/product-collection","queryContextIncludes":["collection"],"__privatePreviewState":{"isPreview":false,"previewMessage":"Actual products will vary depending on current WooCommerce products in the store."}} -->
	<div class="wp-block-woocommerce-product-collection"></div>
	<!-- /wp:woocommerce/product-collection -->
</div>
<!-- /wp:group -->

<!-- BRAND STORY -->
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|24","bottom":"var:preset|spacing|24"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--24);padding-bottom:var(--wp--preset--spacing--24)">
	<!-- wp:columns {"verticalAlignment":"center","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|16"}}}} -->
	<div class="wp-block-columns are-vertically-aligned-center">
		<!-- wp:column {"verticalAlignment":"center","width":"55%"} -->
		<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:55%">
			<!-- wp:cover {"dimRatio":0,"minHeight":450,"minHeightUnit":"px","isDark":false,"style":{"color":{"background":"#e5e7eb"}}} -->
			<div class="wp-block-cover is-light" style="background-color:#e5e7eb;min-height:450px"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim"></span><div class="wp-block-cover__inner-container">
				<!-- wp:paragraph {"align":"center","fontSize":"medium"} -->
				<p class="has-text-align-center has-medium-font-size">Lifestyle image placeholder</p>
				<!-- /wp:paragraph -->
			</div></div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"verticalAlignment":"center","width":"45%"} -->
		<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:45%">
			<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.1em","fontStyle":"normal","fontWeight":"600"}},"textColor":"red","fontSize":"x-small"} -->
			<p class="has-red-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.1em;text-transform:uppercase">Our Story</p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}}},"fontSize":"fluid-xxxx-large"} -->
			<h2 class="wp-block-heading has-fluid-xxxx-large-font-size" style="margin-top:var(--wp--preset--spacing--4)">Built for Those Who Push Limits</h2>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}},"typography":{"lineHeight":"1.8"}},"fontSize":"medium"} -->
			<p class="has-medium-font-size" style="margin-top:var(--wp--preset--spacing--6);line-height:1.8">Aggressive Apparel was born from the belief that what you wear should match your ambition. Every piece is crafted with premium materials and built to perform.</p>
			<!-- /wp:paragraph -->

			<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|8"}}}} -->
			<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--8)">
				<!-- wp:button {"backgroundColor":"black","textColor":"white","style":{"typography":{"fontStyle":"normal","fontWeight":"600","textTransform":"uppercase","letterSpacing":"0.05em"},"spacing":{"padding":{"top":"var:preset|spacing|3","bottom":"var:preset|spacing|3","left":"var:preset|spacing|8","right":"var:preset|spacing|8"}}}} -->
				<div class="wp-block-button"><a class="wp-block-button__link has-white-color has-black-background-color has-text-color has-background wp-element-button" style="padding-top:var(--wp--preset--spacing--3);padding-right:var(--wp--preset--spacing--8);padding-bottom:var(--wp--preset--spacing--3);padding-left:var(--wp--preset--spacing--8);font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase" href="/about">Learn More</a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->

<!-- BRAND VALUES -->
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|24","bottom":"var:preset|spacing|24"},"margin":{"top":"0","bottom":"0"}}},"backgroundColor":"black","textColor":"white","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-white-color has-black-background-color has-text-color has-background" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--24);padding-bottom:var(--wp--preset--spacing--24)">
	<!-- wp:heading {"textAlign":"center","textColor":"white","fontSize":"fluid-xxxx-large"} -->
	<h2 class="wp-block-heading has-text-align-center has-white-color has-text-color has-fluid-xxxx-large-font-size">Why Aggressive?</h2>
	<!-- /wp:heading -->

	<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|12"},"margin":{"top":"var:preset|spacing|14"}}}} -->
	<div class="wp-block-columns" style="margin-top:var(--wp--preset--spacing--14)">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"textAlign":"center","level":3,"textColor":"red","fontSize":"fluid-xxxxxx-large"} -->
			<h3 class="wp-block-heading has-text-align-center has-red-color has-text-color has-fluid-xxxxxx-large-font-size">01</h3>
			<!-- /wp:heading -->

			<!-- wp:heading {"textAlign":"center","level":4,"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"700"},"spacing":{"margin":{"top":"var:preset|spacing|4"}}},"textColor":"white","fontSize":"x-large"} -->
			<h4 class="wp-block-heading has-text-align-center has-white-color has-text-color has-x-large-font-size" style="margin-top:var(--wp--preset--spacing--4);font-style:normal;font-weight:700;text-transform:uppercase">Premium Materials</h4>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}}},"textColor":"white","fontSize":"medium"} -->
			<p class="has-text-align-center has-white-color has-text-color has-medium-font-size" style="margin-top:var(--wp--preset--spacing--4)">Every thread, every stitch — sourced from the best.</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"textAlign":"center","level":3,"textColor":"red","fontSize":"fluid-xxxxxx-large"} -->
			<h3 class="wp-block-heading has-text-align-center has-red-color has-text-color has-fluid-xxxxxx-large-font-size">02</h3>
			<!-- /wp:heading -->

			<!-- wp:heading {"textAlign":"center","level":4,"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"700"},"spacing":{"margin":{"top":"var:preset|spacing|4"}}},"textColor":"white","fontSize":"x-large"} -->
			<h4 class="wp-block-heading has-text-align-center has-white-color has-text-color has-x-large-font-size" style="margin-top:var(--wp--preset--spacing--4);font-style:normal;font-weight:700;text-transform:uppercase">Bold Design</h4>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}}},"textColor":"white","fontSize":"medium"} -->
			<p class="has-text-align-center has-white-color has-text-color has-medium-font-size" style="margin-top:var(--wp--preset--spacing--4)">Stand out from the crowd with pieces that demand attention.</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"textAlign":"center","level":3,"textColor":"red","fontSize":"fluid-xxxxxx-large"} -->
			<h3 class="wp-block-heading has-text-align-center has-red-color has-text-color has-fluid-xxxxxx-large-font-size">03</h3>
			<!-- /wp:heading -->

			<!-- wp:heading {"textAlign":"center","level":4,"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"700"},"spacing":{"margin":{"top":"var:preset|spacing|4"}}},"textColor":"white","fontSize":"x-large"} -->
			<h4 class="wp-block-heading has-text-align-center has-white-color has-text-color has-x-large-font-size" style="margin-top:var(--wp--preset--spacing--4);font-style:normal;font-weight:700;text-transform:uppercase">Built to Last</h4>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}}},"textColor":"white","fontSize":"medium"} -->
			<p class="has-text-align-center has-white-color has-text-color has-medium-font-size" style="margin-top:var(--wp--preset--spacing--4)">Performance-tested and street-proven gear.</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->

<!-- TESTIMONIALS -->
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|24","bottom":"var:preset|spacing|24"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--24);padding-bottom:var(--wp--preset--spacing--24)">
	<!-- wp:heading {"textAlign":"center","fontSize":"fluid-xxxx-large"} -->
	<h2 class="wp-block-heading has-text-align-center has-fluid-xxxx-large-font-size">What Our Customers Say</h2>
	<!-- /wp:heading -->

	<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|8"},"margin":{"top":"var:preset|spacing|14"}}}} -->
	<div class="wp-block-columns" style="margin-top:var(--wp--preset--spacing--14)">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|10","bottom":"var:preset|spacing|10","left":"var:preset|spacing|8","right":"var:preset|spacing|8"}},"border":{"width":"1px","color":"#e5e7eb"}}} -->
			<div class="wp-block-group has-border-color" style="border-color:#e5e7eb;border-width:1px;padding-top:var(--wp--preset--spacing--10);padding-right:var(--wp--preset--spacing--8);padding-bottom:var(--wp--preset--spacing--10);padding-left:var(--wp--preset--spacing--8)">
				<!-- wp:paragraph {"align":"center","fontSize":"medium"} -->
				<p class="has-text-align-center has-medium-font-size">"The quality is unmatched. Every piece feels premium and the fit is perfect."</p>
				<!-- /wp:paragraph -->

				<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}},"typography":{"fontStyle":"normal","fontWeight":"600"}},"fontSize":"small"} -->
				<p class="has-text-align-center has-small-font-size" style="margin-top:var(--wp--preset--spacing--6);font-style:normal;font-weight:600">— Sarah M.</p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|10","bottom":"var:preset|spacing|10","left":"var:preset|spacing|8","right":"var:preset|spacing|8"}},"border":{"width":"1px","color":"#e5e7eb"}}} -->
			<div class="wp-block-group has-border-color" style="border-color:#e5e7eb;border-width:1px;padding-top:var(--wp--preset--spacing--10);padding-right:var(--wp--preset--spacing--8);padding-bottom:var(--wp--preset--spacing--10);padding-left:var(--wp--preset--spacing--8)">
				<!-- wp:paragraph {"align":"center","fontSize":"medium"} -->
				<p class="has-text-align-center has-medium-font-size">"Been shopping here for 2 years. Customer service is exceptional and products exceed expectations."</p>
				<!-- /wp:paragraph -->

				<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}},"typography":{"fontStyle":"normal","fontWeight":"600"}},"fontSize":"small"} -->
				<p class="has-text-align-center has-small-font-size" style="margin-top:var(--wp--preset--spacing--6);font-style:normal;font-weight:600">— Mike R.</p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|10","bottom":"var:preset|spacing|10","left":"var:preset|spacing|8","right":"var:preset|spacing|8"}},"border":{"width":"1px","color":"#e5e7eb"}}} -->
			<div class="wp-block-group has-border-color" style="border-color:#e5e7eb;border-width:1px;padding-top:var(--wp--preset--spacing--10);padding-right:var(--wp--preset--spacing--8);padding-bottom:var(--wp--preset--spacing--10);padding-left:var(--wp--preset--spacing--8)">
				<!-- wp:paragraph {"align":"center","fontSize":"medium"} -->
				<p class="has-text-align-center has-medium-font-size">"Style and comfort level is incredible. I get compliments every time I wear something from this brand."</p>
				<!-- /wp:paragraph -->

				<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}},"typography":{"fontStyle":"normal","fontWeight":"600"}},"fontSize":"small"} -->
				<p class="has-text-align-center has-small-font-size" style="margin-top:var(--wp--preset--spacing--6);font-style:normal;font-weight:600">— Emma L.</p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->

<!-- NEWSLETTER -->
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"},"margin":{"top":"0","bottom":"0"}}},"backgroundColor":"black","textColor":"white","layout":{"type":"constrained","contentSize":"600px"}} -->
<div class="wp-block-group alignfull has-white-color has-black-background-color has-text-color has-background" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20)">
	<!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.1em","fontStyle":"normal","fontWeight":"600"}},"textColor":"red","fontSize":"x-small"} -->
	<p class="has-text-align-center has-red-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.1em;text-transform:uppercase">Join the Movement</p>
	<!-- /wp:paragraph -->

	<!-- wp:heading {"textAlign":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}}},"textColor":"white","fontSize":"fluid-xxxx-large"} -->
	<h2 class="wp-block-heading has-text-align-center has-white-color has-text-color has-fluid-xxxx-large-font-size" style="margin-top:var(--wp--preset--spacing--4)">Get 15% Off Your First Order</h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}}},"textColor":"white","fontSize":"medium"} -->
	<p class="has-text-align-center has-white-color has-text-color has-medium-font-size" style="margin-top:var(--wp--preset--spacing--4)">Sign up for exclusive drops, early access to sales, and insider-only content.</p>
	<!-- /wp:paragraph -->

	<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|10"}}}} -->
	<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--10)">
		<!-- wp:button {"backgroundColor":"red","textColor":"white","style":{"typography":{"fontStyle":"normal","fontWeight":"600","textTransform":"uppercase","letterSpacing":"0.05em"},"spacing":{"padding":{"top":"var:preset|spacing|4","bottom":"var:preset|spacing|4","left":"var:preset|spacing|12","right":"var:preset|spacing|12"}}}} -->
		<div class="wp-block-button"><a class="wp-block-button__link has-white-color has-red-background-color has-text-color has-background wp-element-button" style="padding-top:var(--wp--preset--spacing--4);padding-right:var(--wp--preset--spacing--12);padding-bottom:var(--wp--preset--spacing--4);padding-left:var(--wp--preset--spacing--12);font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase" href="/my-account">Subscribe Now</a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group -->

<!-- SOCIAL FEED -->
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|24","bottom":"0"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--24);padding-bottom:0">
	<!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.1em","fontStyle":"normal","fontWeight":"600"}},"textColor":"red","fontSize":"x-small"} -->
	<p class="has-text-align-center has-red-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.1em;text-transform:uppercase">On the Gram</p>
	<!-- /wp:paragraph -->

	<!-- wp:heading {"textAlign":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|4","bottom":"var:preset|spacing|14"}}},"fontSize":"fluid-xxxx-large"} -->
	<h2 class="wp-block-heading has-text-align-center has-fluid-xxxx-large-font-size" style="margin-top:var(--wp--preset--spacing--4);margin-bottom:var(--wp--preset--spacing--14)">Follow @AggressiveApparel</h2>
	<!-- /wp:heading -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"0","bottom":"0"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:0;padding-bottom:0">
	<!-- wp:columns {"isStackedOnMobile":false,"style":{"spacing":{"blockGap":{"left":"0","top":"0"}}}} -->
	<div class="wp-block-columns is-not-stacked-on-mobile">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:cover {"dimRatio":0,"minHeight":200,"minHeightUnit":"px","isDark":false,"style":{"color":{"background":"#1a1a1a"}}} -->
			<div class="wp-block-cover is-light" style="background-color:#1a1a1a;min-height:200px"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center"></p><!-- /wp:paragraph --></div></div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:cover {"dimRatio":0,"minHeight":200,"minHeightUnit":"px","isDark":false,"style":{"color":{"background":"#2a2a2a"}}} -->
			<div class="wp-block-cover is-light" style="background-color:#2a2a2a;min-height:200px"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center"></p><!-- /wp:paragraph --></div></div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:cover {"dimRatio":0,"minHeight":200,"minHeightUnit":"px","isDark":false,"style":{"color":{"background":"#3a3a3a"}}} -->
			<div class="wp-block-cover is-light" style="background-color:#3a3a3a;min-height:200px"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center"></p><!-- /wp:paragraph --></div></div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:cover {"dimRatio":0,"minHeight":200,"minHeightUnit":"px","isDark":false,"style":{"color":{"background":"#4a4a4a"}}} -->
			<div class="wp-block-cover is-light" style="background-color:#4a4a4a;min-height:200px"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center"></p><!-- /wp:paragraph --></div></div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:cover {"dimRatio":0,"minHeight":200,"minHeightUnit":"px","isDark":false,"style":{"color":{"background":"#333333"}}} -->
			<div class="wp-block-cover is-light" style="background-color:#333333;min-height:200px"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center"></p><!-- /wp:paragraph --></div></div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:cover {"dimRatio":0,"minHeight":200,"minHeightUnit":"px","isDark":false,"style":{"color":{"background":"#222222"}}} -->
			<div class="wp-block-cover is-light" style="background-color:#222222;min-height:200px"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center"></p><!-- /wp:paragraph --></div></div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
