<?php
/**
 * Icons Class
 *
 * Centralized SVG icon library for the theme.
 * Provides consistent icons across all theme components.
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Core;

/**
 * Icons Class
 *
 * @since 1.0.0
 */
class Icons {

	/**
	 * Default SVG attributes.
	 *
	 * @var array<string, int|string>
	 */
	private const DEFAULT_ATTRS = array(
		'width'  => 24,
		'height' => 24,
		'fill'   => 'currentColor',
	);

	/**
	 * Default viewBox for single-path and unspecified multi-path icons.
	 */
	private const DEFAULT_VIEWBOX = '0 0 24 24';

	/**
	 * Allowed optional attributes on path/circle primitives.
	 *
	 * @var array<int, string>
	 */
	private const SHAPE_ATTRS = array(
		'fill',
		'fill-rule',
		'stroke',
		'stroke-width',
		'opacity',
		'transform',
	);

	/**
	 * Per-request icon map cache (cleared when filter runs in tests).
	 *
	 * @var array<string, string|array<string, mixed>>|null
	 */
	private static ?array $definitions_cache = null;

	/**
	 * Lazy icon providers keyed by stable identifier.
	 *
	 * @var array<string, array{
	 *     definition_loader: callable(string): (string|array<string, mixed>|null),
	 *     slug_loader: callable(): array<int, string>
	 * }>
	 */
	private static array $providers = array();

	/**
	 * Normalized definitions cached by slug for the current request.
	 *
	 * @var array<string, array{
	 *     viewBox: string,
	 *     paths: array<int, array<string, string>>,
	 *     circles: array<int, array<string, string>>,
	 *     polygons: array<int, array<string, string>>,
	 *     rects: array<int, array<string, string>>
	 * }|null>
	 */
	private static array $normalized_cache = array();

	/**
	 * Rendered inner SVG markup cached independently from root attributes.
	 *
	 * @var array<string, string>
	 */
	private static array $inner_markup_cache = array();

	/**
	 * Cached list of validated icon slugs.
	 *
	 * @var array<int, string>|null
	 */
	private static ?array $slugs_cache = null;

	/**
	 * Icon definitions (path data only — merged with filter below).
	 *
	 * Slugs may be expanded from PHP via the `aggressive_apparel_icon_definitions` filter.
	 *
	 * Each slug maps to either:
	 * - a string: single path `d` for viewBox `0 0 24 24`, or
	 * - an array: `viewBox`, `paths`, optional `circles`, `polygons`, and `rects`.
	 *
	 * @var array<string, string|array<string, mixed>>
	 */
	private const ICONS = array(
		// Navigation icons.
		'hamburger'     => 'M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z',
		'dots'          => 'M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z',
		'bars'          => 'M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z',
		'close'         => 'M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z',
		'chevron-down'  => 'M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z',
		'chevron-up'    => 'M7.41 15.41L12 10.83l4.59 4.58L18 14l-6-6-6 6 1.41 1.41z',
		'chevron-left'  => 'M15.41 16.59L10.83 12l4.58-4.59L14 6l-6 6 6 6 1.41-1.41z',
		'chevron-right' => 'M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z',
		'arrow-left'    => 'M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z',
		'arrow-right'   => 'M4 11h12.17l-5.59-5.59L12 4l8 8-8 8-1.41-1.41L16.17 13H4v-2z',

		// Action icons.
		'home'          => 'M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z',
		'search'        => 'M13 5c-3.3 0-6 2.7-6 6 0 1.4.5 2.7 1.3 3.7l-3.8 3.8 1.1 1.1 3.8-3.8c1 .8 2.3 1.3 3.7 1.3 3.3 0 6-2.7 6-6S16.3 5 13 5zm0 10.5c-2.5 0-4.5-2-4.5-4.5s2-4.5 4.5-4.5 4.5 2 4.5 4.5-2 4.5-4.5 4.5z',
		'cart'          => 'M17 18a2 2 0 0 1 2 2 2 2 0 0 1-2 2 2 2 0 0 1-2-2 2 2 0 0 1 2-2M1 2h3.27l.94 2H20a1 1 0 0 1 1 1c0 .17-.05.34-.12.5l-3.58 6.47c-.34.61-1 1.03-1.75 1.03H8.1l-.9 1.63-.03.12a.25.25 0 0 0 .25.25H19v2H7a2 2 0 0 1-2-2c0-.35.09-.68.24-.96l1.36-2.45L3 4H1V2m6 16a2 2 0 0 1 2 2 2 2 0 0 1-2 2 2 2 0 0 1-2-2 2 2 0 0 1 2-2m9-7 2.78-5H6.14l2.36 5H16z',
		'user'          => 'M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z',
		'heart'         => 'M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3 1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z',
		'eye'           => 'M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z',

		// UI icons.
		'grid-view'     => 'M3 3h8v8H3V3zm10 0h8v8h-8V3zM3 13h8v8H3v-8zm10 0h8v8h-8v-8z',
		'list-view'     => 'M3 4h18v2H3V4zm0 7h18v2H3v-2zm0 7h18v2H3v-2z',
		'filter'        => 'M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z',
		'check'         => 'M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z',
		'plus'          => 'M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z',
		'minus'         => 'M19 13H5v-2h14v2z',
		'info'          => 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z',
		'warning'       => 'M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z',
		'error'         => 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z',

		// Social icons.
		'facebook'      => 'M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z',
		'twitter'       => 'M22.46 6c-.77.35-1.6.58-2.46.69.88-.53 1.56-1.37 1.88-2.38-.83.5-1.75.85-2.72 1.05C18.37 4.5 17.26 4 16 4c-2.35 0-4.27 1.92-4.27 4.29 0 .34.04.67.11.98C8.28 9.09 5.11 7.38 3 4.79c-.37.63-.58 1.37-.58 2.15 0 1.49.75 2.81 1.91 3.56-.71 0-1.37-.2-1.95-.5v.03c0 2.08 1.48 3.82 3.44 4.21a4.22 4.22 0 0 1-1.93.07 4.28 4.28 0 0 0 4 2.98 8.521 8.521 0 0 1-5.33 1.84c-.34 0-.68-.02-1.02-.06C3.44 20.29 5.7 21 8.12 21 16 21 20.33 14.46 20.33 8.79c0-.19 0-.37-.01-.56.84-.6 1.56-1.36 2.14-2.23z',
		'instagram'     => 'M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2m-.2 2A3.6 3.6 0 0 0 4 7.6v8.8C4 18.39 5.61 20 7.6 20h8.8a3.6 3.6 0 0 0 3.6-3.6V7.6C20 5.61 18.39 4 16.4 4H7.6m9.65 1.5a1.25 1.25 0 0 1 1.25 1.25A1.25 1.25 0 0 1 17.25 8 1.25 1.25 0 0 1 16 6.75a1.25 1.25 0 0 1 1.25-1.25M12 7a5 5 0 0 1 5 5 5 5 0 0 1-5 5 5 5 0 0 1-5-5 5 5 0 0 1 5-5m0 2a3 3 0 0 0-3 3 3 3 0 0 0 3 3 3 3 0 0 0 3-3 3 3 0 0 0-3-3z',

		// Brand — the "A" monogram mark (used for the product rating).
		'brand-mark'    => array(
			'viewBox' => '0 0 199 175',
			'paths'   => array(
				'M12.6,75.3c0-12.1,2.4-22,5.1-29.9c6.5-18.9,26.5-33.8,54.7-33.8h106.8L199,0H68.4c-10.8,0-20.5,1.6-29,4.8c-8.5,3.2-15.6,7.9-21.5,14C12.1,24.9,7.7,32.4,4.6,41.3C1.5,50.2,0,60.2,0,71.3V134l44.6,41l-32-43.7V75.3z',
				'M167.6,17.1h-5.2h-8.5H73.3c-8.8,0-16.6,1.3-23.4,3.9c-6.9,2.6-12.6,6.4-17.3,11.3c-4.7,5-8.3,11-10.8,18.2C19.2,57.6,18,65.7,18,74.8v48.6l27,35.4V88h90.4v70.8l27-35.4V29.7h5.2v101.7l-32,43.7l44.6-41V17.1h-1.8H167.6z M135.4,62.5H46.7c0.5-2.6,1.4-5.1,2.6-7.4c1.2-2.3,3.2-4.2,6-5.8c2.8-1.6,6.7-2.8,11.6-3.7c4.9-0.9,11.4-1.3,19.6-1.3h48.9V62.5z',
			),
		),
	);

	/**
	 * Icon map after the `aggressive_apparel_icon_definitions` filter runs.
	 *
	 * Cached per-request so filters only execute once.
	 *
	 * @return array<string, string|array<string, mixed>>
	 */
	private static function all_icons(): array {
		if ( null !== self::$definitions_cache ) {
			return self::$definitions_cache;
		}

		// Themes/plugins append icons without editing core SVG definitions.
		// Filter docblock lives next to Feature_Settings_Fields::render_social_proof_icon_help_field().
		$merged = apply_filters( 'aggressive_apparel_icon_definitions', self::ICONS );

		if ( ! is_array( $merged ) || array() === $merged ) {
			$merged = self::ICONS;
		}

		self::$definitions_cache = $merged;

		return self::$definitions_cache;
	}

	/**
	 * Register a lazy icon definition provider.
	 *
	 * Providers are keyed so repeated bootstrap calls replace rather than
	 * duplicate registrations.
	 *
	 * @param string   $id                Stable provider identifier.
	 * @param callable $definition_loader Loads one definition by slug.
	 * @param callable $slug_loader       Lists known slugs without loading definitions.
	 * @phpstan-param callable(string): (string|array<string, mixed>|null) $definition_loader
	 * @phpstan-param callable(): array<int, string> $slug_loader
	 */
	public static function register_provider( string $id, callable $definition_loader, callable $slug_loader ): void {
		if ( '' === trim( $id ) ) {
			return;
		}

		self::$providers[ $id ] = array(
			'definition_loader' => $definition_loader,
			'slug_loader'       => $slug_loader,
		);

		self::$normalized_cache   = array();
		self::$inner_markup_cache = array();
		self::$slugs_cache        = null;
	}

	/**
	 * Get SVG icon markup.
	 *
	 * @param string               $icon  Icon name.
	 * @param array<string, mixed> $attrs Optional SVG attributes.
	 * @return string SVG markup or empty string if icon not found.
	 */
	public static function get( string $icon, array $attrs = array() ): string {
		$normalized = self::normalized_icon( $icon );

		if ( null === $normalized ) {
			return '';
		}

		$attrs = wp_parse_args( $attrs, self::DEFAULT_ATTRS );

		$svg_attrs = self::build_root_svg_attrs( $attrs, $normalized['viewBox'] );
		$inner     = self::$inner_markup_cache[ $icon ] ?? self::build_inner_markup( $normalized );

		if ( '' === $inner ) {
			return '';
		}

		self::$inner_markup_cache[ $icon ] = $inner;

		return sprintf( '<svg %s>%s</svg>', $svg_attrs, $inner );
	}

	/**
	 * Echo SVG icon.
	 *
	 * @param string               $icon  Icon name.
	 * @param array<string, mixed> $attrs Optional SVG attributes.
	 */
	public static function render( string $icon, array $attrs = array() ): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted theme SVG output.
		echo self::get( $icon, $attrs );
	}

	/**
	 * Check if an icon exists.
	 *
	 * @param string $icon Icon name.
	 * @return bool
	 */
	public static function exists( string $icon ): bool {
		return null !== self::normalized_icon( $icon );
	}

	/**
	 * Get all available icon names.
	 *
	 * @return array<int, string>
	 */
	public static function list(): array {
		if ( null !== self::$slugs_cache ) {
			return self::$slugs_cache;
		}

		$slugs = array();

		foreach ( self::all_icons() as $slug => $definition ) {
			if ( ! is_string( $slug ) || null === self::normalize_definition( $definition ) ) {
				continue;
			}

			$slugs[] = $slug;
		}

		foreach ( self::$providers as $provider ) {
			$provider_slugs = ( $provider['slug_loader'] )();

			foreach ( $provider_slugs as $slug ) {
				if ( ! is_string( $slug ) || ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug ) ) {
					continue;
				}

				$slugs[] = $slug;
			}
		}

		self::$slugs_cache = array_values( array_unique( $slugs ) );

		return self::$slugs_cache;
	}

	/**
	 * Resolve and normalize one icon definition, consulting lazy providers only
	 * when the icon is not part of the compact built-in collection.
	 *
	 * @param string $icon Icon slug.
	 * @return array{
	 *     viewBox: string,
	 *     paths: array<int, array<string, string>>,
	 *     circles: array<int, array<string, string>>,
	 *     polygons: array<int, array<string, string>>,
	 *     rects: array<int, array<string, string>>
	 * }|null
	 */
	private static function normalized_icon( string $icon ): ?array {
		if ( array_key_exists( $icon, self::$normalized_cache ) ) {
			return self::$normalized_cache[ $icon ];
		}

		$definitions = self::all_icons();
		$definition  = $definitions[ $icon ] ?? null;

		if ( null === $definition ) {
			foreach ( self::$providers as $provider ) {
				$definition = ( $provider['definition_loader'] )( $icon );

				if ( null !== $definition ) {
					break;
				}
			}
		}

		self::$normalized_cache[ $icon ] = null === $definition
			? null
			: self::normalize_definition( $definition );

		return self::$normalized_cache[ $icon ];
	}

	/**
	 * Normalize a slug definition into render-ready primitives.
	 *
	 * @param string|array<string, mixed> $definition Icon definition.
	 * @return array{
	 *     viewBox: string,
	 *     paths: array<int, array<string, string>>,
	 *     circles: array<int, array<string, string>>,
	 *     polygons: array<int, array<string, string>>,
	 *     rects: array<int, array<string, string>>
	 * }|null
	 */
	private static function normalize_definition( string|array $definition ): ?array {
		if ( is_string( $definition ) ) {
			$path_data = trim( $definition );

			if ( '' === $path_data ) {
				return null;
			}

			return array(
				'viewBox'  => self::DEFAULT_VIEWBOX,
				'paths'    => array(
					array( 'd' => $path_data ),
				),
				'circles'  => array(),
				'polygons' => array(),
				'rects'    => array(),
			);
		}

		if ( ! is_array( $definition ) ) {
			return null;
		}

		$view_box = self::DEFAULT_VIEWBOX;

		if ( isset( $definition['viewBox'] ) && is_string( $definition['viewBox'] ) ) {
			$sanitized_view_box = self::sanitize_view_box( $definition['viewBox'] );

			if ( null === $sanitized_view_box ) {
				return null;
			}

			$view_box = $sanitized_view_box;
		}

		$paths    = self::normalize_paths( $definition['paths'] ?? array() );
		$circles  = self::normalize_circles( $definition['circles'] ?? array() );
		$polygons = self::normalize_polygons( $definition['polygons'] ?? array() );
		$rects    = self::normalize_rects( $definition['rects'] ?? array() );

		if ( array() === $paths && array() === $circles && array() === $polygons && array() === $rects ) {
			return null;
		}

		return array(
			'viewBox'  => $view_box,
			'paths'    => $paths,
			'circles'  => $circles,
			'polygons' => $polygons,
			'rects'    => $rects,
		);
	}

	/**
	 * Normalize path entries from string or attribute arrays.
	 *
	 * @param mixed $paths Raw paths value.
	 * @return array<int, array<string, string>>
	 */
	private static function normalize_paths( mixed $paths ): array {
		if ( ! is_array( $paths ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $paths as $path ) {
			if ( is_string( $path ) ) {
				$path_data = trim( $path );

				if ( '' !== $path_data ) {
					$normalized[] = array( 'd' => $path_data );
				}

				continue;
			}

			if ( ! is_array( $path ) || ! isset( $path['d'] ) || ! is_string( $path['d'] ) ) {
				continue;
			}

			$path_data = trim( $path['d'] );

			if ( '' === $path_data ) {
				continue;
			}

			$entry = array( 'd' => $path_data );

			foreach ( self::SHAPE_ATTRS as $attr ) {
				if ( ! isset( $path[ $attr ] ) || ! is_string( $path[ $attr ] ) ) {
					continue;
				}

				$value = trim( $path[ $attr ] );

				if ( '' !== $value ) {
					$entry[ $attr ] = $value;
				}
			}

			$normalized[] = $entry;
		}

		return $normalized;
	}

	/**
	 * Normalize circle entries from coordinate arrays or attribute maps.
	 *
	 * @param mixed $circles Raw circles value.
	 * @return array<int, array<string, string>>
	 */
	private static function normalize_circles( mixed $circles ): array {
		if ( ! is_array( $circles ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $circles as $circle ) {
			if ( is_array( $circle ) && isset( $circle['cx'], $circle['cy'], $circle['r'] ) ) {
				$entry = array(
					'cx' => self::stringify_coordinate( $circle['cx'] ),
					'cy' => self::stringify_coordinate( $circle['cy'] ),
					'r'  => self::stringify_coordinate( $circle['r'] ),
				);

				if ( null === $entry['cx'] || null === $entry['cy'] || null === $entry['r'] ) {
					continue;
				}

				foreach ( self::SHAPE_ATTRS as $attr ) {
					if ( ! isset( $circle[ $attr ] ) || ! is_string( $circle[ $attr ] ) ) {
						continue;
					}

					$value = trim( $circle[ $attr ] );

					if ( '' !== $value ) {
						$entry[ $attr ] = $value;
					}
				}

				$normalized[] = $entry;

				continue;
			}

			if ( is_array( $circle ) && array_is_list( $circle ) && count( $circle ) >= 3 ) {
				$entry = array(
					'cx' => self::stringify_coordinate( $circle[0] ),
					'cy' => self::stringify_coordinate( $circle[1] ),
					'r'  => self::stringify_coordinate( $circle[2] ),
				);

				if ( null === $entry['cx'] || null === $entry['cy'] || null === $entry['r'] ) {
					continue;
				}

				$normalized[] = $entry;
			}
		}

		return $normalized;
	}

	/**
	 * Normalize polygon entries from string or attribute arrays.
	 *
	 * @param mixed $polygons Raw polygons value.
	 * @return array<int, array<string, string>>
	 */
	private static function normalize_polygons( mixed $polygons ): array {
		if ( ! is_array( $polygons ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $polygons as $polygon ) {
			if ( is_string( $polygon ) ) {
				$points = trim( $polygon );

				if ( '' !== $points ) {
					$normalized[] = array( 'points' => $points );
				}

				continue;
			}

			if ( ! is_array( $polygon ) || ! isset( $polygon['points'] ) || ! is_string( $polygon['points'] ) ) {
				continue;
			}

			$points = trim( $polygon['points'] );

			if ( '' === $points ) {
				continue;
			}

			$entry = array( 'points' => $points );

			foreach ( self::SHAPE_ATTRS as $attr ) {
				if ( ! isset( $polygon[ $attr ] ) || ! is_string( $polygon[ $attr ] ) ) {
					continue;
				}

				$value = trim( $polygon[ $attr ] );

				if ( '' !== $value ) {
					$entry[ $attr ] = $value;
				}
			}

			$normalized[] = $entry;
		}

		return $normalized;
	}

	/**
	 * Normalize rect entries from coordinate arrays or attribute maps.
	 *
	 * @param mixed $rects Raw rects value.
	 * @return array<int, array<string, string>>
	 */
	private static function normalize_rects( mixed $rects ): array {
		if ( ! is_array( $rects ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $rects as $rect ) {
			if ( ! is_array( $rect ) ) {
				continue;
			}

			if ( isset( $rect['x'], $rect['y'], $rect['width'], $rect['height'] ) ) {
				$entry = array(
					'x'      => self::stringify_coordinate( $rect['x'] ),
					'y'      => self::stringify_coordinate( $rect['y'] ),
					'width'  => self::stringify_coordinate( $rect['width'] ),
					'height' => self::stringify_coordinate( $rect['height'] ),
				);

				if ( in_array( null, $entry, true ) ) {
					continue;
				}

				if ( isset( $rect['transform'] ) && is_string( $rect['transform'] ) ) {
					$transform = trim( $rect['transform'] );

					if ( '' !== $transform ) {
						$entry['transform'] = $transform;
					}
				}

				foreach ( self::SHAPE_ATTRS as $attr ) {
					if ( ! isset( $rect[ $attr ] ) || ! is_string( $rect[ $attr ] ) ) {
						continue;
					}

					$value = trim( $rect[ $attr ] );

					if ( '' !== $value ) {
						$entry[ $attr ] = $value;
					}
				}

				$normalized[] = $entry;

				continue;
			}

			if ( array_is_list( $rect ) && count( $rect ) >= 4 ) {
				$entry = array(
					'x'      => self::stringify_coordinate( $rect[0] ),
					'y'      => self::stringify_coordinate( $rect[1] ),
					'width'  => self::stringify_coordinate( $rect[2] ),
					'height' => self::stringify_coordinate( $rect[3] ),
				);

				if ( in_array( null, $entry, true ) ) {
					continue;
				}

				if ( isset( $rect[4] ) && is_string( $rect[4] ) ) {
					$transform = trim( $rect[4] );

					if ( '' !== $transform ) {
						$entry['transform'] = $transform;
					}
				}

				$normalized[] = $entry;
			}
		}

		return $normalized;
	}

	/**
	 * Build sanitized root <svg> attributes.
	 *
	 * @param array<string, mixed> $attrs    Render attributes.
	 * @param string               $view_box View box string.
	 * @return string
	 */
	private static function build_root_svg_attrs( array $attrs, string $view_box ): string {
		$svg_attrs = sprintf(
			'xmlns="http://www.w3.org/2000/svg" viewBox="%s" width="%d" height="%d" fill="%s"',
			esc_attr( $view_box ),
			absint( $attrs['width'] ),
			absint( $attrs['height'] ),
			esc_attr( is_string( $attrs['fill'] ) ? $attrs['fill'] : 'currentColor' )
		);

		if ( isset( $attrs['class'] ) && is_string( $attrs['class'] ) ) {
			$svg_attrs .= sprintf( ' class="%s"', esc_attr( $attrs['class'] ) );
		}

		if ( isset( $attrs['aria-hidden'] ) ) {
			$svg_attrs .= sprintf( ' aria-hidden="%s"', esc_attr( (string) $attrs['aria-hidden'] ) );
		}

		return $svg_attrs;
	}

	/**
	 * Build inner SVG primitives markup.
	 *
	 * @param array{viewBox:string,paths:array<int,array<string,string>>,circles:array<int,array<string,string>>,polygons:array<int,array<string,string>>,rects:array<int,array<string,string>>} $definition Normalized icon.
	 * @return string
	 */
	private static function build_inner_markup( array $definition ): string {
		$parts = array();

		foreach ( $definition['paths'] as $path ) {
			$markup = self::build_path_markup( $path );

			if ( '' !== $markup ) {
				$parts[] = $markup;
			}
		}

		foreach ( $definition['polygons'] as $polygon ) {
			$markup = self::build_polygon_markup( $polygon );

			if ( '' !== $markup ) {
				$parts[] = $markup;
			}
		}

		foreach ( $definition['rects'] as $rect ) {
			$markup = self::build_rect_markup( $rect );

			if ( '' !== $markup ) {
				$parts[] = $markup;
			}
		}

		foreach ( $definition['circles'] as $circle ) {
			$markup = self::build_circle_markup( $circle );

			if ( '' !== $markup ) {
				$parts[] = $markup;
			}
		}

		return implode( '', $parts );
	}

	/**
	 * Build a single <path /> element.
	 *
	 * @param array<string, string> $path Path attributes.
	 * @return string
	 */
	private static function build_path_markup( array $path ): string {
		if ( ! isset( $path['d'] ) || '' === trim( $path['d'] ) ) {
			return '';
		}

		$attrs = sprintf( 'd="%s"', esc_attr( $path['d'] ) );

		foreach ( self::SHAPE_ATTRS as $attr ) {
			if ( ! isset( $path[ $attr ] ) || '' === trim( $path[ $attr ] ) ) {
				continue;
			}

			$attrs .= sprintf( ' %s="%s"', esc_attr( $attr ), esc_attr( $path[ $attr ] ) );
		}

		return sprintf( '<path %s/>', $attrs );
	}

	/**
	 * Build a single <polygon /> element.
	 *
	 * @param array<string, string> $polygon Polygon attributes.
	 * @return string
	 */
	private static function build_polygon_markup( array $polygon ): string {
		if ( ! isset( $polygon['points'] ) || '' === trim( $polygon['points'] ) ) {
			return '';
		}

		$attrs = sprintf( 'points="%s"', esc_attr( $polygon['points'] ) );

		foreach ( self::SHAPE_ATTRS as $attr ) {
			if ( ! isset( $polygon[ $attr ] ) || '' === trim( $polygon[ $attr ] ) ) {
				continue;
			}

			$attrs .= sprintf( ' %s="%s"', esc_attr( $attr ), esc_attr( $polygon[ $attr ] ) );
		}

		return sprintf( '<polygon %s/>', $attrs );
	}

	/**
	 * Build a single <rect /> element.
	 *
	 * @param array<string, string> $rect Rect attributes.
	 * @return string
	 */
	private static function build_rect_markup( array $rect ): string {
		if ( ! isset( $rect['x'], $rect['y'], $rect['width'], $rect['height'] ) ) {
			return '';
		}

		$attrs = sprintf(
			'x="%s" y="%s" width="%s" height="%s"',
			esc_attr( $rect['x'] ),
			esc_attr( $rect['y'] ),
			esc_attr( $rect['width'] ),
			esc_attr( $rect['height'] )
		);

		foreach ( self::SHAPE_ATTRS as $attr ) {
			if ( ! isset( $rect[ $attr ] ) || '' === trim( $rect[ $attr ] ) ) {
				continue;
			}

			$attrs .= sprintf( ' %s="%s"', esc_attr( $attr ), esc_attr( $rect[ $attr ] ) );
		}

		return sprintf( '<rect %s/>', $attrs );
	}

	/**
	 * Build a single <circle /> element.
	 *
	 * @param array<string, string> $circle Circle attributes.
	 * @return string
	 */
	private static function build_circle_markup( array $circle ): string {
		if ( ! isset( $circle['cx'], $circle['cy'], $circle['r'] ) ) {
			return '';
		}

		$attrs = sprintf(
			'cx="%s" cy="%s" r="%s"',
			esc_attr( $circle['cx'] ),
			esc_attr( $circle['cy'] ),
			esc_attr( $circle['r'] )
		);

		foreach ( self::SHAPE_ATTRS as $attr ) {
			if ( ! isset( $circle[ $attr ] ) || '' === trim( $circle[ $attr ] ) ) {
				continue;
			}

			$attrs .= sprintf( ' %s="%s"', esc_attr( $attr ), esc_attr( $circle[ $attr ] ) );
		}

		return sprintf( '<circle %s/>', $attrs );
	}

	/**
	 * Sanitize an SVG viewBox value.
	 *
	 * @param string $view_box Raw viewBox.
	 * @return string|null
	 */
	private static function sanitize_view_box( string $view_box ): ?string {
		$view_box = trim( preg_replace( '/\s+/', ' ', $view_box ) ?? '' );

		if ( ! preg_match( '/^-?\d+(?:\.\d+)?(?:\s-?\d+(?:\.\d+)?){3}$/', $view_box ) ) {
			return null;
		}

		return $view_box;
	}

	/**
	 * Convert a numeric coordinate to a safe string.
	 *
	 * @param mixed $value Coordinate value.
	 * @return string|null
	 */
	private static function stringify_coordinate( mixed $value ): ?string {
		if ( is_int( $value ) ) {
			return (string) $value;
		}

		if ( is_float( $value ) ) {
			return rtrim( rtrim( sprintf( '%.4F', $value ), '0' ), '.' );
		}

		if ( is_string( $value ) && is_numeric( $value ) ) {
			return self::stringify_coordinate( (float) $value );
		}

		return null;
	}

	/**
	 * Reset the per-request icon cache (tests only).
	 *
	 * @internal
	 */
	public static function flush_cache_for_tests(): void {
		self::$definitions_cache  = null;
		self::$normalized_cache   = array();
		self::$inner_markup_cache = array();
		self::$slugs_cache        = null;
	}
}
