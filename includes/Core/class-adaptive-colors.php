<?php
/**
 * Adaptive Colors Class
 *
 * Auto-generates light-dark() palette entries from theme.json config
 * and provides per-block adaptive color overrides via the block editor.
 * Enablement lives in Appearance → Theme Features.
 *
 * @package Aggressive_Apparel
 * @since 1.56.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Core;

use Aggressive_Apparel\Assets\Asset_Loader;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adaptive Colors Class
 *
 * Reads color pairs from settings.custom.adaptiveColors in theme.json (source
 * of truth). Palette swatches use the light solid so Site Editor chrome
 * (`wp.components.Theme`) can validate colors; runtime CSS custom properties
 * are overridden to light-dark() from the same pairs. Also handles per-block
 * adaptive overrides via render_block and enqueues editor scripts.
 *
 * @since 1.56.0
 */
class Adaptive_Colors {

	/**
	 * Marker wrapping injected preset CSS so re-filters stay idempotent.
	 */
	private const PRESET_CSS_MARKER = '/*aa-adaptive-presets*/';

	/**
	 * Cached adaptive color pairs from theme.json.
	 *
	 * @var array<int, array{slug: string, name: string, light: string, dark: string}>
	 */
	private array $pairs = array();

	/**
	 * Request-local merged adaptive pairs (style variations included).
	 *
	 * @var array<int, array{slug: string, name: string, light: string, dark: string}>|null
	 */
	private ?array $merged_pairs = null;

	/**
	 * Cached flat palette for resolving var() references.
	 *
	 * @var array<int, array{slug: string, name: string, color: string}>|null
	 */
	private ?array $flat_palette = null;

	/**
	 * Initialize adaptive colors system.
	 *
	 * Honors Appearance → Theme Features → Adaptive Colors. Unconfigured
	 * installs keep the historical always-on behavior until an admin
	 * explicitly turns it off.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( ! Theme_Features::is_enabled( 'adaptive_colors' ) ) {
			return;
		}

		$this->load_pairs();

		if ( empty( $this->pairs ) ) {
			return;
		}

		// Inject adaptive palette entries into theme.json.
		add_filter( 'wp_theme_json_data_theme', array( $this, 'inject_adaptive_palette' ) );

		// Inject light-dark() inline styles for per-block overrides.
		add_filter( 'render_block', array( $this, 'inject_adaptive_styles' ), 10, 2 );

		// Enqueue editor scripts.
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * Load adaptive color pairs from the raw theme.json file.
	 *
	 * Reads directly from the file to avoid recursion with
	 * the wp_theme_json_data_theme filter.
	 *
	 * @return void
	 */
	private function load_pairs(): void {
		$theme_json_path = get_template_directory() . '/theme.json';

		if ( ! file_exists( $theme_json_path ) ) {
			return;
		}

		// Cache the extracted pairs keyed on the file's mtime so the ~38 KB
		// theme.json read + json_decode runs once per edit, not per request.
		$cache_key = 'aggressive_apparel_adaptive_pairs_' . (string) filemtime( $theme_json_path );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			$this->pairs = $cached;
			return;
		}

		$data = wp_json_file_decode( $theme_json_path, array( 'associative' => true ) );
		if ( ! is_array( $data ) ) {
			return;
		}

		$colors = $data['settings']['custom']['adaptiveColors'] ?? array();

		$this->pairs = $this->extract_pairs( $colors );

		set_transient( $cache_key, $this->pairs, WEEK_IN_SECONDS );
	}

	/**
	 * Extract validated adaptive color pairs from theme.json settings.
	 *
	 * @param mixed $colors Raw adaptiveColors array from theme.json.
	 * @return array<int, array{slug: string, name: string, light: string, dark: string}>
	 */
	private function extract_pairs( $colors ): array {
		if ( ! is_array( $colors ) ) {
			return array();
		}

		$pairs = array();

		foreach ( $colors as $pair ) {
			if (
				is_array( $pair ) &&
				! empty( $pair['slug'] ) &&
				! empty( $pair['name'] ) &&
				isset( $pair['light'] ) &&
				isset( $pair['dark'] )
			) {
				$pairs[] = $pair;
			}
		}

		return $pairs;
	}

	/**
	 * Inject adaptive color pairs into the theme.json palette + CSS overrides.
	 *
	 * Palette `color` is the light solid (Theme / colord-safe). Matching
	 * `--wp--preset--color--*` custom properties are set to light-dark() via
	 * styles.css so canvas and front end stay adaptive. Both sides are read
	 * only from settings.custom.adaptiveColors.
	 *
	 * @param \WP_Theme_JSON_Data $theme_json Theme JSON data.
	 * @return \WP_Theme_JSON_Data Modified theme JSON data.
	 */
	public function inject_adaptive_palette( $theme_json ) {
		$data = $theme_json->get_data();

		// Prefer merged theme.json data (includes active style variation overrides).
		$pairs = $this->extract_pairs(
			$data['settings']['custom']['adaptiveColors'] ?? array()
		);

		if ( empty( $pairs ) ) {
			$pairs = $this->pairs;
		}

		if ( empty( $pairs ) ) {
			return $theme_json;
		}

		// Internally, WP_Theme_JSON stores presets under origin keys
		// (e.g. ['theme' => [...]]). Extract the flat array from the
		// correct origin so we don't corrupt the structure.
		$palette_data     = $data['settings']['color']['palette'] ?? array();
		$existing_palette = $palette_data['theme'] ?? $palette_data;

		// Safety: if still not a sequential array, fall back to empty.
		if ( ! is_array( $existing_palette ) || ( ! empty( $existing_palette ) && ! isset( $existing_palette[0] ) ) ) {
			$existing_palette = array();
		}

		$adaptive_entries = array();
		foreach ( $pairs as $pair ) {
			$adaptive_entries[] = array(
				'name'  => $pair['name'],
				'slug'  => $pair['slug'],
				// Solid light for editor chrome validators; CSS override below
				// restores light-dark() for rendering.
				'color' => $pair['light'],
			);
		}

		$existing_css = is_string( $data['styles']['css'] ?? null )
			? $data['styles']['css']
			: '';

		// Pass clean theme.json-formatted data; update_with() handles
		// storing it under the correct origin key internally.
		return $theme_json->update_with(
			array(
				'version'  => 3,
				'settings' => array(
					'color' => array(
						'palette' => array_merge( $adaptive_entries, $existing_palette ),
					),
				),
				'styles'   => array(
					'css' => $this->merge_adaptive_preset_css( $existing_css, $pairs ),
				),
			)
		);
	}

	/**
	 * Build / replace the light-dark() preset custom-property block.
	 *
	 * @param string                                                                     $existing_css Existing styles.css.
	 * @param array<int, array{slug: string, name: string, light: string, dark: string}> $pairs Adaptive pairs.
	 * @return string
	 */
	private function merge_adaptive_preset_css( string $existing_css, array $pairs ): string {
		$block = $this->build_adaptive_preset_css( $pairs );
		if ( '' === $block ) {
			return $existing_css;
		}

		$wrapped = self::PRESET_CSS_MARKER . $block . self::PRESET_CSS_MARKER;
		$pattern = '/' . preg_quote( self::PRESET_CSS_MARKER, '/' ) . '.*?' . preg_quote( self::PRESET_CSS_MARKER, '/' ) . '/s';

		if ( preg_match( $pattern, $existing_css ) ) {
			return (string) preg_replace( $pattern, $wrapped, $existing_css, 1 );
		}

		return rtrim( $existing_css ) . "\n" . $wrapped;
	}

	/**
	 * CSS that restores adaptive light-dark() on preset custom properties.
	 *
	 * @param array<int, array{slug: string, name: string, light: string, dark: string}> $pairs Adaptive pairs.
	 * @return string
	 */
	private function build_adaptive_preset_css( array $pairs ): string {
		$declarations = array();

		foreach ( $pairs as $pair ) {
			$slug  = sanitize_title( $pair['slug'] );
			$light = $this->sanitize_css_color_token( $pair['light'] );
			$dark  = $this->sanitize_css_color_token( $pair['dark'] );

			if ( '' === $slug || '' === $light || '' === $dark ) {
				continue;
			}

			$declarations[] = sprintf(
				'--wp--preset--color--%1$s:light-dark(%2$s,%3$s)',
				$slug,
				$light,
				$dark
			);
		}

		if ( empty( $declarations ) ) {
			return '';
		}

		// :root covers front end + editor documents; low cost, O(pairs) once.
		return ':root{' . implode( ';', $declarations ) . '}';
	}

	/**
	 * Allow only safe color tokens from theme.json (no URLs / expressions).
	 *
	 * @param string $value Raw color token.
	 * @return string Sanitized token or empty string.
	 */
	private function sanitize_css_color_token( string $value ): string {
		$value = trim( $value );
		if ( '' === $value || strlen( $value ) > 200 ) {
			return '';
		}

		// Hex, rgb(a), hsl(a), oklch/oklab/lab/lch, color-mix, named-ish tokens.
		if ( preg_match(
			'/^(?:#[0-9a-fA-F]{3,8}|var\(--[a-zA-Z0-9-_]+\)|(?:rgba?|hsla?|oklch|oklab|lab|lch|color-mix)\([^;{}]*\)|[a-zA-Z][a-zA-Z0-9-]*)$/',
			$value
		) ) {
			return $value;
		}

		return '';
	}

	/**
	 * Merged adaptive pairs for the current request (variations → file fallback).
	 *
	 * @return array<int, array{slug: string, name: string, light: string, dark: string}>
	 */
	private function get_adaptive_pairs(): array {
		if ( null !== $this->merged_pairs ) {
			return $this->merged_pairs;
		}

		$from_global = function_exists( 'wp_get_global_settings' )
			? wp_get_global_settings( array( 'custom', 'adaptiveColors' ) )
			: null;
		$extracted   = $this->extract_pairs( $from_global );

		$this->merged_pairs = ! empty( $extracted ) ? $extracted : $this->pairs;

		return $this->merged_pairs;
	}

	/**
	 * Channel → CSS application map (mirrors src/utils/adaptive-color-channels.ts).
	 *
	 * Wrapper-only channels also set a direct CSS property. Descendant channels
	 * rely on marker classes + CSS variables. Selector rules live only in
	 * src/styles/components/adaptive-block-colors.css (via main.css).
	 *
	 * @var array<string, array{css_var: string, class: string, property?: string}>
	 */
	private const CHANNEL_CSS = array(
		'background' => array(
			'css_var'  => '--aa-adaptive-bg',
			'class'    => 'aa-has-adaptive-bg',
			'property' => 'background',
		),
		'text'       => array(
			'css_var'  => '--aa-adaptive-color',
			'class'    => 'aa-has-adaptive-color',
			'property' => 'color',
		),
		'link'       => array(
			'css_var' => '--aa-adaptive-link',
			'class'   => 'aa-has-adaptive-link',
		),
		'linkHover'  => array(
			'css_var' => '--aa-adaptive-link-hover',
			// Same marker as link — CSS uses --aa-adaptive-link-hover on :hover.
			'class'   => 'aa-has-adaptive-link',
		),
		'heading'    => array(
			'css_var' => '--aa-adaptive-heading',
			'class'   => 'aa-has-adaptive-heading',
		),
		'caption'    => array(
			'css_var' => '--aa-adaptive-caption',
			'class'   => 'aa-has-adaptive-caption',
		),
		'button'     => array(
			'css_var' => '--aa-adaptive-button',
			'class'   => 'aa-has-adaptive-button',
		),
		'border'     => array(
			'css_var'  => '--aa-adaptive-border',
			'class'    => 'aa-has-adaptive-border',
			'property' => 'border-color',
		),
	);

	/**
	 * Inject light-dark() styles for per-block adaptive overrides.
	 *
	 * Supports the scalable `adaptiveColors` map plus legacy
	 * `adaptiveBackground` / `adaptiveText` attributes.
	 *
	 * @param string               $block_content Rendered block HTML.
	 * @param array<string, mixed> $block         Parsed block data.
	 * @return string Modified block HTML.
	 */
	public function inject_adaptive_styles( string $block_content, array $block ): string {
		$attrs    = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		$resolved = $this->resolve_adaptive_colors( $attrs );

		if ( empty( $resolved ) ) {
			return $block_content;
		}

		$styles  = array();
		$classes = array();

		foreach ( self::CHANNEL_CSS as $channel_id => $config ) {
			if ( ! isset( $resolved[ $channel_id ] ) ) {
				continue;
			}

			$css_value = $this->pair_to_css_value( $resolved[ $channel_id ] );
			if ( '' === $css_value ) {
				continue;
			}

			$styles[]  = $config['css_var'] . ':' . $css_value;
			$classes[] = $config['class'];

			if ( ! empty( $config['property'] ) ) {
				$styles[] = $config['property'] . ':' . $css_value;
			}
		}

		if ( empty( $styles ) && empty( $classes ) ) {
			return $block_content;
		}

		if ( ! empty( $styles ) ) {
			$block_content = $this->inject_inline_style( $block_content, implode( ';', $styles ) );
		}

		if ( ! empty( $classes ) ) {
			$block_content = $this->inject_class_names( $block_content, $classes );
		}

		return $block_content;
	}

	/**
	 * Resolve adaptive color pairs from the channel map + legacy attributes.
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 * @return array<string, array{light?: string, dark?: string}>
	 */
	private function resolve_adaptive_colors( array $attrs ): array {
		$resolved = array();

		$legacy_background = $attrs['adaptiveBackground'] ?? null;
		$legacy_text       = $attrs['adaptiveText'] ?? null;

		if ( is_array( $legacy_background ) ) {
			$pair = $this->normalize_pair( $legacy_background );
			if ( null !== $pair ) {
				$resolved['background'] = $pair;
			}
		}

		if ( is_array( $legacy_text ) ) {
			$pair = $this->normalize_pair( $legacy_text );
			if ( null !== $pair ) {
				$resolved['text'] = $pair;
			}
		}

		$map = $attrs['adaptiveColors'] ?? null;
		if ( is_array( $map ) ) {
			foreach ( $map as $channel_id => $raw_pair ) {
				if ( ! is_string( $channel_id ) || ! is_array( $raw_pair ) ) {
					continue;
				}
				$pair = $this->normalize_pair( $raw_pair );
				if ( null !== $pair ) {
					$resolved[ $channel_id ] = $pair;
				}
			}
		}

		return $resolved;
	}

	/**
	 * Normalize a light/dark pair (drop empty sides).
	 *
	 * @param array<string, mixed> $pair Raw pair.
	 * @return array{light?: string, dark?: string}|null
	 */
	private function normalize_pair( array $pair ): ?array {
		$light = isset( $pair['light'] ) ? trim( (string) $pair['light'] ) : '';
		$dark  = isset( $pair['dark'] ) ? trim( (string) $pair['dark'] ) : '';

		$normalized = array();
		if ( '' !== $light ) {
			$normalized['light'] = $light;
		}
		if ( '' !== $dark ) {
			$normalized['dark'] = $dark;
		}

		return empty( $normalized ) ? null : $normalized;
	}

	/**
	 * Compose a CSS color value from a light/dark pair.
	 *
	 * @param array{light?: string, dark?: string} $pair Color pair.
	 * @return string CSS value or empty string.
	 */
	private function pair_to_css_value( array $pair ): string {
		$light = $this->resolve_color_value( $pair['light'] ?? '' );
		$dark  = $this->resolve_color_value( $pair['dark'] ?? '' );

		if ( $light && $dark ) {
			return $light === $dark
				? $light
				: sprintf( 'light-dark(%s, %s)', $light, $dark );
		}

		return '' !== $light ? $light : $dark;
	}

	/**
	 * Append class names to the first HTML element in block content.
	 *
	 * @param string             $html    Block HTML.
	 * @param array<int, string> $classes Class names to add.
	 * @return string Modified HTML.
	 */
	private function inject_class_names( string $html, array $classes ): string {
		$class_string = implode( ' ', array_unique( array_filter( $classes ) ) );

		if ( '' === $class_string ) {
			return $html;
		}

		if ( preg_match( '/^(\s*<[a-z][^>]*)\bclass="([^"]*)"/i', $html ) ) {
			return (string) preg_replace_callback(
				'/^(\s*<[a-z][^>]*)\bclass="([^"]*)"/i',
				static function ( array $m ) use ( $class_string ): string {
					$existing = trim( $m[2] );
					$merged   = '' === $existing ? $class_string : $existing . ' ' . $class_string;
					return $m[1] . 'class="' . esc_attr( $merged ) . '"';
				},
				$html,
				1
			);
		}

		return (string) preg_replace_callback(
			'/^(\s*<[a-z][^>]*?)(\s*\/?>)/i',
			static function ( array $m ) use ( $class_string ): string {
				return $m[1] . ' class="' . esc_attr( $class_string ) . '"' . $m[2];
			},
			$html,
			1
		);
	}

	/**
	 * Enqueue editor scripts for the adaptive color controls.
	 *
	 * @return void
	 */
	public function enqueue_editor_assets(): void {
		Asset_Loader::enqueue_script(
			'aggressive-apparel-adaptive-colors',
			'build/scripts/editor/adaptive-colors',
			array( 'wp-blocks', 'wp-hooks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-compose', 'wp-i18n' )
		);

		Asset_Loader::enqueue_style(
			'aggressive-apparel-adaptive-colors',
			'build/scripts/editor/adaptive-colors'
		);

		Asset_Loader::enqueue_script(
			'aggressive-apparel-color-scheme-toggle',
			'build/scripts/editor/color-scheme-toggle',
			array( 'wp-plugins', 'wp-element', 'wp-components', 'wp-i18n' )
		);
	}

	/**
	 * Resolve a var(--wp--preset--color--<slug>) reference to its actual color value.
	 *
	 * Adaptive slugs resolve to light-dark() from theme.json pairs (not the
	 * solid light stored on the palette for Theme validation). Other presets
	 * use the global palette color. Prevents nested var() inside light-dark().
	 *
	 * @param string $value CSS color value, possibly a var() reference.
	 * @return string Resolved color value or original value if not a var() reference.
	 */
	private function resolve_color_value( string $value ): string {
		if ( ! $value || ! preg_match( '/^var\(--wp--preset--color--(.+)\)$/', $value, $matches ) ) {
			return $value;
		}

		$slug = $matches[1];

		foreach ( $this->get_adaptive_pairs() as $pair ) {
			if ( $pair['slug'] === $slug ) {
				$light = $this->sanitize_css_color_token( $pair['light'] );
				$dark  = $this->sanitize_css_color_token( $pair['dark'] );
				if ( '' !== $light && '' !== $dark ) {
					return $light === $dark
						? $light
						: sprintf( 'light-dark(%s, %s)', $light, $dark );
				}
			}
		}

		if ( null === $this->flat_palette ) {
			$palette = wp_get_global_settings( array( 'color', 'palette' ) );

			if ( ! is_array( $palette ) ) {
				$this->flat_palette = array();
			} elseif ( isset( $palette[0] ) ) {
				// Already a flat sequential array.
				$this->flat_palette = $palette;
			} else {
				// Keyed by origin (theme, custom, default) — flatten.
				$flat = array();
				foreach ( $palette as $origin_entries ) {
					if ( is_array( $origin_entries ) ) {
						$flat = array_merge( $flat, $origin_entries );
					}
				}
				$this->flat_palette = $flat;
			}
		}

		foreach ( $this->flat_palette as $entry ) {
			if ( is_array( $entry ) && ( $entry['slug'] ?? '' ) === $slug ) {
				return $entry['color'];
			}
		}

		// Could not resolve — return original.
		return $value;
	}

	/**
	 * Inject an inline style into the first HTML element of block content.
	 *
	 * Appends to existing style attribute or creates a new one.
	 *
	 * @param string $html  Block HTML.
	 * @param string $style CSS declarations to inject.
	 * @return string Modified HTML.
	 */
	private function inject_inline_style( string $html, string $style ): string {
		$safe_style = esc_attr( $style );

		// If there's an existing style attribute, append to it.
		// Use \s* after ^ to handle leading whitespace from block render templates.
		// preg_replace_callback avoids backreference injection ($1, \1) from style values.
		if ( preg_match( '/^(\s*<[a-z][^>]*)\bstyle="([^"]*)"/i', $html ) ) {
			return (string) preg_replace_callback(
				'/^(\s*<[a-z][^>]*)\bstyle="([^"]*)"/i',
				function ( array $m ) use ( $safe_style ): string {
					return $m[1] . 'style="' . $m[2] . ';' . $safe_style . '"';
				},
				$html,
				1
			);
		}

		// No existing style — add one to the first element.
		return (string) preg_replace_callback(
			'/^(\s*<[a-z][^>]*?)(\s*\/?>)/i',
			function ( array $m ) use ( $safe_style ): string {
				return $m[1] . ' style="' . $safe_style . '"' . $m[2];
			},
			$html,
			1
		);
	}
}
