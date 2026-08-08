<?php
/**
 * Badge style schema — defaults, sanitization, and CSS-var emission.
 *
 * Single compiler for storefront badges and the taxonomy admin preview so
 * both surfaces stay visually in sync.
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
 * Compiles badge visual data into CSS custom properties and classes.
 */
class Badge_Style_Schema {

	public const POSITIONS = array(
		'top-left',
		'top',
		'top-right',
		'left',
		'right',
		'bottom-left',
		'bottom',
		'bottom-right',
	);

	public const SHAPES = array(
		'rect',
		'pill',
		'ticket',
		'shield',
		'hex',
		'burst',
		'ribbon-fold',
		'tag',
		'stamp-circle',
		'custom',
	);

	public const BORDER_STYLES   = array( 'none', 'solid', 'dashed', 'dotted', 'double' );
	public const BADGE_TYPES     = array( 'custom', 'sale', 'new', 'low_stock', 'bestseller' );
	public const SHAPE_MODES     = array( 'mask', 'frame' );
	public const FILL_MODES      = array( 'solid', 'gradient' );
	public const ICON_POSITIONS  = array( 'start', 'end', 'only' );
	public const LINE_HEIGHTS    = array( 'tight', 'snug', 'normal' );
	public const FONT_SIZE_KEYS  = array( 'x-small', 'small', 'medium', 'custom' );
	public const BORDER_MODES    = array( 'none', 'single', 'layered' );
	public const FONT_WEIGHTS    = array( 500, 600, 700, 800 );
	public const TEXT_TRANSFORMS = array( 'none', 'uppercase' );
	public const LETTER_SPACINGS = array( 'normal', 'comfortable', 'wide', 'extra-wide' );

	/**
	 * Defaults for designer fields introduced by the robust badge designer.
	 *
	 * @return array<string, int|string>
	 */
	public static function designer_defaults(): array {
		return Badge_Field_Registry::defaults();
	}

	/**
	 * Baseline visual defaults (legacy + designer) for safe partial badge arrays.
	 *
	 * @return array<string, int|string>
	 */
	public static function base_defaults(): array {
		return Badge_Field_Registry::defaults();
	}

	/**
	 * Merge designer defaults into badge data without overwriting existing keys.
	 *
	 * @param array<string, mixed> $data Badge data.
	 * @return array<string, mixed>
	 */
	public static function with_defaults( array $data ): array {
		return array_merge( self::base_defaults(), $data );
	}

	/**
	 * Sanitize a badge color while retaining an alpha channel.
	 *
	 * Values are canonicalized to #rrggbb / #rrggbbaa, the `transparent`
	 * keyword, or a theme palette preset `var(--wp--preset--color--{slug})`
	 * so adaptive colors stay live with the design system.
	 *
	 * @param mixed $value Candidate color.
	 * @return string
	 */
	public static function sanitize_color( mixed $value ): string {
		$color = strtolower( trim( sanitize_text_field( (string) $value ) ) );
		if ( 'transparent' === $color ) {
			return 'transparent';
		}

		$preset = self::sanitize_preset_color( $color );
		if ( '' !== $preset ) {
			return $preset;
		}

		if ( ! preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $color, $matches ) ) {
			return '';
		}

		$hex = $matches[1];
		if ( 3 === strlen( $hex ) || 4 === strlen( $hex ) ) {
			$expanded = '';
			foreach ( str_split( $hex ) as $digit ) {
				$expanded .= $digit . $digit;
			}
			$hex = $expanded;
		}

		return '#' . $hex;
	}

	/**
	 * Allowlist a theme palette CSS variable.
	 *
	 * @param string $color Candidate (already lowercased / trimmed).
	 * @return string Canonical var(...), or '' when rejected.
	 */
	public static function sanitize_preset_color( string $color ): string {
		if ( 1 !== preg_match( '/^var\(\s*--wp--preset--color--([a-z0-9_-]+)\s*\)$/', $color, $matches ) ) {
			return '';
		}

		$slug = sanitize_key( $matches[1] );
		if ( '' === $slug || ! in_array( $slug, self::known_palette_slugs(), true ) ) {
			return '';
		}

		return 'var(--wp--preset--color--' . $slug . ')';
	}

	/**
	 * Build a preset CSS variable for a known palette slug.
	 *
	 * @param string $slug Palette slug.
	 * @return string var(--wp--preset--color--slug) or '' when unknown.
	 */
	public static function preset_color_var( string $slug ): string {
		$slug = sanitize_key( $slug );
		if ( '' === $slug || ! in_array( $slug, self::known_palette_slugs(), true ) ) {
			return '';
		}

		return 'var(--wp--preset--color--' . $slug . ')';
	}

	/**
	 * Theme + custom + adaptive palette slugs (never WordPress defaults).
	 *
	 * @return array<int, string>
	 */
	public static function known_palette_slugs(): array {
		static $slugs = null;

		if ( is_array( $slugs ) ) {
			return $slugs;
		}

		$found = array();

		$adaptive = wp_get_global_settings( array( 'custom', 'adaptiveColors' ) );
		if ( is_array( $adaptive ) ) {
			foreach ( $adaptive as $pair ) {
				if ( ! is_array( $pair ) ) {
					continue;
				}
				$slug = sanitize_key( (string) ( $pair['slug'] ?? '' ) );
				if ( '' !== $slug ) {
					$found[ $slug ] = true;
				}
			}
		}

		$palette = wp_get_global_settings( array( 'color', 'palette' ) );
		if ( is_array( $palette ) ) {
			$origins = array( 'theme', 'custom' );
			$entries = array();

			if ( isset( $palette[0] ) && is_array( $palette[0] ) ) {
				$entries = $palette;
			} else {
				foreach ( $origins as $origin ) {
					if ( isset( $palette[ $origin ] ) && is_array( $palette[ $origin ] ) ) {
						foreach ( $palette[ $origin ] as $entry ) {
							if ( is_array( $entry ) ) {
								$entries[] = $entry;
							}
						}
					}
				}
			}

			foreach ( $entries as $entry ) {
				$slug = sanitize_key( (string) ( $entry['slug'] ?? '' ) );
				if ( '' !== $slug ) {
					$found[ $slug ] = true;
				}
			}
		}

		$slugs = array_keys( $found );
		return $slugs;
	}

	/**
	 * Read a numeric badge value clamped to its registry bounds.
	 *
	 * The save and REST paths already clamp, but emit is public and reachable
	 * with an arbitrary badge array — an unbounded radius or padding here would
	 * paint a badge no editor control could have produced.
	 *
	 * @param array<string, mixed> $badge Badge data.
	 * @param string               $key   Registry field key.
	 * @return int
	 */
	public static function bounded( array $badge, string $key ): int {
		$spec = Badge_Field_Registry::fields()[ $key ] ?? null;
		if ( null === $spec ) {
			return (int) ( $badge[ $key ] ?? 0 );
		}

		$value = $badge[ $key ] ?? $spec['default'];

		return 'signed' === $spec['type']
			? self::clamp_signed( $value, (int) $spec['min'], (int) $spec['max'] )
			: self::clamp_int( $value, (int) $spec['min'], (int) $spec['max'] );
	}

	/**
	 * Clamp an integer into an inclusive range.
	 *
	 * @param mixed $value Candidate.
	 * @param int   $min   Minimum.
	 * @param int   $max   Maximum.
	 * @return int
	 */
	public static function clamp_int( mixed $value, int $min, int $max ): int {
		return max( $min, min( $max, (int) $value ) );
	}

	/**
	 * Sanitize a signed integer into an inclusive range.
	 *
	 * @param mixed $value Candidate.
	 * @param int   $min   Minimum (may be negative).
	 * @param int   $max   Maximum.
	 * @return int
	 */
	public static function clamp_signed( mixed $value, int $min, int $max ): int {
		if ( is_string( $value ) ) {
			$value = (int) $value;
		}
		return max( $min, min( $max, (int) $value ) );
	}

	/**
	 * Enforce an enum allowlist.
	 *
	 * @param mixed    $value    Candidate.
	 * @param string[] $allowed  Allowed values.
	 * @param string   $fallback Fallback.
	 * @return string
	 */
	public static function sanitize_enum( mixed $value, array $allowed, string $fallback ): string {
		$value = sanitize_text_field( (string) $value );
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Sanitize a custom shape SVG / path fragment.
	 *
	 * @param string $svg Raw markup.
	 * @return string
	 */
	public static function sanitize_shape_svg( string $svg ): string {
		// Lazy delegate keeps SVG allowlist ownership on the taxonomy service.
		return Custom_Badge_Taxonomy::sanitize_svg( $svg );
	}

	/**
	 * Soften an opaque fill so glass backdrop-filter can show through.
	 *
	 * Already-translucent colors are left alone. Theme presets become a
	 * color-mix() so adaptive tokens stay live.
	 *
	 * @param string $color Sanitized color (hex, transparent, or preset var).
	 * @return string
	 */
	public static function frost_color_for_glass( string $color ): string {
		if ( '' === $color || 'transparent' === $color ) {
			return $color;
		}

		if ( str_starts_with( $color, 'var(' ) ) {
			return 'color-mix(in srgb, ' . $color . ' 60%, transparent)';
		}

		if ( 1 !== preg_match( '/^#([0-9a-f]{6})([0-9a-f]{2})?$/', $color, $matches ) ) {
			return $color;
		}

		$alpha = isset( $matches[2] ) ? hexdec( $matches[2] ) / 255 : 1.0;
		if ( $alpha < 0.99 ) {
			return $color;
		}

		// Match the Glass look preset (~60% opacity).
		return '#' . $matches[1] . '99';
	}

	/**
	 * Resolve the CSS background value.
	 *
	 * @param array<string, mixed> $badge Badge data.
	 * @return string
	 */
	public static function resolve_background( array $badge ): string {
		$badge = self::with_defaults( $badge );
		$glass = (int) $badge['glass'] > 0;

		if ( 'gradient' === $badge['fill_mode'] ) {
			$from  = self::sanitize_color( $badge['gradient_from'] );
			$to    = self::sanitize_color( $badge['gradient_to'] );
			$angle = self::clamp_int( $badge['gradient_angle'], 0, 360 );
			if ( '' === $from ) {
				$from = '#000000';
			}
			if ( '' === $to ) {
				$to = '#444444';
			}
			if ( $glass ) {
				$from = self::frost_color_for_glass( $from );
				$to   = self::frost_color_for_glass( $to );
			}
			return sprintf( 'linear-gradient(%ddeg, %s 0%%, %s 100%%)', $angle, $from, $to );
		}

		$bg = self::sanitize_color( (string) $badge['bg_color'] );
		$bg = '' !== $bg ? $bg : '#000000';
		return $glass ? self::frost_color_for_glass( $bg ) : $bg;
	}

	/**
	 * Resolve font-size CSS value for a render context.
	 *
	 * @param array<string, mixed> $badge   Badge data.
	 * @param string               $context `frontend` or `admin`.
	 * @return string
	 */
	public static function resolve_font_size( array $badge, string $context = 'frontend' ): string {
		$badge = self::with_defaults( $badge );

		// Pixel size only applies in explicit custom mode — leftover px from a
		// previous custom choice must not override Small/Medium/Large presets.
		if ( 'custom' === $badge['font_size'] ) {
			$px = self::clamp_int( $badge['font_size_px'], 0, 32 );
			return max( 4, $px > 0 ? $px : 12 ) . 'px';
		}

		if ( 'admin' === $context ) {
			$map = array(
				'x-small' => '0.75rem',
				'small'   => '0.875rem',
				'medium'  => '1rem',
			);
			return $map[ $badge['font_size'] ] ?? $map['x-small'];
		}

		$map = array(
			'x-small' => 'var(--wp--preset--font-size--x-small)',
			'small'   => 'var(--wp--preset--font-size--small)',
			'medium'  => 'var(--wp--preset--font-size--medium)',
		);
		return $map[ $badge['font_size'] ] ?? $map['x-small'];
	}

	/**
	 * Resolve letter-spacing CSS value.
	 *
	 * @param array<string, mixed> $badge Badge data.
	 * @return string
	 */
	public static function resolve_letter_spacing( array $badge ): string {
		$tracking = array(
			'normal'      => '0',
			'comfortable' => '0.02em',
			'wide'        => '0.04em',
			'extra-wide'  => '0.08em',
		);
		$key      = (string) ( $badge['letter_spacing'] ?? 'wide' );
		return $tracking[ $key ] ?? $tracking['wide'];
	}

	/**
	 * Resolve line-height CSS value.
	 *
	 * @param array<string, mixed> $badge Badge data.
	 * @return string
	 */
	public static function resolve_line_height( array $badge ): string {
		$badge = self::with_defaults( $badge );
		$map   = array(
			'tight'  => '1.1',
			'snug'   => '1.25',
			'normal' => '1.4',
		);
		return $map[ $badge['line_height'] ] ?? $map['snug'];
	}

	/**
	 * Resolve the outer CSS border paint for single/layered modes.
	 *
	 * Incomplete inspector state (style still `none`, width 0, empty color)
	 * is common right after switching modes — fill those gaps so a double or
	 * layered border actually paints. CSS `double` also needs ≥3px.
	 *
	 * @param array<string, mixed> $badge Badge data.
	 * @return array{active: bool, width: int, style: string, color: string}
	 */
	public static function resolve_outer_border( array $badge ): array {
		$badge = self::with_defaults( $badge );
		$mode  = self::sanitize_enum( (string) $badge['border_mode'], self::BORDER_MODES, 'none' );

		if ( 'none' === $mode ) {
			return array(
				'active' => false,
				'width'  => 0,
				'style'  => 'none',
				'color'  => 'transparent',
			);
		}

		$style = self::sanitize_enum(
			(string) $badge['border_style'],
			array( 'none', 'solid', 'dashed', 'dotted', 'double' ),
			'none'
		);
		if ( 'none' === $style ) {
			$style = 'solid';
		}

		$width = self::clamp_int( $badge['border_width'], 0, 10 );
		if ( $width <= 0 ) {
			$width = 2;
		}
		if ( 'double' === $style ) {
			$width = max( 3, $width );
		}

		$color = self::sanitize_color( (string) $badge['border_color'] );
		if ( '' === $color ) {
			$color = self::sanitize_color( (string) $badge['text_color'] );
		}
		if ( '' === $color ) {
			$color = '#ffffff';
		}

		return array(
			'active' => true,
			'width'  => $width,
			'style'  => $style,
			'color'  => $color,
		);
	}

	/**
	 * Resolve the inner ring for layered borders.
	 *
	 * @param array<string, mixed> $badge Badge data.
	 * @return array{active: bool, width: int, color: string}
	 */
	public static function resolve_layered_inner( array $badge ): array {
		$badge = self::with_defaults( $badge );
		$mode  = self::sanitize_enum( (string) $badge['border_mode'], self::BORDER_MODES, 'none' );

		if ( 'layered' !== $mode ) {
			return array(
				'active' => false,
				'width'  => 0,
				'color'  => '',
			);
		}

		$width = self::clamp_int( $badge['inner_border_width'], 0, 6 );
		if ( $width <= 0 ) {
			$width = 1;
		}

		// Prefer an opaque ring color — never inherit a cleared/transparent fill.
		$color = self::sanitize_color( (string) $badge['inner_border_color'] );
		if ( '' === $color || 'transparent' === $color ) {
			$color = self::sanitize_color( (string) $badge['border_color'] );
		}
		if ( '' === $color || 'transparent' === $color ) {
			$color = self::sanitize_color( (string) $badge['text_color'] );
		}
		if ( '' === $color || 'transparent' === $color ) {
			$color = '#ffffff';
		}

		return array(
			'active' => true,
			'width'  => $width,
			'color'  => $color,
		);
	}

	/**
	 * Build the outer drop shadow (layered rings use outline + border in CSS).
	 *
	 * @param array<string, mixed> $badge Badge data.
	 * @return string CSS box-shadow value or empty string.
	 */
	public static function build_box_shadow( array $badge ): string {
		$badge  = self::with_defaults( $badge );
		$blur   = self::clamp_int( $badge['shadow_blur'], 0, 40 );
		$spread = self::clamp_int( $badge['shadow_spread'], 0, 20 );
		$scol   = self::sanitize_color( (string) $badge['shadow_color'] );

		if ( ( $blur > 0 || $spread > 0 ) && '' !== $scol ) {
			return sprintf( '0 2px %dpx %dpx %s', $blur, $spread, $scol );
		}

		return '';
	}

	/**
	 * Emit CSS custom-property declarations for a badge.
	 *
	 * @param array<string, mixed> $badge   Badge data.
	 * @param string               $context `frontend` or `admin`.
	 * @return string[] List of `prop:value` pairs (no trailing semicolons).
	 */
	public static function emit_css_variables( array $badge, string $context = 'frontend' ): array {
		$badge      = self::with_defaults( $badge );
		$text_color = self::sanitize_color( (string) $badge['text_color'] );
		if ( '' === $text_color ) {
			$text_color = '#ffffff';
		}

		$text_transform = self::sanitize_enum( (string) $badge['text_transform'], self::TEXT_TRANSFORMS, 'uppercase' );
		$shape          = self::sanitize_enum( $badge['shape'], self::SHAPES, 'rect' );
		$shape_mode     = self::sanitize_enum( $badge['shape_mode'], self::SHAPE_MODES, 'mask' );
		$is_mask_shape  = ! in_array( $shape, array( 'rect', 'pill' ), true ) && 'mask' === $shape_mode;
		$outer_border   = self::resolve_outer_border( $badge );
		$layered_inner  = self::resolve_layered_inner( $badge );

		$parts = array(
			'--badge-bg:' . self::resolve_background( $badge ),
			'--badge-text:' . $text_color,
			'--badge-font-size:' . self::resolve_font_size( $badge, $context ),
			'--badge-font-weight:' . (int) $badge['font_weight'],
			'--badge-text-transform:' . $text_transform,
			'--badge-letter-spacing:' . self::resolve_letter_spacing( $badge ),
			'--badge-line-height:' . self::resolve_line_height( $badge ),
			sprintf(
				'--badge-radius:%dpx %dpx %dpx %dpx',
				self::bounded( $badge, 'radius_tl' ),
				self::bounded( $badge, 'radius_tr' ),
				self::bounded( $badge, 'radius_br' ),
				self::bounded( $badge, 'radius_bl' )
			),
			sprintf( '--badge-pad-x:%dpx', self::bounded( $badge, 'padding_x' ) ),
			sprintf( '--badge-pad-y:%dpx', self::bounded( $badge, 'padding_y' ) ),
			sprintf(
				'--badge-padding:%dpx %dpx',
				self::bounded( $badge, 'padding_y' ),
				self::bounded( $badge, 'padding_x' )
			),
			'--badge-offset-x:' . self::clamp_signed( $badge['offset_x'], -40, 40 ) . 'px',
			'--badge-offset-y:' . self::clamp_signed( $badge['offset_y'], -40, 40 ) . 'px',
			'--badge-rotate:' . self::clamp_signed( $badge['rotation'], -45, 45 ) . 'deg',
			'--badge-border-gap:' . self::clamp_int( $badge['border_gap'], 0, 20 ) . 'px',
			'--badge-icon-gap:' . self::resolve_icon_gap( $badge ),
			'--badge-frame-width:' . self::clamp_int( $badge['frame_width'], 0, 8 ) . 'px',
			'--badge-frame-color:' . self::resolve_frame_color( $badge ),
		);

		// Masked silhouettes clip CSS borders — suppress them so the inspector
		// cannot imply a border that never paints.
		$has_border = ! $is_mask_shape && $outer_border['active'];

		if ( $has_border ) {
			$parts[] = '--badge-border-width:' . $outer_border['width'] . 'px';
			$parts[] = '--badge-border-style:' . $outer_border['style'];
			$parts[] = '--badge-border-color:' . $outer_border['color'];
		} else {
			$parts[] = '--badge-border-width:0';
			$parts[] = '--badge-border-style:none';
			$parts[] = '--badge-border-color:transparent';
		}

		if ( ! $is_mask_shape && $layered_inner['active'] ) {
			$parts[] = '--badge-inner-border-width:' . $layered_inner['width'] . 'px';
			$parts[] = '--badge-inner-border-color:' . $layered_inner['color'];
		}

		$shadow = self::build_box_shadow( $badge );
		if ( '' !== $shadow ) {
			$parts[] = '--badge-shadow:' . $shadow;
		} else {
			$parts[] = '--badge-shadow:none';
		}

		if ( (int) $badge['glass'] ) {
			$parts[] = '--badge-glass-blur:12px';
		} else {
			$parts[] = '--badge-glass-blur:0';
		}

		$mask = Badge_Shapes::mask_image_css( $badge );
		if ( '' !== $mask ) {
			$parts[] = '--badge-shape-mask:' . $mask;
		}

		return $parts;
	}

	/**
	 * Build a style attribute value from emitted CSS variables.
	 *
	 * @param array<string, mixed> $badge   Badge data.
	 * @param string               $context Render context.
	 * @return string
	 */
	public static function emit_style_attribute( array $badge, string $context = 'frontend' ): string {
		return implode( ';', self::emit_css_variables( $badge, $context ) );
	}

	/**
	 * Extra BEM modifier classes for the badge span.
	 *
	 * @param array<string, mixed> $badge Badge data.
	 * @return string[]
	 */
	public static function emit_class_names( array $badge ): array {
		$badge   = self::with_defaults( $badge );
		$shape   = self::sanitize_enum( $badge['shape'], self::SHAPES, 'rect' );
		$classes = array(
			'aggressive-apparel-product-badge',
			'aggressive-apparel-product-badge--custom',
			'aggressive-apparel-product-badge--shape-' . $shape,
			'aggressive-apparel-product-badge--icon-' . self::sanitize_enum( $badge['icon_position'], self::ICON_POSITIONS, 'start' ),
		);

		// Pill is radius-driven rect geometry — not an SVG silhouette.
		if ( ! in_array( $shape, array( 'rect', 'pill' ), true ) ) {
			$classes[] = 'aggressive-apparel-product-badge--shaped';
			$classes[] = 'aggressive-apparel-product-badge--shape-mode-' . self::sanitize_enum( $badge['shape_mode'], self::SHAPE_MODES, 'mask' );
		}

		if ( (int) $badge['glass'] ) {
			$classes[] = 'aggressive-apparel-product-badge--glass';
		}

		$offset_x = self::clamp_int( $badge['offset_x'], -40, 40 );
		$offset_y = self::clamp_int( $badge['offset_y'], -40, 40 );
		$rotation = self::clamp_int( $badge['rotation'], -45, 45 );
		if ( 0 !== $offset_x || 0 !== $offset_y || 0 !== $rotation ) {
			$classes[] = 'aggressive-apparel-product-badge--moved';
		}

		if ( 'gradient' === $badge['fill_mode'] ) {
			$classes[] = 'aggressive-apparel-product-badge--gradient';
		}

		$border_mode   = self::sanitize_enum( (string) $badge['border_mode'], self::BORDER_MODES, 'none' );
		$is_mask_shape = ! in_array( $shape, array( 'rect', 'pill' ), true )
			&& 'mask' === self::sanitize_enum( $badge['shape_mode'], self::SHAPE_MODES, 'mask' );
		if ( 'layered' === $border_mode && ! $is_mask_shape ) {
			$classes[] = 'aggressive-apparel-product-badge--layered';
		}

		return $classes;
	}

	/**
	 * Resolve flex gap between icon and label.
	 *
	 * @param array<string, mixed> $badge Badge data.
	 * @return string CSS length.
	 */
	public static function resolve_icon_gap( array $badge ): string {
		$badge = self::with_defaults( $badge );
		$gap   = self::clamp_int( $badge['icon_gap'] ?? 0, 0, 40 );
		return $gap > 0 ? $gap . 'px' : '0.25em';
	}

	/**
	 * Resolve frame stroke color (frame → border → currentColor).
	 *
	 * @param array<string, mixed> $badge Badge data.
	 * @return string
	 */
	public static function resolve_frame_color( array $badge ): string {
		$badge = self::with_defaults( $badge );
		$frame = self::sanitize_color( (string) $badge['frame_color'] );
		if ( '' !== $frame ) {
			return $frame;
		}
		$border = self::sanitize_color( (string) $badge['border_color'] );
		if ( '' !== $border ) {
			return $border;
		}
		return 'currentColor';
	}

	/**
	 * Compile a full badge span (storefront or admin).
	 *
	 * @param array<string, mixed> $badge      Badge data.
	 * @param string               $inner_html Pre-escaped icon + text markup.
	 * @param string               $context    `frontend` or `admin`.
	 * @param string               $id         Optional element id.
	 * @param string               $aria_label Optional accessible name (icon-only).
	 * @return string
	 */
	public static function compile_badge_span( array $badge, string $inner_html, string $context = 'frontend', string $id = '', string $aria_label = '' ): string {
		$badge   = self::with_defaults( $badge );
		$classes = self::emit_class_names( $badge );
		$style   = self::emit_style_attribute( $badge, $context );
		$shape   = Badge_Shapes::render_frame_svg( $badge );

		$content = sprintf(
			'<span class="aggressive-apparel-product-badge__content">%s</span>',
			aggressive_apparel_trusted_html( $inner_html )
		);

		$attrs = '';
		if ( '' !== $id ) {
			$attrs .= ' id="' . esc_attr( $id ) . '"';
		}
		if ( '' !== $aria_label ) {
			$attrs .= ' role="img" aria-label="' . esc_attr( $aria_label ) . '"';
		}

		return sprintf(
			'<span%1$s class="%2$s" style="%3$s">%4$s%5$s</span>',
			$attrs,
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( $style ),
			$shape,
			$content
		);
	}
}
