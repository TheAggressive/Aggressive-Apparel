<?php
/**
 * Test WooCommerce Block Detector
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\WooCommerce_Block_Asset_Bailout;
use Aggressive_Apparel\WooCommerce\WooCommerce_Block_Detector;
use Aggressive_Apparel\WooCommerce\WooCommerce_Block_Styles;
use Aggressive_Apparel\WooCommerce\WooCommerce_Interactivity_Defaults;
use WP_UnitTestCase;

/**
 * WooCommerce Block Detector Test Case
 */
class TestWooCommerceBlockDetector extends WP_UnitTestCase {

	/**
	 * Reset detector caches between tests.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		WooCommerce_Block_Detector::reset_caches_for_tests();
	}

	/**
	 * Clear detector caches after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		WooCommerce_Block_Detector::reset_caches_for_tests();
		parent::tearDown();
	}

	/**
	 * Detect WooCommerce blocks from a direct block comment.
	 */
	public function test_content_detects_direct_woocommerce_block(): void {
		$content = '<!-- wp:woocommerce/product-collection /-->';
		$this->assertTrue( WooCommerce_Block_Detector::content_has_woocommerce_blocks( $content ) );
	}

	/**
	 * Detect WooCommerce blocks nested inside layout blocks.
	 */
	public function test_content_detects_nested_woocommerce_block(): void {
		$content = '<!-- wp:group --><!-- wp:woocommerce/product-price /--><!-- /wp:group -->';
		$this->assertTrue( WooCommerce_Block_Detector::content_has_woocommerce_blocks( $content ) );
	}

	/**
	 * Plain content without WooCommerce blocks should not match.
	 */
	public function test_content_ignores_non_woocommerce_blocks(): void {
		$content = '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->';
		$this->assertFalse( WooCommerce_Block_Detector::content_has_woocommerce_blocks( $content ) );
	}

	/**
	 * Detect WooCommerce blocks inside reusable block references.
	 */
	public function test_content_detects_reusable_block_reference(): void {
		$reusable_id = wp_insert_post(
			array(
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_title'   => 'Reusable Woo Block',
				'post_content' => '<!-- wp:woocommerce/product-button /-->',
			),
			true
		);

		$this->assertIsInt( $reusable_id );

		$content = sprintf(
			'<!-- wp:block {"ref":%d} /-->',
			$reusable_id
		);

		$this->assertTrue( WooCommerce_Block_Detector::content_has_woocommerce_blocks( $content ) );
	}

	/**
	 * Custom pages with embedded WooCommerce blocks should need assets.
	 */
	public function test_page_with_woo_blocks_needs_assets(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}

		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => '<!-- wp:woocommerce/product-collection /-->',
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		$this->assertTrue( WooCommerce_Block_Detector::request_needs_assets() );
	}

	/**
	 * Blog posts inherit WooCommerce assets when the header includes a mini-cart.
	 *
	 * @return void
	 */
	public function test_blog_post_needs_assets_when_header_includes_mini_cart(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}

		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'post',
				'post_content' => '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->',
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		$this->assertTrue( WooCommerce_Block_Detector::request_needs_assets() );
	}

	/**
	 * The assets filter can force registration when auto-detection is insufficient.
	 */
	public function test_assets_filter_can_force_registration(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}

		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'post',
				'post_content' => '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->',
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		add_filter( 'aggressive_apparel_needs_woocommerce_assets', '__return_true' );

		$this->assertTrue( WooCommerce_Block_Detector::request_needs_assets() );
		$this->assertTrue( WooCommerce_Interactivity_Defaults::should_register() );
	}

	/**
	 * Detect WooCommerce blocks referenced via template-part blocks.
	 */
	public function test_content_detects_template_part_reference(): void {
		$part_dir = get_theme_file_path( 'parts' );

		if ( ! is_dir( $part_dir ) ) {
			$this->markTestSkipped( 'Theme parts directory is not available.' );
		}

		$part_file = $part_dir . '/test-woo-part.html';
		$part_slug = 'test-woo-part';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents(
			$part_file,
			'<!-- wp:woocommerce/mini-cart /-->'
		);

		$content = sprintf(
			'<!-- wp:template-part {"slug":"%s","theme":"%s"} /-->',
			$part_slug,
			get_stylesheet()
		);

		try {
			$this->assertTrue( WooCommerce_Block_Detector::content_has_woocommerce_blocks( $content ) );
		} finally {
			if ( is_readable( $part_file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink( $part_file );
			}
		}
	}

	/**
	 * Runtime bailout should load assets when a WooCommerce block renders.
	 */
	public function test_runtime_bailout_loads_assets_on_woocommerce_block(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}

		$bailout = new WooCommerce_Block_Asset_Bailout();
		$bailout->init();

		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'post',
				'post_content' => '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->',
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		$this->assertTrue( WooCommerce_Block_Detector::request_needs_assets() );

		// The bailout is wired to render_block at priority 1.
		$this->assertNotFalse( has_filter( 'render_block', array( $bailout, 'maybe_bailout' ) ) );

		// Invoke the callback directly rather than firing the global render_block
		// filter: doing the latter would also run core callbacks (e.g.
		// WP_Duotone::render_duotone_support, which requires the 3rd WP_Block arg)
		// and fatal on an incomplete 2-arg call. This keeps the test focused on
		// the bailout's own behaviour.
		$bailout->maybe_bailout(
			'<div class="wc-block-test"></div>',
			array(
				'blockName' => 'woocommerce/product-button',
				'attrs'     => array(),
			)
		);

		$this->assertTrue( wp_style_is( WooCommerce_Block_Styles::HANDLE, 'enqueued' ) );
	}

	/**
	 * Theme product templates containing WooCommerce blocks should be detected.
	 */
	public function test_theme_product_template_contains_woocommerce_blocks(): void {
		$template_path = get_theme_file_path( 'templates/single-product.html' );

		if ( ! is_readable( $template_path ) ) {
			$this->markTestSkipped( 'single-product.html is not available.' );
		}

		$content = file_get_contents( $template_path );

		$this->assertIsString( $content );
		$this->assertTrue( WooCommerce_Block_Detector::content_has_woocommerce_blocks( $content ) );
	}

	/**
	 * Persisted template scans should survive per-request memo resets.
	 */
	public function test_template_scan_uses_transient_cache(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}

		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'post',
				'post_content' => '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->',
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		$first_result = WooCommerce_Block_Detector::request_needs_assets();

		$version = (int) get_option( 'aggressive_apparel_wc_block_detector_version', 1 );
		$parts   = glob( get_theme_file_path( 'parts' ) . '/*.html' );
		$this->assertIsArray( $parts );

		$fingerprint_parts = array();
		foreach ( $parts as $file ) {
			if ( ! is_string( $file ) || ! is_readable( $file ) ) {
				continue;
			}

			$slug = basename( $file, '.html' );
			if ( '' !== $slug ) {
				$fingerprint_parts[] = $slug . ':' . (string) filemtime( $file ) . ':' . (string) filesize( $file );
			}
		}

		sort( $fingerprint_parts );

		$cache_key = sprintf(
			'aa_wc_detector_parts_%d_%s',
			$version,
			md5( get_stylesheet() . '|' . md5( implode( '|', $fingerprint_parts ) ) )
		);

		$this->assertNotFalse( get_transient( $cache_key ) );

		WooCommerce_Block_Detector::reset_runtime_state();
		$second_result = WooCommerce_Block_Detector::request_needs_assets();

		$this->assertSame( $first_result, $second_result );
		$this->assertNotFalse( get_transient( $cache_key ) );
	}

	/**
	 * Saving templates should bump the detector cache version.
	 */
	public function test_bump_cache_version_increments_option(): void {
		update_option( 'aggressive_apparel_wc_block_detector_version', 4, false );

		WooCommerce_Block_Detector::bump_cache_version();

		$this->assertSame( 5, (int) get_option( 'aggressive_apparel_wc_block_detector_version' ) );
	}
}
