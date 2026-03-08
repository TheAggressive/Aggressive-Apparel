<?php
/**
 * Title: Product Cross-Sells
 * Description: "You May Also Like" or "Complete the Look" product recommendation section.
 * Slug: aggressive-apparel/product-cross-sells
 * Categories: aggressive, aggressive-apparel, aggressive-products
 * Keywords: cross-sell, upsell, related, products, recommendations, complete the look
 * Viewport Width: 1200
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"},"margin":{"top":"0","bottom":"0"}},"border":{"top":{"color":"#e5e7eb","width":"1px"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="border-top-color:#e5e7eb;border-top-width:1px;margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20)">
	<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"},"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|12"}}}} -->
	<div class="wp-block-group" style="margin-bottom:var(--wp--preset--spacing--12)">
		<!-- wp:heading {"fontSize":"fluid-xxx-large"} -->
		<h2 class="wp-block-heading has-fluid-xxx-large-font-size">Complete the Look</h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"600","textTransform":"uppercase","letterSpacing":"0.05em"}},"fontSize":"x-small"} -->
		<p class="has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase"><a href="/shop">View All &rarr;</a></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:woocommerce/product-collection {"queryId":10,"query":{"perPage":4,"pages":0,"offset":0,"postType":"product","order":"desc","orderBy":"popularity","search":"","exclude":[],"inherit":false,"taxQuery":{},"isProductCollectionBlock":true,"woocommerceAttributes":[]},"displayLayout":{"type":"flex","columns":4},"collection":"woocommerce/product-collection/best-sellers"} -->
		<!-- wp:woocommerce/product-template -->
			<!-- wp:woocommerce/product-image {"aspectRatio":"3/4","imageSizing":"thumbnail","style":{"border":{"radius":"8px"}}} /-->
			<!-- wp:post-title {"textAlign":"left","level":3,"isLink":true,"style":{"typography":{"fontStyle":"normal","fontWeight":"600","lineHeight":"1.4"}},"fontSize":"small"} /-->
			<!-- wp:woocommerce/product-price {"textAlign":"left","fontSize":"small"} /-->
		<!-- /wp:woocommerce/product-template -->
	<!-- /wp:woocommerce/product-collection -->
</div>
<!-- /wp:group -->
