<?php
/**
 * Back in stock legacy-table migration tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Back_In_Stock_Migration;
use Automattic\WooCommerce\Internal\StockNotifications\Enums\NotificationStatus;
use Automattic\WooCommerce\Internal\StockNotifications\Factory;

/**
 * Confirmed subscribers move from the theme's old table into WooCommerce;
 * unconfirmed ones don't; the table goes only once there is somewhere for the
 * rows to go and they all went.
 */
class TestBackInStockMigration extends BackInStockTestCase {

	/**
	 * Create the old table (a temporary table, under the test suite).
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		delete_option( Back_In_Stock_Migration::DONE );

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixture for the theme's legacy table.
		$wpdb->query(
			"CREATE TABLE {$wpdb->prefix}" . Back_In_Stock_Migration::TABLE . ' (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				email varchar(100) NOT NULL,
				product_id bigint(20) unsigned NOT NULL,
				status varchar(20) NOT NULL DEFAULT \'active\',
				consent tinyint(1) NOT NULL DEFAULT 0,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				notified_at datetime DEFAULT NULL,
				unsubscribe_token varchar(64) NOT NULL,
				PRIMARY KEY  (id)
			)'
		);
		// phpcs:enable
	}

	/**
	 * Drop the old table if a test left it.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}" . Back_In_Stock_Migration::TABLE ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixture cleanup.

		parent::tearDown();
	}

	/**
	 * Add a row to the old table.
	 *
	 * @param string $email      Email address.
	 * @param int    $product_id Product ID.
	 * @param string $status     Legacy status.
	 * @return void
	 */
	private function legacy_row( string $email, int $product_id, string $status ): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture for the theme's legacy table.
			$wpdb->prefix . Back_In_Stock_Migration::TABLE,
			array(
				'email'             => $email,
				'product_id'        => $product_id,
				'status'            => $status,
				'consent'           => 1,
				'created_at'        => '2026-01-02 03:04:05',
				'unsubscribe_token' => wp_generate_password( 64, false ),
			)
		);
	}

	/**
	 * Whether the old table still exists.
	 *
	 * @return bool
	 */
	private function legacy_table_exists(): bool {
		global $wpdb;
		$suppress = $wpdb->suppress_errors();
		$count    = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}" . Back_In_Stock_Migration::TABLE ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixture probe.
		$wpdb->suppress_errors( $suppress );

		return null !== $count;
	}

	/**
	 * Confirmed rows move as active notifications; unconfirmed rows and rows
	 * for deleted products don't; then the table is dropped.
	 *
	 * @return void
	 */
	public function test_confirmed_rows_move_and_the_table_is_dropped(): void {
		$product   = $this->sold_out_product();
		$confirmed = $this->new_email();
		$pending   = $this->new_email();
		$orphan    = $this->new_email();
		$this->legacy_row( strtoupper( $confirmed ), $product, 'active' );
		$this->legacy_row( $pending, $product, 'pending' );
		$this->legacy_row( $orphan, 999999, 'active' );
		update_option( 'aggressive_apparel_bis_db_version', '1.0.0' );

		Back_In_Stock_Migration::move_batch( false );

		$moved = $this->notifications_for( $confirmed, array( NotificationStatus::ACTIVE ) );
		$this->assertCount( 1, $moved, 'The confirmed row moves, its address normalized.' );
		$notification = Factory::get_notification( $moved[0] );
		$this->assertNotFalse( $notification );
		$this->assertSame( $product, $notification->get_product_id() );
		$this->assertSame( array(), $this->notifications_for( $pending ), 'An unconfirmed signup must not become consent.' );
		$this->assertSame( array(), $this->notifications_for( $orphan ) );
		$this->assertFalse( $this->legacy_table_exists() );
		$this->assertFalse( get_option( 'aggressive_apparel_bis_db_version' ) );
		$this->assertSame( '1', (string) get_option( Back_In_Stock_Migration::DONE ) );
	}

	/**
	 * An address already waiting in WooCommerce isn't added twice.
	 *
	 * @return void
	 */
	public function test_moving_is_idempotent(): void {
		$product = $this->sold_out_product();
		$email   = $this->new_email();
		$this->legacy_row( $email, $product, 'active' );
		$this->legacy_row( $email, $product, 'active' );

		Back_In_Stock_Migration::move_batch( false );

		$this->assertCount( 1, $this->notifications_for( $email ) );
	}

	/**
	 * With WooCommerce's feature off there is nowhere to move rows, so the
	 * table stays exactly as it is.
	 *
	 * @return void
	 */
	public function test_table_is_kept_while_the_feature_is_off(): void {
		update_option( 'woocommerce_feature_customer_stock_notifications_enabled', 'no' );
		$email = $this->new_email();
		$this->legacy_row( $email, $this->sold_out_product(), 'active' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		( new Back_In_Stock_Migration() )->maybe_run();
		Back_In_Stock_Migration::move_batch( false );

		$this->assertTrue( $this->legacy_table_exists() );
		$this->assertFalse( get_option( Back_In_Stock_Migration::DONE ) );
	}

	/**
	 * Store managers' admin visits queue the move in the background.
	 *
	 * @return void
	 */
	public function test_admin_visit_queues_the_move(): void {
		$this->legacy_row( $this->new_email(), $this->sold_out_product(), 'active' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		( new Back_In_Stock_Migration() )->maybe_run();

		$this->assertTrue( as_has_scheduled_action( Back_In_Stock_Migration::ACTION ) );
		$this->assertTrue( $this->legacy_table_exists(), 'Nothing moves until the queued batch runs.' );
	}

	/**
	 * Once the table is gone, admin requests stop probing for it.
	 *
	 * @return void
	 */
	public function test_done_flag_short_circuits(): void {
		update_option( Back_In_Stock_Migration::DONE, 1 );
		$this->legacy_row( $this->new_email(), $this->sold_out_product(), 'active' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		( new Back_In_Stock_Migration() )->maybe_run();

		$this->assertFalse( as_has_scheduled_action( Back_In_Stock_Migration::ACTION ) );
	}

	/**
	 * A moved row was confirmed once, so it isn't sent back to verification.
	 *
	 * @return void
	 */
	public function test_moved_rows_are_active(): void {
		$email = $this->new_email();
		$this->legacy_row( $email, $this->sold_out_product(), 'active' );

		Back_In_Stock_Migration::move_batch( false );

		$this->assertCount( 1, $this->notifications_for( $email, array( NotificationStatus::ACTIVE ) ) );
		$this->assertSame( array(), $this->notifications_for( $email, array( NotificationStatus::PENDING ) ) );
	}
}
