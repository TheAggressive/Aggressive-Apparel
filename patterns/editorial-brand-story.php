<?php
/**
 * Title: Editorial — The Aggressive Story
 * Description: A spacious closing brand statement: a large sans headline about original artwork and independence, and a link to the About page when one exists.
 * Slug: aggressive-apparel/editorial-brand-story
 * Categories: aggressive-editorial, aggressive-homepage, aggressive-informational
 * Keywords: brand, story, about, manifesto, independent, homepage
 * Viewport Width: 1440
 *
 * @package Aggressive_Apparel
 */

$aa_about = get_page_by_path( 'about' );
$aa_about = $aa_about instanceof WP_Post && 'publish' === $aa_about->post_status ? get_permalink( $aa_about ) : false;

?><!-- wp:group {"metadata":{"name":"12 The Aggressive Story"},"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|section-immersive","bottom":"var:preset|spacing|section-immersive","left":"var:preset|spacing|gutter","right":"var:preset|spacing|gutter"},"margin":{"top":"0","bottom":"0"},"blockGap":"var:preset|spacing|8"}},"layout":{"type":"constrained","contentSize":"960px"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--section-immersive);padding-right:var(--wp--preset--spacing--gutter);padding-bottom:var(--wp--preset--spacing--section-immersive);padding-left:var(--wp--preset--spacing--gutter)"><!-- wp:separator {"className":"is-style-brand-stripe"} -->
<hr class="wp-block-separator has-alpha-channel-opacity is-style-brand-stripe"/>
<!-- /wp:separator -->

<!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow"><?php echo esc_html__( 'The Aggressive', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"is-style-headline"} -->
<h2 class="wp-block-heading is-style-headline"><?php echo esc_html__( 'Independent since 2010. Built on original artwork.', 'aggressive-apparel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"is-style-lead","textColor":"foreground-muted"} -->
<p class="is-style-lead has-foreground-muted-color has-text-color"><?php echo esc_html__( 'Skate, punk, tattoo and moto culture shaped where we came from. The artwork is where we are going, and every design is ours.', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->
<?php if ( false !== $aa_about ) : ?>

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-ghost"} -->
<div class="wp-block-button is-style-ghost"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $aa_about ); ?>"><?php echo esc_html__( 'Our story', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
<?php endif; ?>
</div>
<!-- /wp:group -->
