<?php
/**
 * Block Pill Helper
 *
 * Shared utilities for WooCommerce variation chip block modifiers.
 * Used by Color_Block_Swatch_Manager and Size_Option_Sorter to avoid
 * duplicating DOMDocument boilerplate. (Pill styling is CSS-only — see
 * variation-pills.css — so no enhancer class is needed.)
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
	 * The WooCommerce block name for a single variation attribute.
	 *
	 * As of WooCommerce 10.x the "Add to Cart with Options" variation selector
	 * renders each attribute as this block, and its options as
	 * `wc-block-product-filter-chips__item` <button> chips (via the Interactivity
	 * API), replacing the old `…-attribute-options` block with `<label>`/`<input>`
	 * pills.
	 *
	 * @var string
	 */
	public const BLOCK_NAME = 'woocommerce/add-to-cart-with-options-variation-selector-attribute';

	/**
	 * The CSS class WooCommerce applies to each option chip button.
	 *
	 * @var string
	 */
	public const PILL_CLASS = 'wc-block-product-filter-chips__item';

	/**
	 * Check if a block is a variation attribute block.
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

		// DOMDocument::loadHTML() assumes ISO-8859-1 when the markup carries no
		// charset, which corrupts multibyte characters in variation labels (e.g.
		// accented sizes or non-Latin attribute names) on the load/save round-trip.
		// Encoding non-ASCII as numeric entities first keeps them intact without the
		// deprecated mb_convert_encoding( …, 'HTML-ENTITIES' ) hack.
		$encoded = mb_encode_numericentity(
			$block_content,
			array( 0x80, 0x10FFFF, 0, 0x10FFFF ),
			'UTF-8'
		);

		$prev_errors = libxml_use_internal_errors( true );
		$loaded      = $dom->loadHTML(
			$encoded,
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
	 * Collect all option chip <button> elements from a DOMDocument.
	 *
	 * Returns an array (not a live NodeList) so callers can safely
	 * mutate the DOM while iterating.
	 *
	 * @param \DOMDocument $dom The loaded document.
	 * @return \DOMElement[] Array of chip button elements.
	 */
	public static function get_option_buttons( \DOMDocument $dom ): array {
		$buttons = $dom->getElementsByTagName( 'button' );
		$chips   = array();

		foreach ( $buttons as $button ) {
			if ( false !== strpos( $button->getAttribute( 'class' ), self::PILL_CLASS ) ) {
				$chips[] = $button;
			}
		}

		return $chips;
	}

	/**
	 * Check if a chip button already contains a color swatch.
	 *
	 * @param \DOMElement $button The option chip <button> element.
	 * @return bool
	 */
	public static function has_color_swatch( \DOMElement $button ): bool {
		$class_attr = $button->getAttribute( 'class' );
		if ( false !== strpos( $class_attr, 'aggressive-apparel-color-swatch' ) ) {
			return true;
		}

		$spans = $button->getElementsByTagName( 'span' );
		foreach ( $spans as $span ) {
			if ( false !== strpos( $span->getAttribute( 'class' ), 'aggressive-apparel-color-swatch' ) ) {
				return true;
			}
		}

		return false;
	}
}
