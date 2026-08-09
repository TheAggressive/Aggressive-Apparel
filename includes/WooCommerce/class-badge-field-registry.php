<?php
/**
 * Single source of truth for badge fields.
 *
 * Every badge field was previously written out in five places — schema
 * defaults, the studio forward map, the REST inverse map, and two save paths —
 * with the bounds and fallbacks retyped by hand in each. Adding a field meant
 * five coordinated edits with no compiler or test to catch a miss, and the
 * copies had already drifted: `inner_border_color` defaulted to '' in the
 * schema and '#ffffff' in the editor.
 *
 * Each field is declared once here; the other sites derive from it. Fields with
 * genuinely irregular handling declare it explicitly (see `save` and `max_len`)
 * rather than being quietly excluded.
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
 * Declarative badge field table.
 */
class Badge_Field_Registry {

	/**
	 * Fields whose POST/meta key is not simply `badge_` + schema key.
	 *
	 * @var array<string, string>
	 */
	private const FIELD_KEY_OVERRIDES = array(
		'badge_type' => 'badge_type',
	);

	/**
	 * Cached, fully-expanded field table.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private static ?array $fields = null;

	/**
	 * The badge field table, keyed by schema key.
	 *
	 * Type reference:
	 * - `color`  — badge-color allowlist; `empty` is used when the value clears.
	 * - `int`    — unsigned, clamped to `max` (and `min`, default 0).
	 * - `signed` — may be negative; clamped to `min`/`max`.
	 * - `enum`   — allowlisted against `allowed`.
	 * - `int_enum` — integer allowlisted against `allowed`.
	 * - `bool`   — checkbox truthiness, stored 0/1.
	 * - `text`   — sanitize_text_field, optionally truncated to `max_len`.
	 * - `icon_slug` — must resolve in the Icons library or clear.
	 * - `svg_icon` / `shape_svg` — passed through their SVG sanitizer.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function fields(): array {
		if ( null !== self::$fields ) {
			return self::$fields;
		}

		$fields = array(
			// Legacy core fields.
			'bg_color'           => array(
				'type'    => 'color',
				'default' => '#000000',
				'empty'   => '#000000',
			),
			'text_color'         => array(
				'type'    => 'color',
				'default' => '#ffffff',
				'empty'   => '#ffffff',
			),
			'icon'               => array(
				'type'    => 'text',
				'default' => '',
				'max_len' => 10,
			),
			'library_icon'       => array(
				'type'    => 'icon_slug',
				'default' => '',
			),
			'svg_icon'           => array(
				'type'    => 'svg_icon',
				'default' => '',
			),
			'icon_color'         => array(
				'type'    => 'color',
				'default' => '',
				'empty'   => '',
			),
			'icon_size'          => array(
				'type'    => 'int',
				'default' => 0,
				'max'     => 64,
			),
			'icon_gap'           => array(
				'type'    => 'int',
				'default' => 0,
				'max'     => 40,
			),
			'priority'           => array(
				'type'    => 'int',
				'default' => 10,
				'max'     => 100,
			),
			'border_color'       => array(
				'type'    => 'color',
				'default' => '',
				'empty'   => '',
			),
			'border_width'       => array(
				'type'    => 'int',
				'default' => 0,
				'max'     => 10,
			),
			'border_style'       => array(
				'type'    => 'enum',
				'default' => 'none',
				'allowed' => Badge_Style_Schema::BORDER_STYLES,
			),
			'radius_tl'          => array(
				'type'    => 'int',
				'default' => 4,
				'max'     => 100,
			),
			'radius_tr'          => array(
				'type'    => 'int',
				'default' => 4,
				'max'     => 100,
			),
			'radius_br'          => array(
				'type'    => 'int',
				'default' => 4,
				'max'     => 100,
			),
			'radius_bl'          => array(
				'type'    => 'int',
				'default' => 4,
				'max'     => 100,
			),
			'padding_x'          => array(
				'type'    => 'int',
				'default' => 8,
				'max'     => 50,
			),
			'padding_y'          => array(
				'type'    => 'int',
				'default' => 3,
				'max'     => 50,
			),
			'position'           => array(
				'type'    => 'enum',
				'default' => 'top-left',
				'allowed' => Badge_Style_Schema::POSITIONS,
			),
			// System badges own their type; the editor must never write it back.
			'badge_type'         => array(
				'type'    => 'enum',
				'default' => 'custom',
				'allowed' => Badge_Style_Schema::BADGE_TYPES,
				'save'    => false,
			),

			// Designer fields.
			'border_mode'        => array(
				'type'    => 'enum',
				'default' => 'none',
				'allowed' => Badge_Style_Schema::BORDER_MODES,
			),
			'inner_border_color' => array(
				'type'    => 'color',
				'default' => '#ffffff',
				'empty'   => '',
			),
			'inner_border_width' => array(
				'type'    => 'int',
				'default' => 1,
				'max'     => 6,
			),
			'border_gap'         => array(
				'type'    => 'int',
				'default' => 0,
				'max'     => 20,
			),
			'font_size'          => array(
				'type'    => 'enum',
				'default' => 'x-small',
				'allowed' => Badge_Style_Schema::FONT_SIZE_KEYS,
			),
			'font_size_px'       => array(
				'type'    => 'int',
				'default' => 0,
				'max'     => 32,
			),
			'font_weight'        => array(
				'type'    => 'int_enum',
				'default' => 700,
				'allowed' => Badge_Style_Schema::FONT_WEIGHTS,
			),
			'text_transform'     => array(
				'type'    => 'enum',
				'default' => 'uppercase',
				'allowed' => Badge_Style_Schema::TEXT_TRANSFORMS,
			),
			'letter_spacing'     => array(
				'type'    => 'enum',
				'default' => 'wide',
				'allowed' => Badge_Style_Schema::LETTER_SPACINGS,
			),
			'line_height'        => array(
				'type'    => 'enum',
				'default' => 'snug',
				'allowed' => Badge_Style_Schema::LINE_HEIGHTS,
			),
			'icon_position'      => array(
				'type'    => 'enum',
				'default' => 'start',
				'allowed' => Badge_Style_Schema::ICON_POSITIONS,
			),
			'offset_x'           => array(
				'type'    => 'signed',
				'default' => 0,
				'min'     => -40,
				'max'     => 40,
			),
			'offset_y'           => array(
				'type'    => 'signed',
				'default' => 0,
				'min'     => -40,
				'max'     => 40,
			),
			'rotation'           => array(
				'type'    => 'signed',
				'default' => 0,
				'min'     => -45,
				'max'     => 45,
			),
			'shadow_blur'        => array(
				'type'    => 'int',
				'default' => 0,
				'max'     => 40,
			),
			'shadow_spread'      => array(
				'type'    => 'int',
				'default' => 0,
				'max'     => 20,
			),
			'shadow_color'       => array(
				'type'    => 'color',
				'default' => '',
				'empty'   => '',
			),
			'glass'              => array(
				'type'    => 'bool',
				'default' => 0,
			),
			'fill_mode'          => array(
				'type'    => 'enum',
				'default' => 'solid',
				'allowed' => Badge_Style_Schema::FILL_MODES,
			),
			'gradient_angle'     => array(
				'type'    => 'int',
				'default' => 135,
				'max'     => 360,
			),
			'gradient_from'      => array(
				'type'    => 'color',
				'default' => '#000000',
				'empty'   => '#000000',
			),
			'gradient_to'        => array(
				'type'    => 'color',
				'default' => '#444444',
				'empty'   => '#444444',
			),
			'shape'              => array(
				'type'    => 'enum',
				'default' => 'rect',
				'allowed' => Badge_Style_Schema::SHAPES,
			),
			'shape_mode'         => array(
				'type'    => 'enum',
				'default' => 'mask',
				'allowed' => Badge_Style_Schema::SHAPE_MODES,
			),
			'shape_svg'          => array(
				'type'    => 'shape_svg',
				'default' => '',
			),
			'frame_color'        => array(
				'type'    => 'color',
				'default' => '',
				'empty'   => '',
			),
			'frame_width'        => array(
				'type'    => 'int',
				'default' => 2,
				'max'     => 8,
			),
		);

		foreach ( $fields as $key => $spec ) {
			$fields[ $key ]['key']   = $key;
			$fields[ $key ]['field'] = self::FIELD_KEY_OVERRIDES[ $key ] ?? 'badge_' . $key;
			$fields[ $key ]['save']  = $spec['save'] ?? true;
			$fields[ $key ]['min']   = $spec['min'] ?? 0;
		}

		self::$fields = $fields;

		return self::$fields;
	}

	/**
	 * Schema key => default value.
	 *
	 * @return array<string, int|string>
	 */
	public static function defaults(): array {
		$defaults = array();

		foreach ( self::fields() as $key => $spec ) {
			/** Declared default. @var int|string $default */
			$default          = $spec['default'];
			$defaults[ $key ] = $default;
		}

		return $defaults;
	}

	/**
	 * Schema key => `badge_*` POST/meta key.
	 *
	 * @return array<string, string>
	 */
	public static function field_keys(): array {
		$map = array();

		foreach ( self::fields() as $key => $spec ) {
			$map[ $key ] = (string) $spec['field'];
		}

		return $map;
	}

	/**
	 * Whether a field's value is an integer in storage.
	 *
	 * @param array<string, mixed> $spec Field spec.
	 * @return bool
	 */
	public static function is_int_field( array $spec ): bool {
		return in_array( $spec['type'], array( 'int', 'signed', 'bool', 'int_enum' ), true );
	}

	/**
	 * Coerce a raw value using its field spec.
	 *
	 * Shared by the save path (POST input) and the REST compile path (JSON
	 * input), so an out-of-range value is rejected identically wherever it
	 * enters.
	 *
	 * @param string $key   Schema key.
	 * @param mixed  $value Raw value.
	 * @return int|string
	 */
	public static function sanitize( string $key, mixed $value ): int|string {
		$fields = self::fields();
		if ( ! isset( $fields[ $key ] ) ) {
			return '';
		}

		$spec = $fields[ $key ];

		switch ( $spec['type'] ) {
			case 'color':
				$color = Custom_Badge_Taxonomy::sanitize_badge_color( $value );
				return '' !== $color ? $color : (string) $spec['empty'];

			case 'int':
				return max( (int) $spec['min'], min( (int) $spec['max'], absint( $value ) ) );

			case 'signed':
				return Badge_Style_Schema::clamp_signed( $value, (int) $spec['min'], (int) $spec['max'] );

			case 'int_enum':
				$number = absint( $value );
				/** Allowed integers. @var array<int, int> $allowed */
				$allowed = $spec['allowed'];
				return in_array( $number, $allowed, true ) ? $number : (int) $spec['default'];

			case 'enum':
				/** Allowed values. @var array<int, string> $allowed */
				$allowed = $spec['allowed'];
				return Badge_Style_Schema::sanitize_enum( $value, $allowed, (string) $spec['default'] );

			case 'bool':
				return in_array( (string) $value, array( '1', 'on', 'true' ), true ) ? 1 : 0;

			case 'icon_slug':
				$slug = sanitize_text_field( (string) $value );
				return '' !== $slug && \Aggressive_Apparel\Core\Icons::exists( $slug ) ? $slug : '';

			case 'svg_icon':
				return aggressive_apparel_sanitize_badge_svg( (string) $value );

			case 'shape_svg':
				return Badge_Style_Schema::sanitize_shape_svg( (string) $value );

			case 'text':
			default:
				$text = sanitize_text_field( (string) $value );
				return isset( $spec['max_len'] ) ? mb_substr( $text, 0, (int) $spec['max_len'] ) : $text;
		}
	}
}
