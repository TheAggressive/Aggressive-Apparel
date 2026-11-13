<?php
/**
 * Body Font Token
 *
 * Exposes the global-styles body font family as `--aa-font-body`.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Assets;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Body Font Token
 *
 * WordPress prints the body font family (theme.json
 * `styles.typography.fontFamily`, or the Site Editor's override of it)
 * straight onto `body` and emits no custom property for it. Elements that
 * must read body type while nested in something with its own font — the
 * mini-cart drawer inside the Mini-Cart block — need it as a variable, so
 * this prints `:root{--aa-font-body:…}` from the merged global styles, inline
 * on the token stylesheet. It is site-wide configuration, so it is safe under
 * a full-page cache.
 */
final class Body_Font_Token {

	/**
	 * The custom property the theme's CSS reads.
	 */
	public const PROPERTY = '--aa-font-body';

	/**
	 * Inline CSS declaring the token, or '' when the body font is unset or
	 * not a value that can be printed safely.
	 *
	 * @return string
	 */
	public static function css(): string {
		$value = self::to_css_value( wp_get_global_styles( array( 'typography', 'fontFamily' ) ) );

		return '' === $value ? '' : ':root{' . self::PROPERTY . ':' . $value . '}';
	}

	/**
	 * Convert a global-styles font-family value to a CSS value.
	 *
	 * Accepts the `var:preset|font-family|<slug>` reference form, its resolved
	 * `var(--wp--preset--font-family--<slug>)` form, or a literal family list.
	 * Anything else (including any character that could end the declaration)
	 * yields ''.
	 *
	 * @param mixed $raw Value from wp_get_global_styles().
	 * @return string
	 */
	public static function to_css_value( mixed $raw ): string {
		if ( ! is_string( $raw ) ) {
			return '';
		}

		$raw = trim( $raw );

		if ( 1 === preg_match( '/^var:preset\|font-family\|([a-z0-9-]+)$/', $raw, $matches ) ) {
			return 'var(--wp--preset--font-family--' . $matches[1] . ')';
		}

		if ( 1 === preg_match( '/^var\(--wp--preset--font-family--[a-z0-9-]+\)$/', $raw ) ) {
			return $raw;
		}

		// A literal family list: names, quotes, commas, spaces and hyphens only,
		// with balanced quotes (an unclosed string would swallow the `}`).
		if (
			1 === preg_match( '/^[\p{L}\p{N}\s,\'"_-]+$/u', $raw )
			&& 0 === substr_count( $raw, "'" ) % 2
			&& 0 === substr_count( $raw, '"' ) % 2
		) {
			return $raw;
		}

		return '';
	}
}
