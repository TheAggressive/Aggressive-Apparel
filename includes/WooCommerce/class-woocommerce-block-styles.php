<?php
/**
 * WooCommerce Block Styles
 *
 * Conditionally enqueues the split WooCommerce blocks CSS bundle.
 *
 * @package Aggressive_Apparel
 * @since 1.79.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Assets\Asset_Loader;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Block Styles
 *
 * @since 1.79.0
 */
class WooCommerce_Block_Styles {

	/**
	 * Stylesheet handle for the WooCommerce blocks bundle.
	 *
	 * @var string
	 */
	public const HANDLE = 'aggressive-apparel-woocommerce-blocks';

	/**
	 * Whether the blocks stylesheet has been enqueued this request.
	 *
	 * @var bool
	 */
	private static bool $loaded = false;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ), 20 );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_editor' ), 20 );
	}

	/**
	 * Enqueue WooCommerce block styles on the frontend when needed.
	 *
	 * @return void
	 */
	public function enqueue_frontend(): void {
		if ( ! WooCommerce_Block_Detector::request_needs_assets() ) {
			return;
		}

		self::enqueue();
	}

	/**
	 * Enqueue WooCommerce block styles in the block editor only.
	 *
	 * @return void
	 */
	public function enqueue_editor(): void {
		if ( ! is_admin() ) {
			return;
		}

		self::enqueue();
	}

	/**
	 * Register and enqueue the blocks stylesheet.
	 *
	 * @return void
	 */
	private static function enqueue(): void {
		Asset_Loader::enqueue_style(
			self::HANDLE,
			'build/styles/woocommerce/blocks',
			array( 'aggressive-apparel-main' )
		);

		self::$loaded = true;
	}

	/**
	 * Enqueue the blocks stylesheet if it is not already loaded.
	 *
	 * Used by the runtime bailout when enqueue-time detection misses a block.
	 *
	 * @return void
	 */
	public static function ensure_loaded(): void {
		if ( self::$loaded || wp_style_is( self::HANDLE, 'enqueued' ) ) {
			self::$loaded = true;
			return;
		}

		self::enqueue();
	}
}
