<?php
/**
 * Mini Cart Enhancements Class
 *
 * Styles the native WooCommerce mini-cart block to match the theme design
 * and optionally injects the free-shipping progress bar.
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
 * Mini Cart Enhancements
 *
 * @since 1.17.0
 */
class Mini_Cart_Enhancements {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_filter( 'render_block', array( $this, 'inject_shipping_bar' ), 10, 2 );
	}

	/**
	 * Enqueue mini-cart override styles.
	 *
	 * @return void
	 */
	public function enqueue_styles(): void {
		$css_file = AGGRESSIVE_APPAREL_DIR . '/build/styles/woocommerce/mini-cart.css';
		if ( ! file_exists( $css_file ) ) {
			return;
		}

		wp_enqueue_style(
			'aggressive-apparel-mini-cart',
			AGGRESSIVE_APPAREL_URI . '/build/styles/woocommerce/mini-cart.css',
			array(),
			(string) filemtime( $css_file ),
		);
	}

	/**
	 * Inject the free-shipping bar at the top of the mini-cart drawer contents.
	 *
	 * @param string $block_content Block HTML.
	 * @param array  $block         Block data.
	 * @return string Modified HTML.
	 */
	public function inject_shipping_bar( string $block_content, array $block ): string {
		if ( ! isset( $block['blockName'] ) || 'woocommerce/mini-cart-contents' !== $block['blockName'] ) {
			return $block_content;
		}

		if ( ! Feature_Settings::is_enabled( 'free_shipping_bar' ) ) {
			return $block_content;
		}

		// Render the shipping bar and prepend it.
		ob_start();
		try {
			( new Free_Shipping_Bar() )->render();
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Intentionally silent; buffer cleaned by ob_get_clean.
			unset( $e );
		}
		$bar = (string) ob_get_clean();

		if ( $bar ) {
			// Inject after the first opening tag.
			$block_content = preg_replace(
				'/^(<[^>]+>)/',
				'$1' . $bar,
				$block_content,
				1,
			) ?? $block_content;
		}

		return $block_content;
	}
}
