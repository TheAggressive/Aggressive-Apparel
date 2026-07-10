<?php
/**
 * Title: Shop Archive Header
 * Description: Utility product listing page header with title, count, filter toggle, and active filter bar.
 * Slug: aggressive-apparel/shop-archive-header
 * Categories: aggressive, aggressive-apparel, aggressive-shop, aggressive-products
 * Keywords: shop, archive, header, filters, plp, product listing
 * Viewport Width: 1200
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|12","bottom":"var:preset|spacing|8"},"margin":{"top":"0","bottom":"0"}},"border":{"bottom":{"color":"var:preset|color|border","width":"1px"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="border-bottom-color:var(--wp--preset--color--border);border-bottom-width:1px;margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--12);padding-bottom:var(--wp--preset--spacing--8)">
	<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"},"style":{"spacing":{"blockGap":"var:preset|spacing|6"}}} -->
	<div class="wp-block-group">
		<!-- wp:group {"layout":{"type":"flex","orientation":"vertical","justifyContent":"left"},"style":{"spacing":{"blockGap":"var:preset|spacing|2"}}} -->
		<div class="wp-block-group">
			<!-- wp:query-title {"type":"archive","showPrefix":false,"style":{"typography":{"fontStyle":"normal","fontWeight":"700","textTransform":"uppercase","letterSpacing":"0.03em"}},"fontSize":"fluid-xxx-large"} /-->

			<!-- wp:term-description {"textColor":"foreground-muted","fontSize":"small"} /-->
		</div>
		<!-- /wp:group -->

		<!-- wp:aggressive-apparel/filter-toggle /-->
	</div>
	<!-- /wp:group -->

	<!-- wp:spacer {"height":"var:preset|spacing|6"} -->
	<div style="height:var(--wp--preset--spacing--6)" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->

	<!-- wp:aggressive-apparel/filter-active-bar /-->
</div>
<!-- /wp:group -->
