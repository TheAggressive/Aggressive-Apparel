<?php
/**
 * Title: Hero Product Spotlight
 * Description: Split hero spotlighting a single product with story copy left, tall product image right, and featured product collection.
 * Slug: aggressive-apparel/hero-product-spotlight
 * Categories: aggressive, aggressive-apparel, aggressive-homepage, aggressive-products
 * Keywords: hero, product, spotlight, featured, single product, launch
 * Viewport Width: 1400
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"0","bottom":"0"},"margin":{"top":"0","bottom":"0"}}},"backgroundColor":"surface","layout":{"type":"default"}} -->
<div class="wp-block-group alignfull has-surface-background-color has-background" style="margin-top:0;margin-bottom:0;padding-top:0;padding-bottom:0">
	<!-- wp:columns {"verticalAlignment":"center","isStackedOnMobile":true,"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|16"}}}} -->
	<div class="wp-block-columns are-vertically-aligned-center is-stacked-on-mobile">
		<!-- wp:column {"verticalAlignment":"center","width":"45%","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|12","right":"var:preset|spacing|12"}}}} -->
		<div class="wp-block-column is-vertically-aligned-center" style="padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--12);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--12);flex-basis:45%">
			<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.15em","fontStyle":"normal","fontWeight":"600"}},"textColor":"accent","fontSize":"x-small"} -->
			<p class="has-accent-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.15em;text-transform:uppercase"><?php echo esc_html__( 'Product Spotlight', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"level":1,"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}},"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"800","lineHeight":"1.05"}},"fontSize":"fluid-xxxxxx-large"} -->
			<h1 class="wp-block-heading has-fluid-xxxxxx-large-font-size" style="margin-top:var(--wp--preset--spacing--4);font-style:normal;font-weight:800;line-height:1.05;text-transform:uppercase">Heavyweight<br>Protocol Hoodie</h1>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}},"typography":{"fontStyle":"normal","fontWeight":"600","textTransform":"uppercase","letterSpacing":"0.05em"}},"fontSize":"large"} -->
			<p class="has-large-font-size" style="margin-top:var(--wp--preset--spacing--6);font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase"><?php echo esc_html__( 'From $98', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}},"typography":{"lineHeight":"1.7"}},"textColor":"foreground-muted","fontSize":"medium"} -->
			<p class="has-foreground-muted-color has-text-color has-medium-font-size" style="margin-top:var(--wp--preset--spacing--6);line-height:1.7"><?php echo esc_html__( '450gsm French terry. Oversized fit. Reinforced kangaroo pocket. The hoodie that started it all — re-engineered for SS26.', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|10"}}}} -->
			<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--10)">
				<!-- wp:button {"backgroundColor":"accent","textColor":"white","style":{"typography":{"fontStyle":"normal","fontWeight":"700","textTransform":"uppercase","letterSpacing":"0.05em"},"spacing":{"padding":{"top":"var:preset|spacing|4","bottom":"var:preset|spacing|4","left":"var:preset|spacing|10","right":"var:preset|spacing|10"}}}} -->
				<div class="wp-block-button"><a class="wp-block-button__link has-white-color has-accent-background-color has-text-color has-background wp-element-button" style="padding-top:var(--wp--preset--spacing--4);padding-right:var(--wp--preset--spacing--10);padding-bottom:var(--wp--preset--spacing--4);padding-left:var(--wp--preset--spacing--10);font-style:normal;font-weight:700;letter-spacing:0.05em;text-transform:uppercase" href="/shop"><?php echo esc_html__( 'Shop Now', 'aggressive-apparel' ); ?></a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->

			<!-- wp:spacer {"height":"var:preset|spacing|12"} -->
			<div style="height:var(--wp--preset--spacing--12)" aria-hidden="true" class="wp-block-spacer"></div>
			<!-- /wp:spacer -->

			<!-- wp:woocommerce/product-collection {"queryId":22,"query":{"perPage":1,"pages":0,"offset":0,"postType":"product","order":"desc","orderBy":"date","search":"","exclude":[],"inherit":false,"taxQuery":[],"isProductCollectionBlock":true,"woocommerceOnSale":false,"woocommerceStockStatus":["instock","outofstock","onbackorder"],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[]},"tagName":"div","displayLayout":{"type":"flex","columns":1,"shrinkColumns":true},"dimensions":{"widthType":"fill"},"collection":"woocommerce/product-collection/featured","queryContextIncludes":["collection"],"className":"is-style-commerce-grid"} -->
<div class="wp-block-woocommerce-product-collection is-style-commerce-grid">
<!-- wp:woocommerce/product-template {"className":"is-style-commerce-cards"} -->
<!-- wp:woocommerce/product-image {"aspectRatio":"3/4","imageSizing":"thumbnail","isDescendentOfQueryLoop":true,"className":"is-style-product-frame"} -->
<!-- wp:woocommerce/product-sale-badge {"align":"right"} /-->
<!-- /wp:woocommerce/product-image -->
<!-- wp:aggressive-apparel/product-color-swatches {"swatchSize":"sm","maxVisible":5,"swatchAlignment":"left"} /-->
<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"center"},"style":{"spacing":{"blockGap":"var:preset|spacing|2","margin":{"top":"var:preset|spacing|3"}}}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--3)">
<!-- wp:post-title {"textAlign":"left","level":3,"isLink":true,"style":{"typography":{"fontStyle":"normal","fontWeight":"600","lineHeight":"1.4"},"spacing":{"margin":{"top":"0","bottom":"0"}}},"layout":{"selfStretch":"fill","flexSize":null},"fontSize":"small","__woocommerceNamespace":"woocommerce/product-collection/product-title"} /-->
<!-- wp:aggressive-apparel/wishlist-button {"iconOnly":true,"showLabel":false,"alignment":"right"} /-->
</div>
<!-- /wp:group -->
<!-- wp:aggressive-apparel/product-rating {"textAlign":"left"} /-->
<!-- wp:woocommerce/product-price {"textAlign":"left","isDescendentOfQueryLoop":true,"className":"is-style-commerce-price","fontSize":"small","style":{"spacing":{"margin":{"top":"var:preset|spacing|1"}}}} /-->
<!-- wp:woocommerce/product-button {"textAlign":"left","isDescendentOfQueryLoop":true,"fontSize":"small","style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}}}} /-->
<!-- /wp:woocommerce/product-template -->
</div>
<!-- /wp:woocommerce/product-collection -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"verticalAlignment":"stretch","width":"55%"} -->
		<div class="wp-block-column is-vertically-aligned-stretch" style="flex-basis:55%">
			<!-- wp:cover {"dimRatio":10,"overlayColor":"black","minHeight":90,"minHeightUnit":"vh","contentPosition":"center center","isDark":true,"style":{"color":{"background":"var:preset|color|surface-elevated"}}} -->
			<div class="wp-block-cover is-dark" style="background-color:var(--wp--preset--color--surface-elevated);min-height:90vh"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-10 has-background-dim"></span><div class="wp-block-cover__inner-container">
				<!-- wp:paragraph {"align":"center","fontSize":"large"} -->
				<p class="has-text-align-center has-large-font-size"></p>
				<!-- /wp:paragraph -->
			</div></div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
