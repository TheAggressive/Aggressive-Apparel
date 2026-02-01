<?php
/**
 * Title: Product Showcase Grid
 * Description: A responsive grid layout showcasing featured products in an attractive card format.
 * Slug: aggressive-apparel/product-showcase-grid
 * Categories: aggressive-apparel
 * Keywords: products, grid, showcase, cards, featured
 * Viewport Width: 1200
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:heading {"textAlign":"center","className":"product-showcase-title"} -->
<h2 class="wp-block-heading has-text-align-center product-showcase-title">Featured Products</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","className":"wp-block-paragraph product-showcase-description"} -->
<p class="has-text-align-center wp-block-paragraph product-showcase-description">Discover our latest collection of premium apparel designed for those who demand the best.</p>
<!-- /wp:paragraph -->

<!-- wp:woocommerce/product-collection {"queryId":7,"query":{"perPage":4,"pages":0,"offset":0,"postType":"product","order":"desc","orderBy":"date","search":"","exclude":[],"inherit":false,"taxQuery":[],"isProductCollectionBlock":true,"woocommerceOnSale":false,"woocommerceStockStatus":["instock","outofstock","onbackorder"],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[]},"tagName":"div","displayLayout":{"type":"flex","columns":4,"shrinkColumns":true},"dimensions":{"widthType":"fill"},"collection":"woocommerce/product-collection","queryContextIncludes":["collection"],"__privatePreviewState":{"isPreview":false,"previewMessage":"Actual products will vary depending on current WooCommerce products in the store."}} -->
<div class="wp-block-woocommerce-product-collection"></div>
<!-- /wp:woocommerce/product-collection -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"className":"view-all-button"} -->
<div class="wp-block-button view-all-button"><a class="wp-block-button__link wp-element-button" href="#">View All Products</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
