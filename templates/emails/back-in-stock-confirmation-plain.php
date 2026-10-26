<?php
/**
 * Back in Stock Confirmation Email Template (Plain Text)
 *
 * @package Aggressive_Apparel
 *
 * @var \WC_Product $product       Product object.
 * @var string      $email_heading Email heading.
 * @var string      $confirm_url   Signup confirmation URL.
 * @var \WC_Email   $email         Email object.
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";

printf(
	/* translators: %s: product name. */
	esc_html__( 'You asked us to email you when %s is back in stock. Please confirm this was you:', 'aggressive-apparel' ),
	esc_html( $product->get_name() )
);
echo "\n\n" . esc_url( $confirm_url ) . "\n\n";

echo esc_html__( 'If you did not sign up, ignore this email and you will not hear from us about this product.', 'aggressive-apparel' ) . "\n";

echo "\n" . wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
