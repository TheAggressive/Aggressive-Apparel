<?php
/**
 * Title: Footer with Columns
 * Description: Multi-column footer with navigation links, contact info, newsletter signup, and social icons.
 * Slug: aggressive-apparel/footer-columns
 * Categories: aggressive, aggressive-apparel, footer
 * Keywords: footer, columns, navigation, newsletter, contact, social
 * Block Types: core/template-part/footer
 * Viewport Width: 1400
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|14"},"margin":{"top":"0","bottom":"0"}}},"backgroundColor":"black","textColor":"white","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-white-color has-black-background-color has-text-color has-background" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--14)">
	<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|12"}}}} -->
	<div class="wp-block-columns">
		<!-- wp:column {"width":"35%"} -->
		<div class="wp-block-column" style="flex-basis:35%">
			<!-- wp:site-title {"level":0,"style":{"typography":{"fontStyle":"normal","fontWeight":"700","textTransform":"uppercase","letterSpacing":"0.05em"}},"textColor":"white","fontSize":"large"} /-->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}},"typography":{"lineHeight":"1.8"}},"textColor":"white","fontSize":"small"} -->
			<p class="has-white-color has-text-color has-small-font-size" style="margin-top:var(--wp--preset--spacing--6);line-height:1.8"><?php echo esc_html__( 'Premium streetwear crafted for those who refuse to blend in. Built tough, designed bold.', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:social-links {"iconColor":"white","iconColorValue":"var(--wp--preset--color--white)","size":"has-small-icon-size","style":{"spacing":{"margin":{"top":"var:preset|spacing|8"},"blockGap":{"left":"var:preset|spacing|4"}}},"className":"is-style-logos-only"} -->
			<ul class="wp-block-social-links has-small-icon-size has-icon-color is-style-logos-only" style="margin-top:var(--wp--preset--spacing--8)">
				<!-- wp:social-link {"url":"#","service":"instagram"} /-->
				<!-- wp:social-link {"url":"#","service":"x"} /-->
				<!-- wp:social-link {"url":"#","service":"tiktok"} /-->
				<!-- wp:social-link {"url":"#","service":"facebook"} /-->
				<!-- wp:social-link {"url":"#","service":"youtube"} /-->
			</ul>
			<!-- /wp:social-links -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"style":{"typography":{"fontStyle":"normal","fontWeight":"700","textTransform":"uppercase","letterSpacing":"0.05em"}},"textColor":"white","fontSize":"x-small"} -->
			<h3 class="wp-block-heading has-white-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:700;letter-spacing:0.05em;text-transform:uppercase"><?php echo esc_html__( 'Shop', 'aggressive-apparel' ); ?></h3>
			<!-- /wp:heading -->

			<!-- wp:list {"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}},"typography":{"lineHeight":"2.2"}},"fontSize":"small"} -->
			<ul class="has-small-font-size" style="margin-top:var(--wp--preset--spacing--6);line-height:2.2">
				<li><a href="/shop"><?php echo esc_html__( 'All Products', 'aggressive-apparel' ); ?></a></li>
				<li><a href="#"><?php echo esc_html__( 'New Arrivals', 'aggressive-apparel' ); ?></a></li>
				<li><a href="#"><?php echo esc_html__( 'Best Sellers', 'aggressive-apparel' ); ?></a></li>
				<li><a href="#"><?php echo esc_html__( 'Sale', 'aggressive-apparel' ); ?></a></li>
				<li><a href="#"><?php echo esc_html__( 'Gift Cards', 'aggressive-apparel' ); ?></a></li>
			</ul>
			<!-- /wp:list -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"style":{"typography":{"fontStyle":"normal","fontWeight":"700","textTransform":"uppercase","letterSpacing":"0.05em"}},"textColor":"white","fontSize":"x-small"} -->
			<h3 class="wp-block-heading has-white-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:700;letter-spacing:0.05em;text-transform:uppercase"><?php echo esc_html__( 'Company', 'aggressive-apparel' ); ?></h3>
			<!-- /wp:heading -->

			<!-- wp:list {"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}},"typography":{"lineHeight":"2.2"}},"fontSize":"small"} -->
			<ul class="has-small-font-size" style="margin-top:var(--wp--preset--spacing--6);line-height:2.2">
				<li><a href="#"><?php echo esc_html__( 'About Us', 'aggressive-apparel' ); ?></a></li>
				<li><a href="#"><?php echo esc_html__( 'Sustainability', 'aggressive-apparel' ); ?></a></li>
				<li><a href="#"><?php echo esc_html__( 'Careers', 'aggressive-apparel' ); ?></a></li>
				<li><a href="#"><?php echo esc_html__( 'Press', 'aggressive-apparel' ); ?></a></li>
				<li><a href="/contact"><?php echo esc_html__( 'Contact', 'aggressive-apparel' ); ?></a></li>
			</ul>
			<!-- /wp:list -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"style":{"typography":{"fontStyle":"normal","fontWeight":"700","textTransform":"uppercase","letterSpacing":"0.05em"}},"textColor":"white","fontSize":"x-small"} -->
			<h3 class="wp-block-heading has-white-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:700;letter-spacing:0.05em;text-transform:uppercase"><?php echo esc_html__( 'Help', 'aggressive-apparel' ); ?></h3>
			<!-- /wp:heading -->

			<!-- wp:list {"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}},"typography":{"lineHeight":"2.2"}},"fontSize":"small"} -->
			<ul class="has-small-font-size" style="margin-top:var(--wp--preset--spacing--6);line-height:2.2">
				<li><a href="#"><?php echo esc_html__( 'Shipping &amp; Returns', 'aggressive-apparel' ); ?></a></li>
				<li><a href="#"><?php echo esc_html__( 'Size Guide', 'aggressive-apparel' ); ?></a></li>
				<li><a href="#"><?php echo esc_html__( 'FAQ', 'aggressive-apparel' ); ?></a></li>
				<li><a href="#"><?php echo esc_html__( 'Track Order', 'aggressive-apparel' ); ?></a></li>
				<li><a href="#"><?php echo esc_html__( 'Privacy Policy', 'aggressive-apparel' ); ?></a></li>
			</ul>
			<!-- /wp:list -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->

	<!-- wp:separator {"style":{"spacing":{"margin":{"top":"var:preset|spacing|14","bottom":"var:preset|spacing|8"}}},"backgroundColor":"white","className":"is-style-wide"} -->
	<hr class="wp-block-separator has-text-color has-white-color has-alpha-channel-opacity has-white-background-color has-background is-style-wide" style="margin-top:var(--wp--preset--spacing--14);margin-bottom:var(--wp--preset--spacing--8)"/>
	<!-- /wp:separator -->

	<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"center"}} -->
	<div class="wp-block-group">
		<!-- wp:aggressive-apparel/copyright {"ownerSource":"legal_name","legalEntity":"LLC","prefix":"\u00a9","suffix":". All rights reserved.","showLegalLinks":true,"showSchema":true,"textAlign":"center","style":{"typography":{"fontSize":"0.75rem"}},"textColor":"white"} /-->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
