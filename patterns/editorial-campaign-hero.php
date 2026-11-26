<?php
/**
 * Title: Editorial — Cover
 * Description: The opening campaign: the photo set in the Site Editor (template part "Homepage campaign image": a Cover block with its own focal point, overlay and content position) full width, the slogan and shopping links over its open side; on phones the copy, then the photo. Names the product marked Homepage cover with its live price. Without a campaign photo, that product's own image beside the slogan on a light ground; otherwise the slogan alone. It never picks a product by itself.
 * Slug: aggressive-apparel/editorial-campaign-hero
 * Categories: aggressive-editorial, aggressive-homepage
 * Keywords: hero, cover, campaign, editorial, homepage, slogan
 * Viewport Width: 1440
 *
 * @package Aggressive_Apparel
 */

use Aggressive_Apparel\WooCommerce\Editorial_Campaign_Cover;
use Aggressive_Apparel\WooCommerce\Editorial_Catalog;
use Aggressive_Apparel\WooCommerce\Editorial_Imagery;
use Aggressive_Apparel\WooCommerce\Editorial_Product_Grid;

$aa_new_arrivals = Editorial_Catalog::shop_url( array( 'orderby' => 'date' ) );
// Only what the merchant chose: the campaign photo, else the cover product's
// own image; neither means the slogan alone. New Arrivals skips the cover
// product if it's the newest.
$aa_hero = Editorial_Imagery::cover();
// The garment's name and live price: a one-product collection, so the price
// is WooCommerce's own (sale, savings tag) and never baked in.
$aa_named = null !== $aa_hero && $aa_hero['product'] > 0
	? Editorial_Product_Grid::markup(
		array(
			'query_id' => 300,
			'products' => array( $aa_hero['product'] ),
			'columns'  => 1,
			'caption'  => true,
			'on_dark'  => $aa_hero['campaign'],
			'align'    => '',
			'label'    => __( 'Cover garment', 'aggressive-apparel' ),
		)
	)
	: '';

if ( null !== $aa_hero && $aa_hero['campaign'] ) {
	ob_start();
	?>
<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|3"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"is-style-meta","textColor":"white"} -->
<p class="is-style-meta has-white-color has-text-color"><?php echo esc_html__( 'Est. 2010', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"is-style-campaign","textColor":"white"} -->
<h1 class="wp-block-heading is-style-campaign has-white-color has-text-color"><?php echo esc_html__( 'Rebellion has a uniform.', 'aggressive-apparel' ); ?></h1>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|8"},"blockGap":"var:preset|spacing|6"}},"layout":{"type":"flex","verticalAlignment":"center"}} -->
<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--8)"><!-- wp:button {"className":"is-style-cta"} -->
<div class="wp-block-button is-style-cta"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $aa_new_arrivals ); ?>"><?php echo esc_html__( 'Shop new arrivals', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-text-on-dark"} -->
<div class="wp-block-button is-style-text-on-dark"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( Editorial_Catalog::shop_url() ); ?>"><?php echo esc_html__( 'Explore the collection', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
	<?php
	$aa_cover = Editorial_Campaign_Cover::markup( Editorial_Campaign_Cover::HERO, (string) ob_get_clean() . $aa_named, '01 Cover' );
	echo $aa_cover; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; copy escaped above, the Cover is the merchant's saved block.
	return;
}

// In the Site Editor an empty campaign slot still shows, so the merchant can
// fill it in place; visitors never see an empty frame.
$aa_slot  = null === $aa_hero && wp_is_serving_rest_request();
$aa_media = null !== $aa_hero || $aa_slot;

?>
<!-- wp:group {"metadata":{"name":"01 Cover"},"align":"full","className":"aa-cover aa-scheme-light","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-compact","bottom":"var:preset|spacing|section-compact","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"}}},"backgroundColor":"surface","layout":{"type":"constrained","contentSize":"1600px"}} -->
<div class="wp-block-group alignfull aa-cover aa-scheme-light has-surface-background-color has-background" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-compact);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-compact);padding-left:var(--wp--preset--spacing--gutter)">
<?php if ( $aa_media ) : ?>
<!-- wp:columns {"verticalAlignment":"center","align":"wide","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|6","left":"0"}}}} -->
<div class="wp-block-columns alignwide are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center","width":"54%","className":"aa-cover-copy"} -->
<div class="wp-block-column is-vertically-aligned-center aa-cover-copy" style="flex-basis:54%">
<?php endif; ?>
<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|3"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow"><?php echo esc_html__( 'Est. 2010', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"is-style-campaign"} -->
<h1 class="wp-block-heading is-style-campaign"><?php echo esc_html__( 'Rebellion has a uniform.', 'aggressive-apparel' ); ?></h1>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|8"},"blockGap":"var:preset|spacing|6"}},"layout":{"type":"flex","verticalAlignment":"center"}} -->
<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--8)"><!-- wp:button {"className":"is-style-cta"} -->
<div class="wp-block-button is-style-cta"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $aa_new_arrivals ); ?>"><?php echo esc_html__( 'Shop new arrivals', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-text"} -->
<div class="wp-block-button is-style-text"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( Editorial_Catalog::shop_url() ); ?>"><?php echo esc_html__( 'Explore the collection', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
<?php if ( $aa_media ) : ?>
</div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"46%","className":"aa-cover-garment"} -->
<div class="wp-block-column is-vertically-aligned-center aa-cover-garment" style="flex-basis:46%">
	<?php if ( $aa_slot ) : ?>
<!-- wp:template-part {"slug":"<?php echo esc_attr( Editorial_Imagery::CAMPAIGN_PART ); ?>","className":"aa-cover-campaign"} /-->
	<?php else : ?>
		<?php
		// The fallback: the cover product's own photo, shown whole.
		$aa_image = Editorial_Imagery::image_markup( $aa_hero, '1', 'contain' );
		echo $aa_image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; URLs and alt escaped in image_markup().
		?>
	<?php endif; ?>
	<?php echo $aa_named; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; attributes encoded by core's serializer, classes escaped. ?>
</div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<?php endif; ?>
</div>
<!-- /wp:group -->
