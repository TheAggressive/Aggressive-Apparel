<?php
/**
 * Block Render Helper
 *
 * Shared helpers for `render_block` filters that inject markup into product
 * card image blocks (wishlist heart, quick-view trigger, hover image, badges).
 *
 * @package Aggressive_Apparel
 * @since 1.78.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block Render Helper
 *
 * Stateless helpers; all methods are static.
 *
 * @since 1.78.0
 */
class Block_Render_Helper {

	/**
	 * Whether the rendered block is the core post featured image block.
	 *
	 * @param array<string, mixed> $block Block data from the `render_block` filter.
	 * @return bool
	 */
	public static function is_featured_image_block( array $block ): bool {
		return isset( $block['blockName'] ) && 'core/post-featured-image' === $block['blockName'];
	}

	/**
	 * Whether the rendered block's name is in the provided allow-list.
	 *
	 * @param array<string, mixed> $block Block data from the `render_block` filter.
	 * @param array<int, string>   $names Block names to match against.
	 * @return bool
	 */
	public static function block_name_in( array $block, array $names ): bool {
		return isset( $block['blockName'] ) && in_array( $block['blockName'], $names, true );
	}

	/**
	 * Inject markup immediately before the wrapper's closing tag.
	 *
	 * Targets the final `</figure>` or `</div>` in the block HTML — the
	 * outer wrapper of the featured image — so injected overlays sit inside
	 * the image frame. Returns the original HTML unchanged if the pattern
	 * does not match.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param string $markup        Markup to insert.
	 * @return string
	 */
	public static function append_before_wrapper_close( string $block_content, string $markup ): string {
		return preg_replace(
			'/(<\/(?:figure|div)>\s*)$/i',
			$markup . '$1',
			$block_content,
			1
		) ?? $block_content;
	}
}
