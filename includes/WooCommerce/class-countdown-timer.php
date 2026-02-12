<?php
/**
 * Countdown Timer Class
 *
 * Displays a live countdown for products with a scheduled sale end date.
 * Uses the Interactivity API for client-side tick updates.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Countdown Timer
 *
 * @since 1.17.0
 */
class Countdown_Timer {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_countdown' ), 11 );
		add_filter( 'render_block', array( $this, 'inject_archive_countdown' ), 10, 2 );
	}

	/**
	 * Enqueue assets and register Interactivity API script module on product pages and shop archives.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! function_exists( 'is_product' ) ) {
			return;
		}

		if ( ! is_product() && ! is_shop() && ! is_product_category() && ! is_product_tag() ) {
			return;
		}

		$css_file = AGGRESSIVE_APPAREL_DIR . '/build/styles/woocommerce/countdown-timer.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'aggressive-apparel-countdown-timer',
				AGGRESSIVE_APPAREL_URI . '/build/styles/woocommerce/countdown-timer.css',
				array(),
				(string) filemtime( $css_file ),
			);
		}

		if ( function_exists( 'wp_register_script_module' ) ) {
			wp_register_script_module(
				'@aggressive-apparel/countdown',
				AGGRESSIVE_APPAREL_URI . '/assets/interactivity/countdown.js',
				array( '@wordpress/interactivity' ),
				AGGRESSIVE_APPAREL_VERSION,
			);
			wp_enqueue_script_module( '@aggressive-apparel/countdown' );
		}
	}

	/**
	 * Render a countdown on the single product page (after price).
	 *
	 * @return void
	 */
	public function render_countdown(): void {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return;
		}

		$product = wc_get_product( get_the_ID() );
		if ( ! $product || ! $product->is_on_sale() ) {
			return;
		}

		$end_date = $product->get_date_on_sale_to();
		if ( ! $end_date ) {
			return;
		}

		$end_ts = $end_date->getTimestamp();
		if ( $end_ts <= time() ) {
			return;
		}

		$this->render_markup( $end_ts );
	}

	/**
	 * Optionally inject a small countdown into archive product cards.
	 *
	 * @param string $block_content Block HTML.
	 * @param array  $block         Block data.
	 * @return string Modified HTML.
	 */
	public function inject_archive_countdown( string $block_content, array $block ): string {
		if ( ! isset( $block['blockName'] ) || 'woocommerce/product-price' !== $block['blockName'] ) {
			return $block_content;
		}

		if ( ! function_exists( 'is_shop' ) ) {
			return $block_content;
		}

		if ( ! is_shop() && ! is_product_category() && ! is_product_tag() ) {
			return $block_content;
		}

		if ( ! function_exists( 'wc_get_product' ) ) {
			return $block_content;
		}

		$product = wc_get_product( get_the_ID() );
		if ( ! $product || ! $product->is_on_sale() ) {
			return $block_content;
		}

		$end_date = $product->get_date_on_sale_to();
		if ( ! $end_date || $end_date->getTimestamp() <= time() ) {
			return $block_content;
		}

		ob_start();
		$this->render_markup( $end_date->getTimestamp(), true );
		$timer = (string) ob_get_clean();

		return $block_content . $timer;
	}

	/**
	 * Output the countdown HTML with Interactivity API directives.
	 *
	 * @param int  $end_ts  Unix timestamp of sale end.
	 * @param bool $compact Whether to use compact (archive) variant.
	 * @return void
	 */
	private function render_markup( int $end_ts, bool $compact = false ): void {
		$diff    = $end_ts - time();
		$days    = (int) floor( $diff / DAY_IN_SECONDS );
		$hours   = (int) floor( ( $diff % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
		$minutes = (int) floor( ( $diff % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
		$seconds = $diff % MINUTE_IN_SECONDS;

		$context = (string) wp_json_encode(
			array(
				'endTs'   => $end_ts,
				'days'    => $days,
				'hours'   => $hours,
				'minutes' => $minutes,
				'seconds' => $seconds,
			),
		);

		$class = 'aggressive-apparel-countdown';
		if ( $compact ) {
			$class .= ' aggressive-apparel-countdown--compact';
		}

		echo '<div class="' . esc_attr( $class ) . '" data-wp-interactive="aggressive-apparel/countdown" data-wp-context=\'' . esc_attr( $context ) . '\' data-wp-init="callbacks.startTicker">';
		echo '<span class="aggressive-apparel-countdown__label">' . esc_html__( 'Sale ends in', 'aggressive-apparel' ) . '</span>';

		$segments = array(
			'days'    => __( 'd', 'aggressive-apparel' ),
			'hours'   => __( 'h', 'aggressive-apparel' ),
			'minutes' => __( 'm', 'aggressive-apparel' ),
			'seconds' => __( 's', 'aggressive-apparel' ),
		);

		foreach ( $segments as $key => $unit ) {
			echo '<span class="aggressive-apparel-countdown__segment">';
			echo '<span class="aggressive-apparel-countdown__value" data-wp-text="context.' . esc_attr( $key ) . '">0</span>';
			echo '<span class="aggressive-apparel-countdown__unit">' . esc_html( $unit ) . '</span>';
			echo '</span>';
		}

		echo '</div>';
	}
}
