<?php
/**
 * Color Block Swatch Manager Class
 *
 * Handles color swatch display in WooCommerce blocks using DOM manipulation
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Term;

/**
 * Color Block Swatch Manager Class
 *
 * Manages the display of color swatches in WooCommerce Add to Cart with Options blocks
 * by directly modifying the HTML to replace text labels with colored span elements.
 *
 * This class uses the render_block filter to intercept WooCommerce blocks during rendering
 * and injects color swatches based on the show_label configuration.
 *
 * @since 1.0.0
 */
class Color_Block_Swatch_Manager {
	/**
	 * Input name values that identify a color attribute.
	 *
	 * @var string
	 */
	private const COLOR_INPUT_NAME = 'attribute_pa_color';

	/**
	 * Whether to show text labels alongside color swatches
	 *
	 * @var bool
	 */
	private bool $show_label = false;

	/**
	 * Initialize the block swatch manager
	 *
	 * @return void
	 */
	public function init(): void {
		$this->register_hooks();
	}

	/**
	 * Set whether to show text labels alongside color swatches
	 *
	 * @param bool $show_label Whether to show text labels.
	 * @return void
	 */
	public function set_show_label( bool $show_label ): void {
		$this->show_label = $show_label;
	}

	/**
	 * Get whether text labels are shown alongside color swatches
	 *
	 * @return bool Whether text labels are shown.
	 */
	public function get_show_label(): bool {
		return $this->show_label;
	}

	/**
	 * Register WordPress hooks for multiple interception points
	 *
	 * Uses multiple hooks to ensure WooCommerce blocks are caught at different
	 * stages of the rendering process.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		add_filter( 'render_block_' . Block_Pill_Helper::BLOCK_NAME, array( $this, 'inject_color_swatches_in_block' ), 10, 2 );
	}

	/**
	 * Inject color swatches into individual blocks during rendering
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The block data.
	 * @return string Modified block content.
	 */
	public function inject_color_swatches_in_block( string $block_content, array $block ): string {
		// Only process WooCommerce color attribute blocks that contain color attributes.
		if ( ! Block_Pill_Helper::is_attribute_options_block( $block ) ) {
			return $block_content;
		}

		if ( ! str_contains( $block_content, self::COLOR_INPUT_NAME ) ) {
			return $block_content;
		}

		return $this->inject_swatches_with_html_processor( $block_content );
	}

	/**
	 * Inject color swatches using DOMDocument via Block_Pill_Helper.
	 *
	 * @param string $block_content The block content to modify.
	 * @return string Modified block content.
	 */
	private function inject_swatches_with_html_processor( string $block_content ): string {
		$dom = Block_Pill_Helper::load_dom( $block_content );
		if ( ! $dom ) {
			return $block_content;
		}

		$buttons  = Block_Pill_Helper::get_option_buttons( $dom );
		$modified = false;

		foreach ( $buttons as $button ) {
			// Skip chips already carrying a swatch (idempotency).
			if ( Block_Pill_Helper::has_color_swatch( $button ) ) {
				continue;
			}

			// WooCommerce stores the option's term slug on the chip's value attr.
			$color_slug = $button->getAttribute( 'value' );
			if ( '' === $color_slug ) {
				continue;
			}

			// Try taxonomy term first, then fall back to swatch data map.
			$color_data = $this->resolve_color_data( $color_slug );
			if ( ! $color_data ) {
				continue;
			}

			$color_name = $color_data['name'];

			$classes = 'aggressive-apparel-color-swatch aggressive-apparel-color-swatch--interactive';
			if ( $this->show_label ) {
				$classes .= ' aggressive-apparel-color-swatch--with-label';
			}

			$swatch = $dom->createElement( 'span' );
			$swatch->setAttribute( 'class', $classes . ' aggressive-apparel-color-swatch__circle' );

			// Decorative: WooCommerce's chip <button> already carries the colour
			// name in its aria-label, so the swatch is hidden from assistive tech
			// to avoid a duplicate announcement. The name stays available to
			// sighted (incl. colour-blind) users via the hover/focus tooltip
			// driven by data-color-name (see swatch-tooltips.css).
			$swatch->setAttribute( 'aria-hidden', 'true' );
			$swatch->setAttribute( 'data-color-name', $color_name );

			if ( 'pattern' === $color_data['type'] ) {
				$swatch->setAttribute( 'data-pattern-url', $color_data['value'] );
				$swatch->setAttribute( 'style', 'background-image: url(' . esc_url( $color_data['value'] ) . '); --swatch-color: rgb(0 0 0 / 40%);' );
				$swatch->setAttribute( 'class', $swatch->getAttribute( 'class' ) . ' aggressive-apparel-color-swatch--pattern' );
			} else {
				$swatch->setAttribute( 'data-color', $color_data['value'] );
				$swatch->setAttribute( 'style', 'background-color: ' . esc_attr( $color_data['value'] ) . '; --swatch-color: ' . esc_attr( $color_data['value'] ) . ';' );
			}

			// Mark the chip as a color chip and prepend the swatch, leaving
			// WooCommerce's own chip label/text intact (CSS hides the text unless
			// "show label" is enabled). Keeping the structure avoids fighting the
			// block's Interactivity bindings.
			$button->setAttribute(
				'class',
				trim( $button->getAttribute( 'class' ) . ' aggressive-apparel-color-chip' )
			);
			$button->insertBefore( $swatch, $button->firstChild );

			$modified = true;
		}

		if ( ! $modified ) {
			return $block_content;
		}

		return Block_Pill_Helper::save_dom( $dom, $block_content );
	}

	/**
	 * Resolve color display data from a slug/value.
	 *
	 * Tries the pa_color taxonomy first, then falls back to Color_Data_Manager
	 * swatch data (which covers both taxonomy and custom attributes).
	 *
	 * @param string $slug The color slug or option value.
	 * @return array{type: string, value: string, name: string}|null Color data or null if not found.
	 */
	private function resolve_color_data( string $slug ): ?array {
		// 1. Try taxonomy term (pa_color).
		$term = get_term_by( 'slug', $slug, 'pa_color' );
		if ( $term instanceof WP_Term ) {
			$data = $this->get_color_display_data_for_term( $term );
			if ( $data['is_valid'] && ! empty( $data['value'] ) ) {
				return array(
					'type'  => $data['type'],
					'value' => $data['value'],
					'name'  => $term->name,
				);
			}
		}

		// 2. Fall back to Color_Data_Manager swatch data (memoized per request
		// — this runs per color button via the render_block filter).
		$swatch_data = Color_Data_Manager::get_safe_swatch_data();
		if ( isset( $swatch_data[ $slug ] ) && ! empty( $swatch_data[ $slug ]['value'] ) ) {
			return $swatch_data[ $slug ];
		}

		return null;
	}

	/**
	 * Retrieve the color display data for a given term
	 *
	 * Handles both solid colors and patterns.
	 *
	 * @param WP_Term $term The color term object.
	 * @return array Array with 'type' ('solid' or 'pattern'), 'value' (color or image URL), and 'is_valid'.
	 */
	private function get_color_display_data_for_term( WP_Term $term ): array {
		$color_type = get_term_meta( $term->term_id, 'color_type', true );

		// Default to solid for backward compatibility.
		if ( empty( $color_type ) ) {
			$color_type = 'solid';
		}

		if ( 'pattern' === $color_type ) {
			$pattern_id = get_term_meta( $term->term_id, 'color_pattern_id', true );

			if ( $pattern_id && wp_attachment_is_image( $pattern_id ) ) {
				$pattern_url = wp_get_attachment_url( $pattern_id );
				return array(
					'type'     => 'pattern',
					'value'    => $pattern_url,
					'is_valid' => true,
				);
			} else {
				// Pattern set but invalid/missing.
				return array(
					'type'     => 'pattern',
					'value'    => '',
					'is_valid' => false,
				);
			}
		} else {
			// Solid color handling.
			$color_value = get_term_meta( $term->term_id, 'color_value', true );

			return array(
				'type'     => 'solid',
				'value'    => $color_value,
				'is_valid' => $this->is_valid_hex_color( $color_value ),
			);
		}
	}

	/**
	 * Validate hex color format
	 *
	 * @param string $color The color string to validate.
	 * @return bool True if valid 6-digit hex color.
	 */
	private function is_valid_hex_color( string $color ): bool {
		return preg_match( '/^#[a-fA-F0-9]{6}$/', $color ) === 1;
	}
}
