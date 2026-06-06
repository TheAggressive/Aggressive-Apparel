<?php
/**
 * Test Scripts Class
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\Assets;

use Aggressive_Apparel\Assets\Scripts;
use WP_UnitTestCase;

/**
 * Scripts Test Case
 */
class TestScripts extends WP_UnitTestCase {

	/**
	 * Scripts instance.
	 *
	 * @var Scripts
	 */
	private Scripts $scripts;

	/**
	 * Set up test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->scripts = new Scripts();
		$this->scripts->init();
	}

	/**
	 * Core theme scripts should enqueue the main bundle.
	 */
	public function test_scripts_enqueued(): void {
		do_action( 'wp_enqueue_scripts' );

		$this->assertTrue(
			wp_script_is( Scripts::HANDLE, 'enqueued' ),
			'Main script should be enqueued'
		);
	}

	/**
	 * Global theme data should be localized on the main handle.
	 */
	public function test_script_localization(): void {
		do_action( 'wp_enqueue_scripts' );

		global $wp_scripts;

		$script_data = $wp_scripts->get_data( Scripts::HANDLE, 'data' );

		$this->assertNotEmpty( $script_data, 'Script should have localized data' );
		$this->assertStringContainsString( 'aggressiveApparelData', $script_data, 'Should contain localized object name' );
		$this->assertStringContainsString( 'restUrl', $script_data, 'Should expose the REST API URL' );
	}
}
