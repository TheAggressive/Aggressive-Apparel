<?php
/**
 * Catalog URL Filters
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * The public catalog filter URL contract.
 *
 * `/shop/t-shirts/?color=black,red&size=m&min_price=20&stock=instock` is what
 * the product filters write (`buildFilterUrl()` in
 * src/interactivity/product-filters/url-state.ts) and what the archive query
 * applies on the server, so a filtered URL renders the same with or without
 * JavaScript.
 */
final class Catalog_Url_Filters {

	/**
	 * URL parameter → attribute taxonomy. Mirrors `ATTRIBUTE_FILTERS` in
	 * src/interactivity/product-filters/types.ts (a test keeps them in sync).
	 */
	public const ATTRIBUTE_PARAMS = array(
		'color'      => 'pa_color',
		'size'       => 'pa_size',
		'collection' => 'pa_collection',
	);

	/**
	 * Catalog_Filter_Set input for a query string, empty when it refines nothing.
	 *
	 * @param array<mixed> $query Unslashed query vars (`$_GET`).
	 * @return array<string, mixed>
	 */
	public static function from_query( array $query ): array {
		$string = static fn( string $key ): string => isset( $query[ $key ] ) && is_string( $query[ $key ] ) ? $query[ $key ] : '';

		$filters    = array();
		$categories = Catalog_Filter_Set::normalize_slugs( $string( 'cat' ) );
		if ( array() !== $categories ) {
			$filters['category'] = implode( ',', $categories );
		}

		foreach ( self::ATTRIBUTE_PARAMS as $param => $taxonomy ) {
			$slugs = Catalog_Filter_Set::normalize_slugs( $string( $param ) );
			if ( array() !== $slugs ) {
				$filters['attributes'][ $taxonomy ] = implode( ',', $slugs );
			}
		}

		$min_price = (float) $string( 'min_price' );
		$max_price = (float) $string( 'max_price' );
		if ( $min_price > 0 ) {
			$filters['min_price'] = $min_price;
		}
		if ( $max_price > 0 ) {
			$filters['max_price'] = $max_price;
		}

		$stock = sanitize_key( $string( 'stock' ) );
		if ( in_array( $stock, array( 'instock', 'outofstock', 'onbackorder' ), true ) ) {
			$filters['stock'] = $stock;
		}

		if ( '1' === $string( 'on_sale' ) ) {
			$filters['on_sale'] = true;
		}

		return $filters;
	}

	/**
	 * Filters for the current request.
	 *
	 * @return array<string, mixed>
	 */
	public static function from_request(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, read-only catalog filters.
		$query = wp_unslash( $_GET );
		$query = is_array( $query ) ? $query : array();

		// An audience landing page (/shop/mens/t-shirts/) is the archive with
		// that one Collection picked.
		$audience = Audience_Landing_Pages::current();
		if ( '' !== $audience ) {
			$query['collection'] = $audience;
		}

		return self::from_query( $query );
	}
}
