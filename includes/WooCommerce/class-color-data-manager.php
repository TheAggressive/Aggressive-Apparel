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
		$color_type   = get_term_meta( $term->term_id, 'color_type', true );
		$color_value  = get_term_meta( $term->term_id, 'color_value', true );
		$format_value = get_term_meta( $term->term_id, 'color_format', true );
		$pattern_id   = get_term_meta( $term->term_id, 'color_pattern_id', true );
		$color_format = $format_value ? $format_value : 'hex';

		// Default to solid for backward compatibility.
		if ( empty( $color_type ) ) {
			$color_type = 'solid';
		}

		// Handle different color types.
		if ( 'pattern' === $color_type ) {
			// For patterns, we use the pattern image URL as the display value.
			$pattern_url   = $pattern_id ? wp_get_attachment_url( $pattern_id ) : '';
			$display_value = $pattern_url;

			return array(
				'id'          => $term->term_id,
				'name'        => $term->name,
				'slug'        => $term->slug,
				'type'        => 'pattern',
				'value'       => $display_value,
				'pattern_id'  => $pattern_id,
				'pattern_url' => $pattern_url,
				'format'      => 'pattern',
				'count'       => $term->count,
			);
		} else {
			// Solid color handling (existing logic).
			// Fallback to hex for backward compatibility.
			if ( empty( $color_value ) ) {
				$hex_value    = get_term_meta( $term->term_id, 'color_hex', true );
				$color_value  = $hex_value ? $hex_value : self::guess_color_from_name( $term->name, $term->slug );
				$color_format = 'hex';
			}

			return array(
				'id'     => $term->term_id,
				'name'   => $term->name,
				'slug'   => $term->slug,
				'type'   => 'solid',
				'value'  => $color_value,
				'format' => $color_format,
				'hex'    => $color_value, // For hex format, value is already hex.
				'count'  => $term->count,
			);
		}
	}

	/**
	 * Get a simplified swatch data map for use in Interactivity API stores.
	 *
	 * Returns a slug-keyed (and term-ID-keyed fallback) map of color
	 * display values suitable for rendering swatches in JS or PHP.
	 *
	 * @return array<string, array{value: string, type: string, name: string}>
	 */
	public function get_swatch_data(): array {
		$colors = $this->get_color_terms();
		$data   = array();

		foreach ( $colors as $slug => $color_info ) {
			$color_value = $color_info['value'] ?? '';
			if ( $color_value ) {
				$entry         = array(
					'value' => $color_value,
					'type'  => $color_info['type'] ?? 'solid',
					'name'  => $color_info['name'] ?? (string) $slug,
				);
				$data[ $slug ] = $entry;

				// Also key by term ID so JS can fall back to ID-based lookup
				// when the Store API returns numeric IDs instead of slugs.
				if ( ! empty( $color_info['id'] ) ) {
					$data[ (string) $color_info['id'] ] = $entry;
				}
			}
		}

		return $data;
	}

	/**
	 * Color attribute slugs (without 'attribute_' prefix).
	 *
	 * Shared list used by Quick_View, Sticky_Add_To_Cart, and
	 * Color_Block_Swatch_Manager to identify color attributes.
	 *
	 * @var string[]
	 */
	private const COLOR_SLUGS = array( 'pa_color', 'pa_colour', 'color', 'colour' );

	/**
	 * Check whether an attribute taxonomy/slug is a color attribute.
	 *
	 * Accepts both prefixed ('attribute_pa_color') and bare ('pa_color')
	 * slugs and normalises before comparison.
	 *
	 * @param string $slug Attribute slug or input name.
	 * @return bool
	 */
	public static function is_color_attribute( string $slug ): bool {
		$bare = strtolower( (string) preg_replace( '/^attribute_/', '', $slug ) );
		return in_array( $bare, self::COLOR_SLUGS, true );
	}

	/**
	 * Get swatch data with defensive error handling.
	 *
	 * Wraps get_swatch_data() in a try-catch so callers like Quick_View
	 * and Sticky_Add_To_Cart don't need their own duplicate wrapper.
	 *
	 * @return array<string, array{value: string, type: string, name: string}>
	 */
	public static function get_safe_swatch_data(): array {
		try {
			return ( new self() )->get_swatch_data();
		} catch ( \Throwable $e ) {
			return array();
		}
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

	/**
	 * Guess a hex color from a term name or slug.
	 *
	 * Used as a fallback when color terms have no metadata (e.g. imported
	 * from Printful). Matches common apparel color names to hex values.
	 *
	 * @param string $name Term display name.
	 * @param string $slug Term slug.
	 * @return string Hex color value.
	 */
	private static function guess_color_from_name( string $name, string $slug ): string {
		static $map = array(
			'black'        => '#000000',
			'white'        => '#ffffff',
			'red'          => '#dc2626',
			'blue'         => '#2563eb',
			'navy'         => '#1e3a5f',
			'navy-blue'    => '#1e3a5f',
			'green'        => '#16a34a',
			'yellow'       => '#eab308',
			'orange'       => '#ea580c',
			'purple'       => '#9333ea',
			'pink'         => '#ec4899',
			'brown'        => '#78350f',
			'gray'         => '#6b7280',
			'grey'         => '#6b7280',
			'charcoal'     => '#374151',
			'silver'       => '#9ca3af',
			'gold'         => '#ca8a04',
			'tan'          => '#d2b48c',
			'beige'        => '#f5f0dc',
			'cream'        => '#fffdd0',
			'ivory'        => '#fffff0',
			'olive'        => '#65a30d',
			'maroon'       => '#7f1d1d',
			'burgundy'     => '#800020',
			'coral'        => '#f87171',
			'teal'         => '#0d9488',
			'cyan'         => '#06b6d4',
			'indigo'       => '#4f46e5',
			'lavender'     => '#a78bfa',
			'mint'         => '#86efac',
			'peach'        => '#fdba74',
			'salmon'       => '#fb923c',
			'sand'         => '#c2b280',
			'slate'        => '#64748b',
			'forest-green' => '#166534',
			'royal-blue'   => '#1d4ed8',
			'sky-blue'     => '#38bdf8',
			'baby-blue'    => '#93c5fd',
			'light-blue'   => '#93c5fd',
			'dark-blue'    => '#1e3a8a',
			'light-gray'   => '#d1d5db',
			'light-grey'   => '#d1d5db',
			'dark-gray'    => '#374151',
			'dark-grey'    => '#374151',
			'heather-gray' => '#9ca3af',
			'heather-grey' => '#9ca3af',
			'khaki'        => '#bdb76b',
			'rust'         => '#b7410e',
			'wine'         => '#722f37',
			'aqua'         => '#06b6d4',
			'magenta'      => '#d946ef',
			'lilac'        => '#c4b5fd',
			'rose'         => '#fb7185',
			'hot-pink'     => '#ec4899',
		);

		// Try slug first (lowercase, hyphenated), then lowercase name.
		$key = strtolower( $slug );
		if ( isset( $map[ $key ] ) ) {
			return $map[ $key ];
		}

		$key = strtolower( str_replace( ' ', '-', $name ) );
		if ( isset( $map[ $key ] ) ) {
			return $map[ $key ];
		}

		// Check if the name contains a known color as a substring
		// (e.g. "Heather Sport Dark Navy" → "navy").
		foreach ( $map as $color_name => $hex ) {
			if ( str_contains( strtolower( $name ), str_replace( '-', ' ', $color_name ) ) ) {
				return $hex;
			}
		}

		return '#000000';
	}
}
