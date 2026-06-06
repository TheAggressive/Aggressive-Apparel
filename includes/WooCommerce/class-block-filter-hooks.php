<?php
/**
 * Block Filter Hooks
 *
 * Registers callbacks on block-specific `render_block_{$name}` filters so
 * feature classes avoid the generic `render_block` hook.
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
 * Block Filter Hooks
 *
 * @since 1.79.0
 */
class Block_Filter_Hooks {

	/**
	 * Build the block-specific `render_block_{$name}` filter hook.
	 *
	 * @param string $block_name Fully qualified block name.
	 * @return string Filter hook name.
	 */
	public static function hook_name( string $block_name ): string {
		return 'render_block_' . $block_name;
	}

	/**
	 * Register a callback on a block-specific render filter.
	 *
	 * @param string   $block_name    Fully qualified block name.
	 * @param callable $callback      Filter callback.
	 * @param int      $priority      Filter priority.
	 * @param int      $accepted_args Number of accepted arguments.
	 * @return void
	 */
	public static function add(
		string $block_name,
		callable $callback,
		int $priority = 10,
		int $accepted_args = 2
	): void {
		add_filter(
			self::hook_name( $block_name ),
			$callback,
			$priority,
			$accepted_args
		);
	}

	/**
	 * Register a callback on the core post featured image block filter.
	 *
	 * @param callable $callback      Filter callback.
	 * @param int      $priority      Filter priority.
	 * @param int      $accepted_args Number of accepted arguments.
	 * @return void
	 */
	public static function add_featured_image(
		callable $callback,
		int $priority = 10,
		int $accepted_args = 2
	): void {
		self::add( 'core/post-featured-image', $callback, $priority, $accepted_args );
	}
}
