<?php
/**
 * Templates Class
 *
 * Handles WooCommerce template modifications
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Templates Class
 *
 * Manages WooCommerce template wrappers and structure.
 *
 * @since 1.0.0
 */
class Templates {

	/**
	 * Initialize template modifications
	 *
	 * @return void
	 */
	public function init() {
		// Remove default WooCommerce wrappers.
		remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
		remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );

		// Add custom wrappers.
		add_action( 'woocommerce_before_main_content', array( $this, 'wrapper_start' ), 10 );
		add_action( 'woocommerce_after_main_content', array( $this, 'wrapper_end' ), 10 );

		// Enqueue WooCommerce styles.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_woocommerce_styles' ), 20 );
	}

	/**
	 * Output opening wrapper for WooCommerce content
	 *
	 * @return void
	 */
	public function wrapper_start() {
		echo '<div class="aggressive-apparel-woocommerce aggressive-apparel-woocommerce__wrapper">';
	}

	/**
	 * Output closing wrapper for WooCommerce content
	 *
	 * @return void
	 */
	public function wrapper_end() {
		echo '</div><!-- .woocommerce-wrapper -->';
	}

	/**
	 * Enqueue WooCommerce-specific styles
	 *
	 * @return void
	 */
	public function enqueue_woocommerce_styles() {
		if ( ! is_woocommerce() && ! is_cart() && ! is_checkout() && ! is_account_page() ) {
			return;
		}

		$theme_uri = get_template_directory_uri();
		$theme     = wp_get_theme();
		$version   = ! $theme->errors() ? $theme->get( 'Version' ) : '1.0.0';

		wp_enqueue_style(
			'aggressive-apparel-woocommerce',
			$theme_uri . '/build/styles/woocommerce.css',
			array( 'aggressive-apparel-main' ),
			$version,
			'all'
		);
	}
}
