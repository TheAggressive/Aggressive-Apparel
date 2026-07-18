<?php
/**
 * Title: Product Scroll Rail
 * Description: Pinned horizontal scroll of oversized product covers — browse by scrolling down. Completely different rhythm from a product grid.
 * Slug: aggressive-apparel/product-scroll-rail
 * Categories: aggressive, aggressive-apparel, aggressive-products, aggressive-homepage
 * Keywords: horizontal scroll, rail, carousel, products, browse, pinned
 * Viewport Width: 1400
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|16","bottom":"0"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--16);padding-bottom:0">
	<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"end"},"style":{"spacing":{"blockGap":"var:preset|spacing|6","margin":{"bottom":"var:preset|spacing|10"}}}} -->
	<div class="wp-block-group" style="margin-bottom:var(--wp--preset--spacing--10)">
		<!-- wp:group {"layout":{"type":"constrained","contentSize":"560px"},"style":{"spacing":{"blockGap":"var:preset|spacing|3"}}} -->
		<div class="wp-block-group">
			<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.14em","fontStyle":"normal","fontWeight":"600"}},"textColor":"accent","fontSize":"x-small"} -->
			<p class="has-accent-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.14em;text-transform:uppercase"><?php echo esc_html__( 'Scroll the rack', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"fontSize":"fluid-xxxx-large"} -->
			<h2 class="wp-block-heading has-fluid-xxxx-large-font-size"><?php echo esc_html__( 'This week\'s cut', 'aggressive-apparel' ); ?></h2>
			<!-- /wp:heading -->
		</div>
		<!-- /wp:group -->

		<!-- wp:paragraph {"textColor":"foreground-muted","fontSize":"small"} -->
		<p class="has-foreground-muted-color has-text-color has-small-font-size"><?php echo esc_html__( 'Scroll down to move sideways.', 'aggressive-apparel' ); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->

<!-- wp:aggressive-apparel/horizontal-scroll {"align":"full","itemWidth":"72vw","speed":1.4,"showProgress":true,"activation":"top","desktopBehavior":"pinned","snapBehavior":"paged","swipeHintStyle":"label","backgroundColor":"black"} -->
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|6","bottom":"var:preset|spacing|6","left":"var:preset|spacing|4","right":"var:preset|spacing|4"}}}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--6);padding-right:var(--wp--preset--spacing--4);padding-bottom:var(--wp--preset--spacing--6);padding-left:var(--wp--preset--spacing--4)">
	<!-- wp:cover {"dimRatio":40,"overlayColor":"black","minHeight":70,"minHeightUnit":"vh","contentPosition":"bottom left","isDark":true,"style":{"color":{"background":"var:preset|color|black"},"spacing":{"padding":{"top":"var:preset|spacing|10","bottom":"var:preset|spacing|10","left":"var:preset|spacing|10","right":"var:preset|spacing|10"}}}} -->
	<div class="wp-block-cover is-dark has-custom-content-position is-position-bottom-left" style="background-color:var(--wp--preset--color--black);padding-top:var(--wp--preset--spacing--10);padding-right:var(--wp--preset--spacing--10);padding-bottom:var(--wp--preset--spacing--10);padding-left:var(--wp--preset--spacing--10);min-height:70vh"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-40 has-background-dim"></span><div class="wp-block-cover__inner-container">
		<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.12em","fontStyle":"normal","fontWeight":"600"}},"textColor":"accent","fontSize":"x-small"} -->
		<p class="has-accent-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.12em;text-transform:uppercase"><?php echo esc_html__( '01 — Hoodie', 'aggressive-apparel' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|3"}},"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"800"}},"textColor":"white","fontSize":"fluid-xxx-large"} -->
		<h3 class="wp-block-heading has-white-color has-text-color has-fluid-xxx-large-font-size" style="margin-top:var(--wp--preset--spacing--3);font-style:normal;font-weight:800;text-transform:uppercase"><?php echo esc_html__( 'Protocol Fleece', 'aggressive-apparel' ); ?></h3>
		<!-- /wp:heading -->

		<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}}}} -->
		<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--6)">
			<!-- wp:button {"className":"is-style-outline-on-dark is-style-cta-small"} -->
			<div class="wp-block-button is-style-outline-on-dark is-style-cta-small"><a class="wp-block-button__link wp-element-button" href="/shop"><?php echo esc_html__( 'Shop piece', 'aggressive-apparel' ); ?></a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
	</div></div>
	<!-- /wp:cover -->
</div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|6","bottom":"var:preset|spacing|6","left":"var:preset|spacing|4","right":"var:preset|spacing|4"}}}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--6);padding-right:var(--wp--preset--spacing--4);padding-bottom:var(--wp--preset--spacing--6);padding-left:var(--wp--preset--spacing--4)">
	<!-- wp:cover {"dimRatio":40,"overlayColor":"black","minHeight":70,"minHeightUnit":"vh","contentPosition":"bottom left","isDark":true,"style":{"color":{"background":"var:preset|color|black"},"spacing":{"padding":{"top":"var:preset|spacing|10","bottom":"var:preset|spacing|10","left":"var:preset|spacing|10","right":"var:preset|spacing|10"}}}} -->
	<div class="wp-block-cover is-dark has-custom-content-position is-position-bottom-left" style="background-color:var(--wp--preset--color--black);padding-top:var(--wp--preset--spacing--10);padding-right:var(--wp--preset--spacing--10);padding-bottom:var(--wp--preset--spacing--10);padding-left:var(--wp--preset--spacing--10);min-height:70vh"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-40 has-background-dim"></span><div class="wp-block-cover__inner-container">
		<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.12em","fontStyle":"normal","fontWeight":"600"}},"textColor":"accent","fontSize":"x-small"} -->
		<p class="has-accent-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.12em;text-transform:uppercase"><?php echo esc_html__( '02 — Tee', 'aggressive-apparel' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|3"}},"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"800"}},"textColor":"white","fontSize":"fluid-xxx-large"} -->
		<h3 class="wp-block-heading has-white-color has-text-color has-fluid-xxx-large-font-size" style="margin-top:var(--wp--preset--spacing--3);font-style:normal;font-weight:800;text-transform:uppercase"><?php echo esc_html__( 'Strike Jersey', 'aggressive-apparel' ); ?></h3>
		<!-- /wp:heading -->

		<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}}}} -->
		<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--6)">
			<!-- wp:button {"className":"is-style-outline-on-dark is-style-cta-small"} -->
			<div class="wp-block-button is-style-outline-on-dark is-style-cta-small"><a class="wp-block-button__link wp-element-button" href="/shop"><?php echo esc_html__( 'Shop piece', 'aggressive-apparel' ); ?></a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
	</div></div>
	<!-- /wp:cover -->
</div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|6","bottom":"var:preset|spacing|6","left":"var:preset|spacing|4","right":"var:preset|spacing|4"}}}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--6);padding-right:var(--wp--preset--spacing--4);padding-bottom:var(--wp--preset--spacing--6);padding-left:var(--wp--preset--spacing--4)">
	<!-- wp:cover {"dimRatio":40,"overlayColor":"black","minHeight":70,"minHeightUnit":"vh","contentPosition":"bottom left","isDark":true,"style":{"color":{"background":"var:preset|color|black"},"spacing":{"padding":{"top":"var:preset|spacing|10","bottom":"var:preset|spacing|10","left":"var:preset|spacing|10","right":"var:preset|spacing|10"}}}} -->
	<div class="wp-block-cover is-dark has-custom-content-position is-position-bottom-left" style="background-color:var(--wp--preset--color--black);padding-top:var(--wp--preset--spacing--10);padding-right:var(--wp--preset--spacing--10);padding-bottom:var(--wp--preset--spacing--10);padding-left:var(--wp--preset--spacing--10);min-height:70vh"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-40 has-background-dim"></span><div class="wp-block-cover__inner-container">
		<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.12em","fontStyle":"normal","fontWeight":"600"}},"textColor":"accent","fontSize":"x-small"} -->
		<p class="has-accent-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.12em;text-transform:uppercase"><?php echo esc_html__( '03 — Bottoms', 'aggressive-apparel' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|3"}},"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"800"}},"textColor":"white","fontSize":"fluid-xxx-large"} -->
		<h3 class="wp-block-heading has-white-color has-text-color has-fluid-xxx-large-font-size" style="margin-top:var(--wp--preset--spacing--3);font-style:normal;font-weight:800;text-transform:uppercase"><?php echo esc_html__( 'Cargo Track', 'aggressive-apparel' ); ?></h3>
		<!-- /wp:heading -->

		<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}}}} -->
		<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--6)">
			<!-- wp:button {"className":"is-style-outline-on-dark is-style-cta-small"} -->
			<div class="wp-block-button is-style-outline-on-dark is-style-cta-small"><a class="wp-block-button__link wp-element-button" href="/shop"><?php echo esc_html__( 'Shop piece', 'aggressive-apparel' ); ?></a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
	</div></div>
	<!-- /wp:cover -->
</div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|6","bottom":"var:preset|spacing|6","left":"var:preset|spacing|4","right":"var:preset|spacing|4"}}}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--6);padding-right:var(--wp--preset--spacing--4);padding-bottom:var(--wp--preset--spacing--6);padding-left:var(--wp--preset--spacing--4)">
	<!-- wp:cover {"dimRatio":40,"overlayColor":"black","minHeight":70,"minHeightUnit":"vh","contentPosition":"bottom left","isDark":true,"style":{"color":{"background":"var:preset|color|black"},"spacing":{"padding":{"top":"var:preset|spacing|10","bottom":"var:preset|spacing|10","left":"var:preset|spacing|10","right":"var:preset|spacing|10"}}}} -->
	<div class="wp-block-cover is-dark has-custom-content-position is-position-bottom-left" style="background-color:var(--wp--preset--color--black);padding-top:var(--wp--preset--spacing--10);padding-right:var(--wp--preset--spacing--10);padding-bottom:var(--wp--preset--spacing--10);padding-left:var(--wp--preset--spacing--10);min-height:70vh"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-40 has-background-dim"></span><div class="wp-block-cover__inner-container">
		<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.12em","fontStyle":"normal","fontWeight":"600"}},"textColor":"accent","fontSize":"x-small"} -->
		<p class="has-accent-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.12em;text-transform:uppercase"><?php echo esc_html__( '04 — Cap', 'aggressive-apparel' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|3"}},"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"800"}},"textColor":"white","fontSize":"fluid-xxx-large"} -->
		<h3 class="wp-block-heading has-white-color has-text-color has-fluid-xxx-large-font-size" style="margin-top:var(--wp--preset--spacing--3);font-style:normal;font-weight:800;text-transform:uppercase"><?php echo esc_html__( 'Mark Cap', 'aggressive-apparel' ); ?></h3>
		<!-- /wp:heading -->

		<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}}}} -->
		<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--6)">
			<!-- wp:button {"className":"is-style-outline-on-dark is-style-cta-small"} -->
			<div class="wp-block-button is-style-outline-on-dark is-style-cta-small"><a class="wp-block-button__link wp-element-button" href="/shop"><?php echo esc_html__( 'Shop piece', 'aggressive-apparel' ); ?></a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
	</div></div>
	<!-- /wp:cover -->
</div>
<!-- /wp:group -->
<!-- /wp:aggressive-apparel/horizontal-scroll -->

<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|16","bottom":"var:preset|spacing|24"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--16);padding-bottom:var(--wp--preset--spacing--24)">
	<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|10"}}},"fontSize":"fluid-xx-large"} -->
	<h3 class="wp-block-heading has-fluid-xx-large-font-size" style="margin-bottom:var(--wp--preset--spacing--10)"><?php echo esc_html__( 'Add from the rail', 'aggressive-apparel' ); ?></h3>
	<!-- /wp:heading -->

	<!-- wp:woocommerce/product-collection {"queryId":101,"query":{"perPage":4,"pages":0,"offset":0,"postType":"product","order":"desc","orderBy":"date","search":"","exclude":[],"inherit":false,"taxQuery":[],"isProductCollectionBlock":true,"woocommerceOnSale":false,"woocommerceStockStatus":["instock","outofstock","onbackorder"],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[]},"tagName":"div","displayLayout":{"type":"flex","columns":4,"shrinkColumns":true},"dimensions":{"widthType":"fill"},"collection":"woocommerce/product-collection/featured","queryContextIncludes":["collection"],"className":"is-style-commerce-grid"} -->
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
<!-- wp:woocommerce/product-price {"textAlign":"left","isDescendentOfQueryLoop":true,"className":"is-style-commerce-price","fontSize":"small","style":{"spacing":{"margin":{"top":"var:preset|spacing|1"}}}} /-->
<!-- /wp:woocommerce/product-template -->
</div>
<!-- /wp:woocommerce/product-collection -->
</div>
<!-- /wp:group -->
