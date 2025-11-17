<?php
/**
 * Test Theme Updates Class
 *
 * Tests for the simplified theme update functionality based on LAAO updater.
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
		$this->theme_updates = Theme_Updates::get_instance();
	}

	/**
	 * Tear down test environment
	 */
	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Test singleton pattern
	 */
	public function test_singleton_pattern(): void {
		$instance1 = Theme_Updates::get_instance();
		$instance2 = Theme_Updates::get_instance();

		$this->assertSame( $instance1, $instance2 );
		$this->assertInstanceOf( Theme_Updates::class, $instance1 );
	}

	/**
	 * Test class has required methods
	 */
	public function test_class_has_required_methods(): void {
		$this->assertTrue( method_exists( $this->theme_updates, 'init' ) );
		$this->assertTrue( method_exists( $this->theme_updates, 'check_for_update' ) );
		$this->assertTrue( method_exists( $this->theme_updates, 'rename_package' ) );
	}

	/**
	 * Test check_for_update method with empty transient
	 */
	public function test_check_for_update_empty_transient(): void {
		$transient = (object) [];
		$result = $this->theme_updates->check_for_update( $transient );

		$this->assertSame( $transient, $result );
	}

	/**
	 * Test check_for_update method returns transient unchanged when no updates
	 */
	public function test_check_for_update_no_updates(): void {
		$transient = (object) [
			'checked' => [
				'aggressive-apparel' => '9.9.9' // Higher version than any possible
			]
		];

		$result = $this->theme_updates->check_for_update( $transient );

		$this->assertSame( $transient, $result );
	}

	/**
	 * Test rename_package method with non-matching remote source
	 */
	public function test_rename_package_no_match(): void {
		$source = '/tmp/test-source';
		$remote_source = 'https://example.com/some-other-repo.zip';
		$theme_slug = 'aggressive-apparel';

		$result = $this->theme_updates->rename_package( $source, $remote_source, $theme_slug );

		// Should return the original source when repo name doesn't match
		$this->assertEquals( $source, $result );
	}
	}
