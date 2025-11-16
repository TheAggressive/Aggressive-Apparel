<?php
/**
 * Performance Tests
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Performance;

use WP_UnitTestCase;

/**
 * Performance Test Case
 */
class TestPerformance extends WP_UnitTestCase {
	/**
	 * Test asset files are not too large
	 */
	public function test_css_file_sizes_reasonable() {
		$build_dir = get_template_directory() . '/build/styles';

		if ( ! is_dir( $build_dir ) ) {
			$this->markTestSkipped( 'Build directory not found. Run npm run build first.' );
		}

		$css_files = glob( $build_dir . '/*.css' );

		foreach ( $css_files as $file ) {
			$size    = filesize( $file );
			$size_kb = $size / 1024;

			// CSS files should be under 200KB (reasonable for production)
			$this->assertLessThan(
				200,
				$size_kb,
				basename( $file ) . " is {$size_kb}KB, should be under 200KB"
			);
		}
	}

	/**
	 * Test JavaScript files are not too large
	 */
	public function test_js_file_sizes_reasonable() {
		$build_dir = get_template_directory() . '/build/scripts';

		if ( ! is_dir( $build_dir ) ) {
			$this->markTestSkipped( 'Build directory not found. Run npm run build first.' );
		}

		$js_files = glob( $build_dir . '/*.js' );

		foreach ( $js_files as $file ) {
			$size    = filesize( $file );
			$size_kb = $size / 1024;

			// JS files should be under 150KB (reasonable for production)
			$this->assertLessThan(
				150,
				$size_kb,
				basename( $file ) . " is {$size_kb}KB, should be under 150KB"
			);
		}
	}

	/**
	 * Test query count is reasonable
	 */
	public function test_query_count_on_homepage() {
		global $wpdb;

		// Reset query count
		$wpdb->num_queries = 0;

		// Simulate homepage load
		$this->go_to( home_url( '/' ) );
		do_action( 'wp' );

		// Should have reasonable number of queries (less than 50 for homepage)
		$this->assertLessThan(
			50,
			$wpdb->num_queries,
			"Homepage generated {$wpdb->num_queries} queries, should be under 50"
		);
	}

	/**
	 * Test no duplicate scripts enqueued
	 */
	public function test_no_duplicate_scripts() {
		global $wp_scripts;

		do_action( 'wp_enqueue_scripts' );

		$handles = array();
		foreach ( $wp_scripts->queue as $handle ) {
			$this->assertNotContains(
				$handle,
				$handles,
				"Script handle '{$handle}' is enqueued multiple times"
			);
			$handles[] = $handle;
		}
	}

	/**
	 * Test no duplicate styles enqueued
	 */
	public function test_no_duplicate_styles() {
		global $wp_styles;

		do_action( 'wp_enqueue_scripts' );

		$handles = array();
		foreach ( $wp_styles->queue as $handle ) {
			$this->assertNotContains(
				$handle,
				$handles,
				"Style handle '{$handle}' is enqueued multiple times"
			);
			$handles[] = $handle;
		}
	}

	/**
	 * Test Bootstrap singleton access performance
	 */
	public function test_bootstrap_singleton_access_performance() {
		$start = microtime( true );

		// Test Bootstrap singleton access (this happens on theme load)
		$bootstrap = \Aggressive_Apparel\Bootstrap::get_instance();
		// Note: Bootstrap singleton ensures only one instance and proper initialization

		$end      = microtime( true );
		$duration = ( $end - $start ) * 1000; // Convert to milliseconds

		$this->assertInstanceOf( 'Aggressive_Apparel\Bootstrap', $bootstrap );
		$this->assertLessThan(
			10,
			$duration,
			"Bootstrap singleton access took {$duration}ms, should be under 10ms"
		);
	}

	/**
	 * Test theme.json parsing is cached
	 */
	public function test_theme_json_exists_and_valid() {
		$theme_json_path = get_template_directory() . '/theme.json';

		$start      = microtime( true );
		$theme_json = json_decode( file_get_contents( $theme_json_path ), true );
		$end        = microtime( true );

		$duration = ( $end - $start ) * 1000;

		$this->assertIsArray( $theme_json );
		$this->assertLessThan(
			50,
			$duration,
			"theme.json parsing took {$duration}ms, should be under 50ms"
		);
	}

	/**
	 * Test memory usage is reasonable
	 */
	public function test_memory_usage_reasonable() {
		$memory_before = memory_get_usage();

		// Test basic theme class loading (avoid triggering WooCommerce template conflicts)
		$bootstrap     = \Aggressive_Apparel\Bootstrap::get_instance();
		$theme_support = new \Aggressive_Apparel\Core\Theme_Support();

		$memory_after   = memory_get_usage();
		$memory_used_mb = ( $memory_after - $memory_before ) / 1024 / 1024;

		// Basic class loading should use less than 2MB
		$this->assertLessThan(
			2,
			$memory_used_mb,
			"Basic theme classes used {$memory_used_mb}MB, should be under 2MB"
		);
	}
}
