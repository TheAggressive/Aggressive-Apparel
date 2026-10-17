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
 * jQuery stays blocking: plugins use the global without declaring it.
 */
class TestJqueryOptimizer extends WP_UnitTestCase {

	/** Register a plugin script that uses jQuery without declaring it. */
	public function setUp(): void {
		parent::setUp();
		wp_register_script( 'wc-add-to-cart', 'https://example.test/add-to-cart.js', array( 'jquery' ), '1', true );
		wp_register_script( 'undeclared-jquery-user', 'https://example.test/plugin.js', array(), '1', true );
	}

	/** Reset the strategies set by the test. */
	public function tearDown(): void {
		wp_deregister_script( 'wc-add-to-cart' );
		wp_deregister_script( 'undeclared-jquery-user' );
		parent::tearDown();
	}

	/** jQuery is never deferred; WooCommerce's dependents are. */
	public function test_defers_woocommerce_scripts_but_not_jquery(): void {
		( new Jquery_Optimizer() )->defer_scripts();

		$this->assertNotSame( 'defer', wp_scripts()->get_data( 'jquery-core', 'strategy' ), 'Plugins use jQuery without declaring it.' );
		$this->assertSame( 'defer', wp_scripts()->get_data( 'wc-add-to-cart', 'strategy' ) );
		$this->assertNotContains( 'jquery-core', Jquery_Optimizer::DEFER_HANDLES );
	}

	/** A script using the jQuery global without declaring it still finds jQuery first. */
	public function test_jquery_prints_blocking_before_undeclared_users(): void {
		( new Jquery_Optimizer() )->defer_scripts();
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'undeclared-jquery-user' );

		$html = get_echo( 'wp_print_scripts' );

		$this->assertMatchesRegularExpression( '/<script[^>]*id="jquery-core-js"/', $html );
		$this->assertDoesNotMatchRegularExpression( '/<script[^>]*\b(defer|async)\b[^>]*id="jquery-core-js"/', $html );
	}
}
