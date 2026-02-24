<?php
/**
 * Size Option Sorter Class
 *
 * Sorts WooCommerce variation size options in logical apparel order
 * (XS → S → M → L → XL → 2XL …) instead of alphabetical/database order.
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
 * Size Option Sorter Class
 *
 * Hooks into WooCommerce block rendering to reorder size attribute pills
 * and provides static utilities for size ranking used by other components
 * (e.g. Sticky_Add_To_Cart).
 *
 * @since 1.45.0
 */
class Size_Option_Sorter {

	/**
	 * Input name values that identify a size attribute.
	 *
	 * @var string[]
	 */
	private const SIZE_INPUT_NAMES = array(
		'attribute_pa_size',
		'attribute_size',
	);

	/**
	 * Base size ranks for standard apparel sizes.
	 *
	 * @var array<string, int>
	 */
	private const BASE_RANKS = array(
		'XS' => 1,
		'S'  => 2,
		'M'  => 3,
		'L'  => 4,
		'XL' => 5,
	);

	/**
	 * Initialize the size option sorter.
	 *
	 * @return void
	 */
	public function init(): void {
		// Priority 20: after Color_Block_Swatch_Manager (priority 10).
		add_filter( 'render_block', array( $this, 'sort_size_options_in_block' ), 20, 2 );
	}

	/**
	 * Sort size options in WooCommerce variation selector blocks.
	 *
	 * @param string               $block_content The block content.
	 * @param array<string, mixed> $block         The block data.
	 * @return string Modified block content.
	 */
	public function sort_size_options_in_block( string $block_content, array $block ): string {
		if ( ! Block_Pill_Helper::is_attribute_options_block( $block ) ) {
			return $block_content;
		}

		// Check if this block contains a size attribute.
		$has_size = false;
		foreach ( self::SIZE_INPUT_NAMES as $name ) {
			if ( strpos( $block_content, $name ) !== false ) {
				$has_size = true;
				break;
			}
		}

		if ( ! $has_size ) {
			return $block_content;
		}

		return $this->sort_labels_by_size( $block_content );
	}

	/**
	 * Reorder label elements by size rank using DOMDocument.
	 *
	 * @param string $block_content The block content to modify.
	 * @return string Modified block content.
	 */
	private function sort_labels_by_size( string $block_content ): string {
		$dom = Block_Pill_Helper::load_dom( $block_content );
		if ( ! $dom ) {
			return $block_content;
		}

		$pills = Block_Pill_Helper::get_pill_labels( $dom );

		// Collect pill labels with their size values.
		$label_data = array();
		foreach ( $pills as $label ) {
			$inputs = $label->getElementsByTagName( 'input' );
			if ( 0 === $inputs->length ) {
				continue;
			}

			// Find the size input.
			$size_value = null;
			foreach ( $inputs as $input ) {
				if ( in_array( $input->getAttribute( 'name' ), self::SIZE_INPUT_NAMES, true ) ) {
					$size_value = $input->getAttribute( 'value' );
					break;
				}
			}

			if ( null === $size_value ) {
				continue;
			}

			$label_data[] = array(
				'node' => $label,
				'rank' => self::size_rank( $size_value ),
			);
		}

		// Need at least 2 labels to sort.
		if ( count( $label_data ) < 2 ) {
			return $block_content;
		}

		// Sort by rank.
		usort(
			$label_data,
			static function ( array $a, array $b ): int {
				return $a['rank'] <=> $b['rank'];
			}
		);

		// Re-append labels in sorted order to their parent.
		$parent = $label_data[0]['node']->parentNode; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		if ( ! $parent ) {
			return $block_content;
		}

		foreach ( $label_data as $item ) {
			$parent->appendChild( $item['node'] );
		}

		return Block_Pill_Helper::save_dom( $dom, $block_content );
	}

	/**
	 * Calculate a numeric rank for a size label.
	 *
	 * Mirrors the JavaScript sizeRank() in quick-view.js.
	 *
	 * Ranking order:
	 * - Multiplied smalls: 3XS (-3), 2XS (-2)
	 * - Base sizes: XS (1), S (2), M (3), L (4), XL (5)
	 * - Multiplied larges: 2XL (7), 3XL (8), … 7XL (12)
	 * - Numeric sizes: 100 + value
	 * - Unknown: PHP_FLOAT_MAX
	 *
	 * @param string $size Size label (case-insensitive).
	 * @return float Sort rank.
	 */
	public static function size_rank( string $size ): float {
		$s = strtoupper( trim( $size ) );

		// Multiplied small: 2XS, 3XS, etc. — smaller means lower rank.
		if ( preg_match( '/^(\d+)XS$/', $s, $matches ) ) {
			return - (float) $matches[1];
		}

		// Base sizes: XS, S, M, L, XL.
		if ( isset( self::BASE_RANKS[ $s ] ) ) {
			return (float) self::BASE_RANKS[ $s ];
		}

		// Multiplied large: 2XL, 3XL, … 7XL.
		if ( preg_match( '/^(\d+)XL$/', $s, $matches ) ) {
			return 5.0 + (float) $matches[1];
		}

		// Numeric sizes (e.g. shoe sizes).
		if ( is_numeric( $s ) ) {
			return 100.0 + (float) $s;
		}

		return PHP_FLOAT_MAX;
	}

	/**
	 * Check if a taxonomy slug is a size attribute.
	 *
	 * @param string $slug Taxonomy slug (e.g. 'pa_size').
	 * @return bool
	 */
	public static function is_size_attribute( string $slug ): bool {
		return in_array( $slug, array( 'pa_size', 'size' ), true );
	}
}
