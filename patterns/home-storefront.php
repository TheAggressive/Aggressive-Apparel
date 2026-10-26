<?php
/**
 * Title: Home Storefront
 * Description: Storefront home page: hero carousel, new arrivals, men's / women's collection covers and brand statement.
 * Slug: aggressive-apparel/home-storefront
 * Categories: aggressive-homepage
 * Keywords: home, storefront, hero, new arrivals, collections
 * Inserter: no
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:aggressive-blocks/hero-carousel {"motion":"random","motionDuration":6,"contentAnimation":"clip","align":"full"} -->
<!-- wp:cover {"url":"https://theaggressive.com/wp-content/uploads/2026/07/man-wearing-black-aggressive-t-shirt.webp","id":7074,"dimRatio":70,"isUserOverlayColor":true,"focalPoint":{"x":0.22,"y":0.4},"minHeight":75,"minHeightUnit":"vh","gradient":"dark-overlay-strong","sizeSlug":"full","metadata":{"name":"Hero Banner"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|0","bottom":"var:preset|spacing|0"},"blockGap":"var:preset|spacing|fluid-18"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-cover" style="margin-top:var(--wp--preset--spacing--0);margin-bottom:var(--wp--preset--spacing--0);min-height:75vh"><img class="wp-block-cover__image-background wp-image-7074 size-full" alt="" src="https://theaggressive.com/wp-content/uploads/2026/07/man-wearing-black-aggressive-t-shirt.webp" style="object-position:22% 40%" data-object-fit="cover" data-object-position="22% 40%"/><span aria-hidden="true" class="wp-block-cover__background has-background-dim-70 has-background-dim wp-block-cover__gradient-background has-background-gradient has-dark-overlay-strong-gradient-background"></span><div class="wp-block-cover__inner-container"><!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"></div>
<!-- /wp:column -->

<!-- wp:column {"style":{"border":{"radius":{"topLeft":"1.5rem","topRight":"1.5rem","bottomLeft":"1.5rem","bottomRight":"1.5rem"}}}} -->
<div class="wp-block-column" style="border-top-left-radius:1.5rem;border-top-right-radius:1.5rem;border-bottom-left-radius:1.5rem;border-bottom-right-radius:1.5rem"><!-- wp:heading {"style":{"typography":{"textAlign":"center"}},"fontSize":"fluid-xxxxxxx-large","fontFamily":"pirata-one"} -->
<h2 class="wp-block-heading has-text-align-center has-pirata-one-font-family has-fluid-xxxxxxx-large-font-size"><?php echo esc_html__( 'Rebellion Has a Uniform', 'aggressive-apparel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"is-style-meta","style":{"typography":{"textAlign":"center","fontStyle":"normal","fontWeight":"400"},"elements":{"link":{"color":{"text":"var:preset|color|white"}}}},"textColor":"white","fontSize":"large"} -->
<p class="has-text-align-center is-style-meta has-white-color has-text-color has-link-color has-large-font-size" style="font-style:normal;font-weight:400"><?php echo esc_html__( 'Built for the ones who learned that loyalty has consequences', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-cta"} -->
<div class="wp-block-button is-style-cta"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/product/trust-kills-t-shirt/' ) ); ?>"><?php echo esc_html__( 'Shop Trust Kills', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-cta-outline-on-dark"} -->
<div class="wp-block-button is-style-cta-outline-on-dark"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><?php echo esc_html__( 'Explore New Arrivals', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div></div>
<!-- /wp:cover -->
<!-- /wp:aggressive-blocks/hero-carousel -->

<!-- wp:group {"align":"wide","className":"is-style-default","style":{"spacing":{"margin":{"top":"var:preset|spacing|fluid-24","bottom":"var:preset|spacing|fluid-24"},"padding":{"right":"var:preset|spacing|fluid-8","left":"var:preset|spacing|fluid-8"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide is-style-default" style="margin-top:var(--wp--preset--spacing--fluid-24);margin-bottom:var(--wp--preset--spacing--fluid-24);padding-right:var(--wp--preset--spacing--fluid-8);padding-left:var(--wp--preset--spacing--fluid-8)"><!-- wp:woocommerce/product-collection {"queryId":3,"query":{"perPage":5,"pages":0,"offset":0,"postType":"product","order":"desc","orderBy":"date","search":"","exclude":[],"inherit":false,"taxQuery":[],"isProductCollectionBlock":true,"woocommerceOnSale":false,"woocommerceStockStatus":[],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[],"filterable":false},"tagName":"div","displayLayout":{"type":"flex","columns":5,"shrinkColumns":false},"dimensions":{"widthType":"fill","fixedWidth":"33vw"},"collection":"woocommerce/product-collection","queryContextIncludes":["collection"],"className":"is-style-commerce-grid"} -->
<div class="wp-block-woocommerce-product-collection is-style-commerce-grid"><!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
<div class="wp-block-group"><!-- wp:heading {"fontSize":"fluid-xxxxx-large"} -->
<h2 class="wp-block-heading has-fluid-xxxxx-large-font-size"><?php echo esc_html__( 'New Arrivals', 'aggressive-apparel' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-cta-small"} -->
<div class="wp-block-button is-style-cta-small"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/shop/?orderby=date' ) ); ?>"><?php echo esc_html__( 'View All', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|2","bottom":"var:preset|spacing|fluid-16"}}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--2);margin-bottom:var(--wp--preset--spacing--fluid-16)"><!-- wp:paragraph {"style":{"typography":{"lineHeight":"1"}},"fontSize":"small"} -->
<p class="has-small-font-size" style="line-height:1"><?php echo esc_html__( 'Fresh graphics. No recycled attitude.', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:woocommerce/product-template {"className":"is-style-default"} -->
<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|3","margin":{"bottom":"var:preset|spacing|fluid-8"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group" style="margin-bottom:var(--wp--preset--spacing--fluid-8)"><!-- wp:woocommerce/product-image {"showSaleBadge":false,"isDescendentOfQueryLoop":true,"width":"","style":{"dimensions":{"aspectRatio":"9/16"}}} -->
<!-- wp:woocommerce/product-sale-badge {"align":"right"} /-->
<!-- /wp:woocommerce/product-image -->

<!-- wp:post-title {"isLink":true,"style":{"typography":{"lineHeight":"1.4","fontStyle":"normal","fontWeight":"400","textAlign":"center"},"elements":{"link":{"color":{"text":"var:preset|color|foreground"},":hover":{"color":{"text":"var:preset|color|accent"}}}}},"fontSize":"fluid-x-large","__woocommerceNamespace":"woocommerce/product-collection/product-title"} /-->

<!-- wp:woocommerce/product-price {"isDescendentOfQueryLoop":true,"textAlign":"center","fontSize":"medium","metadata":{"ignoredHookedBlocks":[]}} /-->

<!-- wp:aggressive-apparel/product-color-swatches {"swatchSize":"xs","showTooltip":false,"swatchAlignment":"center"} /--></div>
<!-- /wp:group -->
<!-- /wp:woocommerce/product-template -->

<!-- wp:woocommerce/product-collection-no-results -->
<!-- wp:group {"layout":{"type":"flex","orientation":"vertical","justifyContent":"center","flexWrap":"wrap"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size"><strong><?php echo esc_html__( 'No results found', 'aggressive-apparel' ); ?></strong></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<!-- /wp:woocommerce/product-collection-no-results --></div>
<!-- /wp:woocommerce/product-collection --></div>
<!-- /wp:group -->

<!-- wp:columns {"align":"full","style":{"spacing":{"blockGap":{"top":"0","left":"var:preset|spacing|0"},"margin":{"top":"0","bottom":"0"}}}} -->
<div class="wp-block-columns alignfull" style="margin-top:0;margin-bottom:0"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:cover {"url":"https://theaggressive.com/wp-content/uploads/2026/07/mens-collection.webp","id":7649,"alt":"<?php echo esc_attr__( 'Man with tattoos on a motorcycle wearing a white t-shirt featuring a skull-patterned letter A graphic', 'aggressive-apparel' ); ?>","dimRatio":40,"overlayColor":"black","isUserOverlayColor":true,"focalPoint":{"x":0.47,"y":0.32},"minHeight":65,"minHeightUnit":"vh","contentPosition":"center center","sizeSlug":"full","className":"is-dark","style":{"color":{"background":"var:preset|color|black"}}} -->
<div class="wp-block-cover is-dark has-background" style="background-color:var(--wp--preset--color--black);min-height:65vh"><img class="wp-block-cover__image-background wp-image-7649 size-full" alt="<?php echo esc_attr__( 'Man with tattoos on a motorcycle wearing a white t-shirt featuring a skull-patterned letter A graphic', 'aggressive-apparel' ); ?>" src="https://theaggressive.com/wp-content/uploads/2026/07/mens-collection.webp" style="object-position:47% 32%" data-object-fit="cover" data-object-position="47% 32%"/><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-40 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|1"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:heading {"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"800","textAlign":"center"}},"textColor":"white","fontSize":"fluid-xxxxx-large"} -->
<h2 class="wp-block-heading has-text-align-center has-white-color has-text-color has-fluid-xxxxx-large-font-size" style="font-style:normal;font-weight:800;text-transform:uppercase"><?php echo esc_html__( 'Men\'s', 'aggressive-apparel' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|8"}}},"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--8)"><!-- wp:button {"className":"is-style-cta-outline-on-dark"} -->
<div class="wp-block-button is-style-cta-outline-on-dark"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/shop/?collection=mens' ) ); ?>"><?php echo esc_html__( 'Shop Men\'s', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div></div>
<!-- /wp:cover --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:cover {"url":"https://theaggressive.com/wp-content/uploads/2026/07/womens-collection.webp","id":7650,"alt":"<?php echo esc_attr__( 'Woman wearing a black t-shirt with the word AGGRESSIVE in a spiky metal-style font', 'aggressive-apparel' ); ?>","dimRatio":40,"overlayColor":"black","isUserOverlayColor":true,"focalPoint":{"x":0.52,"y":0.37},"minHeight":65,"minHeightUnit":"vh","contentPosition":"center center","sizeSlug":"full","className":"is-dark","style":{"color":{"background":"var:preset|color|surface-elevated"}}} -->
<div class="wp-block-cover is-dark has-background" style="background-color:var(--wp--preset--color--surface-elevated);min-height:65vh"><img class="wp-block-cover__image-background wp-image-7650 size-full" alt="<?php echo esc_attr__( 'Woman wearing a black t-shirt with the word AGGRESSIVE in a spiky metal-style font', 'aggressive-apparel' ); ?>" src="https://theaggressive.com/wp-content/uploads/2026/07/womens-collection.webp" style="object-position:52% 37%" data-object-fit="cover" data-object-position="52% 37%"/><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-40 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|1"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:heading {"style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"800","textAlign":"center"}},"textColor":"white","fontSize":"fluid-xxxxx-large"} -->
<h2 class="wp-block-heading has-text-align-center has-white-color has-text-color has-fluid-xxxxx-large-font-size" style="font-style:normal;font-weight:800;text-transform:uppercase"><?php echo esc_html__( 'Women\'s', 'aggressive-apparel' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|8"}}},"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--8)"><!-- wp:button {"className":"is-style-cta-outline-on-dark"} -->
<div class="wp-block-button is-style-cta-outline-on-dark"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/shop/?collection=womens' ) ); ?>"><?php echo esc_html__( 'Shop Women\'s', 'aggressive-apparel' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div></div>
<!-- /wp:cover --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|fluid-24","bottom":"var:preset|spacing|fluid-24"}}},"layout":{"type":"constrained","contentSize":"720px"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--fluid-24);margin-bottom:var(--wp--preset--spacing--fluid-24)"><!-- wp:paragraph {"className":"has-accent-color has-text-color","style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.1em","fontStyle":"normal","fontWeight":"600","textAlign":"center"}},"textColor":"accent","fontSize":"small"} -->
<p class="has-text-align-center has-accent-color has-text-color has-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.1em;text-transform:uppercase">THE AGGRESSIVE</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}},"typography":{"textAlign":"center"}},"fontSize":"fluid-xxxxxx-large"} -->
<h1 class="wp-block-heading has-text-align-center has-fluid-xxxxxx-large-font-size" style="margin-top:var(--wp--preset--spacing--4)"><?php echo esc_html__( 'For the Unruled', 'aggressive-apparel' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|8"}},"typography":{"lineHeight":"1.8","textAlign":"center"}},"fontSize":"medium"} -->
<p class="has-text-align-center has-medium-font-size" style="margin-top:var(--wp--preset--spacing--8);line-height:1.8"><?php echo esc_html__( 'The Aggressive is independent graphic apparel shaped by punk, metal, skate and motocross culture. Designed for people who carry their edge naturally—not people trying to manufacture one.', 'aggressive-apparel' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
