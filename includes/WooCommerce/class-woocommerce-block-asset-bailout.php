<?php
/**
 * WooCommerce Block Asset Bailout
 *
 * Last-resort safety net: if enqueue-time detection misses a WooCommerce block,
 * load styles and interactivity defaults when the first block actually renders.
 *
 * @package Aggressive_Apparel
 * @since 1.79.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Block Asset Bailout
 *
 * @since 1.79.0
 */
class WooCommerce_Block_Asset_Bailout {

	/**
	 * Whether the bailout has already loaded assets this request.
	 *
	 * @var bool
	 */
	private static bool $bailed_out = false;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'render_block', array( $this, 'maybe_bailout' ), 1, 2 );
	}

	/**
	 * Load WooCommerce assets when a block renders and enqueue-time detection missed it.
	 *
	 * @param string               $block_content Rendered block HTML.
	 * @param array<string, mixed> $block         Parsed block data.
	 * @return string
	 */
	public function maybe_bailout( string $block_content, array $block ): string {
		if ( self::$bailed_out || ! WooCommerce_Block_Detector::is_woocommerce_block( $block ) ) {
			return $block_content;
		}

		self::$bailed_out = true;

		WooCommerce_Block_Styles::ensure_loaded();
		WooCommerce_Interactivity_Defaults::ensure_registered();

		return $block_content;
	}
}
