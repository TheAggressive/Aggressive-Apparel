<?php
/**
 * Title: Editorial — Featured Looks
 * Description: Asymmetric lookbook: one lead 4:5 portrait and two smaller 2:3 looks on its baseline (replace the images and link each look to its products) on a warm surface.
 * Slug: aggressive-apparel/editorial-featured-looks
 * Categories: aggressive-editorial, aggressive-homepage, aggressive-drops
 * Keywords: lookbook, looks, outfits, editorial, style, homepage
 * Viewport Width: 1440
 *
 * @package Aggressive_Apparel
 */

use Aggressive_Apparel\WooCommerce\Editorial_Catalog;

$aa_shop  = Editorial_Catalog::shop_url();
$aa_looks = array(
	array(
		/* translators: Lookbook look number. */
		'label' => __( 'Look 01', 'aggressive-apparel' ),
		'width' => '50%',
		'ratio' => '4/5',
	),
	array(
		/* translators: Lookbook look number. */
		'label' => __( 'Look 02', 'aggressive-apparel' ),
		'width' => '25%',
		'ratio' => '2/3',
	),
	array(
		/* translators: Lookbook look number. */
		'label' => __( 'Look 03', 'aggressive-apparel' ),
		'width' => '25%',
		'ratio' => '2/3',
	),
);

?><!-- wp:group {"metadata":{"name":"11 Featured Looks"},"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-editorial","bottom":"var:preset|spacing|section-editorial","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"},"blockGap":"var:preset|spacing|fluid-16"}},"backgroundColor":"surface-muted","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-surface-muted-background-color has-background" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-editorial);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-editorial);padding-left:var(--wp--preset--spacing--gutter)"><!-- wp:group {"layout":{"type":"constrained","contentSize":"720px"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"align":"center","className":"is-style-eyebrow"} -->
<p class="has-text-align-center is-style-eyebrow"><?php echo esc_html__( 'Lookbook', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textAlign":"center","textColor":"foreground"} -->
<h2 class="wp-block-heading has-text-align-center has-foreground-color has-text-color"><?php echo esc_html__( 'How it’s worn', 'aggressive-apparel' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:aggressive-blocks/animate-on-scroll {"animation":"slide","direction":"up","slideDistance":24,"duration":0.7,"align":"wide"} -->
<!-- wp:columns {"verticalAlignment":"bottom","align":"wide","className":"aa-editorial-looks","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|fluid-12","left":"var:preset|spacing|fluid-8"}}}} -->
<div class="wp-block-columns alignwide are-vertically-aligned-bottom aa-editorial-looks">
<?php foreach ( $aa_looks as $aa_look ) : ?>
<!-- wp:column {"verticalAlignment":"bottom","width":"<?php echo esc_attr( $aa_look['width'] ); ?>","style":{"spacing":{"blockGap":"var:preset|spacing|4"}}} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:<?php echo esc_attr( $aa_look['width'] ); ?>"><!-- wp:cover {"dimRatio":100,"overlayColor":"surface-sunken","isUserOverlayColor":true,"isDark":false,"metadata":{"name":"<?php echo esc_attr( $aa_look['label'] ); ?> (replace image)"},"style":{"dimensions":{"aspectRatio":"<?php echo esc_attr( $aa_look['ratio'] ); ?>"}}} -->
<div class="wp-block-cover is-light"><span aria-hidden="true" class="wp-block-cover__background has-surface-sunken-background-color has-background-dim-100 has-background-dim"></span><div class="wp-block-cover__inner-container"></div></div>
<!-- /wp:cover -->

<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"is-style-meta"} -->
<p class="is-style-meta"><?php echo esc_html( $aa_look['label'] ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a href="<?php echo esc_url( $aa_shop ); ?>"><?php echo esc_html__( 'Shop the look', 'aggressive-apparel' ); ?></a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->
<?php endforeach; ?>
</div>
<!-- /wp:columns -->
<!-- /wp:aggressive-blocks/animate-on-scroll --></div>
<!-- /wp:group -->
