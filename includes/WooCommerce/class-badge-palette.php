<?php
/**
 * Theme palette helpers for the badge studio admin UI.
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
 * Resolves theme/adaptive palette swatches and admin :root CSS for badge studio.
 */
class Badge_Palette {

	/**
	 * Theme palette swatches for badge color fields (adaptive + theme only).
	 *
	 * Each swatch's `color` is a design-system preset variable
	 * (`var(--wp--preset--color--slug)`) so picks stay live with light/dark.
	 * WordPress `default` origin colors and legacy aliases (primary/red) are
	 * omitted. Admin preview resolves the vars via Badge_Palette::root_css().
	 *
	 * @return array<int, array{name: string, slug: string, color: string}>
	 */
	public static function swatches(): array {
		$palette = self::flatten_color_palette(
			wp_get_global_settings( array( 'color', 'palette' ) ),
			array( 'theme', 'custom' )
		);

		$by_slug = array();
		foreach ( $palette as $entry ) {
			$slug = sanitize_key( (string) ( $entry['slug'] ?? '' ) );
			if ( '' !== $slug ) {
				$by_slug[ $slug ] = $entry;
			}
		}

		$adaptive_names  = array();
		$adaptive_lights = array();
		$adaptive        = wp_get_global_settings( array( 'custom', 'adaptiveColors' ) );
		if ( is_array( $adaptive ) ) {
			foreach ( $adaptive as $pair ) {
				if ( ! is_array( $pair ) ) {
					continue;
				}
				$slug  = sanitize_key( (string) ( $pair['slug'] ?? '' ) );
				$name  = sanitize_text_field( (string) ( $pair['name'] ?? $slug ) );
				$light = trim( (string) ( $pair['light'] ?? '' ) );
				if ( '' !== $slug ) {
					$adaptive_names[ $slug ]  = $name;
					$adaptive_lights[ $slug ] = $light;
				}
			}
		}

		$out  = array();
		$seen = array();

		// Prefer adaptive pairs first so Accent / Surface lead the swatch row.
		foreach ( $adaptive_names as $slug => $name ) {
			$var = Badge_Style_Schema::preset_color_var( $slug );
			if ( '' === $var ) {
				continue;
			}

			$seen[ $slug ] = true;
			$out[]         = array(
				'name'  => $name,
				'slug'  => $slug,
				'color' => $var,
			);
		}

		// Legacy theme.json aliases that only mirror Accent.
		$skip_aliases = array( 'primary', 'red' );

		foreach ( $palette as $entry ) {
			$slug = sanitize_key( (string) ( $entry['slug'] ?? '' ) );
			$name = sanitize_text_field( (string) ( $entry['name'] ?? $slug ) );
			$raw  = trim( (string) ( $entry['color'] ?? '' ) );

			if ( '' === $slug || isset( $seen[ $slug ] ) ) {
				continue;
			}

			if ( 'transparent' === $slug ) {
				continue;
			}

			if ( in_array( $slug, $skip_aliases, true ) && isset( $seen['accent'] ) ) {
				continue;
			}

			$var = Badge_Style_Schema::preset_color_var( $slug );
			if ( '' === $var ) {
				continue;
			}

			// Skip solids we cannot paint in admin :root (e.g. color-mix light-gray).
			$resolved = self::resolve_color(
				$raw,
				$adaptive_lights,
				$by_slug,
				0
			);
			if ( '' === self::sanitize_css_token( (string) $resolved ) ) {
				continue;
			}

			$seen[ $slug ] = true;
			$out[]         = array(
				'name'  => $name,
				'slug'  => $slug,
				'color' => $var,
			);
		}

		return $out;
	}

	/**
	 * Define --wp--preset--color--* on the badge admin screen so preset
	 * swatches and the live canvas resolve without global styles.
	 *
	 * Adaptive slugs use light-dark(); other theme solids use their concrete
	 * theme.json value (oklch / hex).
	 *
	 * @return string CSS block, or '' when nothing to print.
	 */
	public static function root_css(): string {
		$decls          = array();
		$adaptive_slugs = array();
		$adaptive       = wp_get_global_settings( array( 'custom', 'adaptiveColors' ) );

		if ( is_array( $adaptive ) ) {
			foreach ( $adaptive as $pair ) {
				if ( ! is_array( $pair ) ) {
					continue;
				}
				$slug  = sanitize_key( (string) ( $pair['slug'] ?? '' ) );
				$light = self::sanitize_css_token( (string) ( $pair['light'] ?? '' ) );
				$dark  = self::sanitize_css_token( (string) ( $pair['dark'] ?? '' ) );
				if ( '' === $slug || '' === $light || '' === $dark ) {
					continue;
				}

				$adaptive_slugs[ $slug ] = true;
				$decls[]                 = $light === $dark
					? sprintf( '--wp--preset--color--%s:%s', $slug, $light )
					: sprintf( '--wp--preset--color--%s:light-dark(%s,%s)', $slug, $light, $dark );
			}
		}

		$palette = self::flatten_color_palette(
			wp_get_global_settings( array( 'color', 'palette' ) ),
			array( 'theme', 'custom' )
		);

		$by_slug = array();
		foreach ( $palette as $entry ) {
			$slug = sanitize_key( (string) ( $entry['slug'] ?? '' ) );
			if ( '' !== $slug ) {
				$by_slug[ $slug ] = $entry;
			}
		}

		$adaptive_lights = array();
		foreach ( $adaptive_slugs as $slug => $_true ) {
			// Lights already applied above; map kept for var() resolution.
			$adaptive_lights[ $slug ] = '';
		}
		if ( is_array( $adaptive ) ) {
			foreach ( $adaptive as $pair ) {
				if ( ! is_array( $pair ) ) {
					continue;
				}
				$slug = sanitize_key( (string) ( $pair['slug'] ?? '' ) );
				if ( '' !== $slug ) {
					$adaptive_lights[ $slug ] = (string) ( $pair['light'] ?? '' );
				}
			}
		}

		foreach ( $palette as $entry ) {
			$slug = sanitize_key( (string) ( $entry['slug'] ?? '' ) );
			if ( '' === $slug || isset( $adaptive_slugs[ $slug ] ) ) {
				continue;
			}

			$raw = trim( (string) ( $entry['color'] ?? '' ) );
			if ( '' === $raw || 'transparent' === strtolower( $raw ) ) {
				continue;
			}

			$resolved = self::resolve_color(
				$raw,
				$adaptive_lights,
				$by_slug,
				0
			);
			$token    = self::sanitize_css_token( (string) $resolved );
			if ( '' === $token ) {
				continue;
			}

			$decls[] = sprintf( '--wp--preset--color--%s:%s', $slug, $token );
		}

		if ( empty( $decls ) ) {
			return '';
		}

		return ':root{' . implode( ';', $decls ) . '}';
	}

	/**
	 * Allow only paint-safe CSS color tokens in generated admin preset CSS.
	 *
	 * @param string $value Candidate token.
	 * @return string
	 */
	private static function sanitize_css_token( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}

		if ( 'transparent' === strtolower( $value ) ) {
			return 'transparent';
		}

		if ( 1 === preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value ) ) {
			return strtolower( $value );
		}

		if ( 1 === preg_match( '/^oklch\(\s*[0-9.]+%?\s+[0-9.]+\s+-?[0-9.]+(?:deg)?(?:\s*\/\s*[0-9.]+%?)?\s*\)$/i', $value ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * Flatten theme.json palette origins into a sequential list.
	 *
	 * @param mixed             $palette Raw wp_get_global_settings color.palette value.
	 * @param array<int,string> $origins Origins to keep (e.g. theme, custom). Empty = all.
	 * @return array<int, array<string, mixed>>
	 */
	private static function flatten_color_palette( mixed $palette, array $origins = array() ): array {
		if ( ! is_array( $palette ) ) {
			return array();
		}

		if ( isset( $palette[0] ) && is_array( $palette[0] ) ) {
			return $palette;
		}

		$flat = array();
		foreach ( $palette as $origin => $origin_entries ) {
			if ( ! is_array( $origin_entries ) ) {
				continue;
			}

			if ( $origins && ! in_array( (string) $origin, $origins, true ) ) {
				continue;
			}

			foreach ( $origin_entries as $entry ) {
				if ( is_array( $entry ) ) {
					$flat[] = $entry;
				}
			}
		}

		return $flat;
	}

	/**
	 * Resolve a palette color to a concrete CSS token for admin :root CSS.
	 *
	 * @param string                              $raw             Raw palette color.
	 * @param array<string, string>               $adaptive_lights Adaptive slug → light solid.
	 * @param array<string, array<string, mixed>> $by_slug         Palette indexed by slug.
	 * @param int                                 $depth           Recursion guard.
	 * @return string|null Concrete CSS color, or null when unusable.
	 */
	private static function resolve_color(
		string $raw,
		array $adaptive_lights,
		array $by_slug,
		int $depth
	): ?string {
		if ( $depth > 4 ) {
			return null;
		}

		$raw = trim( $raw );
		if ( '' === $raw ) {
			return null;
		}

		if ( 'transparent' === strtolower( $raw ) ) {
			return 'transparent';
		}

		if ( 1 === preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $raw ) ) {
			return strtolower( $raw );
		}

		$oklch_hex = self::oklch_to_hex( $raw );
		if ( null !== $oklch_hex ) {
			return $raw; // Prefer original oklch token for :root when valid.
		}

		if ( 1 === preg_match( '/^oklch\(/i', $raw ) ) {
			return $raw;
		}

		if ( 1 === preg_match( '/^var\(\s*--wp--preset--color--([a-z0-9_-]+)\s*\)$/i', $raw, $matches ) ) {
			$slug = sanitize_key( $matches[1] );

			if ( isset( $adaptive_lights[ $slug ] ) && '' !== $adaptive_lights[ $slug ] ) {
				return self::resolve_color(
					$adaptive_lights[ $slug ],
					$adaptive_lights,
					$by_slug,
					$depth + 1
				);
			}

			if ( isset( $by_slug[ $slug ]['color'] ) ) {
				return self::resolve_color(
					(string) $by_slug[ $slug ]['color'],
					$adaptive_lights,
					$by_slug,
					$depth + 1
				);
			}

			return null;
		}

		return null;
	}

	/**
	 * Convert an oklch() solid to #rrggbb (validation helper).
	 *
	 * @param string $value Candidate CSS color.
	 * @return string|null
	 */
	private static function oklch_to_hex( string $value ): ?string {
		if ( 1 !== preg_match(
			'/^oklch\(\s*([0-9.]+%?)\s+([0-9.]+)\s+(-?[0-9.]+)(?:deg)?(?:\s*\/\s*[0-9.]+%?)?\s*\)$/i',
			trim( $value ),
			$matches
		) ) {
			return null;
		}

		$lightness_token = $matches[1];
		$lightness       = str_ends_with( $lightness_token, '%' )
			? (float) $lightness_token / 100.0
			: (float) $lightness_token;
		if ( $lightness > 1.0 ) {
			$lightness /= 100.0;
		}

		$chroma = (float) $matches[2];
		$hue    = deg2rad( (float) $matches[3] );
		$a      = $chroma * cos( $hue );
		$b      = $chroma * sin( $hue );

		$l_ = $lightness + 0.3963377774 * $a + 0.2158037573 * $b;
		$m_ = $lightness - 0.1055613458 * $a - 0.0638541728 * $b;
		$s_ = $lightness - 0.0894841775 * $a - 1.2914855480 * $b;

		$l = $l_ * $l_ * $l_;
		$m = $m_ * $m_ * $m_;
		$s = $s_ * $s_ * $s_;

		$r_lin = +4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s;
		$g_lin = -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s;
		$b_lin = -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s;

		$channel = static function ( float $c ): int {
			$c = max( 0.0, min( 1.0, $c ) );
			$c = $c <= 0.0031308
				? 12.92 * $c
				: 1.055 * ( $c ** ( 1.0 / 2.4 ) ) - 0.055;
			return (int) round( max( 0.0, min( 1.0, $c ) ) * 255.0 );
		};

		return sprintf(
			'#%02x%02x%02x',
			$channel( $r_lin ),
			$channel( $g_lin ),
			$channel( $b_lin )
		);
	}
}
