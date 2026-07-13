<?php
/**
 * Title: Design System Preview
 * Description: Living preview of design system primitives, type roles, commerce states, and WooCommerce block styling. Dev/QA only — hidden from the pattern inserter.
 * Slug: aggressive-apparel/design-system-preview
 * Categories: aggressive, aggressive-apparel
 * Keywords: design system, tokens, preview, buttons, badges, woocommerce
 * Viewport Width: 1400
 * Inserter: no
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","right":"var:preset|spacing|8","bottom":"var:preset|spacing|20","left":"var:preset|spacing|8"},"margin":{"top":"0","bottom":"0"},"blockGap":"var:preset|spacing|12"}},"layout":{"type":"constrained","contentSize":"1100px"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--8);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--8)">
	<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|4"}},"layout":{"type":"constrained","contentSize":"760px"}} -->
	<div class="wp-block-group">
		<!-- wp:paragraph {"className":"is-style-eyebrow"} -->
		<p class="is-style-eyebrow">Design System</p>
		<!-- /wp:paragraph -->

		<!-- wp:heading {"level":1,"fontSize":"fluid-xxxxx-large"} -->
		<h1 class="wp-block-heading has-fluid-xxxxx-large-font-size">Primitive preview</h1>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"className":"is-style-caption"} -->
		<p class="is-style-caption">Use this pattern to review token changes, registered block styles, commerce states, and component rhythm in one place.</p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|8"}}}} -->
	<div class="wp-block-columns">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:group {"className":"is-style-surface-card","layout":{"type":"constrained"}} -->
			<div class="wp-block-group is-style-surface-card">
				<!-- wp:paragraph {"className":"is-style-meta"} -->
				<p class="is-style-meta">Buttons</p>
				<!-- /wp:paragraph -->

				<!-- wp:buttons {"style":{"spacing":{"blockGap":"var:preset|spacing|4"}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
				<div class="wp-block-buttons">
					<!-- wp:button {"className":"is-style-cta"} -->
					<div class="wp-block-button is-style-cta"><a class="wp-block-button__link wp-element-button" href="#">CTA</a></div>
					<!-- /wp:button -->

					<!-- wp:button {"className":"is-style-cta-small"} -->
					<div class="wp-block-button is-style-cta-small"><a class="wp-block-button__link wp-element-button" href="#">CTA Small</a></div>
					<!-- /wp:button -->

					<!-- wp:button {"className":"is-style-ghost"} -->
					<div class="wp-block-button is-style-ghost"><a class="wp-block-button__link wp-element-button" href="#">Ghost</a></div>
					<!-- /wp:button -->

					<!-- wp:button {"className":"is-style-text"} -->
					<div class="wp-block-button is-style-text"><a class="wp-block-button__link wp-element-button" href="#">Text Link</a></div>
					<!-- /wp:button -->
				</div>
				<!-- /wp:buttons -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:group {"className":"is-style-bordered","layout":{"type":"constrained"}} -->
			<div class="wp-block-group is-style-bordered">
				<!-- wp:paragraph {"className":"is-style-meta"} -->
				<p class="is-style-meta">Badges and states</p>
				<!-- /wp:paragraph -->

				<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|3"}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
				<div class="wp-block-group">
					<!-- wp:paragraph {"className":"is-style-badge"} -->
					<p class="is-style-badge">Default</p>
					<!-- /wp:paragraph -->

					<!-- wp:paragraph {"className":"is-style-badge-muted"} -->
					<p class="is-style-badge-muted">Muted</p>
					<!-- /wp:paragraph -->

					<!-- wp:paragraph {"className":"is-style-badge aa-commerce-state-sale"} -->
					<p class="is-style-badge aa-commerce-state-sale">Sale</p>
					<!-- /wp:paragraph -->

					<!-- wp:paragraph {"className":"is-style-badge aa-commerce-state-new"} -->
					<p class="is-style-badge aa-commerce-state-new">New</p>
					<!-- /wp:paragraph -->

					<!-- wp:paragraph {"className":"is-style-badge aa-commerce-state-low-stock"} -->
					<p class="is-style-badge aa-commerce-state-low-stock">Low Stock</p>
					<!-- /wp:paragraph -->

					<!-- wp:paragraph {"className":"is-style-badge aa-commerce-state-out-of-stock"} -->
					<p class="is-style-badge aa-commerce-state-out-of-stock">Out of Stock</p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->

	<!-- wp:group {"className":"is-style-surface-card","style":{"spacing":{"blockGap":"var:preset|spacing|5"}},"layout":{"type":"constrained"}} -->
	<div class="wp-block-group is-style-surface-card">
		<!-- wp:paragraph {"className":"is-style-eyebrow"} -->
		<p class="is-style-eyebrow">Typography Roles</p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"className":"is-style-caption"} -->
		<p class="is-style-caption">Caption text is for supporting detail and secondary explanatory copy.</p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"className":"is-style-meta"} -->
		<p class="is-style-meta">Meta text uses compact uppercase rhythm</p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"className":"is-style-price"} -->
		<p class="is-style-price">$79.00</p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"className":"is-style-legal"} -->
		<p class="is-style-legal">Legal text is intentionally quiet and compact while remaining readable.</p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:woocommerce/product-collection {"queryId":99,"query":{"perPage":4,"pages":0,"offset":0,"postType":"product","order":"desc","orderBy":"date","search":"","exclude":[],"inherit":false,"taxQuery":[],"isProductCollectionBlock":true,"woocommerceOnSale":false,"woocommerceStockStatus":["instock","outofstock","onbackorder"],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[]},"tagName":"div","displayLayout":{"type":"flex","columns":4,"shrinkColumns":true},"dimensions":{"widthType":"fill"},"queryContextIncludes":["collection"],"className":"is-style-commerce-grid"} -->
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
