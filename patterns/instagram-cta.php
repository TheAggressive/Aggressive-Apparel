<?php
/**
 * Title: Instagram CTA
 * Description: Follow-us-on-Instagram section with handle, tagline, and gallery placeholder grid.
 * Slug: aggressive-apparel/instagram-cta
 * Categories: aggressive, aggressive-apparel, aggressive-social-proof
 * Keywords: instagram, social, follow, gallery, feed, ugc
 * Viewport Width: 1400
 *
 * @package Aggressive_Apparel
 */

$placeholder_svg = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgdmlld0JveD0iMCAwIDQwMCA0MDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjQwMCIgaGVpZ2h0PSI0MDAiIGZpbGw9IiMxYTFhMWEiLz48dGV4dCB4PSIyMDAiIHk9IjIwMCIgZm9udC1mYW1pbHk9InN5c3RlbS11aSIgZm9udC1zaXplPSIxMiIgZmlsbD0iIzQ0NCIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZG9taW5hbnQtYmFzZWxpbmU9Im1pZGRsZSI+QGFnZ3Jlc3NpdmVhcHBhcmVsPC90ZXh0Pjwvc3ZnPg==';

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20)">
	<!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.1em","fontStyle":"normal","fontWeight":"600"}},"textColor":"red","fontSize":"x-small"} -->
	<p class="has-text-align-center has-red-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.1em;text-transform:uppercase">Follow Us</p>
	<!-- /wp:paragraph -->

	<!-- wp:heading {"textAlign":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}}},"fontSize":"fluid-xxxx-large"} -->
	<h2 class="wp-block-heading has-text-align-center has-fluid-xxxx-large-font-size" style="margin-top:var(--wp--preset--spacing--4)">@aggressiveapparel</h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|4","bottom":"var:preset|spacing|12"}}},"fontSize":"medium"} -->
	<p class="has-text-align-center has-medium-font-size" style="margin-top:var(--wp--preset--spacing--4);margin-bottom:var(--wp--preset--spacing--12)">Tag us in your fits for a chance to be featured.</p>
	<!-- /wp:paragraph -->

	<!-- wp:gallery {"columns":6,"linkTo":"none","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|2","top":"var:preset|spacing|2"}}}} -->
	<figure class="wp-block-gallery has-nested-images columns-6">
		<!-- wp:image {"sizeSlug":"medium"} -->
		<figure class="wp-block-image size-medium"><img src="<?php echo esc_url( $placeholder_svg ); ?>" alt="Instagram post"/></figure>
		<!-- /wp:image -->

		<!-- wp:image {"sizeSlug":"medium"} -->
		<figure class="wp-block-image size-medium"><img src="<?php echo esc_url( $placeholder_svg ); ?>" alt="Instagram post"/></figure>
		<!-- /wp:image -->

		<!-- wp:image {"sizeSlug":"medium"} -->
		<figure class="wp-block-image size-medium"><img src="<?php echo esc_url( $placeholder_svg ); ?>" alt="Instagram post"/></figure>
		<!-- /wp:image -->

		<!-- wp:image {"sizeSlug":"medium"} -->
		<figure class="wp-block-image size-medium"><img src="<?php echo esc_url( $placeholder_svg ); ?>" alt="Instagram post"/></figure>
		<!-- /wp:image -->

		<!-- wp:image {"sizeSlug":"medium"} -->
		<figure class="wp-block-image size-medium"><img src="<?php echo esc_url( $placeholder_svg ); ?>" alt="Instagram post"/></figure>
		<!-- /wp:image -->

		<!-- wp:image {"sizeSlug":"medium"} -->
		<figure class="wp-block-image size-medium"><img src="<?php echo esc_url( $placeholder_svg ); ?>" alt="Instagram post"/></figure>
		<!-- /wp:image -->
	</figure>
	<!-- /wp:gallery -->
</div>
<!-- /wp:group -->
