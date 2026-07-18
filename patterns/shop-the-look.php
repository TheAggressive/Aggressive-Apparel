<?php
/**
 * Title: Shop the Look
 * Description: Editorial lookbook section with large cover image, caption, and curated product collection to shop the outfit.
 * Slug: aggressive-apparel/shop-the-look
 * Categories: aggressive, aggressive-apparel, aggressive-products, aggressive-pdp
 * Keywords: shop the look, editorial, lookbook, outfit, curated, products
 * Viewport Width: 1400
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:cover {"dimRatio":35,"overlayColor":"black","minHeight":75,"minHeightUnit":"vh","contentPosition":"bottom left","align":"full","isDark":true,"style":{"color":{"background":"var:preset|color|black"},"spacing":{"padding":{"top":"var:preset|spacing|12","bottom":"var:preset|spacing|12","left":"var:preset|spacing|12","right":"var:preset|spacing|12"},"margin":{"top":"0","bottom":"0"}}}} -->
<div class="wp-block-cover alignfull is-dark has-custom-content-position is-position-bottom-left" style="background-color:var(--wp--preset--color--black);margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--12);padding-right:var(--wp--preset--spacing--12);padding-bottom:var(--wp--preset--spacing--12);padding-left:var(--wp--preset--spacing--12);min-height:75vh"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-35 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:group {"layout":{"type":"constrained","contentSize":"520px","justifyContent":"left"}} -->
<div class="wp-block-group">
	<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.15em","fontStyle":"normal","fontWeight":"600"}},"textColor":"accent","fontSize":"x-small"} -->
	<p class="has-accent-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.15em;text-transform:uppercase"><?php echo esc_html__( 'Look 07', 'aggressive-apparel' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:heading {"level":2,"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}},"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"800","lineHeight":"1.05"}},"textColor":"white","fontSize":"fluid-xxxx-large"} -->
	<h2 class="wp-block-heading has-white-color has-text-color has-fluid-xxxx-large-font-size" style="margin-top:var(--wp--preset--spacing--4);font-style:normal;font-weight:800;line-height:1.05;text-transform:uppercase"><?php echo esc_html__( 'Night Shift Uniform', 'aggressive-apparel' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}},"typography":{"lineHeight":"1.6"}},"textColor":"white","fontSize":"medium"} -->
	<p class="has-white-color has-text-color has-medium-font-size" style="margin-top:var(--wp--preset--spacing--4);line-height:1.6"><?php echo esc_html__( 'Layered for late runs and early mornings — heavyweight hoodie, tapered cargos, and the cap that ties it together.', 'aggressive-apparel' ); ?></p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group --></div></div>
<!-- /wp:cover -->

<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|24","bottom":"var:preset|spacing|24"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--24);padding-bottom:var(--wp--preset--spacing--24)">
	<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
	<div class="wp-block-group">
		<!-- wp:heading {"fontSize":"fluid-xxx-large"} -->
		<h2 class="wp-block-heading has-fluid-xxx-large-font-size"><?php echo esc_html__( 'Shop This Look', 'aggressive-apparel' ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"600","textTransform":"uppercase","letterSpacing":"0.05em"}},"fontSize":"x-small"} -->
		<p class="has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase"><a href="/shop"><?php echo esc_html__( 'View All Pieces &rarr;', 'aggressive-apparel' ); ?></a></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:spacer {"height":"var:preset|spacing|10"} -->
	<div style="height:var(--wp--preset--spacing--10)" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->

	<!-- wp:woocommerce/product-collection {"queryId":24,"query":{"perPage":3,"pages":0,"offset":0,"postType":"product","order":"desc","orderBy":"popularity","search":"","exclude":[],"inherit":false,"taxQuery":[],"isProductCollectionBlock":true,"woocommerceOnSale":false,"woocommerceStockStatus":["instock","outofstock","onbackorder"],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[]},"tagName":"div","displayLayout":{"type":"flex","columns":3,"shrinkColumns":true},"dimensions":{"widthType":"fill"},"collection":"woocommerce/product-collection/best-sellers","queryContextIncludes":["collection"],"className":"is-style-commerce-grid"} -->
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
