<?php
/**
 * Title: Mega Menu Panel
 * Description: Content panel for nav mega menus — link columns, featured collection cover, and a compact product row.
 * Slug: aggressive-apparel/mega-menu-panel
 * Categories: aggressive, aggressive-apparel, navigation, aggressive-products
 * Keywords: mega menu, navigation, dropdown, featured, shop, panel
 * Viewport Width: 1100
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|8","bottom":"var:preset|spacing|8","left":"var:preset|spacing|8","right":"var:preset|spacing|8"},"blockGap":"var:preset|spacing|10"}},"layout":{"type":"constrained","contentSize":"1100px"}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--8);padding-right:var(--wp--preset--spacing--8);padding-bottom:var(--wp--preset--spacing--8);padding-left:var(--wp--preset--spacing--8)">
	<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|10"}}}} -->
	<div class="wp-block-columns">
		<!-- wp:column {"width":"22%"} -->
		<div class="wp-block-column" style="flex-basis:22%">
			<!-- wp:heading {"level":4,"style":{"typography":{"fontStyle":"normal","fontWeight":"700","textTransform":"uppercase","letterSpacing":"0.08em"},"spacing":{"margin":{"bottom":"var:preset|spacing|4"}}},"fontSize":"x-small"} -->
			<h4 class="wp-block-heading has-x-small-font-size" style="margin-bottom:var(--wp--preset--spacing--4);font-style:normal;font-weight:700;letter-spacing:0.08em;text-transform:uppercase"><?php echo esc_html__( 'Shop', 'aggressive-apparel' ); ?></h4>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|2","bottom":"var:preset|spacing|2"}}},"fontSize":"small"} -->
			<p class="has-small-font-size" style="margin-top:var(--wp--preset--spacing--2);margin-bottom:var(--wp--preset--spacing--2)"><a href="/product-category/hoodies"><?php echo esc_html__( 'Hoodies', 'aggressive-apparel' ); ?></a></p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|2","bottom":"var:preset|spacing|2"}}},"fontSize":"small"} -->
			<p class="has-small-font-size" style="margin-top:var(--wp--preset--spacing--2);margin-bottom:var(--wp--preset--spacing--2)"><a href="/product-category/tees"><?php echo esc_html__( 'Tees', 'aggressive-apparel' ); ?></a></p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|2","bottom":"var:preset|spacing|2"}}},"fontSize":"small"} -->
			<p class="has-small-font-size" style="margin-top:var(--wp--preset--spacing--2);margin-bottom:var(--wp--preset--spacing--2)"><a href="/product-category/bottoms"><?php echo esc_html__( 'Bottoms', 'aggressive-apparel' ); ?></a></p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|2","bottom":"var:preset|spacing|2"}}},"fontSize":"small"} -->
			<p class="has-small-font-size" style="margin-top:var(--wp--preset--spacing--2);margin-bottom:var(--wp--preset--spacing--2)"><a href="/product-category/accessories"><?php echo esc_html__( 'Accessories', 'aggressive-apparel' ); ?></a></p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}},"typography":{"fontStyle":"normal","fontWeight":"600"}},"fontSize":"small"} -->
			<p class="has-small-font-size" style="margin-top:var(--wp--preset--spacing--4);font-style:normal;font-weight:600"><a href="/shop"><?php echo esc_html__( 'Shop all →', 'aggressive-apparel' ); ?></a></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"width":"22%"} -->
		<div class="wp-block-column" style="flex-basis:22%">
			<!-- wp:heading {"level":4,"style":{"typography":{"fontStyle":"normal","fontWeight":"700","textTransform":"uppercase","letterSpacing":"0.08em"},"spacing":{"margin":{"bottom":"var:preset|spacing|4"}}},"fontSize":"x-small"} -->
			<h4 class="wp-block-heading has-x-small-font-size" style="margin-bottom:var(--wp--preset--spacing--4);font-style:normal;font-weight:700;letter-spacing:0.08em;text-transform:uppercase"><?php echo esc_html__( 'Collections', 'aggressive-apparel' ); ?></h4>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|2","bottom":"var:preset|spacing|2"}}},"fontSize":"small"} -->
			<p class="has-small-font-size" style="margin-top:var(--wp--preset--spacing--2);margin-bottom:var(--wp--preset--spacing--2)"><a href="/shop?orderby=date"><?php echo esc_html__( 'New Arrivals', 'aggressive-apparel' ); ?></a></p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|2","bottom":"var:preset|spacing|2"}}},"fontSize":"small"} -->
			<p class="has-small-font-size" style="margin-top:var(--wp--preset--spacing--2);margin-bottom:var(--wp--preset--spacing--2)"><a href="/shop?orderby=popularity"><?php echo esc_html__( 'Best Sellers', 'aggressive-apparel' ); ?></a></p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|2","bottom":"var:preset|spacing|2"}}},"fontSize":"small"} -->
			<p class="has-small-font-size" style="margin-top:var(--wp--preset--spacing--2);margin-bottom:var(--wp--preset--spacing--2)"><a href="/product-category/nightfall"><?php echo esc_html__( 'Nightfall', 'aggressive-apparel' ); ?></a></p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|2","bottom":"var:preset|spacing|2"}}},"fontSize":"small"} -->
			<p class="has-small-font-size" style="margin-top:var(--wp--preset--spacing--2);margin-bottom:var(--wp--preset--spacing--2)"><a href="/shop?on_sale=true"><?php echo esc_html__( 'Sale', 'aggressive-apparel' ); ?></a></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"width":"56%"} -->
		<div class="wp-block-column" style="flex-basis:56%">
			<!-- wp:cover {"dimRatio":40,"overlayColor":"black","minHeight":220,"minHeightUnit":"px","contentPosition":"bottom left","isDark":true,"style":{"color":{"background":"var:preset|color|black"},"spacing":{"padding":{"top":"var:preset|spacing|6","bottom":"var:preset|spacing|6","left":"var:preset|spacing|6","right":"var:preset|spacing|6"}}}} -->
			<div class="wp-block-cover is-dark has-custom-content-position is-position-bottom-left" style="background-color:var(--wp--preset--color--black);min-height:220px;padding-top:var(--wp--preset--spacing--6);padding-right:var(--wp--preset--spacing--6);padding-bottom:var(--wp--preset--spacing--6);padding-left:var(--wp--preset--spacing--6)"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-40 has-background-dim"></span><div class="wp-block-cover__inner-container">
				<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.1em","fontStyle":"normal","fontWeight":"600"}},"textColor":"accent","fontSize":"x-small"} -->
				<p class="has-accent-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.1em;text-transform:uppercase"><?php echo esc_html__( 'Featured', 'aggressive-apparel' ); ?></p>
				<!-- /wp:paragraph -->

				<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|2"}},"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"700"}},"textColor":"white","fontSize":"x-large"} -->
				<h3 class="wp-block-heading has-white-color has-text-color has-x-large-font-size" style="margin-top:var(--wp--preset--spacing--2);font-style:normal;font-weight:700;text-transform:uppercase"><?php echo esc_html__( 'Nightfall Drop', 'aggressive-apparel' ); ?></h3>
				<!-- /wp:heading -->

				<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}}}} -->
				<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--4)">
					<!-- wp:button {"className":"is-style-cta-small is-style-outline-on-dark"} -->
					<div class="wp-block-button is-style-cta-small is-style-outline-on-dark"><a class="wp-block-button__link wp-element-button" href="/product-category/nightfall"><?php echo esc_html__( 'Shop the drop', 'aggressive-apparel' ); ?></a></div>
					<!-- /wp:button -->
				</div>
				<!-- /wp:buttons -->
			</div></div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->

	<!-- wp:separator {"backgroundColor":"border","className":"is-style-wide"} -->
	<hr class="wp-block-separator has-text-color has-border-color has-alpha-channel-opacity has-border-background-color has-background is-style-wide"/>
	<!-- /wp:separator -->

	<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
	<div class="wp-block-group">
		<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.08em","fontStyle":"normal","fontWeight":"700"}},"fontSize":"x-small"} -->
		<p class="has-x-small-font-size" style="font-style:normal;font-weight:700;letter-spacing:0.08em;text-transform:uppercase"><?php echo esc_html__( 'Trending now', 'aggressive-apparel' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"fontSize":"small"} -->
		<p class="has-small-font-size"><a href="/shop?orderby=popularity"><?php echo esc_html__( 'View all →', 'aggressive-apparel' ); ?></a></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:woocommerce/product-collection {"queryId":82,"query":{"perPage":3,"pages":0,"offset":0,"postType":"product","order":"desc","orderBy":"popularity","search":"","exclude":[],"inherit":false,"taxQuery":[],"isProductCollectionBlock":true,"woocommerceOnSale":false,"woocommerceStockStatus":["instock","outofstock","onbackorder"],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[]},"tagName":"div","displayLayout":{"type":"flex","columns":3,"shrinkColumns":true},"dimensions":{"widthType":"fill"},"collection":"woocommerce/product-collection/best-sellers","queryContextIncludes":["collection"],"className":"is-style-commerce-grid"} -->
<div class="wp-block-woocommerce-product-collection is-style-commerce-grid">
<!-- wp:woocommerce/product-template {"className":"is-style-commerce-cards"} -->
<!-- wp:woocommerce/product-image {"aspectRatio":"3/4","imageSizing":"thumbnail","isDescendentOfQueryLoop":true,"className":"is-style-product-frame"} -->
<!-- wp:woocommerce/product-sale-badge {"align":"right"} /-->
<!-- /wp:woocommerce/product-image -->
<!-- wp:aggressive-apparel/product-color-swatches {"swatchSize":"xs","maxVisible":4,"swatchAlignment":"left"} /-->
<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"center"},"style":{"spacing":{"blockGap":"var:preset|spacing|2","margin":{"top":"var:preset|spacing|3"}}}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--3)">
<!-- wp:post-title {"textAlign":"left","level":3,"isLink":true,"style":{"typography":{"fontStyle":"normal","fontWeight":"600","lineHeight":"1.4"},"spacing":{"margin":{"top":"0","bottom":"0"}}},"layout":{"selfStretch":"fill","flexSize":null},"fontSize":"small","__woocommerceNamespace":"woocommerce/product-collection/product-title"} /-->
<!-- wp:aggressive-apparel/wishlist-button {"iconOnly":true,"showLabel":false,"alignment":"right"} /-->
</div>
<!-- /wp:group -->
<!-- wp:woocommerce/product-price {"textAlign":"left","isDescendentOfQueryLoop":true,"className":"is-style-commerce-price","fontSize":"small","style":{"spacing":{"margin":{"top":"var:preset|spacing|1"}}}} /-->
<!-- /wp:woocommerce/product-template -->
</div>
<!-- /wp:woocommerce/product-collection -->
</div>
<!-- /wp:group -->
