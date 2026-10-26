<?php
/**
 * Color Data Manager Class
 *
 * Reads and writes colour swatch data. Swatches are WooCommerce's own visual
 * attribute term meta — `color` (a hex) or `image` (an attachment ID) — the
 * same values WooCommerce draws on the product page; every theme surface
 * (product cards, Quick View, sticky add-to-cart, filters) reads them here.
 *
 * A term without them falls back to the theme's pre-migration meta, then to a
 * colour guessed from its name (resolve_swatch()). Nothing is stored for a
 * fallback: WooCommerce's own storefront reads get it through
 * `default_term_metadata`, so it follows renames and an admin can clear a
 * swatch, exactly as the old dynamic fallback did.
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Core\Cache_Helper;

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
	 * WooCommerce visual-attribute term meta: the swatch colour (hex).
	 */
	public const COLOR_META = 'color';

	/**
	 * WooCommerce visual-attribute term meta: the swatch image (attachment ID).
	 * An image wins over a colour, as in WooCommerce.
	 */
	public const IMAGE_META = 'image';

	/**
	 * Default colors
	 */
	private const DEFAULT_COLORS = array(
		'black' => '#000000',
	);

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
			// Prime term meta in one query so format_color_term() does not N+1.
			update_termmeta_cache( wp_list_pluck( $terms, 'term_id' ) );

			foreach ( $terms as $term ) {
				$colors[ $term->slug ] = $this->format_color_term( $term );
			}
		}

		return $colors;
	}

	/**
	 * Format a color term for display.
	 *
	 * @param \WP_Term $term Color term.
	 * @return array Formatted color data.
	 */
	private function format_color_term( \WP_Term $term ): array {
		$swatch = self::resolve_swatch( $term );

		return array(
			'id'    => $term->term_id,
			'name'  => $term->name,
			'slug'  => $term->slug,
			'type'  => $swatch['type'],
			'value' => $swatch['value'],
			'count' => $term->count,
		);
	}

	/**
	 * The swatch a term shows: WooCommerce's `image`, else its `color`; for a
	 * term with neither stored, the theme's pre-migration pattern / colour, else
	 * a colour guessed from the name. The old meta stays on migrated terms as a
	 * rollback copy, so it is never consulted once WooCommerce meta exists.
	 * Reads stored rows only, never the `default_term_metadata` fallback this
	 * feeds.
	 *
	 * @param \WP_Term $term Color term.
	 * @return array{type: 'pattern'|'solid', value: string, image_id: int}
	 */
	public static function resolve_swatch( \WP_Term $term ): array {
		$id     = $term->term_id;
		$stored = self::has_swatch_meta( $id );

		$image_id = $stored
			? self::image_id( get_metadata_raw( 'term', $id, self::IMAGE_META, true ) )
			: ( 'pattern' === get_metadata_raw( 'term', $id, 'color_type', true ) ? self::image_id( get_metadata_raw( 'term', $id, 'color_pattern_id', true ) ) : 0 );
		if ( $image_id ) {
			return array(
				'type'     => 'pattern',
				'value'    => (string) wp_get_attachment_url( $image_id ),
				'image_id' => $image_id,
			);
		}

		$color = self::to_hex( (string) get_metadata_raw( 'term', $id, $stored ? self::COLOR_META : 'color_value', true ) );

		return array(
			'type'     => 'solid',
			'value'    => '' !== $color ? $color : self::guess_color_from_name( $term->name, $term->slug ),
			'image_id' => 0,
		);
	}

	/**
	 * Serve a colour term's fallback swatch to WooCommerce's storefront reads
	 * of `color` / `image` when the term stores none.
	 *
	 * Admin screens keep seeing only what is stored, so WooCommerce's term
	 * fields show (and save) the real value.
	 *
	 * @param mixed  $value     Default value.
	 * @param int    $object_id Term ID.
	 * @param string $meta_key  Meta key.
	 * @param bool   $single    Whether a single value was requested.
	 * @return mixed
	 */
	public static function default_swatch_meta( $value, $object_id, $meta_key, $single ) {
		if ( ! in_array( $meta_key, array( self::COLOR_META, self::IMAGE_META ), true ) || ( is_admin() && ! wp_doing_ajax() ) ) {
			return $value;
		}

		$term = get_term( (int) $object_id );
		if ( ! $term instanceof \WP_Term || self::ATTRIBUTE_NAME !== $term->taxonomy ) {
			return $value;
		}

		$swatch   = self::resolve_swatch( $term );
		$fallback = self::IMAGE_META === $meta_key
			? ( 'pattern' === $swatch['type'] ? $swatch['image_id'] : '' )
			: ( 'solid' === $swatch['type'] ? $swatch['value'] : '' );
		if ( '' === $fallback ) {
			return $value;
		}

		return $single ? $fallback : array( $fallback );
	}

	/**
	 * A stored attachment ID, when it is an image that still exists.
	 *
	 * @param mixed $value Stored meta value.
	 */
	private static function image_id( $value ): int {
		$image_id = absint( is_scalar( $value ) ? $value : 0 );

		return $image_id && wp_attachment_is_image( $image_id ) ? $image_id : 0;
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
	 * Check whether an attribute taxonomy/slug is a color attribute.
	 *
	 * Accepts the canonical taxonomy with or without WooCommerce's variation
	 * input prefix.
	 *
	 * @param string $slug Attribute slug or input name.
	 * @return bool
	 */
	public static function is_color_attribute( string $slug ): bool {
		$bare = strtolower( (string) preg_replace( '/^attribute_/', '', $slug ) );

		return self::ATTRIBUTE_NAME === $bare;
	}

	/**
	 * Transient key for cross-request swatch data.
	 *
	 * @var string
	 */
	private const SWATCH_TRANSIENT_KEY = 'aggressive_apparel_color_swatch_data_v2';

	/**
	 * Swatch data TTL (invalidated immediately on color-term mutations).
	 *
	 * @var int
	 */
	private const SWATCH_CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Per-request memo for get_safe_swatch_data().
	 *
	 * @var array<string, array{value: string, type: string, name: string}>|null
	 */
	private static ?array $swatch_data_memo = null;

	/**
	 * Get swatch data with defensive error handling.
	 *
	 * Wraps get_swatch_data() in a try-catch so callers like Quick_View
	 * and Sticky_Add_To_Cart don't need their own duplicate wrapper.
	 * Memoized per request and across requests via a transient — Quick_View,
	 * Sticky_Add_To_Cart, and the per-block swatch manager all read the same
	 * term data.
	 *
	 * @return array<string, array{value: string, type: string, name: string}>
	 */
	public static function get_safe_swatch_data(): array {
		if ( null !== self::$swatch_data_memo ) {
			return self::$swatch_data_memo;
		}

		/**
		 * Swatch map from transient or rebuild.
		 *
		 * @var array<string, array{value: string, type: string, name: string}> $data
		 */
		$data = Cache_Helper::remember(
			self::SWATCH_TRANSIENT_KEY,
			self::SWATCH_CACHE_TTL,
			static function (): array {
				try {
					return ( new self() )->get_swatch_data();
				} catch ( \Throwable $e ) {
					return array();
				}
			},
			'is_array'
		);

		self::$swatch_data_memo = $data;
		return self::$swatch_data_memo;
	}

	/**
	 * Reset request + persistent swatch caches after a color-term mutation.
	 *
	 * @return void
	 */
	public static function flush_swatch_data_memo(): void {
		self::$swatch_data_memo = null;
		delete_transient( self::SWATCH_TRANSIENT_KEY );
	}

	/**
	 * Serve fallback swatches to WooCommerce (default_swatch_meta()) and hook
	 * the memo flush to the color taxonomy's term lifecycle.
	 *
	 * The flush covers mutation paths outside this class — WooCommerce's swatch fields,
	 * saved on created_term / edit_term (both fire before the taxonomy-specific
	 * hooks below), and term deletes/renames —
	 * so invalidation holds by construction instead of relying on each
	 * mutator to remember a manual flush call. This class's own mutators
	 * still flush at method end because their meta writes land after the
	 * created_/edited_ action has already fired.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		$attribute_name = self::ATTRIBUTE_NAME;

		add_filter( 'default_term_metadata', array( self::class, 'default_swatch_meta' ), 10, 4 );
		add_action( 'created_' . $attribute_name, array( self::class, 'flush_swatch_data_memo' ), 99, 0 );
		add_action( 'edited_' . $attribute_name, array( self::class, 'flush_swatch_data_memo' ), 99, 0 );
		add_action( 'delete_' . $attribute_name, array( self::class, 'flush_swatch_data_memo' ), 99, 0 );
	}

	/**
	 * Add default color terms
	 *
	 * @return void
	 */
	public function add_default_color_terms(): void {
		$default_colors = self::DEFAULT_COLORS;

		foreach ( $default_colors as $color_name => $color_value ) {
			$this->add_color_term( $color_name, $color_value );
		}
	}

	/**
	 * Add a single color term
	 *
	 * @param string $color_name  Color name (slug).
	 * @param string $color_value Color value (hex or oklch()).
	 * @return void
	 */
	private function add_color_term( string $color_name, string $color_value ): void {
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
				update_term_meta( $term_id['term_id'], self::COLOR_META, self::to_hex( $color_value ) );
			}
		} elseif ( ! self::has_swatch_meta( $existing_term->term_id ) ) {
			// Fill in a colour only where the term has no swatch yet.
			update_term_meta( $existing_term->term_id, self::COLOR_META, self::to_hex( $color_value ) );
		}

		self::flush_swatch_data_memo();
	}

	/**
	 * Whether a term already carries a WooCommerce swatch (colour or image).
	 *
	 * @param int $term_id Term ID.
	 */
	public static function has_swatch_meta( int $term_id ): bool {
		return metadata_exists( 'term', $term_id, self::COLOR_META ) || metadata_exists( 'term', $term_id, self::IMAGE_META );
	}

	/**
	 * Convert a stored colour to the `#rrggbb` hex WooCommerce keeps.
	 *
	 * The kind is read from the value itself, not a stored format flag: the
	 * old storefront drew a hex or an `oklch()` value whatever its flag said.
	 * `oklch()` maps to the nearest sRGB colour (clipped per channel, alpha
	 * dropped), exact for every opaque colour sRGB can show.
	 *
	 * @param string $value Colour value.
	 * @return string Lowercase hex, or '' when the value can't be read.
	 */
	public static function to_hex( string $value ): string {
		$value = trim( $value );
		if ( 0 === stripos( $value, 'oklch(' ) ) {
			return (string) Badge_Palette::oklch_to_hex( $value );
		}

		return strtolower( (string) sanitize_hex_color( $value ) );
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
