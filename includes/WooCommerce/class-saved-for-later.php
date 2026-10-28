<?php
/**
 * Saved for Later list layout and markup fixes.
 *
 * WooCommerce hooks its Saved for Later block in after the cart block with no
 * alignment, so a constrained page drops it to the narrow content width while
 * the cart above it is wide. Give the auto-inserted block the cart's width,
 * and three columns instead of WooCommerce's five, which are too narrow for
 * the theme's row-style items. A block the merchant placed or edited keeps its
 * own attributes: this filter only runs for the hooked insertion.
 *
 * Each item's photo is a link with an empty-alt image, so it has no accessible
 * name and repeats the product-name link beside it. It is hidden from
 * assistive technology and the tab order, leaving the name as the one link.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Saved for Later list layout and markup fixes.
 */
class Saved_For_Later {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'hooked_block_woocommerce/saved-for-later', array( $this, 'align_hooked_block' ), 20, 4 );
		add_filter( 'render_block_woocommerce/saved-for-later', array( $this, 'hide_redundant_image_links' ) );
	}

	/**
	 * Match the hooked block's width to the cart block it follows.
	 *
	 * The cart block defaults to wide, so an anchor without an alignment
	 * attribute is wide too. Attributes another filter already set are kept.
	 *
	 * @param array<string, mixed>|null $parsed_hooked_block The hooked block, or null when suppressed.
	 * @param string                    $hooked_block_type   The hooked block type name.
	 * @param string                    $relative_position   Insertion position relative to the anchor.
	 * @param array<string, mixed>      $parsed_anchor_block The anchor block.
	 * @return array<string, mixed>|null
	 */
	public function align_hooked_block( $parsed_hooked_block, $hooked_block_type, $relative_position, $parsed_anchor_block ) {
		if ( ! is_array( $parsed_hooked_block ) || 'after' !== $relative_position ) {
			return $parsed_hooked_block;
		}

		if ( 'woocommerce/cart' !== ( $parsed_anchor_block['blockName'] ?? null ) ) {
			return $parsed_hooked_block;
		}

		$anchor_attrs = is_array( $parsed_anchor_block['attrs'] ?? null ) ? $parsed_anchor_block['attrs'] : array();
		$attrs        = is_array( $parsed_hooked_block['attrs'] ?? null ) ? $parsed_hooked_block['attrs'] : array();

		$attrs['align']       ??= $anchor_attrs['align'] ?? 'wide';
		$attrs['columnCount'] ??= 3;

		$parsed_hooked_block['attrs'] = $attrs;

		return $parsed_hooked_block;
	}

	/**
	 * Take each item's photo link out of the accessibility tree and tab order.
	 *
	 * Only an anchor that is the image box's first child is changed, so an
	 * image box without a link never reaches past itself to the name link.
	 * Covers the server-rendered items and the item template WooCommerce
	 * clones for items saved later, which share the same markup.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @return string
	 */
	public function hide_redundant_image_links( string $block_content ): string {
		$processor = new \WP_HTML_Tag_Processor( $block_content );

		while ( $processor->next_tag( array( 'class_name' => 'wc-block-components-product-image' ) ) ) {
			if ( ! $processor->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
				break;
			}

			if ( 'A' === $processor->get_tag() && ! $processor->is_tag_closer() ) {
				$processor->set_attribute( 'aria-hidden', 'true' );
				$processor->set_attribute( 'tabindex', '-1' );
			}
		}

		return $processor->get_updated_html();
	}
}
