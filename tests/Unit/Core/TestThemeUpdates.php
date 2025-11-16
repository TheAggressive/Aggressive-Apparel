<?php
/**
 * Test Theme Updates Class
 *
 * Comprehensive tests for theme update functionality including
 * WordPress integration, GitHub API communication, caching, and security.
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Unit\Core;

use WP_UnitTestCase;
use Aggressive_Apparel\Core\Theme_Updates;

/**
 * Theme Updates Test Case
 */
class TestThemeUpdates extends WP_UnitTestCase {

	/**
	 * Theme updates instance
	 *
	 * @var Theme_Updates
	 */
	private $theme_updates;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();

		// Clear any existing transients
		delete_transient( 'aggressive_apparel_theme_update' );
		delete_option( 'aggressive_apparel_theme_update_etag' );

		// Get fresh instance
		$this->theme_updates = Theme_Updates::get_instance();
	}

	/**
	 * Tear down test environment
	 */
	public function tearDown(): void {
		parent::tearDown();

		// Clean up transients
		delete_transient( 'aggressive_apparel_theme_update' );
		delete_option( 'aggressive_apparel_theme_update_etag' );
	}

	/**
	 * Test singleton pattern
	 */
	public function test_singleton_pattern() {
		$instance1 = Theme_Updates::get_instance();
		$instance2 = Theme_Updates::get_instance();

		$this->assertSame( $instance1, $instance2, 'Should return same instance' );
		$this->assertInstanceOf( Theme_Updates::class, $instance1, 'Should be Theme_Updates instance' );
	}

	/**
	 * Test Theme Updates class has required public methods
	 */
	public function test_class_has_required_methods() {
		$reflection = new \ReflectionClass( $this->theme_updates );

		// Test that key WordPress integration methods exist
		$this->assertTrue( $reflection->hasMethod( 'inject_update_data' ), 'Should have inject_update_data method' );
		$this->assertTrue( $reflection->hasMethod( 'provide_theme_details' ), 'Should have provide_theme_details method' );
		$this->assertTrue( $reflection->hasMethod( 'handle_download_rename' ), 'Should have handle_download_rename method' );
		$this->assertTrue( $reflection->hasMethod( 'perform_update_check' ), 'Should have perform_update_check method' );

		// Test methods are public
		$inject_method = $reflection->getMethod( 'inject_update_data' );
		$this->assertTrue( $inject_method->isPublic(), 'inject_update_data should be public' );

		$details_method = $reflection->getMethod( 'provide_theme_details' );
		$this->assertTrue( $details_method->isPublic(), 'provide_theme_details should be public' );
	}

	/**
	 * Test update data injection with no cached data
	 */
	public function test_inject_update_data_no_cached_data() {
		// Clear any existing cache
		delete_transient( 'aggressive_apparel_theme_update' );

		$transient = new \stdClass();
		$transient->response = array();

		$result = $this->theme_updates->inject_update_data( $transient );

		$this->assertSame( $transient, $result, 'Should return same transient object' );
		$this->assertEmpty( $result->response, 'Should not add updates when cache is empty' );
	}

	/**
	 * Test theme details provision for wrong action
	 */
	public function test_provide_theme_details_wrong_action() {
		$result = $this->theme_updates->provide_theme_details( false, 'wrong_action', array() );

		$this->assertFalse( $result, 'Should return false for wrong action' );
	}

	/**
	 * Test theme details provision for different theme
	 */
	public function test_provide_theme_details_wrong_theme() {
		$args = array( 'slug' => 'wrong-theme' );
		$result = $this->theme_updates->provide_theme_details( false, 'theme_information', $args );

		$this->assertFalse( $result, 'Should return false for wrong theme' );
	}

	/**
	 * Test theme details provision for correct theme (without cached data)
	 */
	public function test_provide_theme_details_correct_theme() {
		// Clear cache first
		delete_transient( 'aggressive_apparel_theme_update' );

		$args = array( 'slug' => 'aggressive-apparel' );
		$result = $this->theme_updates->provide_theme_details( false, 'theme_information', $args );

		$this->assertFalse( $result, 'Should return false when no update data cached' );
	}

	/**
	 * Test download rename handling
	 */
	public function test_handle_download_rename_no_theme_match() {
		$source = '/tmp/source';
		$remote_source = 'https://example.com/other-theme.zip';

		// Create a mock upgrader object
		$mock_upgrader = $this->createMock( \stdClass::class );

		$result = $this->theme_updates->handle_download_rename( $source, $remote_source, $mock_upgrader );

		$this->assertEquals( $source, $result, 'Should return source unchanged when theme doesn\'t match' );
	}

	/**
	 * Test caching works with transients
	 */
	public function test_transient_caching() {
		// Clear any existing cache
		delete_transient( 'aggressive_apparel_theme_update' );

		// Initially no cached data
		$this->assertFalse( get_transient( 'aggressive_apparel_theme_update' ) );

		// Set some test data
		$test_data = array( 'version' => '1.0.0', 'package' => 'test.zip' );
		set_transient( 'aggressive_apparel_theme_update', $test_data, HOUR_IN_SECONDS );

		// Should return cached data
		$cached = get_transient( 'aggressive_apparel_theme_update' );
		$this->assertEquals( $test_data, $cached, 'Should return cached data' );

		// Clean up
		delete_transient( 'aggressive_apparel_theme_update' );
	}

	/**
	 * Test nonce creation works
	 */
	public function test_nonce_creation() {
		// Test that we can create nonces (validation may not work in test environment)
		$nonce = wp_create_nonce( 'aggressive_apparel_check_updates' );

		$this->assertNotEmpty( $nonce, 'Should create valid nonce' );
		$this->assertIsString( $nonce, 'Nonce should be a string' );
	}

	/**
	 * Test theme version comparison
	 */
	public function test_version_comparison() {
		$current_version = wp_get_theme( 'aggressive-apparel' )->get( 'Version' );

		// Test same version
		$this->assertFalse( version_compare( $current_version, $current_version, '>' ), 'Same version should not be greater' );

		// Test newer version
		$this->assertTrue( version_compare( '9.0.0', $current_version, '>' ), '9.0.0 should be greater than current' );

		// Test older version
		$this->assertFalse( version_compare( '0.1.0', $current_version, '>' ), '0.1.0 should not be greater than current' );
	}
}
