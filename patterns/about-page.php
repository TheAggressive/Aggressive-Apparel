<?php
/**
 * Title: About Page
 * Description: Full about page layout with brand intro, mission statement, values grid, and team CTA.
 * Slug: aggressive-apparel/about-page
 * Categories: aggressive, aggressive-informational
 * Keywords: about, page, story, mission, values, brand, company
 * Viewport Width: 1200
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|24","bottom":"var:preset|spacing|10"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained","contentSize":"720px"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--24);padding-bottom:var(--wp--preset--spacing--10)">
	<!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.1em","fontStyle":"normal","fontWeight":"600"}},"textColor":"accent","fontSize":"x-small"} -->
	<p class="has-text-align-center has-accent-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.1em;text-transform:uppercase"><?php echo esc_html__( 'Our Story', 'aggressive-apparel' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:heading {"textAlign":"center","level":1,"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}}},"fontSize":"fluid-xxxx-large"} -->
	<h1 class="wp-block-heading has-text-align-center has-fluid-xxxx-large-font-size" style="margin-top:var(--wp--preset--spacing--4)"><?php echo esc_html__( 'Original artwork. Independent since 2010.', 'aggressive-apparel' ); ?></h1>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|8"}},"typography":{"lineHeight":"1.8"}},"fontSize":"medium"} -->
	<p class="has-text-align-center has-medium-font-size" style="margin-top:var(--wp--preset--spacing--8);line-height:1.8"><?php echo esc_html__( 'The Aggressive is an independent label built on original artwork. Skate, punk, tattoo and moto culture shaped where we came from; the artwork is where we are going.', 'aggressive-apparel' ); ?></p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|14","bottom":"var:preset|spacing|14"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--14);padding-bottom:var(--wp--preset--spacing--14)">
	<!-- wp:columns {"verticalAlignment":"center","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|14"}}}} -->
	<div class="wp-block-columns are-vertically-aligned-center">
		<!-- wp:column {"verticalAlignment":"center","width":"50%"} -->
		<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:50%">
			<!-- wp:image {"aspectRatio":"4/5","scale":"cover","style":{"border":{"radius":"var(--wp--custom--radius--card)"}},"className":"about-hero-image"} -->
			<figure class="wp-block-image has-custom-border about-hero-image"><img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAwIiBoZWlnaHQ9Ijc1MCIgdmlld0JveD0iMCAwIDYwMCA3NTAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjYwMCIgaGVpZ2h0PSI3NTAiIGZpbGw9IiMxYTFhMWEiLz48dGV4dCB4PSIzMDAiIHk9IjM3NSIgZm9udC1mYW1pbHk9InN5c3RlbS11aSIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzY2NiIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZG9taW5hbnQtYmFzZWxpbmU9Im1pZGRsZSI+UmVwbGFjZSB3aXRoIGJyYW5kIHBob3RvPC90ZXh0Pjwvc3ZnPg==" alt="Brand story" style="border-radius:var(--wp--custom--radius--card);aspect-ratio:4/5;object-fit:cover"/></figure>
			<!-- /wp:image -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"verticalAlignment":"center","width":"50%"} -->
		<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:50%">
			<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.1em","fontStyle":"normal","fontWeight":"600"}},"textColor":"accent","fontSize":"x-small"} -->
			<p class="has-accent-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.1em;text-transform:uppercase"><?php echo esc_html__( 'What we make', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}}},"fontSize":"fluid-xxx-large"} -->
			<h2 class="wp-block-heading has-fluid-xxx-large-font-size" style="margin-top:var(--wp--preset--spacing--4)"><?php echo esc_html__( 'Art first. Garment second.', 'aggressive-apparel' ); ?></h2>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}},"typography":{"lineHeight":"1.8"}},"fontSize":"medium"} -->
			<p class="has-medium-font-size" style="margin-top:var(--wp--preset--spacing--6);line-height:1.8"><?php echo esc_html__( 'Every design starts as an original drawing. The garment is how it leaves the studio.', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}},"typography":{"lineHeight":"1.8"}},"fontSize":"medium"} -->
			<p class="has-medium-font-size" style="margin-top:var(--wp--preset--spacing--6);line-height:1.8"><?php echo esc_html__( '[Tell customers how your pieces are made: materials, printing, sourcing. State only what you can verify.]', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"},"margin":{"top":"0","bottom":"0"}},"border":{"top":{"color":"var:preset|color|border","width":"1px"},"bottom":{"color":"var:preset|color|border","width":"1px"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="border-top-color:var(--wp--preset--color--border);border-top-width:1px;border-bottom-color:var(--wp--preset--color--border);border-bottom-width:1px;margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20)">
	<!-- wp:heading {"textAlign":"center","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|14"}}},"fontSize":"fluid-xxx-large"} -->
	<h2 class="wp-block-heading has-text-align-center has-fluid-xxx-large-font-size" style="margin-bottom:var(--wp--preset--spacing--14)"><?php echo esc_html__( 'What we stand on', 'aggressive-apparel' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|10"}}}} -->
	<div class="wp-block-columns">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"textAlign":"center","level":3,"fontSize":"xx-large"} -->
			<h3 class="wp-block-heading has-text-align-center has-xx-large-font-size">01</h3>
			<!-- /wp:heading -->

			<!-- wp:heading {"textAlign":"center","level":4,"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}},"typography":{"textTransform":"uppercase","letterSpacing":"0.05em"}},"fontSize":"small"} -->
			<h4 class="wp-block-heading has-text-align-center has-small-font-size" style="margin-top:var(--wp--preset--spacing--4);letter-spacing:0.05em;text-transform:uppercase"><?php echo esc_html__( 'Materials', 'aggressive-apparel' ); ?></h4>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}},"typography":{"lineHeight":"1.7"}},"fontSize":"small"} -->
			<p class="has-text-align-center has-small-font-size" style="margin-top:var(--wp--preset--spacing--4);line-height:1.7"><?php echo esc_html__( '[List the fabrics and weights you use. State only what you can verify.]', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"textAlign":"center","level":3,"fontSize":"xx-large"} -->
			<h3 class="wp-block-heading has-text-align-center has-xx-large-font-size">02</h3>
			<!-- /wp:heading -->

			<!-- wp:heading {"textAlign":"center","level":4,"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}},"typography":{"textTransform":"uppercase","letterSpacing":"0.05em"}},"fontSize":"small"} -->
			<h4 class="wp-block-heading has-text-align-center has-small-font-size" style="margin-top:var(--wp--preset--spacing--4);letter-spacing:0.05em;text-transform:uppercase"><?php echo esc_html__( 'How it is made', 'aggressive-apparel' ); ?></h4>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}},"typography":{"lineHeight":"1.7"}},"fontSize":"small"} -->
			<p class="has-text-align-center has-small-font-size" style="margin-top:var(--wp--preset--spacing--4);line-height:1.7"><?php echo esc_html__( '[Describe your production and print partners. Leave out anything you cannot document.]', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"textAlign":"center","level":3,"fontSize":"xx-large"} -->
			<h3 class="wp-block-heading has-text-align-center has-xx-large-font-size">03</h3>
			<!-- /wp:heading -->

			<!-- wp:heading {"textAlign":"center","level":4,"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}},"typography":{"textTransform":"uppercase","letterSpacing":"0.05em"}},"fontSize":"small"} -->
			<h4 class="wp-block-heading has-text-align-center has-small-font-size" style="margin-top:var(--wp--preset--spacing--4);letter-spacing:0.05em;text-transform:uppercase"><?php echo esc_html__( 'Direct from us', 'aggressive-apparel' ); ?></h4>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}},"typography":{"lineHeight":"1.7"}},"fontSize":"small"} -->
			<p class="has-text-align-center has-small-font-size" style="margin-top:var(--wp--preset--spacing--4);line-height:1.7"><?php echo esc_html__( 'Designed and sold by The Aggressive.', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"textAlign":"center","level":3,"fontSize":"xx-large"} -->
			<h3 class="wp-block-heading has-text-align-center has-xx-large-font-size">04</h3>
			<!-- /wp:heading -->

			<!-- wp:heading {"textAlign":"center","level":4,"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}},"typography":{"textTransform":"uppercase","letterSpacing":"0.05em"}},"fontSize":"small"} -->
			<h4 class="wp-block-heading has-text-align-center has-small-font-size" style="margin-top:var(--wp--preset--spacing--4);letter-spacing:0.05em;text-transform:uppercase"><?php echo esc_html__( 'Who we draw for', 'aggressive-apparel' ); ?></h4>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}},"typography":{"lineHeight":"1.7"}},"fontSize":"small"} -->
			<p class="has-text-align-center has-small-font-size" style="margin-top:var(--wp--preset--spacing--4);line-height:1.7"><?php echo esc_html__( 'The culture we came from: skaters, punks, tattooers and riders.', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
