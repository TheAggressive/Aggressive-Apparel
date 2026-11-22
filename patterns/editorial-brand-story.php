<?php
/**
 * Title: Editorial — The Aggressive Story
 * Description: A short blunt closing line that hands the page on to the shop, a link into the catalog, and a link to the About page when one exists.
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

?><!-- wp:group {"metadata":{"name":"12 The Aggressive Story"},"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-standard","bottom":"var:preset|spacing|section-standard","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"},"blockGap":"var:preset|spacing|8"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-standard);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-standard);padding-left:var(--wp--preset--spacing--gutter)"><!-- wp:aggressive-blocks/animate-on-scroll {"align":"wide","animation":"slide","direction":"up","slideDistance":24,"duration":0.7,"threshold":"0.1","detectionBoundary":{"top":"100%","right":"0%","bottom":"0%","left":"0%"},"staggerChildren":true,"staggerDelay":0.08,"style":{"spacing":{"blockGap":"var:preset|spacing|8"}}} -->
<!-- wp:separator {"className":"is-style-brand-stripe"} -->
<hr class="wp-block-separator has-alpha-channel-opacity is-style-brand-stripe"/>
<!-- /wp:separator -->

<!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow"><?php echo esc_html__( 'The Aggressive', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"is-style-headline","fontSize":"fluid-xxxxxxx-large"} -->
<h2 class="wp-block-heading is-style-headline has-fluid-xxxxxxx-large-font-size"><?php echo esc_html__( 'Nobody needs another hoodie.', 'aggressive-apparel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"is-style-lead"} -->
<p class="is-style-lead"><?php echo esc_html__( 'We made one anyway.', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-cta"} -->
<div class="wp-block-button is-style-cta"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( Editorial_Catalog::shop_url() ); ?>"><?php echo esc_html__( 'Shop all', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button -->
<?php if ( false !== $aa_about ) : ?>

<!-- wp:button {"className":"is-style-ghost"} -->
<div class="wp-block-button is-style-ghost"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $aa_about ); ?>"><?php echo esc_html__( 'Our story', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button -->
<?php endif; ?></div>
<!-- /wp:buttons -->
<!-- /wp:aggressive-blocks/animate-on-scroll -->
</div>
<!-- /wp:group -->
