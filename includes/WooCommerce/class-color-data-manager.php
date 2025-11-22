<?php
/**
 * Color Data Manager Class
 *
 * Handles color data operations and validation
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Color Data Manager Class
 *
 * Handles color data operations, validation, and storage
 *
 * @since 1.0.0
 */
class Color_Data_Manager {

	/**
	 * Color attribute name
	 */
	private const ATTRIBUTE_NAME = 'pa_color';

	/**
	 * Default colors
	 */
	private const DEFAULT_COLORS = array(
		'black' => '#000000',
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		// No dependencies needed.
	}

	/**
	 * Get all color terms with their data
	 *
	 * @return array Array of color terms with data.
	 */
	public function get_color_terms(): array {
		$attribute_name = self::ATTRIBUTE_NAME;

		$terms = get_terms(
			array(
				'taxonomy'   => $attribute_name,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		$colors = array();

		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				$colors[ $term->slug ] = $this->format_color_term( $term );
			}
		}

		return $colors;
	}

	/**
	 * Format color term data
	 *
	 * @param \WP_Term $term Term object.
	 * @return array Formatted color data.
	 */
	private function format_color_term( \WP_Term $term ): array {
		$color_value  = get_term_meta( $term->term_id, 'color_value', true );
		$format_value = get_term_meta( $term->term_id, 'color_format', true );
		$color_format = $format_value ? $format_value : 'hex';

		// Fallback to hex for backward compatibility.
		if ( empty( $color_value ) ) {
			$hex_value    = get_term_meta( $term->term_id, 'color_hex', true );
			$color_value  = $hex_value ? $hex_value : '#000000';
			$color_format = 'hex';
		}

		return array(
			'id'     => $term->term_id,
			'name'   => $term->name,
			'slug'   => $term->slug,
			'value'  => $color_value,
			'format' => $color_format,
			'hex'    => $color_value, // For hex format, value is already hex.
			'count'  => $term->count,
		);
	}

	/**
	 * Add custom color term
	 *
	 * @param string $name   Color name.
	 * @param string $value  Color value.
	 * @param string $format Color format.
	 * @return int|\WP_Error Term ID on success, WP_Error on failure.
	 */
	public function add_custom_color( string $name, string $value, string $format = 'hex' ): int|\WP_Error {
		$attribute_name = self::ATTRIBUTE_NAME;

		if ( ! taxonomy_exists( $attribute_name ) ) {
			return new \WP_Error( 'taxonomy_not_found', 'Color attribute taxonomy does not exist.' );
		}

		// Validate color value.
		if ( ! $this->validate_color_value( $value, $format ) ) {
			return new \WP_Error(
				'invalid_color',
				'hex' === $format
					? 'Invalid hex color format. Use format #RRGGBB.'
					: 'Invalid OKLCH color format. Use format oklch(L% C H).'
			);
		}

		$term_slug = sanitize_title( $name );

		$result = wp_insert_term(
			$name,
			$attribute_name,
			array( 'slug' => $term_slug )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		update_term_meta( $result['term_id'], 'color_value', $value );
		update_term_meta( $result['term_id'], 'color_format', $format );

		// Keep backward compatibility with hex field.
		if ( 'hex' === $format ) {
			update_term_meta( $result['term_id'], 'color_hex', $value );
		}

		return $result['term_id'];
	}

	/**
	 * Validate color value based on format
	 *
	 * @param string $color_value Color value to validate.
	 * @param string $color_format Color format ('hex' or 'oklch').
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_color_value( string $color_value, string $color_format ): bool {
		switch ( $color_format ) {
			case 'hex':
				return (bool) preg_match( '/^#[a-fA-F0-9]{6}$/', $color_value );
			case 'oklch':
				// OKLCH format: oklch(L% C H) or oklch(L% C H / A).
				return (bool) preg_match(
					'/^oklch\(\s*\d*\.?\d+%?\s+\d*\.?\d+\s+\d*\.?\d+(?:\s*\/\s*\d*\.?\d+)?\s*\)$/i',
					$color_value
				);
			default:
				return false;
		}
	}

	/**
	 * Save color field value
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function save_color_field( int $term_id ): void {
		// Verify nonce for security.
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'update-tag_' . $term_id ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Default to hex format for user-friendly color picker.
		$color_format = 'hex';
		$color_value  = isset( $_POST['color_value'] ) ? sanitize_hex_color( wp_unslash( $_POST['color_value'] ) ) : '';

		// Validate color value.
		if ( ! empty( $color_value ) && $this->validate_color_value( $color_value, $color_format ) ) {
			update_term_meta( $term_id, 'color_value', $color_value );
			update_term_meta( $term_id, 'color_format', $color_format );

			// Keep backward compatibility with hex field.
			update_term_meta( $term_id, 'color_hex', $color_value );
		} else {
			// Clear color data if invalid.
			delete_term_meta( $term_id, 'color_value' );
			delete_term_meta( $term_id, 'color_format' );
			delete_term_meta( $term_id, 'color_hex' );
		}
	}

	/**
	 * Add default color terms
	 *
	 * @return void
	 */
	public function add_default_color_terms(): void {
		$default_colors = self::DEFAULT_COLORS;

		foreach ( $default_colors as $color_name => $color_value ) {
			$this->add_color_term( $color_name, $color_value, 'hex' );
		}
	}

	/**
	 * Add a single color term
	 *
	 * @param string $color_name  Color name (slug).
	 * @param string $color_value Color value.
	 * @param string $color_format Color format.
	 * @return void
	 */
	private function add_color_term( string $color_name, string $color_value, string $color_format = 'hex' ): void {
		$attribute_name = self::ATTRIBUTE_NAME;

		if ( ! taxonomy_exists( $attribute_name ) ) {
			return;
		}

		// Check if term already exists.
		$existing_term = get_term_by( 'slug', $color_name, $attribute_name );

		if ( ! $existing_term ) {
			// Create the term.
			$term_id = wp_insert_term(
				ucwords( str_replace( '-', ' ', $color_name ) ), // Term name.
				$attribute_name,
				array(
					'slug' => $color_name,
				)
			);

			if ( ! is_wp_error( $term_id ) ) {
				// Store the color value and format as term meta.
				update_term_meta( $term_id['term_id'], 'color_value', $color_value );
				update_term_meta( $term_id['term_id'], 'color_format', $color_format );
				// Keep backward compatibility with hex field.
				if ( 'hex' === $color_format ) {
					update_term_meta( $term_id['term_id'], 'color_hex', $color_value );
				}
			}
		} else {
			// Update color value if term exists but no color value.
			$current_value = get_term_meta( $existing_term->term_id, 'color_value', true );
			if ( empty( $current_value ) ) {
				update_term_meta( $existing_term->term_id, 'color_value', $color_value );
				update_term_meta( $existing_term->term_id, 'color_format', $color_format );
				if ( 'hex' === $color_format ) {
					update_term_meta( $existing_term->term_id, 'color_hex', $color_value );
				}
			}
		}
	}
}
