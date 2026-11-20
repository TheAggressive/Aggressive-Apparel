<?php
/**
 * Title: Editorial — Featured Drop
 * Description: Campaign Cover for the current release (replace the image) plus its products. Reads the drop from the store by slug (default "dead-signal"); without it, the section links to the newest products instead.
 * Slug: aggressive-apparel/editorial-featured-drop
 * Categories: aggressive-editorial, aggressive-homepage, aggressive-drops
 * Keywords: drop, release, collection, campaign, capsule, homepage
 * Viewport Width: 1440
 *
 * @package Aggressive_Apparel
 */

use Aggressive_Apparel\WooCommerce\Editorial_Catalog;
use Aggressive_Apparel\WooCommerce\Editorial_Product_Grid;

/**
 * Filters the store term slugs the Featured Drop section looks for.
 *
 * @param array<int, string> $slugs Candidate slugs, preferred first.
 */
$aa_slugs = (array) apply_filters( 'aggressive_apparel_featured_drop_slugs', array( 'dead-signal' ) );
$aa_drop  = Editorial_Catalog::term( array( 'pa_collection', 'product_cat', 'product_tag' ), array_map( 'strval', $aa_slugs ) );
$aa_title = null !== $aa_drop ? $aa_drop->name : __( 'The latest drop', 'aggressive-apparel' );
$aa_url   = null !== $aa_drop ? Editorial_Catalog::url( $aa_drop ) : Editorial_Catalog::shop_url( array( 'orderby' => 'date' ) );

?><!-- wp:group {"metadata":{"name":"06 Featured Drop"},"align":"full","style":{"spacing":{"padding":{"top":"0","bottom":"var:preset|spacing|section-standard","left":"0","right":"0"},"margin":{"top":"0","bottom":"0"},"blockGap":"var:preset|spacing|fluid-16"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:0;padding-right:0;padding-bottom:var(--wp--preset--spacing--section-standard);padding-left:0"><!-- wp:cover {"dimRatio":30,"overlayColor":"black","isUserOverlayColor":true,"minHeight":75,"minHeightUnit":"vh","contentPosition":"center right","isDark":true,"metadata":{"name":"Drop campaign image (replace image)"},"align":"full","style":{"color":{"background":"var:preset|color|black"},"spacing":{"padding":{"top":"var:preset|spacing|section-editorial","bottom":"var:preset|spacing|section-editorial","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-cover alignfull is-dark has-custom-content-position is-position-center-right has-background" style="background-color:var(--wp--preset--color--black);padding-top:var(--wp--preset--spacing--section-editorial);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-editorial);padding-left:var(--wp--preset--spacing--gutter);min-height:75vh"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-30 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:group {"layout":{"type":"constrained","contentSize":"560px","justifyContent":"right"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"is-style-meta","textColor":"white"} -->
<p class="is-style-meta has-white-color has-text-color"><?php echo esc_html__( 'Featured drop', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"is-style-campaign","textColor":"white"} -->
<h2 class="wp-block-heading is-style-campaign has-white-color has-text-color"><?php echo esc_html( $aa_title ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"white"} -->
<p class="has-white-color has-text-color"><?php echo esc_html__( 'A new set of original graphics, released together and printed in limited runs.', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|8"}}}} -->
<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--8)"><!-- wp:button {"className":"is-style-cta"} -->
<div class="wp-block-button is-style-cta"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $aa_url ); ?>"><?php echo esc_html__( 'Shop the drop', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div></div>
<!-- /wp:cover -->
<?php if ( null !== $aa_drop ) : ?>

<!-- wp:group {"style":{"spacing":{"padding":{"left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="padding-right:var(--wp--preset--spacing--gutter);padding-left:var(--wp--preset--spacing--gutter)">
	<?php
	$aa_grid = Editorial_Product_Grid::markup(
		array(
			'query_id' => 306,
			'per_page' => 4,
			'columns'  => 4,
			'term'     => $aa_drop,
			'label'    => __( 'Drop products', 'aggressive-apparel' ),
		)
	);
	echo $aa_grid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; attributes encoded by core's serializer, classes escaped.
	?>
</div>
<!-- /wp:group -->
<?php endif; ?>
</div>
<!-- /wp:group -->
