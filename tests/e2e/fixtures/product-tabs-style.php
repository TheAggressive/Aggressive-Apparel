<?php
/**
 * Plugin Name: Aggressive Apparel Product Tabs E2E Fixture
 * Description: Applies request-scoped Product Tabs block attributes inside wp-env.
 *
 * This file is mapped into wp-env by .wp-env.json. It is never loaded by the
 * production theme and tests/ is removed before release packages are created.
 *
 * @package Aggressive_Apparel
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read a scalar E2E query parameter through the WordPress sanitization boundary.
 *
 * @param string $key Query parameter name.
 * @return string Sanitized value, or an empty string when absent.
 */
$aggressive_apparel_e2e_query_value = static function ( string $key ): string {
	if ( ! isset( $_GET[ $key ] ) || ! is_string( $_GET[ $key ] ) ) {
		return '';
	}

	return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
};

add_action(
	'send_headers',
	static function () use ( $aggressive_apparel_e2e_query_value ): void {
		$request_id = $aggressive_apparel_e2e_query_value( 'e2e_product_tabs_request' );

		if ( '' === $request_id || '1' !== $aggressive_apparel_e2e_query_value( 'e2e_product_tabs_probe' ) ) {
			return;
		}

		header( 'X-AA-E2E-Product-Tabs-Fixture: ready' );
	}
);

add_filter(
	'render_block_data',
	static function ( array $parsed_block ) use ( $aggressive_apparel_e2e_query_value ): array {
		if ( 'aggressive-apparel/product-tabs' !== ( $parsed_block['blockName'] ?? '' ) ) {
			return $parsed_block;
		}

		$request_id = $aggressive_apparel_e2e_query_value( 'e2e_product_tabs_request' );
		if ( '' === $request_id ) {
			return $parsed_block;
		}

		$style        = sanitize_key( $aggressive_apparel_e2e_query_value( 'e2e_product_tabs_style' ) );
		$valid_styles = array( 'accordion', 'inline', 'modern-tabs', 'scrollspy' );

		if ( in_array( $style, $valid_styles, true ) ) {
			$parsed_block['attrs']['displayStyle'] = $style;
		}

		// Keep the fixture deterministic regardless of attributes stored in the
		// Single Product template.
		$parsed_block['attrs']['hideContentTitles']   = false;
		$parsed_block['attrs']['accordionExclusive'] = '1' === $aggressive_apparel_e2e_query_value( 'e2e_product_tabs_exclusive' );
		$parsed_block['attrs']['headingFontSize']     = $aggressive_apparel_e2e_query_value( 'e2e_product_tabs_heading_size' );
		$parsed_block['attrs']['headingColor']        = $aggressive_apparel_e2e_query_value( 'e2e_product_tabs_heading_color' );
		$parsed_block['attrs']['accentColor']         = $aggressive_apparel_e2e_query_value( 'e2e_product_tabs_accent_color' );

		return $parsed_block;
	}
);

unset( $aggressive_apparel_e2e_query_value );
