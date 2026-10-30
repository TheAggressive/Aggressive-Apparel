<?php
/**
 * Cache Health
 *
 * Surfaces the theme's caching prerequisites as a Site Health test so an
 * operator sees, in an admin surface they already check, when the catalog
 * cache layer is silently inactive.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Reports whether a persistent object cache backs the theme's catalog caches.
 *
 * Facet counts, search results and rate-limit counters are cached as
 * transients; without a persistent object cache they live in `wp_options`
 * and every anonymous request writes to the database. Product grids (first
 * load, Load More, filters) are full pages and rely on the page cache.
 */
final class Cache_Health {

	/** Site Health test identifier. */
	private const TEST_ID = 'aggressive_apparel_object_cache';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'site_status_tests', array( $this, 'add_tests' ) );
	}

	/**
	 * Register the object-cache test with Site Health.
	 *
	 * @param array<string, mixed> $tests Existing Site Health tests.
	 * @return array<string, mixed>
	 */
	public function add_tests( array $tests ): array {
		if ( ! is_array( $tests['direct'] ?? null ) ) {
			$tests['direct'] = array();
		}

		$tests['direct'][ self::TEST_ID ] = array(
			'label' => __( 'Aggressive Apparel catalog cache', 'aggressive-apparel' ),
			'test'  => array( $this, 'test_object_cache' ),
		);

		return $tests;
	}

	/**
	 * Whether a persistent (external) object cache is active.
	 *
	 * @return bool
	 */
	private function has_object_cache(): bool {
		return wp_using_ext_object_cache();
	}

	/**
	 * Run the Site Health test.
	 *
	 * @return array<string, mixed>
	 */
	public function test_object_cache(): array {
		$badge = array(
			'label' => __( 'Performance', 'aggressive-apparel' ),
			'color' => 'blue',
		);

		if ( $this->has_object_cache() ) {
			return array(
				'label'       => __( 'Persistent object cache is active', 'aggressive-apparel' ),
				'status'      => 'good',
				'badge'       => $badge,
				'description' => '<p>' . esc_html__(
					'Filter availability, search results and rate-limit counters are cached in the persistent object cache instead of the database.',
					'aggressive-apparel'
				) . '</p>',
				'test'        => self::TEST_ID,
			);
		}

		$badge['color'] = 'orange';

		return array(
			'label'       => __( 'No persistent object cache for the catalog', 'aggressive-apparel' ),
			'status'      => 'recommended',
			'badge'       => $badge,
			'description' => '<p>' . esc_html__(
				'Aggressive Apparel caches filter availability, search results and rate-limit counters as transients. Without a persistent object cache they are stored in the database, so busy catalogs write to it on anonymous requests.',
				'aggressive-apparel'
			) . '</p><p>' . esc_html__(
				'How to fix: install and enable a persistent object cache backed by Redis or Memcached. Most managed WordPress hosts offer this as a one-click add-on; self-hosted sites install the Redis/Memcached server plus a drop-in plugin (for example Redis Object Cache), which writes wp-content/object-cache.php. Additionally place a full-page cache (Varnish, Batcache, or a host/CDN page cache) in front of anonymous archive traffic, configured to bypass cart, checkout, and my-account and to vary on the WooCommerce session cookies — catalog pages, including those Load More fetches, rely on it.',
				'aggressive-apparel'
			) . '</p>',
			'actions'     => sprintf(
				'<p><a href="%s" target="_blank" rel="noopener noreferrer">%s<span class="screen-reader-text"> %s</span> <span aria-hidden="true" class="dashicons dashicons-external"></span></a></p>',
				esc_url( 'https://developer.wordpress.org/advanced-administration/performance/optimization/' ),
				esc_html__( 'Learn about object caching', 'aggressive-apparel' ),
				/* translators: Accessibility text. */
				esc_html__( '(opens in a new tab)', 'aggressive-apparel' )
			),
			'test'        => self::TEST_ID,
		);
	}
}
