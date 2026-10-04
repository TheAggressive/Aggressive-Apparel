<?php
/**
 * My Account dashboard — replaces WooCommerce's myaccount/dashboard.php.
 *
 * Swapped in by Account_Page::locate_template(); keeps WooCommerce's hooks.
 *
 * @package Aggressive_Apparel
 *
 * @var WP_User $current_user Passed by WooCommerce.
 */

defined( 'ABSPATH' ) || exit;

if ( isset( $current_user ) && $current_user instanceof WP_User ) {
	\Aggressive_Apparel\WooCommerce\Account_Dashboard::render( $current_user );
}

do_action( 'woocommerce_account_dashboard' );
do_action( 'woocommerce_before_my_account' );
do_action( 'woocommerce_after_my_account' );
