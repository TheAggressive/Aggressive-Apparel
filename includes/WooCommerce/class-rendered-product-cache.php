<?php
/**
 * Rendered Product Cache
 *
 * @package Aggressive_Apparel
 * @since 1.66.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Caches anonymous rendered Product Collection responses.
 *
 * @since 1.66.0
 */
final class Rendered_Product_Cache {

	/** Persistent object-cache group. */
	private const GROUP = 'aggressive-apparel-rendered-products';

	/** Maximum orphaned regeneration-lock lifetime. */
	private const LOCK_TTL = 30;

	/**
	 * Shared catalog version provider.
	 *
	 * @var Catalog_Cache_Version
	 */
	private Catalog_Cache_Version $version;

	/**
	 * Constructor.
	 *
	 * @param Catalog_Cache_Version $version Shared catalog cache version.
	 */
	public function __construct( Catalog_Cache_Version $version ) {
		$this->version = $version;
	}

	/**
	 * Build a privacy-safe cache key for a public rendered fragment.
	 *
	 * @param array<string, mixed> $query_args       Final query arguments.
	 * @param array<string, mixed> $collection_block Parsed Product Collection block.
	 * @param bool                 $with_facets      Whether facets are included.
	 * @return string Cache key, or an empty string when caching is unsafe.
	 */
	public function key( array $query_args, array $collection_block, bool $with_facets ): string {
		$enabled = ! is_user_logged_in()
			&& function_exists( 'wp_using_ext_object_cache' )
			&& wp_using_ext_object_cache()
			&& '' === (string) apply_filters( 'aggressive_apparel_dynamic_style_nonce', '' );

		/**
		 * Filters whether anonymous rendered Product Collection fragments are cached.
		 *
		 * @param bool                 $enabled          Default eligibility.
		 * @param array<string, mixed> $query_args       Final query arguments.
		 * @param array<string, mixed> $collection_block Parsed collection block.
		 */
		$enabled = (bool) apply_filters( 'aggressive_apparel_rendered_products_cache_enabled', $enabled, $query_args, $collection_block );
		if ( ! $enabled ) {
			return '';
		}

		$location = $this->taxable_location();
		$context  = array(
			'locale'       => determine_locale(),
			'currency'     => function_exists( 'get_woocommerce_currency' ) ? \get_woocommerce_currency() : '',
			'country'      => $location['country'],
			'state'        => $location['state'],
			'postcode'     => $location['postcode'],
			'city'         => $location['city'],
			'prices_taxed' => function_exists( 'wc_prices_include_tax' ) ? \wc_prices_include_tax() : false,
		);

		/**
		 * Filters request context that can change anonymous product-card markup.
		 *
		 * Multi-currency, membership, or geo-pricing integrations should add their
		 * public pricing discriminator here, or disable fragment caching above.
		 *
		 * @param array<string, mixed> $context Pricing/locale cache context.
		 */
		$context = (array) apply_filters( 'aggressive_apparel_rendered_products_cache_context', $context );
		$payload = array(
			'v'           => $this->version->current(),
			'theme'       => AGGRESSIVE_APPAREL_VERSION,
			'wp'          => get_bloginfo( 'version' ),
			'wc'          => defined( 'WC_VERSION' ) ? WC_VERSION : '',
			'query'       => $query_args,
			'collection'  => $collection_block,
			'with_facets' => $with_facets,
			'context'     => $context,
		);

		$encoded = wp_json_encode( $payload );
		if ( ! is_string( $encoded ) ) {
			// A third-party context filter may return a non-serializable value.
			// Disable caching rather than collapsing it into a shared empty hash.
			return '';
		}

		return hash( 'sha256', $encoded );
	}

	/**
	 * Resolve the location that can affect public product-card tax display.
	 *
	 * WooCommerce does not guarantee that its customer object exists during an
	 * anonymous REST request. Use the native configured/geolocated default when
	 * the session customer has not been initialized.
	 *
	 * @return array{country:string,state:string,postcode:string,city:string}
	 */
	private function taxable_location(): array {
		$location    = array(
			'country'  => '',
			'state'    => '',
			'postcode' => '',
			'city'     => '',
		);
		$woocommerce = function_exists( 'WC' ) ? \WC() : null;
		$customer    = is_object( $woocommerce ) ? $woocommerce->customer : null;

		if ( $customer instanceof \WC_Customer ) {
			$taxable              = (array) $customer->get_taxable_address();
			$location['country']  = sanitize_text_field( (string) ( $taxable[0] ?? '' ) );
			$location['state']    = sanitize_text_field( (string) ( $taxable[1] ?? '' ) );
			$location['postcode'] = sanitize_text_field( (string) ( $taxable[2] ?? '' ) );
			$location['city']     = sanitize_text_field( (string) ( $taxable[3] ?? '' ) );
			return $location;
		}

		if ( function_exists( 'wc_get_customer_default_location' ) ) {
			$default             = (array) \wc_get_customer_default_location();
			$location['country'] = sanitize_text_field( (string) ( $default['country'] ?? '' ) );
			$location['state']   = sanitize_text_field( (string) ( $default['state'] ?? '' ) );
		}

		return $location;
	}

	/**
	 * Return a fresh response when one exists.
	 *
	 * @param string $key Cache key.
	 * @return ?array<string, mixed>
	 */
	public function fresh( string $key ): ?array {
		$cached = '' !== $key ? wp_cache_get( $key, self::GROUP ) : false;
		return is_array( $cached ) ? $cached : null;
	}

	/**
	 * Acquire the regeneration lock for a cache key.
	 *
	 * @param string $key Cache key.
	 * @return bool
	 */
	public function acquire_lock( string $key ): bool {
		// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined -- Locks need a short lease so a fatal cannot block cold-cache regeneration for five minutes.
		return '' !== $key && wp_cache_add( $key . ':lock', 1, self::GROUP, self::LOCK_TTL );
	}

	/**
	 * Return a stale response while another request owns the render lock.
	 *
	 * @param string $key Cache key.
	 * @return ?array<string, mixed>
	 */
	public function stale( string $key ): ?array {
		$cached = '' !== $key ? wp_cache_get( $key . ':stale', self::GROUP ) : false;
		return is_array( $cached ) ? $cached : null;
	}

	/**
	 * Store fresh and stale response copies and release an owned lock.
	 *
	 * @param string               $key      Cache key (empty when disabled).
	 * @param array<string, mixed> $data     REST response data.
	 * @param bool                 $has_lock Whether this request owns the lock.
	 * @return void
	 */
	public function store( string $key, array $data, bool $has_lock ): void {
		if ( '' === $key ) {
			return;
		}

		wp_cache_set( $key, $data, self::GROUP, 300 );
		wp_cache_set( $key . ':stale', $data, self::GROUP, 3600 );
		$this->release_lock( $key, $has_lock );
	}

	/**
	 * Release a regeneration lock owned by the current request.
	 *
	 * @param string $key      Cache key.
	 * @param bool   $has_lock Whether this request acquired the lock.
	 * @return void
	 */
	public function release_lock( string $key, bool $has_lock ): void {
		if ( '' !== $key && $has_lock ) {
			wp_cache_delete( $key . ':lock', self::GROUP );
		}
	}
}
