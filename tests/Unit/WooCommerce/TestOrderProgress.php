<?php
/**
 * Order progress tracker and single-order view tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Account_Order_View;
use Aggressive_Apparel\WooCommerce\Order_Progress;
use WP_UnitTestCase;

/**
 * @covers \Aggressive_Apparel\WooCommerce\Order_Progress
 * @covers \Aggressive_Apparel\WooCommerce\Account_Order_View
 */
class TestOrderProgress extends WP_UnitTestCase {

	/**
	 * @return array<string, array{0: string, 1: array<int, string>}>
	 */
	public function status_states(): array {
		return array(
			'on hold'    => array( 'on-hold', array( 'current', 'upcoming', 'upcoming' ) ),
			'processing' => array( 'processing', array( 'done', 'current', 'upcoming' ) ),
			'completed'  => array( 'completed', array( 'done', 'done', 'done' ) ),
			'pending'    => array( 'pending', array() ),
			'failed'     => array( 'failed', array() ),
			'cancelled'  => array( 'cancelled', array() ),
			'refunded'   => array( 'refunded', array() ),
		);
	}

	/**
	 * @dataProvider status_states
	 *
	 * @param string             $status   Order status.
	 * @param array<int, string> $expected Step states.
	 * @return void
	 */
	public function test_status_maps_to_steps( string $status, array $expected ): void {
		$this->assertSame( $expected, Order_Progress::states( $status ) );
	}

	/**
	 * @return void
	 */
	public function test_render_marks_current_step(): void {
		$order = wc_create_order();
		$order->set_status( 'processing' );
		$order->save();

		ob_start();
		Order_Progress::render( $order );
		$html = (string) ob_get_clean();

		$this->assertSame( 1, substr_count( $html, 'aria-current="step"' ) );
		$this->assertStringContainsString( 'is-current" aria-current="step"><span class="aa-order-progress__label">' . esc_html__( 'In production', 'aggressive-apparel' ), $html );
	}

	/**
	 * @return void
	 */
	public function test_render_prints_nothing_for_cancelled(): void {
		$order = wc_create_order();
		$order->set_status( 'cancelled' );
		$order->save();

		ob_start();
		Order_Progress::render( $order );

		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * @return void
	 */
	public function test_status_text_becomes_date_and_chip(): void {
		$order = wc_create_order();
		$order->set_status( 'completed' );
		$order->save();

		$html = Account_Order_View::filter_status_text( 'Order #1 was placed…', $order );

		$this->assertIsString( $html );
		$this->assertStringStartsWith( '<span class="aa-order-head__date">', $html );
		$this->assertStringContainsString( 'aa-order-status--success', $html );
		$this->assertSame( $html, wp_kses_post( $html ), 'Output must survive the template\'s wp_kses_post.' );
	}

	/**
	 * @return void
	 */
	public function test_item_and_totals_filters_skip_outside_view_order(): void {
		$item = new \WC_Order_Item_Product();

		$this->assertSame( 'Tee', Account_Order_View::filter_item_name( 'Tee', $item ) );

		$totals = array(
			'order_total' => array(
				'label' => 'Total:',
				'value' => '$10',
			),
		);

		$this->assertSame( $totals, Account_Order_View::filter_totals( $totals ) );
	}
}
