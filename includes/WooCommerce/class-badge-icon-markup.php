<?php
/**
 * Badge icon markup: SVG sanitization and icon HTML assembly.
 *
 * Extracted from Custom_Badge_Taxonomy to keep that file under the length cap.
 * Composed via `use`, so every existing Custom_Badge_Taxonomy::sanitize_svg()
 * and ::build_badge_icon_html() call site is unchanged.
 *
 * These two belong together: build_badge_icon_html() is the only caller that
 * feeds authored markup to sanitize_svg(), and the allowlist below is the
 * theme's single SVG trust boundary — the badge studio has no client-side
 * sanitizer, so anything that reaches a badge passes through here first.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Core\Icons;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SVG sanitization and badge icon markup.
 */
trait Badge_Icon_Markup {
	/**
	 * Sanitize SVG markup using wp_kses with allowed SVG elements.
	 *
	 * @param string $svg Raw SVG markup.
	 * @return string Sanitized SVG.
	 */
	public static function sanitize_svg( string $svg ): string {
		$allowed = array(
			'svg'      => array(
				'xmlns'       => true,
				'viewbox'     => true,
				'width'       => true,
				'height'      => true,
				'fill'        => true,
				'class'       => true,
				'aria-hidden' => true,
				'role'        => true,
				'focusable'   => true,
			),
			'path'     => array(
				'd'               => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'fill-rule'       => true,
				'clip-rule'       => true,
			),
			'circle'   => array(
				'cx'           => true,
				'cy'           => true,
				'r'            => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'rect'     => array(
				'x'            => true,
				'y'            => true,
				'width'        => true,
				'height'       => true,
				'rx'           => true,
				'ry'           => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'line'     => array(
				'x1'           => true,
				'y1'           => true,
				'x2'           => true,
				'y2'           => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'polyline' => array(
				'points'       => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'polygon'  => array(
				'points'       => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'g'        => array(
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
				'transform'    => true,
			),
			'defs'     => array(),
			'title'    => array(),
		);

		return wp_kses( trim( $svg ), $allowed );
	}

	/**
	 * Build the icon HTML for a badge (priority: custom SVG > library > emoji).
	 *
	 * @param string $svg_icon     Custom SVG markup.
	 * @param string $library_icon Library icon name.
	 * @param string $emoji        Emoji/text icon.
	 * @param string $icon_color   Optional hex color for the icon.
	 * @param int    $icon_size    Optional size in px (0 = auto).
	 * @return string Icon HTML with wrapper span, or empty string.
	 */
	public static function build_badge_icon_html( string $svg_icon, string $library_icon, string $emoji, string $icon_color = '', int $icon_size = 0 ): string {
		$style_parts = array();
		$safe_color  = Badge_Style_Schema::sanitize_color( $icon_color );
		if ( '' !== $safe_color ) {
			$style_parts[] = 'color:' . $safe_color;
		}
		if ( $icon_size > 0 ) {
			// font-size sizes emoji glyphs; --badge-icon-size sizes SVGs (1:1,
			// not 1.25x) via the CSS rule. Both kept so "size" means size for
			// every icon type, on the front end and the admin preview.
			$style_parts[] = 'font-size:' . $icon_size . 'px';
			$style_parts[] = '--badge-icon-size:' . $icon_size . 'px';
		}
		// Spacing uses flex gap (--badge-icon-gap) so start/end placement both work.
		$style_attr = ! empty( $style_parts ) ? ' style="' . esc_attr( implode( ';', $style_parts ) ) . '"' : '';

		if ( '' !== $svg_icon ) {
			$safe_svg = self::sanitize_svg( $svg_icon );
			if ( '' === $safe_svg ) {
				return '';
			}
			return '<span class="aggressive-apparel-product-badge__icon" aria-hidden="true"' . $style_attr . '>' . $safe_svg . '</span>';
		}

		if ( '' !== $library_icon && Icons::exists( $library_icon ) ) {
			$svg = Icons::get(
				$library_icon,
				array(
					'width'       => 16,
					'height'      => 16,
					'aria-hidden' => 'true',
				),
			);
			return '<span class="aggressive-apparel-product-badge__icon" aria-hidden="true"' . $style_attr . '>' . $svg . '</span>';
		}

		if ( '' !== $emoji ) {
			return '<span class="aggressive-apparel-product-badge__icon" aria-hidden="true"' . $style_attr . '>' . esc_html( $emoji ) . '</span>';
		}

		return '';
	}
}
