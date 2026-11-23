<?php
/**
 * Title: Editorial — The Aggressive Story
 * Description: One compact closing row: the label's positioning in one statement on the left, a link into the catalog (and the About page when one exists) on the right.
 * Slug: aggressive-apparel/editorial-brand-story
 * Categories: aggressive-editorial, aggressive-homepage, aggressive-informational
 * Keywords: brand, story, about, manifesto, independent, homepage
 * Viewport Width: 1440
 *
 * @package Aggressive_Apparel
 */

use Aggressive_Apparel\WooCommerce\Editorial_Catalog;

$aa_about = get_page_by_path( 'about' );
$aa_about = $aa_about instanceof WP_Post && 'publish' === $aa_about->post_status ? get_permalink( $aa_about ) : false;

?><!-- wp:group {"metadata":{"name":"12 The Aggressive Story"},"align":"full","className":"aa-page-section","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-compact","bottom":"var:preset|spacing|section-compact","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull aa-page-section" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-compact);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-compact);padding-left:var(--wp--preset--spacing--gutter)"><!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|8"}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group alignwide"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|3"},"layout":{"selfStretch":"fill","flexSize":null}},"layout":{"type":"constrained","contentSize":"1040px","justifyContent":"left"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"is-style-eyebrow aa-chapter-index"} -->
<p class="is-style-eyebrow aa-chapter-index"><?php echo esc_html__( 'The Aggressive', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"is-style-headline"} -->
<h2 class="wp-block-heading is-style-headline"><?php echo esc_html__( 'Without the edges sanded off.', 'aggressive-apparel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"is-style-lead"} -->
<p class="is-style-lead"><?php echo esc_html__( 'Graphic pieces, plain essentials and accessories from an independent label.', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-cta"} -->
<div class="wp-block-button is-style-cta"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( Editorial_Catalog::shop_url() ); ?>"><?php echo esc_html__( 'Shop all', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button -->
<?php if ( false !== $aa_about ) : ?>

<!-- wp:button {"className":"is-style-ghost"} -->
<div class="wp-block-button is-style-ghost"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $aa_about ); ?>"><?php echo esc_html__( 'Our story', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button -->
<?php endif; ?></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
