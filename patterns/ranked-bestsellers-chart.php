<?php
/**
 * Title: Ranked Bestsellers Chart
 * Description: Editorial numbered chart of top sellers — list rhythm with one product card per row, not a uniform grid.
 * Slug: aggressive-apparel/ranked-bestsellers-chart
 * Categories: aggressive, aggressive-apparel, aggressive-products, aggressive-homepage
 * Keywords: ranked, chart, bestsellers, list, editorial, top products
 * Viewport Width: 1200
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|24","bottom":"var:preset|spacing|24"},"margin":{"top":"0","bottom":"0"}}},"backgroundColor":"surface","layout":{"type":"constrained","contentSize":"1000px"}} -->
<div class="wp-block-group alignfull has-surface-background-color has-background" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--24);padding-bottom:var(--wp--preset--spacing--24)">
	<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.14em","fontStyle":"normal","fontWeight":"600"}},"textColor":"accent","fontSize":"x-small"} -->
	<p class="has-accent-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.14em;text-transform:uppercase">This week</p>
	<!-- /wp:paragraph -->

	<!-- wp:heading {"style":{"spacing":{"margin":{"top":"var:preset|spacing|3","bottom":"var:preset|spacing|14"}}},"fontSize":"fluid-xxxx-large"} -->
	<h2 class="wp-block-heading has-fluid-xxxx-large-font-size" style="margin-top:var(--wp--preset--spacing--3);margin-bottom:var(--wp--preset--spacing--14)">Most wanted</h2>
	<!-- /wp:heading -->

	<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|8","bottom":"var:preset|spacing|8"}},"border":{"top":{"color":"var:preset|color|border","width":"1px"}}},"layout":{"type":"default"}} -->
	<div class="wp-block-group" style="border-top-color:var(--wp--preset--color--border);border-top-width:1px;padding-top:var(--wp--preset--spacing--8);padding-bottom:var(--wp--preset--spacing--8)">
		<!-- wp:columns {"verticalAlignment":"center","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|8"}}}} -->
		<div class="wp-block-columns are-vertically-aligned-center">
			<!-- wp:column {"verticalAlignment":"center","width":"12%"} -->
			<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:12%">
				<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"900","lineHeight":"1"}},"textColor":"accent","fontSize":"fluid-xxxx-large"} -->
				<p class="has-accent-color has-text-color has-fluid-xxxx-large-font-size" style="font-style:normal;font-weight:900;line-height:1">01</p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:column -->

			<!-- wp:column {"verticalAlignment":"center","width":"48%"} -->
			<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:48%">
				<!-- wp:heading {"level":3,"style":{"typography":{"fontStyle":"normal","fontWeight":"700"}},"fontSize":"x-large"} -->
				<h3 class="wp-block-heading has-x-large-font-size" style="font-style:normal;font-weight:700">Hand-pick #1 in the collection</h3>
				<!-- /wp:heading -->

				<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|2"}},"typography":{"lineHeight":"1.6"}},"textColor":"foreground-muted","fontSize":"small"} -->
				<p class="has-foreground-muted-color has-text-color has-small-font-size" style="margin-top:var(--wp--preset--spacing--2);line-height:1.6">Replace this row's product collection with a single hand-picked SKU.</p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:column -->

			<!-- wp:column {"verticalAlignment":"center","width":"40%"} -->
			<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:40%">
				<!-- wp:woocommerce/product-collection {"queryId":111,"query":{"perPage":1,"pages":1,"offset":0,"postType":"product","order":"desc","orderBy":"popularity","search":"","exclude":[],"inherit":false,"taxQuery":[],"isProductCollectionBlock":true,"woocommerceOnSale":false,"woocommerceStockStatus":["instock","outofstock","onbackorder"],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[]},"tagName":"div","displayLayout":{"type":"flex","columns":1,"shrinkColumns":true},"dimensions":{"widthType":"fill"},"collection":"woocommerce/product-collection/best-sellers","queryContextIncludes":["collection"],"className":"is-style-commerce-grid"} -->
<div class="wp-block-woocommerce-product-collection is-style-commerce-grid">
<!-- wp:woocommerce/product-template {"className":"is-style-commerce-cards"} -->
<!-- wp:woocommerce/product-image {"aspectRatio":"1","imageSizing":"thumbnail","isDescendentOfQueryLoop":true,"className":"is-style-product-frame"} -->
<!-- wp:woocommerce/product-sale-badge {"align":"right"} /-->
<!-- /wp:woocommerce/product-image -->
<!-- wp:post-title {"textAlign":"left","level":4,"isLink":true,"style":{"typography":{"fontStyle":"normal","fontWeight":"600"},"spacing":{"margin":{"top":"var:preset|spacing|2","bottom":"0"}}},"fontSize":"small","__woocommerceNamespace":"woocommerce/product-collection/product-title"} /-->
<!-- wp:woocommerce/product-price {"textAlign":"left","isDescendentOfQueryLoop":true,"className":"is-style-commerce-price","fontSize":"small"} /-->
<!-- /wp:woocommerce/product-template -->
</div>
<!-- /wp:woocommerce/product-collection -->
			</div>
			<!-- /wp:column -->
		</div>
		<!-- /wp:columns -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|8","bottom":"var:preset|spacing|8"}},"border":{"top":{"color":"var:preset|color|border","width":"1px"}}},"layout":{"type":"default"}} -->
	<div class="wp-block-group" style="border-top-color:var(--wp--preset--color--border);border-top-width:1px;padding-top:var(--wp--preset--spacing--8);padding-bottom:var(--wp--preset--spacing--8)">
		<!-- wp:columns {"verticalAlignment":"center","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|8"}}}} -->
		<div class="wp-block-columns are-vertically-aligned-center">
			<!-- wp:column {"verticalAlignment":"center","width":"12%"} -->
			<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:12%">
				<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"900","lineHeight":"1"}},"textColor":"foreground-muted","fontSize":"fluid-xxxx-large"} -->
				<p class="has-foreground-muted-color has-text-color has-fluid-xxxx-large-font-size" style="font-style:normal;font-weight:900;line-height:1">02</p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:column -->

			<!-- wp:column {"verticalAlignment":"center","width":"48%"} -->
			<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:48%">
				<!-- wp:heading {"level":3,"style":{"typography":{"fontStyle":"normal","fontWeight":"700"}},"fontSize":"x-large"} -->
				<h3 class="wp-block-heading has-x-large-font-size" style="font-style:normal;font-weight:700">Hand-pick #2 in the collection</h3>
				<!-- /wp:heading -->

				<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|2"}},"typography":{"lineHeight":"1.6"}},"textColor":"foreground-muted","fontSize":"small"} -->
				<p class="has-foreground-muted-color has-text-color has-small-font-size" style="margin-top:var(--wp--preset--spacing--2);line-height:1.6">Offset this query or pick a specific product so the chart stays intentional.</p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:column -->

			<!-- wp:column {"verticalAlignment":"center","width":"40%"} -->
			<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:40%">
				<!-- wp:woocommerce/product-collection {"queryId":112,"query":{"perPage":1,"pages":1,"offset":1,"postType":"product","order":"desc","orderBy":"popularity","search":"","exclude":[],"inherit":false,"taxQuery":[],"isProductCollectionBlock":true,"woocommerceOnSale":false,"woocommerceStockStatus":["instock","outofstock","onbackorder"],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[]},"tagName":"div","displayLayout":{"type":"flex","columns":1,"shrinkColumns":true},"dimensions":{"widthType":"fill"},"collection":"woocommerce/product-collection/best-sellers","queryContextIncludes":["collection"],"className":"is-style-commerce-grid"} -->
<div class="wp-block-woocommerce-product-collection is-style-commerce-grid">
<!-- wp:woocommerce/product-template {"className":"is-style-commerce-cards"} -->
<!-- wp:woocommerce/product-image {"aspectRatio":"1","imageSizing":"thumbnail","isDescendentOfQueryLoop":true,"className":"is-style-product-frame"} -->
<!-- wp:woocommerce/product-sale-badge {"align":"right"} /-->
<!-- /wp:woocommerce/product-image -->
<!-- wp:post-title {"textAlign":"left","level":4,"isLink":true,"style":{"typography":{"fontStyle":"normal","fontWeight":"600"},"spacing":{"margin":{"top":"var:preset|spacing|2","bottom":"0"}}},"fontSize":"small","__woocommerceNamespace":"woocommerce/product-collection/product-title"} /-->
<!-- wp:woocommerce/product-price {"textAlign":"left","isDescendentOfQueryLoop":true,"className":"is-style-commerce-price","fontSize":"small"} /-->
<!-- /wp:woocommerce/product-template -->
</div>
<!-- /wp:woocommerce/product-collection -->
			</div>
			<!-- /wp:column -->
		</div>
		<!-- /wp:columns -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|8","bottom":"var:preset|spacing|8"}},"border":{"top":{"color":"var:preset|color|border","width":"1px"},"bottom":{"color":"var:preset|color|border","width":"1px"}}},"layout":{"type":"default"}} -->
	<div class="wp-block-group" style="border-top-color:var(--wp--preset--color--border);border-top-width:1px;border-bottom-color:var(--wp--preset--color--border);border-bottom-width:1px;padding-top:var(--wp--preset--spacing--8);padding-bottom:var(--wp--preset--spacing--8)">
		<!-- wp:columns {"verticalAlignment":"center","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|8"}}}} -->
		<div class="wp-block-columns are-vertically-aligned-center">
			<!-- wp:column {"verticalAlignment":"center","width":"12%"} -->
			<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:12%">
				<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"900","lineHeight":"1"}},"textColor":"foreground-muted","fontSize":"fluid-xxxx-large"} -->
				<p class="has-foreground-muted-color has-text-color has-fluid-xxxx-large-font-size" style="font-style:normal;font-weight:900;line-height:1">03</p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:column -->

			<!-- wp:column {"verticalAlignment":"center","width":"48%"} -->
			<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:48%">
				<!-- wp:heading {"level":3,"style":{"typography":{"fontStyle":"normal","fontWeight":"700"}},"fontSize":"x-large"} -->
				<h3 class="wp-block-heading has-x-large-font-size" style="font-style:normal;font-weight:700">Hand-pick #3 in the collection</h3>
				<!-- /wp:heading -->

				<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|2"}},"typography":{"lineHeight":"1.6"}},"textColor":"foreground-muted","fontSize":"small"} -->
				<p class="has-foreground-muted-color has-text-color has-small-font-size" style="margin-top:var(--wp--preset--spacing--2);line-height:1.6">Keep the chart short — three to five rows reads stronger than a full grid.</p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:column -->

			<!-- wp:column {"verticalAlignment":"center","width":"40%"} -->
			<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:40%">
				<!-- wp:woocommerce/product-collection {"queryId":113,"query":{"perPage":1,"pages":1,"offset":2,"postType":"product","order":"desc","orderBy":"popularity","search":"","exclude":[],"inherit":false,"taxQuery":[],"isProductCollectionBlock":true,"woocommerceOnSale":false,"woocommerceStockStatus":["instock","outofstock","onbackorder"],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[]},"tagName":"div","displayLayout":{"type":"flex","columns":1,"shrinkColumns":true},"dimensions":{"widthType":"fill"},"collection":"woocommerce/product-collection/best-sellers","queryContextIncludes":["collection"],"className":"is-style-commerce-grid"} -->
<div class="wp-block-woocommerce-product-collection is-style-commerce-grid">
<!-- wp:woocommerce/product-template {"className":"is-style-commerce-cards"} -->
<!-- wp:woocommerce/product-image {"aspectRatio":"1","imageSizing":"thumbnail","isDescendentOfQueryLoop":true,"className":"is-style-product-frame"} -->
<!-- wp:woocommerce/product-sale-badge {"align":"right"} /-->
<!-- /wp:woocommerce/product-image -->
<!-- wp:post-title {"textAlign":"left","level":4,"isLink":true,"style":{"typography":{"fontStyle":"normal","fontWeight":"600"},"spacing":{"margin":{"top":"var:preset|spacing|2","bottom":"0"}}},"fontSize":"small","__woocommerceNamespace":"woocommerce/product-collection/product-title"} /-->
<!-- wp:woocommerce/product-price {"textAlign":"left","isDescendentOfQueryLoop":true,"className":"is-style-commerce-price","fontSize":"small"} /-->
<!-- /wp:woocommerce/product-template -->
</div>
<!-- /wp:woocommerce/product-collection -->
			</div>
			<!-- /wp:column -->
		</div>
		<!-- /wp:columns -->
	</div>
	<!-- /wp:group -->

	<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"left"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|12"}}}} -->
	<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--12)">
		<!-- wp:button {"backgroundColor":"foreground","textColor":"surface","style":{"typography":{"fontStyle":"normal","fontWeight":"700","textTransform":"uppercase","letterSpacing":"0.06em"},"spacing":{"padding":{"top":"var:preset|spacing|3","bottom":"var:preset|spacing|3","left":"var:preset|spacing|8","right":"var:preset|spacing|8"}}}} -->
		<div class="wp-block-button"><a class="wp-block-button__link has-surface-color has-foreground-background-color has-text-color has-background wp-element-button" style="padding-top:var(--wp--preset--spacing--3);padding-right:var(--wp--preset--spacing--8);padding-bottom:var(--wp--preset--spacing--3);padding-left:var(--wp--preset--spacing--8);font-style:normal;font-weight:700;letter-spacing:0.06em;text-transform:uppercase" href="/shop?orderby=popularity">Shop all bestsellers</a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
