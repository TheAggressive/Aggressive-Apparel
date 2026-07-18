<?php
/**
 * Title: Cart Upsell Strip
 * Description: Compact cart drawer style upsell section with heading and two-column WooCommerce product recommendations.
 * Slug: aggressive-apparel/cart-upsell-strip
 * Categories: aggressive, aggressive-apparel, aggressive-conversion, aggressive-products, aggressive-pdp
 * Keywords: cart, upsell, cross-sell, drawer, complete order, products
 * Viewport Width: 800
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|8","bottom":"var:preset|spacing|8","left":"var:preset|spacing|6","right":"var:preset|spacing|6"},"margin":{"top":"0","bottom":"0"}},"border":{"top":{"color":"var:preset|color|border","width":"1px"}}},"backgroundColor":"surface-elevated","layout":{"type":"constrained","contentSize":"720px"}} -->
<div class="wp-block-group alignfull has-surface-elevated-background-color has-background" style="border-top-color:var(--wp--preset--color--border);border-top-width:1px;margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--8);padding-right:var(--wp--preset--spacing--6);padding-bottom:var(--wp--preset--spacing--8);padding-left:var(--wp--preset--spacing--6)">
	<!-- wp:heading {"level":3,"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"700","letterSpacing":"0.05em"}},"fontSize":"small"} -->
	<h3 class="wp-block-heading has-small-font-size" style="font-style:normal;font-weight:700;letter-spacing:0.05em;text-transform:uppercase"><?php echo esc_html__( 'Complete Your Order', 'aggressive-apparel' ); ?></h3>
	<!-- /wp:heading -->

	<!-- wp:spacer {"height":"var:preset|spacing|6"} -->
	<div style="height:var(--wp--preset--spacing--6)" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->

	<!-- wp:woocommerce/product-collection {"queryId":71,"query":{"perPage":2,"pages":0,"offset":0,"postType":"product","order":"desc","orderBy":"popularity","search":"","exclude":[],"inherit":false,"taxQuery":[],"isProductCollectionBlock":true,"woocommerceOnSale":false,"woocommerceStockStatus":["instock","outofstock","onbackorder"],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[]},"tagName":"div","displayLayout":{"type":"flex","columns":2,"shrinkColumns":true},"dimensions":{"widthType":"fill"},"collection":"woocommerce/product-collection/best-sellers","queryContextIncludes":["collection"],"className":"is-style-commerce-grid"} -->
<div class="wp-block-woocommerce-product-collection is-style-commerce-grid">
<!-- wp:woocommerce/product-template {"className":"is-style-commerce-cards"} -->
<!-- wp:woocommerce/product-image {"aspectRatio":"3/4","imageSizing":"thumbnail","isDescendentOfQueryLoop":true,"className":"is-style-product-frame"} -->
<!-- wp:woocommerce/product-sale-badge {"align":"right"} /-->
<!-- /wp:woocommerce/product-image -->
<!-- wp:aggressive-apparel/product-color-swatches {"swatchSize":"sm","maxVisible":4,"swatchAlignment":"left"} /-->
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
