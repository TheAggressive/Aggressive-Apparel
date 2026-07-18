<?php
/**
 * Title: On Sale Row
 * Description: High-impact sale section with accent header band and WooCommerce on-sale product collection.
 * Slug: aggressive-apparel/on-sale-row
 * Categories: aggressive, aggressive-apparel, aggressive-products, aggressive-conversion
 * Keywords: sale, discount, on sale, promotion, products, conversion
 * Viewport Width: 1200
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|12","bottom":"var:preset|spacing|12"},"margin":{"top":"0","bottom":"0"}}},"backgroundColor":"accent","textColor":"white","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-white-color has-accent-background-color has-text-color has-background" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--12);padding-bottom:var(--wp--preset--spacing--12)">
	<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
	<div class="wp-block-group">
		<!-- wp:heading {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.05em","fontStyle":"normal","fontWeight":"800"}},"textColor":"white","fontSize":"fluid-xxxx-large"} -->
		<h2 class="wp-block-heading has-white-color has-text-color has-fluid-xxxx-large-font-size" style="font-style:normal;font-weight:800;letter-spacing:0.05em;text-transform:uppercase"><?php echo esc_html__( 'Up to 40% Off', 'aggressive-apparel' ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"textColor":"white","fontSize":"medium"} -->
		<p class="has-white-color has-text-color has-medium-font-size"><?php echo esc_html__( 'Selected styles. Limited time.', 'aggressive-apparel' ); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|16","bottom":"var:preset|spacing|24"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--16);padding-bottom:var(--wp--preset--spacing--24)">
	<!-- wp:woocommerce/product-collection {"queryId":26,"query":{"perPage":4,"pages":0,"offset":0,"postType":"product","order":"desc","orderBy":"date","search":"","exclude":[],"inherit":false,"taxQuery":[],"isProductCollectionBlock":true,"woocommerceOnSale":true,"woocommerceStockStatus":["instock","outofstock","onbackorder"],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[]},"tagName":"div","displayLayout":{"type":"flex","columns":4,"shrinkColumns":true},"dimensions":{"widthType":"fill"},"collection":"woocommerce/product-collection/on-sale","queryContextIncludes":["collection"],"className":"is-style-commerce-grid"} -->
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

	<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|12"}}}} -->
	<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--12)">
		<!-- wp:button {"backgroundColor":"black","textColor":"white","style":{"typography":{"fontStyle":"normal","fontWeight":"600","textTransform":"uppercase","letterSpacing":"0.05em"},"spacing":{"padding":{"top":"var:preset|spacing|3","bottom":"var:preset|spacing|3","left":"var:preset|spacing|10","right":"var:preset|spacing|10"}}}} -->
		<div class="wp-block-button"><a class="wp-block-button__link has-white-color has-black-background-color has-text-color has-background wp-element-button" style="padding-top:var(--wp--preset--spacing--3);padding-right:var(--wp--preset--spacing--10);padding-bottom:var(--wp--preset--spacing--3);padding-left:var(--wp--preset--spacing--10);font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase" href="/shop?on_sale=true"><?php echo esc_html__( 'Shop All Sale', 'aggressive-apparel' ); ?></a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
