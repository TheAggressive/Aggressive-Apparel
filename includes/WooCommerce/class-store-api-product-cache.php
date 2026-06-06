<?php
/**
 * Store API Product Cache
 *
 * Transient caching and invalidation for per-product Store API extension
 * payloads. Cart and checkout endpoints are never handled here.
 *
 * @package Aggressive_Apparel
 * @since 1.79.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Core\Cache_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Store API Product Cache
 *
 * @since 1.79.0
 */
class Store_Api_Product_Cache {

	/**
	 * Option key storing a global cache version for product endpoint payloads.
	 *
	 * @var string
	 */
	private const VERSION_OPTION = 'aggressive_apparel_store_api_cache_version';

	/**
	 * Default TTL for cached product extension payloads.
	 *
	 * @var int
	 */
	public const DEFAULT_TTL = 900;

	/**
	 * Minimum allowed TTL in seconds.
	 *
	 * @var int
	 */
	public const MIN_TTL = 60;

	/**
	 * Namespaces registered with caching enabled.
	 *
	 * @var array<string, true>
	 */
	private static array $namespaces = array();

	/**
	 * Whether invalidation hooks have been registered.
	 *
	 * @var bool
	 */
	private static bool $hooks_registered = false;

	/**
	 * Wrap a product data callback with a transient read-through cache.
	 *
	 * @param string   $data_namespace Extension namespace.
	 * @param callable $data_callback  Original callback.
	 * @param int      $ttl            Cache TTL in seconds.
	 * @return callable
	 */
	public static function wrap_callback( string $data_namespace, callable $data_callback, int $ttl ): callable {
		self::track_namespace( $data_namespace );
		self::register_invalidation_hooks();

		return static function ( \WC_Product $product ) use ( $data_namespace, $data_callback, $ttl ): array {
			$product_id = (int) $product->get_id();

			if ( $product_id <= 0 ) {
				return self::normalize_payload( $data_callback( $product ) );
			}

			$cached = Cache_Helper::remember(
				self::cache_key( $data_namespace, $product_id ),
				$ttl,
				static function () use ( $data_callback, $product ): array {
					return self::normalize_payload( $data_callback( $product ) );
				},
				static function ( $value ): bool {
					return is_array( $value );
				}
			);

			return is_array( $cached ) ? $cached : array();
		};
	}

	/**
	 * Delete cached Store API product payloads for one product.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public static function invalidate_product( int $product_id ): void {
		if ( $product_id <= 0 ) {
			return;
		}

		foreach ( array_keys( self::$namespaces ) as $data_namespace ) {
			delete_transient( self::cache_key( $data_namespace, $product_id ) );
		}
	}

	/**
	 * Bump the global cache version so scheduled sales invalidate all entries.
	 *
	 * @return void
	 */
	public static function bump_version(): void {
		$version = (int) get_option( self::VERSION_OPTION, 1 );
		update_option( self::VERSION_OPTION, $version + 1, false );
	}

	/**
	 * Build a versioned transient key for a cached product extension payload.
	 *
	 * @param string $data_namespace Extension namespace.
	 * @param int    $product_id     Product ID.
	 * @return string
	 */
	public static function cache_key( string $data_namespace, int $product_id ): string {
		$version = (int) get_option( self::VERSION_OPTION, 1 );
		$slug    = sanitize_key( str_replace( array( '/', '\\' ), '_', $data_namespace ) );

		return sprintf( 'aa_sapi_%s_%d_v%d', $slug, $product_id, $version );
	}

	/**
	 * Remember a namespace so invalidation can clear its transients.
	 *
	 * @param string $data_namespace Extension namespace.
	 * @return void
	 */
	private static function track_namespace( string $data_namespace ): void {
		self::$namespaces[ $data_namespace ] = true;
	}

	/**
	 * Register hooks that keep cached product payloads fresh.
	 *
	 * @return void
	 */
	private static function register_invalidation_hooks(): void {
		if ( self::$hooks_registered ) {
			return;
		}

		self::$hooks_registered = true;

		add_action( 'save_post_product', array( self::class, 'handle_product_save' ) );
		add_action( 'woocommerce_update_product', array( self::class, 'handle_product_object_update' ), 10, 1 );
		add_action( 'woocommerce_product_set_stock', array( self::class, 'handle_product_object_update' ), 10, 1 );
		add_action( 'woocommerce_scheduled_sales', array( self::class, 'bump_version' ) );
		add_action(
			'save_post',
			static function ( int $post_id, \WP_Post $post ): void {
				if ( 'product_variation' !== $post->post_type ) {
					return;
				}

				$parent_id = (int) wp_get_post_parent_id( $post_id );
				if ( $parent_id > 0 ) {
					self::invalidate_product( $parent_id );
				}
			},
			10,
			2
		);
	}

	/**
	 * Invalidate cache when a product post is saved.
	 *
	 * @param int $post_id Product post ID.
	 * @return void
	 */
	public static function handle_product_save( int $post_id ): void {
		self::invalidate_product( $post_id );
	}

	/**
	 * Invalidate cache when WooCommerce updates a product object.
	 *
	 * @param int|\WC_Product $product Product ID or object.
	 * @return void
	 */
	public static function handle_product_object_update( $product ): void {
		if ( $product instanceof \WC_Product ) {
			self::invalidate_product( (int) $product->get_id() );
			return;
		}

		self::invalidate_product( (int) $product );
	}

	/**
	 * Ensure a Store API extension payload is always an array.
	 *
	 * @param mixed $payload Raw callback return value.
	 * @return array<string, mixed>
	 */
	private static function normalize_payload( $payload ): array {
		return is_array( $payload ) ? $payload : array();
	}
}
