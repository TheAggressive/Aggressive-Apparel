<?php
/**
 * Front-end jQuery optimizer.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Jquery_Optimizer;
use WP_UnitTestCase;

/**
 * jquery-migrate leaves the front end; jQuery itself is untouched.
 */
class TestJqueryOptimizer extends WP_UnitTestCase {

	/** The front end loads jQuery without jquery-migrate. */
	public function test_drops_migrate_on_front_end(): void {
		$scripts = new \WP_Scripts();
		$scripts->add( 'jquery', false, array( 'jquery-core', 'jquery-migrate' ) );

		( new Jquery_Optimizer() )->remove_migrate( $scripts );

		$this->assertSame( array( 'jquery-core' ), $scripts->registered['jquery']->deps );
	}

	/** The admin keeps the full jQuery bundle. */
	public function test_keeps_migrate_in_admin(): void {
		set_current_screen( 'dashboard' );
		$scripts = new \WP_Scripts();
		$scripts->add( 'jquery', false, array( 'jquery-core', 'jquery-migrate' ) );

		( new Jquery_Optimizer() )->remove_migrate( $scripts );

		$this->assertSame( array( 'jquery-core', 'jquery-migrate' ), $scripts->registered['jquery']->deps );
		set_current_screen( 'front' );
	}

	/** jQuery is never deferred: plugins use the global without declaring it. */
	public function test_jquery_stays_blocking(): void {
		( new Jquery_Optimizer() )->init();
		wp_enqueue_script( 'jquery' );

		$html = get_echo( 'wp_print_scripts' );

		$this->assertMatchesRegularExpression( '/<script[^>]*id="jquery-core-js"/', $html );
		$this->assertDoesNotMatchRegularExpression( '/<script[^>]*\b(defer|async)\b[^>]*id="jquery-core-js"/', $html );
	}
}
