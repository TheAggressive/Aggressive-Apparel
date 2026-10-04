<?php
/**
 * My Account presentation tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Account_Page;
use WP_UnitTestCase;

/**
 * @covers \Aggressive_Apparel\WooCommerce\Account_Page
 */
class TestAccountPage extends WP_UnitTestCase {

	/**
	 * @return void
	 */
	public function test_items_column_follows_order_number_and_actions_drop(): void {
		$columns = Account_Page::filter_order_columns(
			array(
				'order-number'  => 'Order',
				'order-date'    => 'Date',
				'order-actions' => 'Actions',
			)
		);

		$this->assertSame( array( 'order-number', 'order-items', 'order-date' ), array_keys( $columns ) );
	}

	/**
	 * @return void
	 */
	public function test_items_column_appends_without_order_number(): void {
		$columns = Account_Page::filter_order_columns( array( 'order-date' => 'Date' ) );

		$this->assertSame( array( 'order-date', 'order-items' ), array_keys( $columns ) );
	}

	/**
	 * @return void
	 */
	public function test_drops_downloads_for_customer_without_downloads(): void {
		wp_set_current_user( self::factory()->user->create() );

		$items = Account_Page::filter_menu_items(
			array(
				'dashboard' => 'Dashboard',
				'downloads' => 'Downloads',
			)
		);

		$this->assertSame( array( 'dashboard' ), array_keys( $items ) );
	}

	/**
	 * @return void
	 */
	public function test_status_chip_tone(): void {
		$order = wc_create_order();
		$order->set_status( 'processing' );
		$order->save();

		ob_start();
		Account_Page::render_order_status( $order );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'aa-order-status--info', $html );
		$this->assertStringContainsString( esc_html( wc_get_order_status_name( 'processing' ) ), $html );
	}

	/**
	 * @return void
	 */
	public function test_swaps_core_dashboard_template_only(): void {
		$core = WC()->plugin_path() . '/templates/myaccount/dashboard.php';

		$this->assertSame( AGGRESSIVE_APPAREL_DIR . '/templates/myaccount/dashboard.php', Account_Page::locate_template( $core, 'myaccount/dashboard.php' ) );
		$this->assertSame( '/child/woocommerce/myaccount/dashboard.php', Account_Page::locate_template( '/child/woocommerce/myaccount/dashboard.php', 'myaccount/dashboard.php' ) );
		$this->assertSame( $core, Account_Page::locate_template( $core, 'myaccount/orders.php' ) );
	}

	/**
	 * @return void
	 */
	public function test_profile_fieldset_wraps_balanced(): void {
		ob_start();
		Account_Page::open_profile_fieldset();
		Account_Page::close_profile_fieldset();
		$html = (string) ob_get_clean();

		$this->assertStringStartsWith( '<fieldset class="aa-account-fieldset"><legend>', $html );
		$this->assertStringEndsWith( '</fieldset>', $html );
	}
}
