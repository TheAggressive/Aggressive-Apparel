<?php
/**
 * Back in stock retention tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Back_In_Stock;
use Aggressive_Apparel\WooCommerce\Back_In_Stock_Installer;
use WP_UnitTestCase;

/**
 * Covers completed-subscription cleanup behavior.
 */
class TestBackInStockRetention extends WP_UnitTestCase {

	/**
	 * Email prefix used for test rows.
	 *
	 * @var string
	 */
	private string $email_prefix = '';

	/**
	 * Ensure the custom table exists.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->email_prefix = 'retention-' . wp_generate_uuid4();
		( new Back_In_Stock_Installer() )->maybe_install();
	}

	/**
	 * Remove rows created by this test.
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

		remove_all_filters( 'aggressive_apparel_back_in_stock_retention_days' );

		parent::tearDown();
	}

	/**
	 * Insert a subscription row.
	 *
	 * @param string $status     Subscription status.
	 * @param string $created_at Created timestamp.
	 * @param int    $product_id Product ID.
	 * @return int Inserted row id.
	 */
	private function insert_subscription( string $status, string $created_at, int $product_id = 123 ): int {
		global $wpdb;
		$table = Back_In_Stock_Installer::get_table_name();

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Test fixture for custom table.
			$table,
			array(
				'email'             => $this->email_prefix . '-' . wp_generate_uuid4() . '@example.com',
				'product_id'        => $product_id,
				'status'            => $status,
				'consent'           => 1,
				'created_at'        => $created_at,
				'unsubscribe_token' => wp_generate_password( 64, false ),
			),
			array( '%s', '%d', '%s', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Whether a subscription row still exists.
	 *
	 * @param int $id Row id.
	 * @return bool
	 */
	private function row_exists( int $id ): bool {
		global $wpdb;
		$table = Back_In_Stock_Installer::get_table_name();

		return (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Test assertion for custom table.
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table name.
				$id
			)
		);
	}

	/**
	 * Build a MySQL datetime relative to the site's configured timezone.
	 *
	 * @param int $days_ago Number of days in the past.
	 * @return string
	 */
	private function days_ago( int $days_ago ): string {
		return current_datetime()
			->modify( '-' . $days_ago . ' days' )
			->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Default cleanup removes old completed subscriptions after 90 days.
	 *
	 * @return void
	 */
	public function test_retention_cleanup_defaults_to_90_days(): void {
		$old    = $this->days_ago( 120 );
		$recent = $this->days_ago( 30 );

		$old_notified = $this->insert_subscription( 'notified', $old );
		$old_active   = $this->insert_subscription( 'active', $old );
		$recent_done  = $this->insert_subscription( 'notified', $recent );

		$deleted = ( new Back_In_Stock() )->cleanup_expired_subscriptions();

		$this->assertSame( 1, $deleted );
		$this->assertFalse( $this->row_exists( $old_notified ) );
		$this->assertTrue( $this->row_exists( $old_active ) );
		$this->assertTrue( $this->row_exists( $recent_done ) );
	}

	/**
	 * Stores can still disable automatic cleanup when they need manual control.
	 *
	 * @return void
	 */
	public function test_retention_cleanup_can_be_disabled(): void {
		add_filter( 'aggressive_apparel_back_in_stock_retention_days', static fn(): int => 0 );

		$old = $this->days_ago( 120 );
		$id  = $this->insert_subscription( 'notified', $old );

		$deleted = ( new Back_In_Stock() )->cleanup_expired_subscriptions();

		$this->assertSame( 0, $deleted );
		$this->assertTrue( $this->row_exists( $id ) );
	}

	/**
	 * Configured cleanup removes only old completed subscriptions.
	 *
	 * @return void
	 */
	public function test_retention_cleanup_keeps_active_and_recent_rows(): void {
		add_filter( 'aggressive_apparel_back_in_stock_retention_days', static fn(): int => 30 );

		$old    = $this->days_ago( 90 );
		$recent = $this->days_ago( 10 );

		$old_notified = $this->insert_subscription( 'notified', $old );
		$old_inactive = $this->insert_subscription( 'unsubscribed', $old );
		$old_active   = $this->insert_subscription( 'active', $old );
		$recent_done  = $this->insert_subscription( 'notified', $recent );

		$deleted = ( new Back_In_Stock() )->cleanup_expired_subscriptions();

		$this->assertSame( 2, $deleted );
		$this->assertFalse( $this->row_exists( $old_notified ) );
		$this->assertFalse( $this->row_exists( $old_inactive ) );
		$this->assertTrue( $this->row_exists( $old_active ) );
		$this->assertTrue( $this->row_exists( $recent_done ) );
	}

	/**
	 * Discontinued products remove active waitlist rows but retain history rows.
	 *
	 * @return void
	 */
	public function test_discontinued_product_cleanup_removes_only_active_rows(): void {
		$product_id = self::factory()->post->create(
			array(
				'post_type'   => 'product',
				'post_status' => 'publish',
			)
		);
		$old        = $this->days_ago( 10 );

		$active   = $this->insert_subscription( 'active', $old, $product_id );
		$notified = $this->insert_subscription( 'notified', $old, $product_id );

		( new Back_In_Stock() )->cleanup_discontinued_product_subscriptions( $product_id );

		$this->assertFalse( $this->row_exists( $active ) );
		$this->assertTrue( $this->row_exists( $notified ) );
	}
}
