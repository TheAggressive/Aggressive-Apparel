<?php
/**
 * Curated SVG badge shape library.
 *
 * Paths live in PHP so the designer does not depend on a separate icon build
 * step. Custom shapes reuse the same SVG sanitizer as badge icons.
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
 * Badge silhouette / frame definitions.
 */
class Badge_Shapes {

	/**
	 * Human labels for the shape picker.
	 *
	 * @return array<string, string>
	 */
	public static function labels(): array {
		return array(
			'rect'         => __( 'Rectangle', 'aggressive-apparel' ),
			'pill'         => __( 'Pill', 'aggressive-apparel' ),
			'ticket'       => __( 'Ticket', 'aggressive-apparel' ),
			'shield'       => __( 'Shield', 'aggressive-apparel' ),
			'hex'          => __( 'Hexagon', 'aggressive-apparel' ),
			'burst'        => __( 'Burst', 'aggressive-apparel' ),
			'ribbon-fold'  => __( 'Ribbon fold', 'aggressive-apparel' ),
			'tag'          => __( 'Tag', 'aggressive-apparel' ),
			'stamp-circle' => __( 'Stamp', 'aggressive-apparel' ),
			'custom'       => __( 'Custom SVG', 'aggressive-apparel' ),
		);
	}

	/**
	 * Path `d` attributes in a 100×40 viewBox (except stamp-circle 40×40).
	 *
	 * Paths keep a wide, flat content well so `mask-size: 100% 100%` still
	 * leaves room for label text after the CSS shape insets are applied.
	 *
	 * @return array<string, array{viewBox: string, path: string}>
	 */
	public static function definitions(): array {
		return array(
			'pill'         => array(
				'viewBox' => '0 0 100 40',
				'path'    => 'M20 0h60a20 20 0 0 1 0 40H20a20 20 0 0 1 0-40z',
			),
			// Small side notches; body stays nearly rectangular.
			'ticket'       => array(
				'viewBox' => '0 0 100 40',
				'path'    => 'M0 5a5 5 0 0 1 5-5h90a5 5 0 0 1 5 5v11a4 4 0 1 0 0 8v11a5 5 0 0 1-5 5H5a5 5 0 0 1-5-5V24a4 4 0 1 0 0-8V5z',
			),
			// Wide crest — tapers only in the lower third so mid-band text clears.
			'shield'       => array(
				'viewBox' => '0 0 100 40',
				'path'    => 'M8 1.5h84l4 7.5v12c0 9-14 15-42 17C26 36 4 30 4 21V9L8 1.5z',
			),
			// Shallow points so ends do not swallow short labels.
			'hex'          => array(
				'viewBox' => '0 0 100 40',
				'path'    => 'M10 0h80l10 20-10 20H10L0 20 10 0z',
			),
			// 12-point elliptical seal — designed for a 100×40 box so it stays
			// a readable burst when CSS locks the badge to that aspect ratio.
			'burst'        => array(
				'viewBox' => '0 0 100 40',
				'path'    => 'M50 0L59.1 7.9L75 2.7L74.7 11.2L93.3 10L83.8 16.8L100 20L83.8 23.2L93.3 30L74.7 28.8L75 37.3L59.1 32.1L50 40L40.9 32.1L25 37.3L25.3 28.8L6.7 30L16.2 23.2L0 20L16.2 16.8L6.7 10L25.3 11.2L25 2.7L40.9 7.9z',
			),
			// Long rectangular body; modest chevron tip on the right.
			'ribbon-fold'  => array(
				'viewBox' => '0 0 100 40',
				'path'    => 'M0 0h82l18 20-18 20H0V0z',
			),
			// Extended label body before the tip + eyelet.
			'tag'          => array(
				'viewBox' => '0 0 100 40',
				'path'    => 'M0 6a6 6 0 0 1 6-6h64l30 20-30 20H6a6 6 0 0 1-6-6V6zm15 14a5 5 0 1 0 0-.01z',
			),
			'stamp-circle' => array(
				'viewBox' => '0 0 40 40',
				'path'    => 'M20 0a20 20 0 1 1 0 40 20 20 0 0 1 0-40z',
			),
		);
	}

	/**
	 * Resolve the active shape definition for a badge.
	 *
	 * @param array<string, mixed> $badge Badge data.
	 * @return array{viewBox: string, path: string}|null
	 */
	public static function resolve( array $badge ): ?array {
		$badge = Badge_Style_Schema::with_defaults( $badge );
		$shape = Badge_Style_Schema::sanitize_enum( $badge['shape'], Badge_Style_Schema::SHAPES, 'rect' );

		if ( 'rect' === $shape || 'pill' === $shape ) {
			// Pill is radius-driven on rect geometry; no SVG mask required.
			return null;
		}

		if ( 'custom' === $shape ) {
			$svg = Badge_Style_Schema::sanitize_shape_svg( (string) $badge['shape_svg'] );
			if ( '' === $svg ) {
				return null;
			}
			// Prefer a path `d` extracted from sanitized SVG (double or single quotes).
			if ( preg_match( '/\bd=(["\'])([^"\']+)\1/', $svg, $m ) ) {
				$path = self::sanitize_path_d( $m[2] );
				if ( '' === $path ) {
					return null;
				}
				$view = '0 0 100 40';
				if ( preg_match( '/viewBox=(["\'])([^"\']+)\1/i', $svg, $vb ) ) {
					$view = self::sanitize_viewbox( $vb[2] );
				}
				return array(
					'viewBox' => $view,
					'path'    => $path,
				);
			}
			return null;
		}

		$defs = self::definitions();
		return $defs[ $shape ] ?? null;
	}

	/**
	 * Allowlist an SVG path `d` attribute.
	 *
	 * @param string $path Raw path data.
	 * @return string Empty when invalid.
	 */
	public static function sanitize_path_d( string $path ): string {
		$path = trim( $path );
		if ( '' === $path || strlen( $path ) > 4000 ) {
			return '';
		}
		if ( ! preg_match( '/^[MmLlHhVvCcSsQqTtAaZz0-9eE.,\s+\-]+$/', $path ) ) {
			return '';
		}
		return $path;
	}

	/**
	 * Allowlist an SVG viewBox attribute.
	 *
	 * @param string $viewbox Raw viewBox.
	 * @return string Safe viewBox.
	 */
	public static function sanitize_viewbox( string $viewbox ): string {
		$viewbox = trim( $viewbox );
		if ( ! preg_match( '/^[\d.\s,\-]+$/', $viewbox ) ) {
			return '0 0 100 40';
		}
		return $viewbox;
	}

	/**
	 * CSS mask-image value for shaped badges, or empty for rect/pill.
	 *
	 * @param array<string, mixed> $badge Badge data.
	 * @return string
	 */
	public static function mask_image_css( array $badge ): string {
		$badge = Badge_Style_Schema::with_defaults( $badge );
		$mode  = Badge_Style_Schema::sanitize_enum( $badge['shape_mode'], Badge_Style_Schema::SHAPE_MODES, 'mask' );
		$def   = self::resolve( $badge );

		if ( null === $def || 'frame' === $mode ) {
			return '';
		}

		// Encode after building; path/viewBox are curated theme constants (or sanitized custom).
		$svg = rawurlencode(
			sprintf(
				'<svg xmlns="http://www.w3.org/2000/svg" viewBox="%s"><path fill="black" d="%s"/></svg>',
				$def['viewBox'],
				$def['path']
			)
		);

		return 'url("data:image/svg+xml,' . $svg . '")';
	}

	/**
	 * Render an absolutely-positioned frame SVG for frame mode shapes.
	 *
	 * @param array<string, mixed> $badge Badge data.
	 * @return string HTML or empty.
	 */
	public static function render_frame_svg( array $badge ): string {
		$badge = Badge_Style_Schema::with_defaults( $badge );
		$shape = Badge_Style_Schema::sanitize_enum( $badge['shape'], Badge_Style_Schema::SHAPES, 'rect' );
		$mode  = Badge_Style_Schema::sanitize_enum( $badge['shape_mode'], Badge_Style_Schema::SHAPE_MODES, 'mask' );

		if ( 'rect' === $shape || 'pill' === $shape || 'frame' !== $mode ) {
			return '';
		}

		$def = self::resolve( $badge );
		if ( null === $def ) {
			// Custom full SVG markup fallback.
			if ( 'custom' === $shape && '' !== (string) $badge['shape_svg'] ) {
				$safe = Badge_Style_Schema::sanitize_shape_svg( (string) $badge['shape_svg'] );
				if ( '' === $safe ) {
					return '';
				}
				return sprintf(
					'<span class="aggressive-apparel-product-badge__shape" aria-hidden="true">%s</span>',
					$safe
				);
			}
			return '';
		}

		$width = Badge_Style_Schema::clamp_int( $badge['frame_width'], 0, 8 );
		if ( 0 === $width ) {
			// The inspector's width slider bottoms out at 0, so 0 is a
			// deliberate "no frame", not missing data — absent data defaults to
			// 2 in designer_defaults(). Returning early keeps this agreeing with
			// emit_css_variables(), which emits `--badge-frame-width:0px`. The
			// CSS var beats this element's stroke-width attribute, so flooring
			// here to 2 only ever produced an invisible SVG plus a preview that
			// disagreed with the storefront.
			return '';
		}

		// Stroke color comes from --badge-frame-color (emitted with fallbacks).
		return sprintf(
			'<svg class="aggressive-apparel-product-badge__shape" viewBox="%1$s" aria-hidden="true" focusable="false"><path d="%2$s" fill="none" stroke-width="%3$d" vector-effect="non-scaling-stroke"/></svg>',
			esc_attr( $def['viewBox'] ),
			esc_attr( $def['path'] ),
			$width
		);
	}
}
