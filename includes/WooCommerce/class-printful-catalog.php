<?php
/**
 * Printful Catalog
 *
 * Reads Printful's public product catalog (API v1, no key needed): the
 * product list for search, one product's details, and its size tables.
 * Responses are cached, so browsing the import screen doesn't hit Printful
 * on every keystroke, and nothing here writes anywhere.
 *
 * @link https://developers.printful.com/docs/#tag/Catalog-API
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
 * Printful catalog reads.
 *
 * @phpstan-type Summary array{id: int, title: string, brand: string, model: string, image: string, group: string, popular: bool}
 */
final class Printful_Catalog {

	/**
	 * API base.
	 *
	 * @var string
	 */
	private const API = 'https://api.printful.com';

	/**
	 * Cache lifetime: the catalog changes rarely.
	 *
	 * @var int
	 */
	private const TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Top-level catalog groups that hold garments with size charts.
	 *
	 * @var list<string>
	 */
	public const GROUPS = array( "Men's clothing", "Women's clothing", "Kids' & youth clothing", 'Hats' );

	/**
	 * Blank models most stores start with, shown first before a search.
	 *
	 * @var list<string>
	 */
	private const POPULAR = array( '3001', '64000', '5000', '18000', '18500', '1717', '1566', '3719', '3739', '3600', 'SS4500', '6245CM', '112' );

	/**
	 * Products matching a search, apparel only, best matches first.
	 *
	 * @param string $search Words to find in the title, brand, or model.
	 * @param string $group  Only this top-level group ('' for all apparel).
	 * @return list<Summary>|\WP_Error
	 */
	public static function search( string $search, string $group = '' ) {
		$all = self::products();
		if ( is_wp_error( $all ) ) {
			return $all;
		}

		$split  = preg_split( '/\s+/', strtolower( trim( $search ) ) );
		$words  = array_filter( false !== $split ? $split : array() );
		$scored = array();
		$rank   = array_flip( array_map( 'strtolower', self::POPULAR ) );
		foreach ( $all as $product ) {
			if ( '' !== $group && $product['group'] !== $group ) {
				continue;
			}
			$haystack = strtolower( $product['title'] . ' ' . $product['brand'] . ' ' . $product['model'] );
			$score    = 0;
			foreach ( $words as $word ) {
				if ( ! str_contains( $haystack, $word ) ) {
					continue 2;
				}
				$score += str_contains( strtolower( $product['model'] ), $word ) ? 2 : 1;
			}
			// Before a search, popular blanks lead, in the list's order.
			$popular            = isset( $rank[ strtolower( $product['model'] ) ] );
			$product['popular'] = array() === $words && $popular;
			$scored[]           = array( array() === $words ? ( $popular ? 1000 - $rank[ strtolower( $product['model'] ) ] : 0 ) : $score, $product );
		}
		usort( $scored, static fn( array $a, array $b ): int => $b[0] <=> $a[0] );

		return array_values( array_map( static fn( array $pair ): array => $pair[1], array_slice( $scored, 0, 40 ) ) );
	}

	/**
	 * Every apparel product in the catalog (not discontinued), cached.
	 *
	 * @return list<Summary>|\WP_Error
	 */
	public static function products() {
		$cached = get_transient( 'aa_printful_products' );
		if ( is_array( $cached ) ) {
			return array_values( array_map( array( self::class, 'summary' ), array_filter( $cached, 'is_array' ) ) );
		}

		$categories = self::get( '/categories' );
		$products   = is_wp_error( $categories ) ? $categories : self::get( '/products' );
		if ( is_wp_error( $products ) ) {
			return $products;
		}

		$groups = self::groups( $categories );
		$list   = array();
		foreach ( is_array( $products ) ? $products : array() as $product ) {
			$group = $groups[ (int) ( $product['main_category_id'] ?? 0 ) ] ?? '';
			if ( ! is_array( $product ) || ! in_array( $group, self::GROUPS, true ) || ! empty( $product['is_discontinued'] ) ) {
				continue;
			}
			$list[] = self::summary( array( 'group' => $group ) + $product );
		}

		set_transient( 'aa_printful_products', $list, self::TTL );

		return $list;
	}

	/**
	 * One product's details (title, brand, model, description), cached.
	 *
	 * @param int $id Printful catalog product ID.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function product( int $id ) {
		$result = self::cached( 'aa_printful_product_' . $id, '/products/' . $id );

		return is_wp_error( $result ) ? $result : ( is_array( $result['product'] ?? null ) ? $result['product'] : array() );
	}

	/**
	 * One product's size tables, cached.
	 *
	 * @param int $id Printful catalog product ID.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function sizes( int $id ) {
		$result = self::cached( 'aa_printful_sizes_' . $id, '/products/' . $id . '/sizes' );

		return is_wp_error( $result ) ? $result : ( is_array( $result ) ? $result : array() );
	}

	/**
	 * A cached GET.
	 *
	 * @param string $key  Transient key.
	 * @param string $path API path.
	 * @return mixed|\WP_Error
	 */
	private static function cached( string $key, string $path ) {
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return $cached;
		}

		$result = self::get( $path );
		if ( ! is_wp_error( $result ) ) {
			set_transient( $key, $result, self::TTL );
		}

		return $result;
	}

	/**
	 * GET an API path and return its `result`.
	 *
	 * @param string $path API path.
	 * @return mixed|\WP_Error
	 */
	private static function get( string $path ) {
		$response = wp_safe_remote_get(
			self::API . $path,
			array(
				// Admin-only and cached for hours; the full catalog is ~250 KB.
				'timeout' => 10, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code || ! is_array( $body ) || ! array_key_exists( 'result', $body ) ) {
			return new \WP_Error(
				'aa_printful_unavailable',
				404 === $code
					? __( 'Printful has no size chart for that product.', 'aggressive-apparel' )
					/* translators: %d: HTTP status code. */
					: sprintf( __( 'Printful didn’t answer (status %d). Try again in a moment.', 'aggressive-apparel' ), $code ),
				array( 'status' => 404 === $code ? 404 : 502 )
			);
		}

		return $body['result'];
	}

	/**
	 * A product as a search result.
	 *
	 * @param array<mixed> $product Catalog product (or a cached summary).
	 * @return Summary
	 */
	private static function summary( array $product ): array {
		return array(
			'id'      => (int) ( $product['id'] ?? 0 ),
			'title'   => self::text( $product['title'] ?? '' ),
			'brand'   => self::text( $product['brand'] ?? '' ),
			'model'   => self::text( $product['model'] ?? '' ),
			'image'   => esc_url_raw( (string) ( $product['image'] ?? '' ) ),
			'group'   => self::text( $product['group'] ?? '' ),
			'popular' => false,
		);
	}

	/**
	 * Each category's top-level group name, by category ID.
	 *
	 * @param mixed $result The categories response.
	 * @return array<int, string>
	 */
	private static function groups( $result ): array {
		$categories = is_array( $result ) && is_array( $result['categories'] ?? null ) ? $result['categories'] : array();
		$byid       = array();
		foreach ( $categories as $category ) {
			if ( is_array( $category ) ) {
				$byid[ (int) ( $category['id'] ?? 0 ) ] = $category;
			}
		}

		$groups = array();
		foreach ( array_keys( $byid ) as $id ) {
			$current = $byid[ $id ];
			// Walk up to the top (guarding against a malformed loop).
			for ( $depth = 0; $depth < 10 && (int) ( $current['parent_id'] ?? 0 ) > 0 && isset( $byid[ (int) $current['parent_id'] ] ); $depth++ ) {
				$current = $byid[ (int) $current['parent_id'] ];
			}
			$groups[ $id ] = self::text( $current['title'] ?? '' );
		}

		return $groups;
	}

	/**
	 * Plain text from an API string.
	 *
	 * @param mixed $value Value.
	 */
	private static function text( $value ): string {
		return is_scalar( $value ) ? trim( sanitize_text_field( (string) $value ) ) : '';
	}
}
