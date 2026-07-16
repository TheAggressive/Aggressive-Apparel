<?php
/**
 * Catalog Cursor
 *
 * Opaque keyset tokens for Product Collection pagination.
 *
 * @package Aggressive_Apparel
 * @since 1.90.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Encodes and decodes stable catalog pagination cursors.
 *
 * @since 1.90.0
 */
final class Catalog_Cursor {

	/**
	 * Supported public orderby values.
	 *
	 * @var list<string>
	 */
	public const ORDERBY_VALUES = array(
		'menu_order',
		'date',
		'popularity',
		'rating',
		'price',
		'price-desc',
		'title-asc',
		'title-desc',
	);

	/**
	 * Encode a validated cursor payload.
	 *
	 * @param array<string, mixed> $payload Cursor fields including `v` and `id`.
	 * @return string Opaque token, or empty string when encoding fails.
	 */
	public function encode( array $payload ): string {
		$json = wp_json_encode( $payload );
		if ( false === $json ) {
			return '';
		}

		return rtrim( strtr( base64_encode( $json ), '+/', '-_' ), '=' );
	}

	/**
	 * Read the orderby version embedded in a cursor without full validation.
	 *
	 * Used when REST callers omit `orderby` so the schema default cannot remount
	 * a date (or other) SSR cursor as menu_order.
	 *
	 * @param string $token Opaque cursor token.
	 * @return string|null Orderby slug, or null when the token is unreadable.
	 */
	public function peek_orderby( string $token ): ?string {
		$data = $this->decode_payload( $token );
		if ( null === $data ) {
			return null;
		}

		$version = sanitize_key( (string) ( $data['v'] ?? '' ) );
		if ( ! in_array( $version, self::ORDERBY_VALUES, true ) ) {
			return null;
		}

		return $version;
	}

	/**
	 * Decode and validate a cursor for the active sort.
	 *
	 * @param string $token   Opaque cursor token.
	 * @param string $orderby Expected orderby value.
	 * @return array<string, mixed>|null
	 */
	public function decode( string $token, string $orderby ): ?array {
		$data = $this->decode_payload( $token );
		if ( null === $data ) {
			return null;
		}

		$version = sanitize_key( (string) ( $data['v'] ?? '' ) );
		$id      = absint( $data['id'] ?? 0 );
		if ( $version !== $orderby || $id < 1 || ! in_array( $orderby, self::ORDERBY_VALUES, true ) ) {
			return null;
		}

		$payload = array(
			'v'  => $version,
			'id' => $id,
		);

		switch ( $orderby ) {
			case 'date':
				$date = sanitize_text_field( (string) ( $data['d'] ?? '' ) );
				if ( ! $this->is_mysql_datetime( $date ) ) {
					return null;
				}
				$payload['d'] = $date;
				break;

			case 'price':
			case 'price-desc':
				if ( ! is_numeric( $data['p'] ?? null ) ) {
					return null;
				}
				$payload['p'] = (float) $data['p'];
				break;

			case 'popularity':
				$payload['s'] = absint( $data['s'] ?? -1 );
				if ( $payload['s'] < 0 ) {
					return null;
				}
				break;

			case 'rating':
				if ( ! is_numeric( $data['r'] ?? null ) ) {
					return null;
				}
				$payload['r'] = (float) $data['r'];
				break;

			case 'title-asc':
			case 'title-desc':
				$title = sanitize_text_field( (string) ( $data['t'] ?? '' ) );
				if ( '' === $title && ! array_key_exists( 't', $data ) ) {
					return null;
				}
				$payload['t'] = $title;
				break;

			case 'menu_order':
				if ( ! isset( $data['m'] ) || ! is_numeric( $data['m'] ) ) {
					return null;
				}
				$title = sanitize_text_field( (string) ( $data['t'] ?? '' ) );
				if ( '' === $title && ! array_key_exists( 't', $data ) ) {
					return null;
				}
				$payload['m'] = (int) $data['m'];
				$payload['t'] = $title;
				break;

			default:
				return null;
		}

		return $payload;
	}

	/**
	 * Base64url-decode a cursor token to its raw payload array.
	 *
	 * @param string $token Opaque cursor token.
	 * @return array<string, mixed>|null
	 */
	private function decode_payload( string $token ): ?array {
		$token = trim( $token );
		if ( '' === $token || strlen( $token ) > 512 ) {
			return null;
		}

		$padded = strtr( $token, '-_', '+/' );
		$pad    = strlen( $padded ) % 4;
		if ( $pad > 0 ) {
			$padded .= str_repeat( '=', 4 - $pad );
		}

		$json = base64_decode( $padded, true );
		if ( ! is_string( $json ) || '' === $json ) {
			return null;
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return null;
		}

		return $data;
	}

	/**
	 * Build a cursor token from the last product in a result page.
	 *
	 * @param \WP_Post $post    Last product post.
	 * @param string   $orderby Active orderby value.
	 * @return string
	 */
	public function from_post( \WP_Post $post, string $orderby ): string {
		$orderby = sanitize_key( $orderby );
		if ( ! in_array( $orderby, self::ORDERBY_VALUES, true ) ) {
			return '';
		}

		$payload = array(
			'v'  => $orderby,
			'id' => (int) $post->ID,
		);

		switch ( $orderby ) {
			case 'date':
				$payload['d'] = (string) $post->post_date;
				break;

			case 'price':
			case 'price-desc':
				$payload['p'] = $this->lookup_float( (int) $post->ID, 'min_price' );
				break;

			case 'popularity':
				$payload['s'] = (int) $this->lookup_float( (int) $post->ID, 'total_sales' );
				break;

			case 'rating':
				$payload['r'] = $this->lookup_float( (int) $post->ID, 'average_rating' );
				break;

			case 'title-asc':
			case 'title-desc':
				$payload['t'] = (string) $post->post_title;
				break;

			case 'menu_order':
				$payload['m'] = (int) $post->menu_order;
				$payload['t'] = (string) $post->post_title;
				break;
		}

		return $this->encode( $payload );
	}

	/**
	 * Whether a string looks like a MySQL datetime.
	 *
	 * @param string $value Candidate datetime.
	 * @return bool
	 */
	private function is_mysql_datetime( string $value ): bool {
		return (bool) preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value );
	}

	/**
	 * Read a numeric column from the WooCommerce product meta lookup table.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $column     Lookup column.
	 * @return float
	 */
	private function lookup_float( int $product_id, string $column ): float {
		global $wpdb;

		if ( $product_id < 1 ) {
			return 0.0;
		}

		$allowed = array( 'min_price', 'total_sales', 'average_rating' );
		if ( ! in_array( $column, $allowed, true ) ) {
			return 0.0;
		}

		$cache_key = "aa_cursor_lookup_{$product_id}_{$column}";
		$cached    = wp_cache_get( $cache_key, 'aggressive_apparel' );
		if ( false !== $cached ) {
			return is_numeric( $cached ) ? (float) $cached : 0.0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Indexed WC lookup for keyset cursor seeds; result cached in the object cache below.
		$value = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT %i FROM %i WHERE product_id = %d',
				$column,
				$wpdb->wc_product_meta_lookup,
				$product_id
			)
		);

		$result = is_numeric( $value ) ? (float) $value : 0.0;
		wp_cache_set( $cache_key, $result, 'aggressive_apparel', MINUTE_IN_SECONDS );

		return $result;
	}
}
