<?php
/**
 * Title: Founder Story
 * Description: Founder section with photo, quote and origin story. Every bracketed line is a prompt: replace it with the founder's real words and history.
 * Slug: aggressive-apparel/founder-story
 * Categories: aggressive, aggressive-informational, aggressive-homepage
 * Keywords: founder, team, story, personal, about, authentic, human
 * Viewport Width: 1200
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|24","bottom":"var:preset|spacing|24"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--24);padding-bottom:var(--wp--preset--spacing--24)">
	<!-- wp:columns {"verticalAlignment":"center","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|16"}}}} -->
	<div class="wp-block-columns are-vertically-aligned-center">
		<!-- wp:column {"verticalAlignment":"center","width":"55%"} -->
		<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:55%">
			<!-- wp:cover {"dimRatio":0,"minHeight":600,"minHeightUnit":"px","contentPosition":"center center","isDark":false,"style":{"color":{"background":"var:preset|color|surface-elevated"}}} -->
			<div class="wp-block-cover is-light has-custom-content-position is-position-center-center has-background" style="background-color:var(--wp--preset--color--surface-elevated);min-height:600px"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim"></span><div class="wp-block-cover__inner-container">
				<!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.08em","fontStyle":"normal","fontWeight":"600"}},"textColor":"foreground-muted","fontSize":"x-small"} -->
				<p class="has-text-align-center has-foreground-muted-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.08em;text-transform:uppercase"><?php echo esc_html__( 'Add founder photo', 'aggressive-apparel' ); ?></p>
				<!-- /wp:paragraph -->
			</div></div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"verticalAlignment":"center","width":"45%"} -->
		<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:45%">
			<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.1em","fontStyle":"normal","fontWeight":"600"}},"textColor":"accent","fontSize":"x-small"} -->
			<p class="has-accent-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.1em;text-transform:uppercase"><?php echo esc_html__( 'Meet the Founder', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}}},"fontSize":"fluid-xxxx-large"} -->
			<h2 class="wp-block-heading has-fluid-xxxx-large-font-size" style="margin-top:var(--wp--preset--spacing--4)"><?php echo esc_html__( '[Founder name]', 'aggressive-apparel' ); ?></h2>
			<!-- /wp:heading -->

			<!-- wp:quote {"style":{"spacing":{"margin":{"top":"var:preset|spacing|8"}},"typography":{"fontStyle":"italic","lineHeight":"1.7"}},"fontSize":"large"} -->
			<blockquote class="wp-block-quote has-large-font-size" style="margin-top:var(--wp--preset--spacing--8);font-style:italic;line-height:1.7"><p><?php echo esc_html__( '"[A line from the founder, in their own words.]"', 'aggressive-apparel' ); ?></p></blockquote>
			<!-- /wp:quote -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}},"typography":{"lineHeight":"1.8"}},"textColor":"foreground-muted","fontSize":"medium"} -->
			<p class="has-foreground-muted-color has-text-color has-medium-font-size" style="margin-top:var(--wp--preset--spacing--6);line-height:1.8"><?php echo esc_html__( '[How the label started, in two or three sentences. Real history only: no invented dates, numbers or anecdotes.]', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:separator {"style":{"spacing":{"margin":{"top":"var:preset|spacing|8","bottom":"var:preset|spacing|6"}}},"className":"is-style-wide"} -->
			<hr class="wp-block-separator has-alpha-channel-opacity is-style-wide" style="margin-top:var(--wp--preset--spacing--8);margin-bottom:var(--wp--preset--spacing--6)"/>
			<!-- /wp:separator -->

			<!-- wp:paragraph {"style":{"typography":{"fontStyle":"italic","fontWeight":"600"}},"fontSize":"medium"} -->
			<p class="has-medium-font-size" style="font-style:italic;font-weight:600"><?php echo esc_html__( '— [Founder name], [role]', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
