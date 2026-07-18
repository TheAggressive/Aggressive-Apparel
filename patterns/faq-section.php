<?php
/**
 * Title: FAQ Section
 * Description: Two-column FAQ layout with heading and support CTA on the left, accordion questions on the right.
 * Slug: aggressive-apparel/faq-section
 * Categories: aggressive, aggressive-apparel, aggressive-informational
 * Keywords: faq, questions, answers, help, support, accordion
 * Viewport Width: 1200
 *
 * @package Aggressive_Apparel
 */

?><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|24","bottom":"var:preset|spacing|24"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--24);padding-bottom:var(--wp--preset--spacing--24)">
	<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|14"}}}} -->
	<div class="wp-block-columns">
		<!-- wp:column {"width":"35%"} -->
		<div class="wp-block-column" style="flex-basis:35%">
			<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.1em","fontStyle":"normal","fontWeight":"600"}},"textColor":"accent","fontSize":"x-small"} -->
			<p class="has-accent-color has-text-color has-x-small-font-size" style="font-style:normal;font-weight:600;letter-spacing:0.1em;text-transform:uppercase"><?php echo esc_html__( 'Support', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"style":{"spacing":{"margin":{"top":"var:preset|spacing|4"}}},"fontSize":"fluid-xxxx-large"} -->
			<h2 class="wp-block-heading has-fluid-xxxx-large-font-size" style="margin-top:var(--wp--preset--spacing--4)"><?php echo esc_html__( 'Frequently Asked Questions', 'aggressive-apparel' ); ?></h2>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|6"}},"typography":{"lineHeight":"1.8"}},"fontSize":"medium"} -->
			<p class="has-medium-font-size" style="margin-top:var(--wp--preset--spacing--6);line-height:1.8"><?php echo esc_html__( 'Can\'t find what you\'re looking for? Reach out to our support team.', 'aggressive-apparel' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|8"}}}} -->
			<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--8)">
				<!-- wp:button {"backgroundColor":"black","textColor":"white","style":{"typography":{"fontStyle":"normal","fontWeight":"600","textTransform":"uppercase","letterSpacing":"0.05em"},"spacing":{"padding":{"top":"var:preset|spacing|3","bottom":"var:preset|spacing|3","left":"var:preset|spacing|8","right":"var:preset|spacing|8"}}}} -->
				<div class="wp-block-button"><a class="wp-block-button__link has-white-color has-black-background-color has-text-color has-background wp-element-button" style="padding-top:var(--wp--preset--spacing--3);padding-right:var(--wp--preset--spacing--8);padding-bottom:var(--wp--preset--spacing--3);padding-left:var(--wp--preset--spacing--8);font-style:normal;font-weight:600;letter-spacing:0.05em;text-transform:uppercase" href="/contact"><?php echo esc_html__( 'Contact Support', 'aggressive-apparel' ); ?></a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"width":"65%"} -->
		<div class="wp-block-column" style="flex-basis:65%">
			<!-- wp:details {"style":{"spacing":{"padding":{"top":"var:preset|spacing|6","bottom":"var:preset|spacing|6"}},"border":{"bottom":{"color":"var:preset|color|border","width":"1px"}}}} -->
			<details class="wp-block-details" style="border-bottom-color:var(--wp--preset--color--border);border-bottom-width:1px;padding-top:var(--wp--preset--spacing--6);padding-bottom:var(--wp--preset--spacing--6)">
				<summary><?php echo esc_html__( 'What is your shipping policy?', 'aggressive-apparel' ); ?></summary>
				<!-- wp:paragraph {"fontSize":"medium"} -->
				<p class="has-medium-font-size"><?php echo esc_html__( 'We offer free standard shipping on all orders over $100. Standard delivery takes 3–5 business days. Express shipping is available at checkout for 1–2 business day delivery.', 'aggressive-apparel' ); ?></p>
				<!-- /wp:paragraph -->
			</details>
			<!-- /wp:details -->

			<!-- wp:details {"style":{"spacing":{"padding":{"top":"var:preset|spacing|6","bottom":"var:preset|spacing|6"}},"border":{"bottom":{"color":"var:preset|color|border","width":"1px"}}}} -->
			<details class="wp-block-details" style="border-bottom-color:var(--wp--preset--color--border);border-bottom-width:1px;padding-top:var(--wp--preset--spacing--6);padding-bottom:var(--wp--preset--spacing--6)">
				<summary><?php echo esc_html__( 'What is your return policy?', 'aggressive-apparel' ); ?></summary>
				<!-- wp:paragraph {"fontSize":"medium"} -->
				<p class="has-medium-font-size"><?php echo esc_html__( 'We accept returns within 30 days of purchase. Items must be unworn with original tags attached. Refunds are processed within 5–7 business days of receiving the return.', 'aggressive-apparel' ); ?></p>
				<!-- /wp:paragraph -->
			</details>
			<!-- /wp:details -->

			<!-- wp:details {"style":{"spacing":{"padding":{"top":"var:preset|spacing|6","bottom":"var:preset|spacing|6"}},"border":{"bottom":{"color":"var:preset|color|border","width":"1px"}}}} -->
			<details class="wp-block-details" style="border-bottom-color:var(--wp--preset--color--border);border-bottom-width:1px;padding-top:var(--wp--preset--spacing--6);padding-bottom:var(--wp--preset--spacing--6)">
				<summary><?php echo esc_html__( 'How do I find my size?', 'aggressive-apparel' ); ?></summary>
				<!-- wp:paragraph {"fontSize":"medium"} -->
				<p class="has-medium-font-size"><?php echo esc_html__( 'Check our size guide on each product page for detailed measurements. We recommend measuring yourself and comparing against our charts. If you\'re between sizes, we suggest sizing up for a more relaxed fit.', 'aggressive-apparel' ); ?></p>
				<!-- /wp:paragraph -->
			</details>
			<!-- /wp:details -->

			<!-- wp:details {"style":{"spacing":{"padding":{"top":"var:preset|spacing|6","bottom":"var:preset|spacing|6"}},"border":{"bottom":{"color":"var:preset|color|border","width":"1px"}}}} -->
			<details class="wp-block-details" style="border-bottom-color:var(--wp--preset--color--border);border-bottom-width:1px;padding-top:var(--wp--preset--spacing--6);padding-bottom:var(--wp--preset--spacing--6)">
				<summary><?php echo esc_html__( 'How should I care for my apparel?', 'aggressive-apparel' ); ?></summary>
				<!-- wp:paragraph {"fontSize":"medium"} -->
				<p class="has-medium-font-size"><?php echo esc_html__( 'Machine wash cold with like colors, tumble dry low. Avoid bleach and ironing directly on prints. Following these care instructions will keep your gear looking fresh for years.', 'aggressive-apparel' ); ?></p>
				<!-- /wp:paragraph -->
			</details>
			<!-- /wp:details -->

			<!-- wp:details {"style":{"spacing":{"padding":{"top":"var:preset|spacing|6","bottom":"var:preset|spacing|6"}},"border":{"bottom":{"color":"var:preset|color|border","width":"1px"}}}} -->
			<details class="wp-block-details" style="border-bottom-color:var(--wp--preset--color--border);border-bottom-width:1px;padding-top:var(--wp--preset--spacing--6);padding-bottom:var(--wp--preset--spacing--6)">
				<summary><?php echo esc_html__( 'Do you ship internationally?', 'aggressive-apparel' ); ?></summary>
				<!-- wp:paragraph {"fontSize":"medium"} -->
				<p class="has-medium-font-size"><?php echo esc_html__( 'Yes, we ship to over 50 countries worldwide. International shipping rates and delivery times vary by destination. You\'ll see the exact cost at checkout.', 'aggressive-apparel' ); ?></p>
				<!-- /wp:paragraph -->
			</details>
			<!-- /wp:details -->

			<!-- wp:details {"style":{"spacing":{"padding":{"top":"var:preset|spacing|6","bottom":"var:preset|spacing|6"}},"border":{"bottom":{"color":"var:preset|color|border","width":"1px"}}}} -->
			<details class="wp-block-details" style="border-bottom-color:var(--wp--preset--color--border);border-bottom-width:1px;padding-top:var(--wp--preset--spacing--6);padding-bottom:var(--wp--preset--spacing--6)">
				<summary><?php echo esc_html__( 'Can I cancel or modify my order?', 'aggressive-apparel' ); ?></summary>
				<!-- wp:paragraph {"fontSize":"medium"} -->
				<p class="has-medium-font-size"><?php echo esc_html__( 'Orders can be modified or cancelled within 1 hour of placement. After that, orders enter our fulfillment process. Contact support immediately if you need changes.', 'aggressive-apparel' ); ?></p>
				<!-- /wp:paragraph -->
			</details>
			<!-- /wp:details -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
