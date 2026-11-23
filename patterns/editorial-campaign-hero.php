<?php
/**
 * Title: Editorial — Cover
 * Description: The opening spread on a light studio ground (light in both schemes): the slogan at poster scale beside the merchant's cover — a campaign photo (Media Library, Homepage use: Cover) and/or the product marked Homepage cover, named with its live price. With neither chosen, the newest arrival's product photo; with no stock, the slogan alone.
 * Slug: aggressive-apparel/editorial-campaign-hero
 * Categories: aggressive-editorial, aggressive-homepage
 * Keywords: hero, cover, campaign, editorial, homepage, slogan
 * Viewport Width: 1440
 *
 * @package Aggressive_Apparel
 */

use Aggressive_Apparel\WooCommerce\Editorial_Catalog;
use Aggressive_Apparel\WooCommerce\Editorial_Imagery;
use Aggressive_Apparel\WooCommerce\Editorial_Product_Grid;

$aa_new_arrivals = Editorial_Catalog::shop_url( array( 'orderby' => 'date' ) );
// The merchant's choice first (campaign photo, cover product); the newest
// arrival only when nothing is chosen. New Arrivals skips it if it's newest.
$aa_hero = Editorial_Imagery::cover();

?><!-- wp:group {"metadata":{"name":"01 Cover"},"align":"full","className":"aa-cover aa-scheme-light","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-compact","bottom":"var:preset|spacing|section-compact","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"}}},"backgroundColor":"surface","layout":{"type":"constrained","contentSize":"1600px"}} -->
<div class="wp-block-group alignfull aa-cover aa-scheme-light has-surface-background-color has-background" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-compact);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-compact);padding-left:var(--wp--preset--spacing--gutter)">
<?php if ( null !== $aa_hero ) : ?>
<!-- wp:columns {"verticalAlignment":"center","align":"wide","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|6","left":"0"}}}} -->
<div class="wp-block-columns alignwide are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center","width":"54%","className":"aa-cover-copy"} -->
<div class="wp-block-column is-vertically-aligned-center aa-cover-copy" style="flex-basis:54%">
<?php endif; ?>
<!-- wp:paragraph {"className":"is-style-eyebrow aa-chapter-index"} -->
<p class="is-style-eyebrow aa-chapter-index"><?php echo esc_html__( 'Est. 2010', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"is-style-campaign"} -->
<h1 class="wp-block-heading is-style-campaign"><?php echo esc_html__( 'Rebellion has a uniform.', 'aggressive-apparel' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"is-style-lead"} -->
<p class="is-style-lead"><?php echo esc_html__( 'This season’s is in.', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|8"},"blockGap":"var:preset|spacing|6"}},"layout":{"type":"flex","verticalAlignment":"center"}} -->
<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--8)"><!-- wp:button {"className":"is-style-cta"} -->
<div class="wp-block-button is-style-cta"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $aa_new_arrivals ); ?>"><?php echo esc_html__( 'Shop new arrivals', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-text"} -->
<div class="wp-block-button is-style-text"><a class="wp-block-button__link wp-element-button" href="#the-label"><?php echo esc_html__( 'About the name', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
<?php if ( null !== $aa_hero ) : ?>
</div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"46%","className":"aa-cover-garment"} -->
<div class="wp-block-column is-vertically-aligned-center aa-cover-garment" style="flex-basis:46%">
	<?php
	// A campaign photo fills a 4:5 frame; a product photo is shown whole.
	$aa_image = $aa_hero['campaign'] ? Editorial_Imagery::image_markup( $aa_hero, '4/5' ) : Editorial_Imagery::image_markup( $aa_hero, '1', 'contain' );
	echo $aa_image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; URLs and alt escaped in image_markup().
	?>
	<?php if ( $aa_hero['product'] > 0 ) : ?>
		<?php
		// The garment's name and live price: a one-product collection, so the
		// price is WooCommerce's own (sale, savings tag) and never baked in.
		$aa_named = Editorial_Product_Grid::markup(
			array(
				'query_id' => 300,
				'products' => array( $aa_hero['product'] ),
				'columns'  => 1,
				'caption'  => true,
				'align'    => '',
				'label'    => __( 'Cover garment', 'aggressive-apparel' ),
			)
		);
		echo $aa_named; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; attributes encoded by core's serializer, classes escaped.
		?>
	<?php endif; ?>
</div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<?php endif; ?>
</div>
<!-- /wp:group -->
