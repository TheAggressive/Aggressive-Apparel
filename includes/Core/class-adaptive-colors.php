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
 * Reads color pairs from settings.custom.adaptiveColors in theme.json and
 * injects them into the palette as light-dark() CSS values. Also handles
 * per-block adaptive color overrides on the frontend via render_block and
 * enqueues the editor extension scripts.
 *
 * @since 1.56.0
 */
class Adaptive_Colors {

	/**
	 * Cached adaptive color pairs from theme.json.
	 *
	 * @var array<int, array{slug: string, name: string, light: string, dark: string}>
	 */
	private array $pairs = array();

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
	 * Inject adaptive color pairs into the theme.json palette.
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
				'color' => sprintf( 'light-dark(%s, %s)', $pair['light'], $pair['dark'] ),
			);
		}

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
			)
		);
	}

	/**
	 * Inject light-dark() inline styles for per-block adaptive overrides.
	 *
	 * Reads adaptiveBackground and adaptiveText attributes from the block
	 * and injects the appropriate CSS inline styles.
	 *
	 * @param string               $block_content Rendered block HTML.
	 * @param array<string, mixed> $block         Parsed block data.
	 * @return string Modified block HTML.
	 */
	public function inject_adaptive_styles( string $block_content, array $block ): string {
		$attrs = $block['attrs'] ?? array();

		$adaptive_bg   = $attrs['adaptiveBackground'] ?? null;
		$adaptive_text = $attrs['adaptiveText'] ?? null;

		if ( ! $adaptive_bg && ! $adaptive_text ) {
			return $block_content;
		}

		$styles = array();

		if ( is_array( $adaptive_bg ) ) {
			$light = $this->resolve_color_value( $adaptive_bg['light'] ?? '' );
			$dark  = $this->resolve_color_value( $adaptive_bg['dark'] ?? '' );

			if ( $light && $dark ) {
				$styles[] = sprintf( 'background-color:light-dark(%s, %s)', $light, $dark );
			} elseif ( $light ) {
				$styles[] = sprintf( 'background-color:%s', $light );
			} elseif ( $dark ) {
				$styles[] = sprintf( 'background-color:%s', $dark );
			}
		}

		if ( is_array( $adaptive_text ) ) {
			$light = $this->resolve_color_value( $adaptive_text['light'] ?? '' );
			$dark  = $this->resolve_color_value( $adaptive_text['dark'] ?? '' );

			if ( $light && $dark ) {
				$styles[] = sprintf( 'color:light-dark(%s, %s)', $light, $dark );
			} elseif ( $light ) {
				$styles[] = sprintf( 'color:%s', $light );
			} elseif ( $dark ) {
				$styles[] = sprintf( 'color:%s', $dark );
			}
		}

		if ( empty( $styles ) ) {
			return $block_content;
		}

		$style_string = implode( ';', $styles );

		return $this->inject_inline_style( $block_content, $style_string );
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
	 * Looks up the slug in the global palette (which includes our adaptive entries)
	 * and returns the raw color value. This prevents nested var() inside light-dark()
	 * in inline styles.
	 *
	 * @param string $value CSS color value, possibly a var() reference.
	 * @return string Resolved color value or original value if not a var() reference.
	 */
	private function resolve_color_value( string $value ): string {
		if ( ! $value || ! preg_match( '/^var\(--wp--preset--color--(.+)\)$/', $value, $matches ) ) {
			return $value;
		}

		$slug = $matches[1];

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
