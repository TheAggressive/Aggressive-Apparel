<?php
/**
 * Store API product cache key tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Store_Api_Product_Cache;
use WP_UnitTestCase;

/**
 * The cached Store API product payload can embed currency- and locale-specific
 * values, so its cache key must vary by both or a multi-currency switcher would
 * serve the priming request's currency to everyone.
 */
class TestStoreApiProductCache extends WP_UnitTestCase {

	/**
	 * Reset any request-context filters between tests.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_all_filters( 'woocommerce_currency' );
		remove_all_filters( 'determine_locale' );

		parent::tearDown();
	}

	/**
	 * A different active currency produces a different cache key.
	 *
	 * @return void
	 */
	public function test_cache_key_varies_by_currency(): void {
		$usd = Store_Api_Product_Cache::cache_key( 'aggressive-apparel/variation-prices', 42 );

		add_filter( 'woocommerce_currency', static fn(): string => 'EUR' );
		$eur = Store_Api_Product_Cache::cache_key( 'aggressive-apparel/variation-prices', 42 );

		$this->assertNotSame( $usd, $eur, 'Cache key must differ when the active currency changes.' );
	}

	/**
	 * A different locale produces a different cache key.
	 *
	 * @return void
	 */
	public function test_cache_key_varies_by_locale(): void {
		$default = Store_Api_Product_Cache::cache_key( 'aggressive-apparel/variation-prices', 42 );

		add_filter( 'determine_locale', static fn(): string => 'fr_FR' );
		$french = Store_Api_Product_Cache::cache_key( 'aggressive-apparel/variation-prices', 42 );

		$this->assertNotSame( $default, $french, 'Cache key must differ when the locale changes.' );
	}

	/**
	 * Same product, namespace, currency, and locale are stable across calls.
	 *
	 * @return void
	 */
	public function test_cache_key_is_stable_for_identical_context(): void {
		$this->assertSame(
			Store_Api_Product_Cache::cache_key( 'aggressive-apparel/variation-prices', 42 ),
			Store_Api_Product_Cache::cache_key( 'aggressive-apparel/variation-prices', 42 )
		);
	}
}
