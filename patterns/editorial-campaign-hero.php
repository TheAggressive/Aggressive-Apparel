<?php
/**
 * Title: Editorial — Campaign Hero
 * Description: Black opening band: the slogan at display scale beside a tall portrait photo of a real product, linked to it, with two CTAs into the live catalog. Shows the store's newest portrait product photo until campaign photography replaces it; without one, the slogan runs full width as type.
 * Slug: aggressive-apparel/editorial-campaign-hero
 * Categories: aggressive-editorial, aggressive-homepage
 * Keywords: hero, campaign, editorial, photo, homepage
 * Viewport Width: 1440
 *
 * @package Aggressive_Apparel
 */

use Aggressive_Apparel\WooCommerce\Editorial_Catalog;
use Aggressive_Apparel\WooCommerce\Editorial_Imagery;

$aa_new_arrivals = Editorial_Catalog::shop_url( array( 'orderby' => 'date' ) );
// The newest portrait product photo, except the newest product's own:
// New Arrivals shows that one as a large card directly below.
Editorial_Imagery::reserve_newest_arrival();
$aa_lead = Editorial_Imagery::leads( 1 )[0] ?? null;

?><!-- wp:group {"metadata":{"name":"01 Campaign Hero"},"align":"full","className":"aa-campaign-hero","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-compact","bottom":"var:preset|spacing|section-compact","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"}},"elements":{"link":{"color":{"text":"var:preset|color|white"}}}},"backgroundColor":"black","textColor":"white","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull aa-campaign-hero has-white-color has-black-background-color has-text-color has-background has-link-color" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-compact);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-compact);padding-left:var(--wp--preset--spacing--gutter)">
<?php if ( null !== $aa_lead ) : ?>
<!-- wp:columns {"verticalAlignment":"bottom","align":"wide","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|fluid-12","left":"var:preset|spacing|fluid-16"}}}} -->
<div class="wp-block-columns alignwide are-vertically-aligned-bottom"><!-- wp:column {"verticalAlignment":"bottom","width":"58%"} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:58%">
<?php else : ?>
<!-- wp:group {"align":"wide","layout":{"type":"constrained","contentSize":"880px","justifyContent":"left"}} -->
<div class="wp-block-group alignwide">
<?php endif; ?>
<!-- wp:paragraph {"className":"is-style-meta","textColor":"white"} -->
<p class="is-style-meta has-white-color has-text-color"><?php echo esc_html__( 'Est. 2010', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"is-style-campaign","textColor":"white"} -->
<h1 class="wp-block-heading is-style-campaign has-white-color has-text-color"><?php echo esc_html__( 'Rebellion has a uniform.', 'aggressive-apparel' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"is-style-lead","textColor":"white"} -->
<p class="is-style-lead has-white-color has-text-color"><?php echo esc_html__( 'This season’s is in.', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|8"}}}} -->
<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--8)"><!-- wp:button {"className":"is-style-cta"} -->
<div class="wp-block-button is-style-cta"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $aa_new_arrivals ); ?>"><?php echo esc_html__( 'Shop new arrivals', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-cta-outline-on-dark"} -->
<div class="wp-block-button is-style-cta-outline-on-dark"><a class="wp-block-button__link wp-element-button" href="#the-label"><?php echo esc_html__( 'About the name', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
<?php if ( null !== $aa_lead ) : ?>
</div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"bottom","width":"42%"} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:42%">
	<?php echo Editorial_Imagery::image_markup( $aa_lead, '4/5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; URLs and alt escaped in image_markup(). ?>
</div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<?php else : ?>
</div>
<!-- /wp:group -->
<?php endif; ?>
</div>
<!-- /wp:group -->
