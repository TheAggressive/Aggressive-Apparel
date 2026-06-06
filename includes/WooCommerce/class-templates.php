<?php
/**
 * Templates Class
 *
 * Handles WooCommerce template wrapper markup.
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
	 * Initialize template modifications.
	 *
	 * @return void
	 */
	public function init(): void {
		remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
		remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );

		add_action( 'woocommerce_before_main_content', array( $this, 'wrapper_start' ), 10 );
		add_action( 'woocommerce_after_main_content', array( $this, 'wrapper_end' ), 10 );
	}

	/**
	 * Output opening wrapper for WooCommerce content.
	 *
	 * @return void
	 */
	public function wrapper_start(): void {
		echo '<div class="aggressive-apparel-woocommerce aggressive-apparel-woocommerce__wrapper">';
	}

	/**
	 * Output closing wrapper for WooCommerce content.
	 *
	 * @return void
	 */
	public function wrapper_end(): void {
		echo '</div><!-- .woocommerce-wrapper -->';
	}
}
