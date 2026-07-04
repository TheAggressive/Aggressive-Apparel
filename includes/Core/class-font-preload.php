<?php
/**
 * Font Preload Class
 *
 * Emits <link rel="preload"> hints for the theme's critical font files.
 *
 * The brand fonts load via @font-face rules inlined deep inside the
 * global-styles block, so the browser only discovers the woff2 URLs after
 * CSS parse + style resolution. Preloading the body and heading faces lets
 * the fetch start with the first bytes of HTML, removing a full
 * discovery round-trip from first text render (font-display swaps sooner,
 * less fallback-flash).
 *
 * @package Aggressive_Apparel
 * @since 1.132.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Font Preload Class
 *
 * @since 1.132.0
 */
class Font_Preload {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Priority 2: before styles and scripts print, so the hints lead the head.
		add_action( 'wp_head', array( $this, 'print_preload_links' ), 2 );
	}

	/**
	 * Print preload links for the body and heading font faces.
	 *
	 * @return void
	 */
	public function print_preload_links(): void {
		/**
		 * Filter whether critical font preload hints are printed.
		 *
		 * @since 1.132.0
		 *
		 * @param bool $preload True to print the preload links.
		 */
		if ( ! apply_filters( 'aggressive_apparel_preload_fonts', true ) ) {
			return;
		}

		foreach ( $this->get_critical_font_urls() as $url ) {
			printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
				esc_url( $url )
			);
		}
	}

	/**
	 * Resolve the woff2 URLs for the configured body and heading fonts.
	 *
	 * Reads the font-family presets assigned in global styles, matches them
	 * against the registered @font-face data (theme.json + Font Library),
	 * and returns the regular (400/normal) face of each — the weights that
	 * gate first text paint. Silently returns fewer/no URLs when anything
	 * is unresolvable.
	 *
	 * @return array<int, string> Unique font file URLs (woff2 only).
	 */
	private function get_critical_font_urls(): array {
		if ( ! class_exists( '\WP_Font_Face_Resolver' ) || ! function_exists( 'wp_get_global_styles' ) ) {
			return array();
		}

		$slugs = array_filter(
			array(
				$this->get_font_slug_from_styles( array( 'typography', 'fontFamily' ) ),
				$this->get_font_slug_from_styles( array( 'elements', 'heading', 'typography', 'fontFamily' ) ),
			)
		);

		if ( empty( $slugs ) ) {
			return array();
		}

		$urls = array();

		foreach ( \WP_Font_Face_Resolver::get_fonts_from_theme_json() as $faces ) {
			if ( ! is_array( $faces ) ) {
				continue;
			}

			foreach ( $faces as $face ) {
				if ( ! is_array( $face ) ) {
					continue;
				}

				$family = (string) ( $face['font-family'] ?? '' );

				if ( '' === $family || ! in_array( sanitize_title( $family ), $slugs, true ) ) {
					continue;
				}

				if ( ! $this->is_regular_face( $face ) ) {
					continue;
				}

				$src = $face['src'] ?? '';
				$src = is_array( $src ) ? (string) reset( $src ) : (string) $src;

				if ( '' !== $src && str_ends_with( strtolower( $src ), '.woff2' ) ) {
					$urls[] = $src;
				}
			}
		}

		return array_values( array_unique( $urls ) );
	}

	/**
	 * Extract a font-family preset slug from a global styles path.
	 *
	 * @param array<int, string> $path Global styles path.
	 * @return string Slug, or empty string when not a preset reference.
	 */
	private function get_font_slug_from_styles( array $path ): string {
		$value = wp_get_global_styles( $path );

		if ( ! is_string( $value ) || ! preg_match( '/var\(--wp--preset--font-family--([a-z0-9-]+)\)/', $value, $matches ) ) {
			return '';
		}

		return $matches[1];
	}

	/**
	 * Whether a font face is the regular (400 / normal) variant.
	 *
	 * Weight ranges such as "300 700" count when they include 400.
	 *
	 * @param array<string, mixed> $face Font face definition.
	 * @return bool
	 */
	private function is_regular_face( array $face ): bool {
		$style = strtolower( (string) ( $face['font-style'] ?? 'normal' ) );

		if ( 'normal' !== $style ) {
			return false;
		}

		$weight = strtolower( trim( (string) ( $face['font-weight'] ?? '400' ) ) );

		if ( '' === $weight || 'normal' === $weight || '400' === $weight ) {
			return true;
		}

		// Variable-font range, e.g. "300 700".
		if ( preg_match( '/^(\d+)\s+(\d+)$/', $weight, $matches ) ) {
			return (int) $matches[1] <= 400 && 400 <= (int) $matches[2];
		}

		return false;
	}
}
