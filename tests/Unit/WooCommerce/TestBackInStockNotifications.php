<?php
/**
 * Back in stock restock-notification delivery tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Back_In_Stock;
use Aggressive_Apparel\WooCommerce\Back_In_Stock_Installer;
use ReflectionClass;
use WP_UnitTestCase;

/**
 * Covers the batch notification worker: the continuation handler must exist,
 * rows are only marked delivered when the email is actually sent, and waitlists
 * larger than one batch are drained via a keyset cursor.
 */
class TestBackInStockNotifications extends WP_UnitTestCase {

	/**
	 * Email prefix used for test rows.
	 *
	 * @var string
	 */
	private string $email_prefix = '';

	/**
	 * In-stock product used across tests.
	 *
	 * @var int
	 */
	private int $product_id = 0;

	/**
	 * Configured batch size read from the class under test.
	 *
	 * @var int
	 */
	private int $batch_size = 0;

	/**
	 * Prepare the table, a real in-stock product, and a disabled email so the
	 * send path is deterministic (trigger() returns false → no delivery).
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->email_prefix = 'notify-' . wp_generate_uuid4();
		( new Back_In_Stock_Installer() )->maybe_install();

		$product = new \WC_Product_Simple();
		$product->set_name( 'Waitlisted Product' );
		$product->set_stock_status( 'instock' );
		$product->save();
		$this->product_id = $product->get_id();

		// Disable the customer email so trigger() reports "not sent" without
		// depending on the global mailer state or wp_mail.
		add_filter( 'woocommerce_email_enabled_back_in_stock', '__return_false' );

		$reflection       = new ReflectionClass( Back_In_Stock::class );
		$this->batch_size = (int) $reflection->getConstant( 'BATCH_SIZE' );
	}

	/**
	 * Remove rows, the product, and the scheduled continuation events.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		global $wpdb;
		$table = Back_In_Stock_Installer::get_table_name();

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Test cleanup for custom table.
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE email LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table name.
				$wpdb->esc_like( $this->email_prefix ) . '%'
			)
		);

		wp_clear_scheduled_hook( 'aggressive_apparel_bis_send_batch', array( $this->product_id ) );
		remove_all_filters( 'woocommerce_email_enabled_back_in_stock' );

		parent::tearDown();
	}

	/**
	 * Insert one active subscription row and return its id.
	 *
	 * @return int
	 */
	private function insert_active(): int {
		global $wpdb;
		$table = Back_In_Stock_Installer::get_table_name();

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Test fixture for custom table.
			$table,
			array(
				'email'             => $this->email_prefix . '-' . wp_generate_uuid4() . '@example.com',
				'product_id'        => $this->product_id,
				'status'            => 'active',
				'consent'           => 1,
				'unsubscribe_token' => wp_generate_password( 64, false ),
			),
			array( '%s', '%d', '%s', '%d', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Read a row's status.
	 *
	 * @param int $id Row id.
	 * @return string
	 */
	private function status_of( int $id ): string {
		global $wpdb;
		$table = Back_In_Stock_Installer::get_table_name();

		return (string) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Test assertion for custom table.
			$wpdb->prepare(
				"SELECT status FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table name.
				$id
			)
		);
	}

	/**
	 * init() must register the continuation handler; without it the scheduled
	 * event fires into the void and subscribers past the first batch are lost.
	 *
	 * @return void
	 */
	public function test_init_registers_batch_continuation_handler(): void {
		( new Back_In_Stock() )->init();

		$this->assertNotFalse(
			has_action( 'aggressive_apparel_bis_send_batch' ),
			'The batch continuation event must have a handler.'
		);
	}

	/**
	 * A failed send leaves the row active (not "notified") so a later restock can
	 * retry it, and a partial batch does not schedule a continuation.
	 *
	 * @return void
	 */
	public function test_failed_send_leaves_row_active_and_does_not_reschedule(): void {
		$id = $this->insert_active();

		( new Back_In_Stock() )->send_notification_batch( $this->product_id, 0 );

		$this->assertSame( 'active', $this->status_of( $id ), 'A row must stay active when nothing was sent.' );
		$this->assertFalse(
			wp_next_scheduled( 'aggressive_apparel_bis_send_batch', array( $this->product_id, $id ) ),
			'A partial batch must not schedule a continuation.'
		);
	}

	/**
	 * A full batch schedules a keyset continuation past the last row it touched,
	 * so waitlists larger than one batch are fully drained instead of stopping at
	 * BATCH_SIZE.
	 *
	 * @return void
	 */
	public function test_full_batch_schedules_keyset_continuation(): void {
		$ids = array();
		for ( $i = 0; $i < $this->batch_size; $i++ ) {
			$ids[] = $this->insert_active();
		}
		$last_id = max( $ids );

		( new Back_In_Stock() )->send_notification_batch( $this->product_id, 0 );

		$this->assertNotFalse(
			wp_next_scheduled( 'aggressive_apparel_bis_send_batch', array( $this->product_id, $last_id ) ),
			'A full batch must schedule a continuation cursored past the last row.'
		);
	}

	/**
	 * The worker stops for a product that is no longer in stock between batches.
	 *
	 * @return void
	 */
	public function test_out_of_stock_product_is_skipped(): void {
		$id = $this->insert_active();

		$product = wc_get_product( $this->product_id );
		$product->set_stock_status( 'outofstock' );
		$product->save();

		( new Back_In_Stock() )->send_notification_batch( $this->product_id, 0 );

		$this->assertSame( 'active', $this->status_of( $id ), 'Out-of-stock products must not be processed.' );
	}
}
