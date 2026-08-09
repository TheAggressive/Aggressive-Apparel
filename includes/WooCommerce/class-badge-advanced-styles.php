<?php
/**
 * Product badge advanced style metadata.
 *
 * Keeps the taxonomy service focused while providing backward-compatible
 * storage for layered borders and typography controls.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Badge_Advanced_Styles {
	private const META_BORDER_MODE        = 'badge_border_mode';
	private const META_INNER_BORDER_COLOR = 'badge_inner_border_color';
	private const META_INNER_BORDER_WIDTH = 'badge_inner_border_width';
	private const META_BORDER_GAP         = 'badge_border_gap';
	private const META_FONT_SIZE          = 'badge_font_size';
	private const META_FONT_SIZE_PX       = 'badge_font_size_px';
	private const META_FONT_WEIGHT        = 'badge_font_weight';
	private const META_TEXT_TRANSFORM     = 'badge_text_transform';
	private const META_LETTER_SPACING     = 'badge_letter_spacing';
	private const META_LINE_HEIGHT        = 'badge_line_height';
	private const META_ICON_POSITION      = 'badge_icon_position';
	private const META_OFFSET_X           = 'badge_offset_x';
	private const META_OFFSET_Y           = 'badge_offset_y';
	private const META_ROTATION           = 'badge_rotation';
	private const META_SHADOW_BLUR        = 'badge_shadow_blur';
	private const META_SHADOW_SPREAD      = 'badge_shadow_spread';
	private const META_SHADOW_COLOR       = 'badge_shadow_color';
	private const META_GLASS              = 'badge_glass';
	private const META_FILL_MODE          = 'badge_fill_mode';
	private const META_GRADIENT_ANGLE     = 'badge_gradient_angle';
	private const META_GRADIENT_FROM      = 'badge_gradient_from';
	private const META_GRADIENT_TO        = 'badge_gradient_to';
	private const META_SHAPE              = 'badge_shape';
	private const META_SHAPE_MODE         = 'badge_shape_mode';
	private const META_SHAPE_SVG          = 'badge_shape_svg';
	private const META_FRAME_COLOR        = 'badge_frame_color';
	private const META_FRAME_WIDTH        = 'badge_frame_width';

	private const BORDER_MODES    = Badge_Style_Schema::BORDER_MODES;
	private const FONT_SIZES      = Badge_Style_Schema::FONT_SIZE_KEYS;
	private const FONT_WEIGHTS    = Badge_Style_Schema::FONT_WEIGHTS;
	private const TEXT_TRANSFORMS = Badge_Style_Schema::TEXT_TRANSFORMS;
	private const LETTER_SPACINGS = Badge_Style_Schema::LETTER_SPACINGS;

	/**
	 * Sanitize a badge color while retaining an alpha channel.
	 *
	 * @param mixed $value Candidate color.
	 * @return string
	 */
	public static function sanitize_badge_color( mixed $value ): string {
		return Badge_Style_Schema::sanitize_color( $value );
	}

	/** Register metadata added by the enhanced editor. */
	private static function register_advanced_style_meta(): void {
		register_meta(
			'term',
			self::META_INNER_BORDER_COLOR,
			array(
				'object_subtype'    => self::TAXONOMY,
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( self::class, 'sanitize_badge_color' ),
			),
		);

		register_meta(
			'term',
			self::META_INNER_BORDER_WIDTH,
			array(
				'object_subtype'    => self::TAXONOMY,
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => 1,
				'sanitize_callback' => static fn( $value ): int => min( absint( $value ), 6 ),
			),
		);

		self::register_enum_meta( self::META_BORDER_MODE, self::BORDER_MODES, 'none' );
		self::register_enum_meta( self::META_FONT_SIZE, self::FONT_SIZES, 'x-small' );
		self::register_enum_meta( self::META_TEXT_TRANSFORM, self::TEXT_TRANSFORMS, 'uppercase' );
		self::register_enum_meta( self::META_LETTER_SPACING, self::LETTER_SPACINGS, 'wide' );
		self::register_enum_meta( self::META_LINE_HEIGHT, Badge_Style_Schema::LINE_HEIGHTS, 'snug' );
		self::register_enum_meta( self::META_ICON_POSITION, Badge_Style_Schema::ICON_POSITIONS, 'start' );
		self::register_enum_meta( self::META_FILL_MODE, Badge_Style_Schema::FILL_MODES, 'solid' );
		self::register_enum_meta( self::META_SHAPE, Badge_Style_Schema::SHAPES, 'rect' );
		self::register_enum_meta( self::META_SHAPE_MODE, Badge_Style_Schema::SHAPE_MODES, 'mask' );

		register_meta(
			'term',
			self::META_FONT_WEIGHT,
			array(
				'object_subtype'    => self::TAXONOMY,
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => 700,
				'sanitize_callback' => static function ( $value ): int {
					$weight = absint( $value );
					return in_array( $weight, self::FONT_WEIGHTS, true ) ? $weight : 700;
				},
			),
		);

		self::register_int_meta( self::META_BORDER_GAP, 0, 0, 20 );
		self::register_int_meta( self::META_FONT_SIZE_PX, 0, 0, 32 );
		self::register_int_meta( self::META_OFFSET_X, 0, -40, 40, true );
		self::register_int_meta( self::META_OFFSET_Y, 0, -40, 40, true );
		self::register_int_meta( self::META_ROTATION, 0, -45, 45, true );
		self::register_int_meta( self::META_SHADOW_BLUR, 0, 0, 40 );
		self::register_int_meta( self::META_SHADOW_SPREAD, 0, 0, 20 );
		self::register_int_meta( self::META_GLASS, 0, 0, 1 );
		self::register_int_meta( self::META_GRADIENT_ANGLE, 135, 0, 360 );
		self::register_int_meta( self::META_FRAME_WIDTH, 2, 0, 8 );

		register_meta(
			'term',
			self::META_SHADOW_COLOR,
			array(
				'object_subtype'    => self::TAXONOMY,
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( self::class, 'sanitize_badge_color' ),
			),
		);
		register_meta(
			'term',
			self::META_GRADIENT_FROM,
			array(
				'object_subtype'    => self::TAXONOMY,
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => '#000000',
				'sanitize_callback' => array( self::class, 'sanitize_badge_color' ),
			),
		);
		register_meta(
			'term',
			self::META_GRADIENT_TO,
			array(
				'object_subtype'    => self::TAXONOMY,
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => '#444444',
				'sanitize_callback' => array( self::class, 'sanitize_badge_color' ),
			),
		);
		register_meta(
			'term',
			self::META_FRAME_COLOR,
			array(
				'object_subtype'    => self::TAXONOMY,
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( self::class, 'sanitize_badge_color' ),
			),
		);
		register_meta(
			'term',
			self::META_SHAPE_SVG,
			array(
				'object_subtype'    => self::TAXONOMY,
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => static fn( $value ): string => Badge_Style_Schema::sanitize_shape_svg( (string) $value ),
			),
		);
	}

	/**
	 * Register a clamped integer term-meta field.
	 *
	 * @param string $key           Meta key.
	 * @param int    $default_value Default.
	 * @param int    $min           Minimum.
	 * @param int    $max           Maximum.
	 * @param bool   $signed        Allow negative values.
	 */
	private static function register_int_meta( string $key, int $default_value, int $min, int $max, bool $signed = false ): void {
		register_meta(
			'term',
			$key,
			array(
				'object_subtype'    => self::TAXONOMY,
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => $default_value,
				'sanitize_callback' => static function ( $value ) use ( $min, $max, $signed ): int {
					return $signed
						? Badge_Style_Schema::clamp_signed( $value, $min, $max )
						: Badge_Style_Schema::clamp_int( $value, $min, $max );
				},
			),
		);
	}

	/**
	 * Register a string enum term-meta field.
	 *
	 * @param string   $key      Meta key.
	 * @param string[] $allowed  Allowed values.
	 * @param string   $fallback Invalid-value fallback.
	 */
	private static function register_enum_meta( string $key, array $allowed, string $fallback ): void {
		register_meta(
			'term',
			$key,
			array(
				'object_subtype'    => self::TAXONOMY,
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => $fallback,
				'sanitize_callback' => static function ( $value ) use ( $allowed, $fallback ): string {
					$value = sanitize_text_field( (string) $value );
					return in_array( $value, $allowed, true ) ? $value : $fallback;
				},
			),
		);
	}

	/**
	 * Get defaults for controls introduced by the enhanced editor.
	 *
	 * @return array<string, int|string>
	 */
	private static function get_advanced_style_defaults(): array {
		return array_merge(
			array(
				'border_mode'        => 'none',
				'inner_border_color' => '#ffffff',
				'inner_border_width' => 1,
				'font_weight'        => 700,
				'text_transform'     => 'uppercase',
				'letter_spacing'     => 'wide',
			),
			Badge_Style_Schema::designer_defaults(),
		);
	}



	/**
	 * Read enhanced styles, deriving border mode for badges saved before it existed.
	 *
	 * @param int                  $term_id Term ID.
	 * @param array<string, mixed> $legacy  Existing badge data.
	 * @return array<string, int|string>
	 */
	private static function get_advanced_style_data( int $term_id, array $legacy ): array {
		$stored_mode = metadata_exists( 'term', $term_id, self::META_BORDER_MODE )
			? get_term_meta( $term_id, self::META_BORDER_MODE, true )
			: '';
		$border_mode = is_string( $stored_mode ) && in_array( $stored_mode, self::BORDER_MODES, true )
			? $stored_mode
			: ( $legacy['border_width'] > 0 && 'none' !== $legacy['border_style'] ? 'single' : 'none' );

		$font_weight = (int) self::get_meta( $term_id, self::META_FONT_WEIGHT, '700' );
		if ( ! in_array( $font_weight, self::FONT_WEIGHTS, true ) ) {
			$font_weight = 700;
		}

		return array(
			'border_mode'        => $border_mode,
			'inner_border_color' => self::get_meta( $term_id, self::META_INNER_BORDER_COLOR, '#ffffff' ),
			'inner_border_width' => (int) self::get_meta( $term_id, self::META_INNER_BORDER_WIDTH, '1' ),
			'border_gap'         => (int) self::get_meta( $term_id, self::META_BORDER_GAP, '0' ),
			'font_size'          => self::validated_meta( $term_id, self::META_FONT_SIZE, self::FONT_SIZES, 'x-small' ),
			'font_size_px'       => (int) self::get_meta( $term_id, self::META_FONT_SIZE_PX, '0' ),
			'font_weight'        => $font_weight,
			'text_transform'     => self::validated_meta( $term_id, self::META_TEXT_TRANSFORM, self::TEXT_TRANSFORMS, 'uppercase' ),
			'letter_spacing'     => self::validated_meta( $term_id, self::META_LETTER_SPACING, self::LETTER_SPACINGS, 'wide' ),
			'line_height'        => self::validated_meta( $term_id, self::META_LINE_HEIGHT, Badge_Style_Schema::LINE_HEIGHTS, 'snug' ),
			'icon_position'      => self::validated_meta( $term_id, self::META_ICON_POSITION, Badge_Style_Schema::ICON_POSITIONS, 'start' ),
			'offset_x'           => (int) self::get_meta( $term_id, self::META_OFFSET_X, '0' ),
			'offset_y'           => (int) self::get_meta( $term_id, self::META_OFFSET_Y, '0' ),
			'rotation'           => (int) self::get_meta( $term_id, self::META_ROTATION, '0' ),
			'shadow_blur'        => (int) self::get_meta( $term_id, self::META_SHADOW_BLUR, '0' ),
			'shadow_spread'      => (int) self::get_meta( $term_id, self::META_SHADOW_SPREAD, '0' ),
			'shadow_color'       => self::get_meta( $term_id, self::META_SHADOW_COLOR, '' ),
			'glass'              => (int) self::get_meta( $term_id, self::META_GLASS, '0' ),
			'fill_mode'          => self::validated_meta( $term_id, self::META_FILL_MODE, Badge_Style_Schema::FILL_MODES, 'solid' ),
			'gradient_angle'     => (int) self::get_meta( $term_id, self::META_GRADIENT_ANGLE, '135' ),
			'gradient_from'      => self::get_meta( $term_id, self::META_GRADIENT_FROM, '#000000' ),
			'gradient_to'        => self::get_meta( $term_id, self::META_GRADIENT_TO, '#444444' ),
			'shape'              => self::validated_meta( $term_id, self::META_SHAPE, Badge_Style_Schema::SHAPES, 'rect' ),
			'shape_mode'         => self::validated_meta( $term_id, self::META_SHAPE_MODE, Badge_Style_Schema::SHAPE_MODES, 'mask' ),
			'shape_svg'          => self::get_meta( $term_id, self::META_SHAPE_SVG, '' ),
			'frame_color'        => self::get_meta( $term_id, self::META_FRAME_COLOR, '' ),
			'frame_width'        => (int) self::get_meta( $term_id, self::META_FRAME_WIDTH, '2' ),
		);
	}

	/**
	 * Read a stored enum field and enforce its allowlist.
	 *
	 * @param int      $term_id  Term ID.
	 * @param string   $key      Meta key.
	 * @param string[] $allowed  Allowed values.
	 * @param string   $fallback Invalid or missing value fallback.
	 * @return string
	 */
	private static function validated_meta( int $term_id, string $key, array $allowed, string $fallback ): string {
		$value = self::get_meta( $term_id, $key, $fallback );
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}
}
