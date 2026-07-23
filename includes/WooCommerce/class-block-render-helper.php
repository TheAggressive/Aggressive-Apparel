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

	/**
	 * Resolve a block's WordPress alignment CSS class ('alignwide'/'alignfull').
	 *
	 * When a `render_block` filter wraps a block in its own container, that
	 * container becomes the direct child of the (possibly constrained) layout, so
	 * it must carry the block's alignment or WordPress core caps it at
	 * content-size. Only wide/full are meaningful for width; other values (left,
	 * right, none) yield an empty string.
	 *
	 * @param array<string, mixed> $block Parsed block data (as passed to render_block).
	 * @return string The alignment class, or '' when the block has no wide/full alignment.
	 */
	public static function alignment_class( array $block ): string {
		$align = isset( $block['attrs']['align'] ) ? (string) $block['attrs']['align'] : '';

		return in_array( $align, array( 'wide', 'full' ), true ) ? 'align' . $align : '';
	}
}
