<?php
/**
 * Back in Stock Email Template (Plain Text)
 *
 * @package Aggressive_Apparel
 * @since 1.18.0
 *
 * @var \WC_Product $product           Product object.
 * @var string      $email_heading     Email heading.
 * @var string      $unsubscribe_token Unsubscribe token.
 * @var \WC_Email   $email             Email object.
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";

echo esc_html__( 'Great news! A product you were waiting for is back in stock:', 'aggressive-apparel' ) . "\n\n";

echo esc_html( $product->get_name() ) . "\n";
echo esc_html( wp_strip_all_tags( $product->get_price_html() ) ) . "\n\n";

echo esc_html__( 'Shop now:', 'aggressive-apparel' ) . ' ' . esc_url( $product->get_permalink() ) . "\n\n";

echo "---\n\n";

$unsubscribe = add_query_arg( 'aa_unsubscribe', $unsubscribe_token, home_url() );
echo esc_html__( 'Unsubscribe:', 'aggressive-apparel' ) . ' ' . esc_url( $unsubscribe ) . "\n";

echo "\n" . wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
