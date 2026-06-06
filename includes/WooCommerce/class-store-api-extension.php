<?php
/**
 * Store API Extension Helper
 *
 * Thin wrapper for registering WooCommerce Store API `product` endpoint data.
 * Caching is delegated to Store_Api_Product_Cache when requested.
 *
 * @package Aggressive_Apparel
 * @since 1.78.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Store API Extension
 *
 * @since 1.78.0
 */
class Store_Api_Extension {

	/**
	 * Register additional data on the Store API `product` endpoint.
	 *
	 * No-ops gracefully when the Store API extensibility function is missing
	 * (older WooCommerce or WooCommerce inactive).
	 *
	 * @param string               $data_namespace Unique data namespace key.
	 * @param callable             $data_callback  Returns the per-product data array.
	 * @param array<string, mixed> $options        Optional settings: `cache` (bool), `ttl` (int seconds).
	 * @return void
	 */
	public static function register_product_data(
		string $data_namespace,
		callable $data_callback,
		array $options = array()
	): void {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		if ( ! empty( $options['cache'] ) ) {
			$ttl           = isset( $options['ttl'] )
				? max( Store_Api_Product_Cache::MIN_TTL, (int) $options['ttl'] )
				: Store_Api_Product_Cache::DEFAULT_TTL;
			$data_callback = Store_Api_Product_Cache::wrap_callback( $data_namespace, $data_callback, $ttl );
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => 'product',
				'namespace'       => $data_namespace,
				'data_callback'   => $data_callback,
				'schema_callback' => static function (): array {
					return array();
				},
				'schema_type'     => ARRAY_A,
			)
		);
	}
}
