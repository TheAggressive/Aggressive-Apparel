<?php
/**
 * Test Scripts Class
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Unit\Assets;

use WP_UnitTestCase;
use Aggressive_Apparel\Assets\Scripts;

/**
 * Scripts Test Case
 */
class TestScripts extends WP_UnitTestCase {
	/**
	 * Scripts instance
	 *
	 * @var Scripts
	 */
	private $scripts;

	/**
	 * Set up test
	 */
	public function setUp(): void {
		parent::setUp();
		$this->scripts = new Scripts( '1.0.0' );
		$this->scripts->init();
	}

	/**
	 * Test scripts are registered
	 */
	public function test_scripts_enqueued() {
		// Trigger the enqueue action.
		do_action( 'wp_enqueue_scripts' );

		$this->assertTrue(
			wp_script_is( 'aggressive-apparel-main', 'registered' ),
			'Main script should be registered'
		);
	}

	/**
	 * Test script localization
	 */
	public function test_script_localization() {
		do_action( 'wp_enqueue_scripts' );

		global $wp_scripts;
		$script_data = $wp_scripts->get_data( 'aggressive-apparel-main', 'data' );

		$this->assertNotEmpty( $script_data, 'Script should have localized data' );
		$this->assertStringContainsString( 'aggressiveApparelData', $script_data, 'Should contain localized object name' );
	}
}
