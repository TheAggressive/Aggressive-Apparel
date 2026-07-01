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
	 * The empty global theme bundle should not be registered or enqueued.
	 */
	public function test_empty_global_script_is_not_enqueued(): void {
		do_action( 'wp_enqueue_scripts' );

		$this->assertFalse(
			wp_script_is( 'aggressive-apparel-main', 'registered' ),
			'Empty main script should not be registered'
		);
	}

	/**
	 * The extension hook should still fire without a global bundle.
	 */
	public function test_after_enqueue_scripts_hook_still_fires(): void {
		$did_fire = false;

		add_action(
			'aggressive_apparel_after_enqueue_scripts',
			static function () use ( &$did_fire ): void {
				$did_fire = true;
			}
		);

		do_action( 'wp_enqueue_scripts' );

		$this->assertTrue( $did_fire );
	}
}
