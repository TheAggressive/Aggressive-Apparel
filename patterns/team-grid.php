<?php
/**
 * Title: Team Grid
 * Description: Meet the team section with photo cards, names, roles, and social links.
 * Slug: aggressive-apparel/team-grid
 * Categories: aggressive, aggressive-apparel, aggressive-informational
 * Keywords: team, people, staff, about, grid, members
 * Viewport Width: 1200
 *
 * @package Aggressive_Apparel
 */

$placeholder_svg = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQ4MCIgdmlld0JveD0iMCAwIDQwMCA0ODAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjQwMCIgaGVpZ2h0PSI0ODAiIGZpbGw9IiNlNWU3ZWIiLz48Y2lyY2xlIGN4PSIyMDAiIGN5PSIxODAiIHI9IjYwIiBmaWxsPSIjOWNhM2FmIi8+PHBhdGggZD0iTTIwMCAyNTBjLTY2IDAtMTEwIDI0LTExMCA1NHYyNmgyMjB2LTI2YzAtMzAtNDQtNTQtMTEwLTU0eiIgZmlsbD0iIzljYTNhZiIvPjwvc3ZnPg==';

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20)">
	<!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.1em","fontStyle":"normal","fontWeight":"600"}},"textColor":"red","fontSize":"x-small"} -->
	<p class="has-text-align-center has-red-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.1em;text-transform:uppercase">The Crew</p>
	<!-- /wp:paragraph -->

	<!-- wp:heading {"textAlign":"center","style":{"spacing":{"margin":{"top":"var:preset|spacing|4","bottom":"var:preset|spacing|14"}}},"fontSize":"fluid-xxxx-large"} -->
	<h2 class="wp-block-heading has-text-align-center has-fluid-xxxx-large-font-size" style="margin-top:var(--wp--preset--spacing--4);margin-bottom:var(--wp--preset--spacing--14)">Meet the Team</h2>
	<!-- /wp:heading -->

	<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|8"}}}} -->
	<div class="wp-block-columns">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:image {"aspectRatio":"5/6","scale":"cover","style":{"border":{"radius":"8px"}}} -->
			<figure class="wp-block-image" style="border-radius:8px"><img src="<?php echo esc_url( $placeholder_svg ); ?>" alt="Team member" style="aspect-ratio:5/6;object-fit:cover"/></figure>
			<!-- /wp:image -->

			<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}},"typography":{"fontStyle":"normal","fontWeight":"700"}},"fontSize":"medium"} -->
			<h3 class="wp-block-heading has-medium-font-size" style="margin-top:var(--wp--preset--spacing--6);font-style:normal;font-weight:700">Marcus Chen</h3>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.05em","fontStyle":"normal","fontWeight":"600"},"spacing":{"margin":{"top":"var:preset|spacing|1"}}},"fontSize":"x-small"} -->
			<p class="has-x-small-font-size" style="margin-top:var(--wp--preset--spacing--1);font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase">Founder &amp; Creative Director</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:image {"aspectRatio":"5/6","scale":"cover","style":{"border":{"radius":"8px"}}} -->
			<figure class="wp-block-image" style="border-radius:8px"><img src="<?php echo esc_url( $placeholder_svg ); ?>" alt="Team member" style="aspect-ratio:5/6;object-fit:cover"/></figure>
			<!-- /wp:image -->

			<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}},"typography":{"fontStyle":"normal","fontWeight":"700"}},"fontSize":"medium"} -->
			<h3 class="wp-block-heading has-medium-font-size" style="margin-top:var(--wp--preset--spacing--6);font-style:normal;font-weight:700">Priya Sharma</h3>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.05em","fontStyle":"normal","fontWeight":"600"},"spacing":{"margin":{"top":"var:preset|spacing|1"}}},"fontSize":"x-small"} -->
			<p class="has-x-small-font-size" style="margin-top:var(--wp--preset--spacing--1);font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase">Head of Design</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:image {"aspectRatio":"5/6","scale":"cover","style":{"border":{"radius":"8px"}}} -->
			<figure class="wp-block-image" style="border-radius:8px"><img src="<?php echo esc_url( $placeholder_svg ); ?>" alt="Team member" style="aspect-ratio:5/6;object-fit:cover"/></figure>
			<!-- /wp:image -->

			<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}},"typography":{"fontStyle":"normal","fontWeight":"700"}},"fontSize":"medium"} -->
			<h3 class="wp-block-heading has-medium-font-size" style="margin-top:var(--wp--preset--spacing--6);font-style:normal;font-weight:700">Dex Williams</h3>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.05em","fontStyle":"normal","fontWeight":"600"},"spacing":{"margin":{"top":"var:preset|spacing|1"}}},"fontSize":"x-small"} -->
			<p class="has-x-small-font-size" style="margin-top:var(--wp--preset--spacing--1);font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase">Production Manager</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:image {"aspectRatio":"5/6","scale":"cover","style":{"border":{"radius":"8px"}}} -->
			<figure class="wp-block-image" style="border-radius:8px"><img src="<?php echo esc_url( $placeholder_svg ); ?>" alt="Team member" style="aspect-ratio:5/6;object-fit:cover"/></figure>
			<!-- /wp:image -->

			<!-- wp:heading {"level":3,"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}},"typography":{"fontStyle":"normal","fontWeight":"700"}},"fontSize":"medium"} -->
			<h3 class="wp-block-heading has-medium-font-size" style="margin-top:var(--wp--preset--spacing--6);font-style:normal;font-weight:700">Ava Torres</h3>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.05em","fontStyle":"normal","fontWeight":"600"},"spacing":{"margin":{"top":"var:preset|spacing|1"}}},"fontSize":"x-small"} -->
			<p class="has-x-small-font-size" style="margin-top:var(--wp--preset--spacing--1);font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase">Marketing Lead</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
