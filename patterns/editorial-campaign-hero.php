<?php
/**
 * Title: Editorial — Campaign Hero
 * Description: Black opening spread: the slogan stacked at display scale beside two full-height portrait photos of real products, edge to edge, each linked to its product and named in a "Shown" line. Uses the store's newest portrait product photos until campaign photography replaces them; one photo fills the side alone, and with none the slogan runs full width as type.
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
// The newest portrait product photos, except the newest product's own:
// New Arrivals opens with that one as a large card directly below.
Editorial_Imagery::reserve_newest_arrival();
$aa_leads = Editorial_Imagery::leads( 2 );
$aa_shown = array();
foreach ( $aa_leads as $aa_lead ) {
	$aa_shown[] = '<a href="' . esc_url( (string) get_permalink( $aa_lead['product'] ) ) . '">' . esc_html( get_the_title( $aa_lead['product'] ) ) . '</a>';
}

// The copy column's left padding lines its text up with the wide column
// below on screens wider than the wide size, and is the gutter elsewhere.
?><!-- wp:group {"metadata":{"name":"01 Campaign Hero"},"align":"full","className":"aa-campaign-hero","style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"margin":{"top":"0","bottom":"0"}},"elements":{"link":{"color":{"text":"var:preset|color|white"}}}},"backgroundColor":"black","textColor":"white","layout":{"type":"default"}} -->
<div class="wp-block-group alignfull aa-campaign-hero has-white-color has-black-background-color has-text-color has-background has-link-color" style="margin-top:0;margin-bottom:0;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0">
<?php if ( array() !== $aa_leads ) : ?>
<!-- wp:columns {"align":"full","style":{"spacing":{"blockGap":{"top":"0","left":"0"},"margin":{"top":"0","bottom":"0"}}}} -->
<div class="wp-block-columns alignfull" style="margin-top:0;margin-bottom:0"><!-- wp:column {"width":"40%","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-compact","right":"var:preset|spacing|gutter","bottom":"var:preset|spacing|section-compact","left":"max(var(--wp--preset--spacing--gutter), calc((100% - var(--wp--style--global--wide-size)) / 2))"}}}} -->
<div class="wp-block-column" style="padding-top:var(--wp--preset--spacing--section-compact);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-compact);padding-left:max(var(--wp--preset--spacing--gutter), calc((100% - var(--wp--style--global--wide-size)) / 2));flex-basis:40%"><!-- wp:group {"style":{"dimensions":{"minHeight":"100%"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch","verticalAlignment":"space-between"}} -->
<div class="wp-block-group" style="min-height:100%">
<?php else : ?>
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|section-editorial","right":"var:preset|spacing|gutter","bottom":"var:preset|spacing|section-standard","left":"var:preset|spacing|gutter"}}},"layout":{"type":"constrained","contentSize":"1600px","justifyContent":"left"}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--section-editorial);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-standard);padding-left:var(--wp--preset--spacing--gutter)">
<?php endif; ?>
<!-- wp:paragraph {"className":"is-style-meta","textColor":"white"} -->
<p class="is-style-meta has-white-color has-text-color"><?php echo esc_html__( 'Est. 2010', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":1,"className":"is-style-campaign","textColor":"white"} -->
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
<?php if ( array() !== $aa_shown ) : ?>

<!-- wp:paragraph {"className":"is-style-meta","textColor":"white"} -->
<p class="is-style-meta has-white-color has-text-color">
	<?php
	/* translators: %s: linked names of the products in the hero photos, separated by a middle dot. */
	printf( esc_html__( 'Shown: %s', 'aggressive-apparel' ), implode( ' · ', $aa_shown ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Links built above with esc_url()/esc_html().
	?>
</p>
<!-- /wp:paragraph -->
<?php endif; ?>
</div>
<!-- /wp:group -->
<?php if ( array() !== $aa_leads ) : ?>
</div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"60%"} -->
<div class="wp-block-column" style="flex-basis:60%">
	<?php if ( 1 === count( $aa_leads ) ) : ?>
		<?php echo Editorial_Imagery::image_markup( $aa_leads[0], '4/5' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; URLs and alt escaped in image_markup(). ?>
	<?php else : ?>
<!-- wp:columns {"isStackedOnMobile":false,"style":{"spacing":{"blockGap":{"top":"0","left":"0"},"margin":{"top":"0","bottom":"0"}}}} -->
<div class="wp-block-columns is-not-stacked-on-mobile" style="margin-top:0;margin-bottom:0"><!-- wp:column -->
<div class="wp-block-column">
		<?php echo Editorial_Imagery::image_markup( $aa_leads[0], '2/3' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; URLs and alt escaped in image_markup(). ?>
</div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column">
		<?php echo Editorial_Imagery::image_markup( $aa_leads[1], '2/3' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup; URLs and alt escaped in image_markup(). ?>
</div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
	<?php endif; ?>
</div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<?php else : ?>
</div>
<!-- /wp:group -->
<?php endif; ?>
</div>
<!-- /wp:group -->
