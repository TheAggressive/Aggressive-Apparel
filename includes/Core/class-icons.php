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

namespace Aggressive_Apparel\Core;

/**
 * Icons Class
 *
 * @since 1.0.0
 */
class Icons {

	/**
	 * Default SVG attributes
	 *
	 * @var array
	 */
	private const DEFAULT_ATTRS = array(
		'width'  => 24,
		'height' => 24,
		'fill'   => 'currentColor',
	);

	/**
	 * Icon definitions (path data only)
	 *
	 * @var array
	 */
	private const ICONS = array(
		// Navigation icons.
		'hamburger'      => 'M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z',
		'dots'           => 'M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z',
		'bars'           => 'M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z',
		'close'          => 'M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z',
		'chevron-down'   => 'M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z',
		'chevron-up'     => 'M7.41 15.41L12 10.83l4.59 4.58L18 14l-6-6-6 6 1.41 1.41z',
		'chevron-left'   => 'M15.41 16.59L10.83 12l4.58-4.59L14 6l-6 6 6 6 1.41-1.41z',
		'chevron-right'  => 'M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z',
		'arrow-left'     => 'M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z',
		'arrow-right'    => 'M4 11h12.17l-5.59-5.59L12 4l8 8-8 8-1.41-1.41L16.17 13H4v-2z',

		// Action icons.
		'search'         => 'M13.5 6C10.5 6 8 8.5 8 11.5c0 1.1.3 2.1.9 3l-3.4 3.4 1.1 1.1 3.4-3.4c.9.6 1.9.9 3 .9 3 0 5.5-2.5 5.5-5.5S16.5 6 13.5 6zm0 9c-2 0-3.5-1.5-3.5-3.5S11.5 8 13.5 8 17 9.5 17 11.5 15.5 15 13.5 15z',
		'cart'           => 'M17 18a2 2 0 0 1 2 2 2 2 0 0 1-2 2 2 2 0 0 1-2-2 2 2 0 0 1 2-2M1 2h3.27l.94 2H20a1 1 0 0 1 1 1c0 .17-.05.34-.12.5l-3.58 6.47c-.34.61-1 1.03-1.75 1.03H8.1l-.9 1.63-.03.12a.25.25 0 0 0 .25.25H19v2H7a2 2 0 0 1-2-2c0-.35.09-.68.24-.96l1.36-2.45L3 4H1V2m6 16a2 2 0 0 1 2 2 2 2 0 0 1-2 2 2 2 0 0 1-2-2 2 2 0 0 1 2-2m9-7 2.78-5H6.14l2.36 5H16z',
		'user'           => 'M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z',
		'heart'          => 'M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z',

		// UI icons.
		'check'          => 'M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z',
		'plus'           => 'M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z',
		'minus'          => 'M19 13H5v-2h14v2z',
		'info'           => 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z',
		'warning'        => 'M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z',
		'error'          => 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z',

		// Social icons.
		'facebook'       => 'M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z',
		'twitter'        => 'M22.46 6c-.77.35-1.6.58-2.46.69.88-.53 1.56-1.37 1.88-2.38-.83.5-1.75.85-2.72 1.05C18.37 4.5 17.26 4 16 4c-2.35 0-4.27 1.92-4.27 4.29 0 .34.04.67.11.98C8.28 9.09 5.11 7.38 3 4.79c-.37.63-.58 1.37-.58 2.15 0 1.49.75 2.81 1.91 3.56-.71 0-1.37-.2-1.95-.5v.03c0 2.08 1.48 3.82 3.44 4.21a4.22 4.22 0 0 1-1.93.07 4.28 4.28 0 0 0 4 2.98 8.521 8.521 0 0 1-5.33 1.84c-.34 0-.68-.02-1.02-.06C3.44 20.29 5.7 21 8.12 21 16 21 20.33 14.46 20.33 8.79c0-.19 0-.37-.01-.56.84-.6 1.56-1.36 2.14-2.23z',
		'instagram'      => 'M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2m-.2 2A3.6 3.6 0 0 0 4 7.6v8.8C4 18.39 5.61 20 7.6 20h8.8a3.6 3.6 0 0 0 3.6-3.6V7.6C20 5.61 18.39 4 16.4 4H7.6m9.65 1.5a1.25 1.25 0 0 1 1.25 1.25A1.25 1.25 0 0 1 17.25 8 1.25 1.25 0 0 1 16 6.75a1.25 1.25 0 0 1 1.25-1.25M12 7a5 5 0 0 1 5 5 5 5 0 0 1-5 5 5 5 0 0 1-5-5 5 5 0 0 1 5-5m0 2a3 3 0 0 0-3 3 3 3 0 0 0 3 3 3 3 0 0 0 3-3 3 3 0 0 0-3-3z',

		// Legacy aliases for backwards compatibility.
		'hamburger-menu' => 'M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z',
		'back'           => 'M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z',
		'chevron'        => 'M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z',
		'arrow'          => 'M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z',
	);

	/**
	 * Get SVG icon markup
	 *
	 * @param string $icon  Icon name.
	 * @param array  $attrs Optional SVG attributes.
	 * @return string SVG markup or empty string if icon not found.
	 */
	public static function get( string $icon, array $attrs = array() ): string {
		if ( ! isset( self::ICONS[ $icon ] ) ) {
			return '';
		}

		$attrs = wp_parse_args( $attrs, self::DEFAULT_ATTRS );

		$svg_attrs = sprintf(
			'xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="%d" height="%d" fill="%s"',
			absint( $attrs['width'] ),
			absint( $attrs['height'] ),
			esc_attr( $attrs['fill'] )
		);

		// Add any additional attributes.
		if ( isset( $attrs['class'] ) ) {
			$svg_attrs .= sprintf( ' class="%s"', esc_attr( $attrs['class'] ) );
		}
		if ( isset( $attrs['aria-hidden'] ) ) {
			$svg_attrs .= sprintf( ' aria-hidden="%s"', esc_attr( $attrs['aria-hidden'] ) );
		}

		return sprintf( '<svg %s><path d="%s"/></svg>', $svg_attrs, self::ICONS[ $icon ] );
	}

	/**
	 * Echo SVG icon
	 *
	 * @param string $icon  Icon name.
	 * @param array  $attrs Optional SVG attributes.
	 */
	public static function render( string $icon, array $attrs = array() ): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::get( $icon, $attrs );
	}

	/**
	 * Check if an icon exists
	 *
	 * @param string $icon Icon name.
	 * @return bool
	 */
	public static function exists( string $icon ): bool {
		return isset( self::ICONS[ $icon ] );
	}

	/**
	 * Get all available icon names
	 *
	 * @return array
	 */
	public static function list(): array {
		return array_keys( self::ICONS );
	}
}
