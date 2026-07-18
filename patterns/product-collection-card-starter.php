<?php
/**
 * Title: Product Collection — Card Starter
 * Description: Forkable WooCommerce Product Collection with the full Aggressive card template (swatches, wishlist, rating, price, button). Change the query, columns, and card blocks — start here instead of a blank collection.
 * Slug: aggressive-apparel/product-collection-card-starter
 * Categories: aggressive, aggressive-apparel, aggressive-products, aggressive-shop
 * Keywords: product collection, starter, cards, grid, woocommerce, template, fork
 * Viewport Width: 1200
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|16","bottom":"var:preset|spacing|16"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--16);padding-bottom:var(--wp--preset--spacing--16)">
	<!-- wp:heading {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|10"}}},"fontSize":"fluid-xxx-large"} -->
	<h2 class="wp-block-heading has-fluid-xxx-large-font-size" style="margin-bottom:var(--wp--preset--spacing--10)"><?php echo esc_html__( 'Product Collection', 'aggressive-apparel' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:woocommerce/product-collection {"queryId":90,"query":{"perPage":4,"pages":0,"offset":0,"postType":"product","order":"desc","orderBy":"date","search":"","exclude":[],"inherit":false,"taxQuery":[],"isProductCollectionBlock":true,"woocommerceOnSale":false,"woocommerceStockStatus":["instock","outofstock","onbackorder"],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[]},"tagName":"div","displayLayout":{"type":"flex","columns":4,"shrinkColumns":true},"dimensions":{"widthType":"fill"},"collection":"woocommerce/product-collection/new-arrivals","queryContextIncludes":["collection"],"className":"is-style-commerce-grid"} -->
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
<!-- /wp:group -->
