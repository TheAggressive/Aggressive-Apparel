<?php
/**
 * Color Block Swatch Manager Class
 *
 * Handles color swatch display in WooCommerce blocks using DOM manipulation
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

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
		// Use render_block filter as the primary method for WooCommerce blocks.
		add_filter( 'render_block', array( $this, 'inject_color_swatches_in_block' ), 10, 2 );
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
		if ( isset( $block['blockName'] ) &&
			'woocommerce/add-to-cart-with-options-variation-selector-attribute-options' === $block['blockName'] &&
			strpos( $block_content, 'attribute_pa_color' ) !== false ) {

			return $this->inject_swatches_with_html_processor( $block_content );
		}

		return $block_content;
	}

	/**
	 * Inject color swatches using WP_HTML_Processor
	 *
	 * @param string $block_content The block content to modify.
	 * @return string Modified block content.
	 */
	private function inject_swatches_with_html_processor( string $block_content ): string {
		// Use regex to find and replace label elements with color swatches.
		$pattern = '/<label[^>]*class="[^"]*wc-block-add-to-cart-with-options-variation-selector-attribute-options__pill[^"]*"[^>]*>(.*?)<\/label>/s';

		$result = preg_replace_callback(
			$pattern,
			function ( $matches ) {
				$label_content = $matches[0];
				$inner_content = $matches[1];

				// Extract the color value from the input.
				if ( preg_match( '/name="attribute_pa_color"\s+value="([^"]*)"/', $inner_content, $input_matches ) ) {
					$color_slug = $input_matches[1];

					// Get color term and value.
					$color_term = get_term_by( 'slug', $color_slug, 'pa_color' );

					if ( $color_term ) {
						$color_value = $this->get_color_value_for_term( $color_term );

						if ( ! empty( $color_value ) && $this->is_valid_hex_color( $color_value ) ) {
							// Create accessible swatch HTML with optional margin.
							$margin_style = $this->show_label ? 'margin-right: 0.5rem;' : '';
							$aria_label   = sprintf(
								/* translators: %s: color name */
								__( 'Color option: %s', 'aggressive-apparel' ),
								$color_term->name
							);

							$classes = 'aggressive-apparel-color-swatch aggressive-apparel-color-swatch--interactive';
							if ( $this->show_label ) {
								$classes .= ' aggressive-apparel-color-swatch--with-label';
							}

							$swatch_html = '<span class="' . esc_attr( $classes ) . ' aggressive-apparel-color-swatch__circle" ' .
								'style="background-color: ' . esc_attr( $color_value ) . ';" ' .
								'aria-label="' . esc_attr( $aria_label ) . '" ' .
								'role="img" ' .
								'tabindex="0" ' .
								'title="' . esc_attr( $color_term->name ) . '" ' .
								'data-color="' . esc_attr( $color_value ) . '" ' .
								'data-color-name="' . esc_attr( $color_term->name ) . '">' .
								'</span>';

							// Modify the label content based on show_label setting.
							if ( ! $this->show_label ) {
								// Replace content after input tag with swatch.
								$modified_content = preg_replace( '/(<input[^>]*>)(.*?)<\/label>$/s', '$1' . $swatch_html . '</label>', $label_content );
							} else {
								// Insert swatch after input tag, keep text.
								$modified_content = preg_replace( '/(<input[^>]*>)/', '$1' . $swatch_html, $label_content, 1 );
							}

							// Ensure we have valid content.
							if ( null === $modified_content ) {
								$modified_content = $label_content;
							}

							return $modified_content;
						}
					}
				}

				// Return unchanged if no color processing needed.
				return $label_content;
			},
			$block_content
		);

		// Ensure we always return a string, even if preg_replace_callback fails.
		return ( null !== $result ) ? $result : $block_content;
	}

	/**
	 * Retrieve the color value for a given term
	 *
	 * Checks both 'color_value' and 'color_hex' meta fields for backward compatibility.
	 *
	 * @param WP_Term $term The color term object.
	 * @return string The color value or empty string if not found.
	 */
	private function get_color_value_for_term( WP_Term $term ): string {
		$color_value = get_term_meta( $term->term_id, 'color_value', true );

		// Fallback to hex field for backward compatibility.
		if ( empty( $color_value ) ) {
			$color_value = get_term_meta( $term->term_id, 'color_hex', true );
		}

		return $color_value;
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
