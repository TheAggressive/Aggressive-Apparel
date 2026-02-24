<?php
/**
 * Block Pill Helper
 *
 * Shared utilities for WooCommerce variation pill block modifiers.
 * Used by Color_Block_Swatch_Manager, Size_Option_Sorter, and
 * Variation_Pill_Enhancer to avoid duplicating DOMDocument boilerplate.
 *
 * @package Aggressive_Apparel
 * @since 1.45.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block Pill Helper
 *
 * Static utility methods for loading, querying, and saving WooCommerce
 * variation selector block HTML via DOMDocument.
 *
 * @since 1.45.0
 */
class Block_Pill_Helper {

	/**
	 * The WooCommerce block name for variation attribute options.
	 *
	 * @var string
	 */
	public const BLOCK_NAME = 'woocommerce/add-to-cart-with-options-variation-selector-attribute-options';

	/**
	 * The CSS class WooCommerce applies to each pill label.
	 *
	 * @var string
	 */
	public const PILL_CLASS = 'wc-block-add-to-cart-with-options-variation-selector-attribute-options__pill';

	/**
	 * Check if a block is the variation attribute options block.
	 *
	 * @param array<string, mixed> $block The block data.
	 * @return bool
	 */
	public static function is_attribute_options_block( array $block ): bool {
		return isset( $block['blockName'] ) && self::BLOCK_NAME === $block['blockName'];
	}

	/**
	 * Load block HTML into a DOMDocument with libxml error suppression.
	 *
	 * @param string $block_content The block HTML.
	 * @return \DOMDocument|null The loaded document, or null on failure.
	 */
	public static function load_dom( string $block_content ): ?\DOMDocument {
		if ( empty( $block_content ) ) {
			return null;
		}

		$dom = new \DOMDocument();

		$prev_errors = libxml_use_internal_errors( true );
		$loaded      = $dom->loadHTML(
			$block_content,
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $prev_errors );

		return $loaded ? $dom : null;
	}

	/**
	 * Save DOMDocument back to HTML string.
	 *
	 * @param \DOMDocument $dom      The document to serialize.
	 * @param string       $fallback Returned if saveHTML fails.
	 * @return string The HTML output.
	 */
	public static function save_dom( \DOMDocument $dom, string $fallback ): string {
		$output = $dom->saveHTML();

		return $output ? $output : $fallback;
	}

	/**
	 * Collect all pill label elements from a DOMDocument.
	 *
	 * Returns an array (not a live NodeList) so callers can safely
	 * mutate the DOM while iterating.
	 *
	 * @param \DOMDocument $dom The loaded document.
	 * @return \DOMElement[] Array of pill label elements.
	 */
	public static function get_pill_labels( \DOMDocument $dom ): array {
		$labels = $dom->getElementsByTagName( 'label' );
		$pills  = array();

		foreach ( $labels as $label ) {
			if ( false !== strpos( $label->getAttribute( 'class' ), self::PILL_CLASS ) ) {
				$pills[] = $label;
			}
		}

		return $pills;
	}

	/**
	 * Check if a pill label contains a color swatch.
	 *
	 * @param \DOMElement $label The pill label element.
	 * @return bool
	 */
	public static function has_color_swatch( \DOMElement $label ): bool {
		$class_attr = $label->getAttribute( 'class' );
		if ( false !== strpos( $class_attr, 'aggressive-apparel-color-swatch' ) ) {
			return true;
		}

		$spans = $label->getElementsByTagName( 'span' );
		foreach ( $spans as $span ) {
			if ( false !== strpos( $span->getAttribute( 'class' ), 'aggressive-apparel-color-swatch' ) ) {
				return true;
			}
		}

		return false;
	}
}
