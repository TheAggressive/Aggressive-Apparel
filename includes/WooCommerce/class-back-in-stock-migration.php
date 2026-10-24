<?php
/**
 * Back in Stock Migration
 *
 * Moves waitlists from the theme's old `{prefix}aa_stock_subscribers` table
 * into WooCommerce's native stock notifications, then drops the table.
 *
 * Only confirmed (`active`) rows move: a `pending` row never confirmed its
 * address, so carrying it over would turn an unverified signup into consent.
 * Rows for products that no longer exist are dropped with the table. Moving is
 * idempotent (an address already waiting on the product is left alone), runs
 * in Action Scheduler batches behind a cursor, and waits until WooCommerce's
 * feature is enabled — the table is never dropped while there is nowhere to
 * move it. A failed row keeps the table; the next admin visit retries.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme subscriber table → WooCommerce stock notifications.
 */
final class Back_In_Stock_Migration {

	/**
	 * Old table name, after the WordPress prefix.
	 *
	 * @var string
	 */
	public const TABLE = 'aa_stock_subscribers';

	/**
	 * Action Scheduler hook for one batch of rows.
	 *
	 * @var string
	 */
	public const ACTION = 'aggressive_apparel_bis_move_subscribers';

	/**
	 * Option recording that the old table is gone, so admin requests stop
	 * probing for it.
	 *
	 * @var string
	 */
	public const DONE = 'aggressive_apparel_bis_moved';

	/**
	 * Option holding the last row ID a batch reached.
	 *
	 * @var string
	 */
	private const CURSOR = 'aggressive_apparel_bis_move_cursor';

	/**
	 * Option set when a row failed to move, so the table is kept.
	 *
	 * @var string
	 */
	private const FAILED = 'aggressive_apparel_bis_move_failed';

	/**
	 * Transient that stops overlapping runs and throttles retries.
	 *
	 * @var string
	 */
	private const LOCK = 'aggressive_apparel_bis_move_lock';

	/**
	 * Options and cron hooks the old implementation left behind.
	 *
	 * @var array{options: list<string>, hooks: list<string>}
	 */
	private const LEGACY = array(
		'options' => array( 'aggressive_apparel_bis_db_version' ),
		'hooks'   => array( 'aggressive_apparel_bis_cleanup', 'aggressive_apparel_bis_send_batch' ),
	);

	/**
	 * Rows moved per batch.
	 *
	 * @var int
	 */
	private const BATCH = 100;

	/**
	 * Register hooks.
	 */
	public function init(): void {
		add_action( 'admin_init', array( $this, 'maybe_run' ) );
		add_action( self::ACTION, array( self::class, 'scheduled_batch' ) );
	}

	/**
	 * Start (or resume) the move from an admin page a store manager opens.
	 */
	public function maybe_run(): void {
		if ( get_option( self::DONE ) || ! current_user_can( 'manage_woocommerce' ) || wp_doing_ajax() || false !== get_transient( self::LOCK ) ) {
			return;
		}

		if ( ! self::table_exists() ) {
			self::clean_up_legacy();
			return;
		}

		if ( ! Back_In_Stock_Native::is_enabled() ) {
			return;
		}

		set_transient( self::LOCK, 1, 5 * MINUTE_IN_SECONDS );

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::ACTION, array(), 'aggressive-apparel' );
			return;
		}

		do {
			$moved = self::move_batch( false );
		} while ( self::BATCH === $moved );
	}

	/**
	 * The Action Scheduler callback.
	 */
	public static function scheduled_batch(): void {
		self::move_batch();
	}

	/**
	 * Move one batch of confirmed rows; queues the next batch while more
	 * remain, and finishes once none do.
	 *
	 * @param bool $queue_next Queue the next batch through Action Scheduler.
	 * @return int Rows read.
	 */
	public static function move_batch( bool $queue_next = true ): int {
		if ( ! self::table_exists() || ! Back_In_Stock_Native::is_enabled() ) {
			delete_transient( self::LOCK );
			return 0;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off move out of the theme's own legacy table.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, email, product_id, created_at FROM %i WHERE status = 'active' AND id > %d ORDER BY id ASC LIMIT %d",
				self::table(),
				(int) get_option( self::CURSOR, 0 ),
				self::BATCH
			),
			ARRAY_A
		);
		$rows = is_array( $rows ) ? $rows : array();

		foreach ( $rows as $row ) {
			$product_id = (int) $row['product_id'];
			if ( wc_get_product( $product_id ) instanceof \WC_Product
				&& ! Back_In_Stock_Native::import_active( $product_id, (string) $row['email'], (string) $row['created_at'] ) ) {
				update_option( self::FAILED, 1, false );
			}
			update_option( self::CURSOR, (int) $row['id'], false );
		}

		if ( count( $rows ) < self::BATCH ) {
			self::finish();
		} elseif ( $queue_next && function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::ACTION, array(), 'aggressive-apparel' );
		}

		return count( $rows );
	}

	/**
	 * Drop the table once every row moved; otherwise rewind and retry later.
	 */
	private static function finish(): void {
		delete_option( self::CURSOR );

		if ( get_option( self::FAILED ) ) {
			delete_option( self::FAILED );
			// Moving is idempotent, so the retry re-reads every row. Throttled
			// so a row that keeps failing doesn't cost a batch per admin page.
			set_transient( self::LOCK, 1, HOUR_IN_SECONDS );
			wc_get_logger()->warning( 'Some back-in-stock subscribers could not be moved to WooCommerce; the old table is kept and the move retries in an hour.', array( 'source' => 'aggressive-apparel-back-in-stock' ) );
			return;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Retiring the theme's own table after its rows moved.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::table() ) );

		self::clean_up_legacy();
		delete_transient( self::LOCK );
	}

	/**
	 * Remove the old implementation's options and cron events, and record
	 * the move as done.
	 */
	private static function clean_up_legacy(): void {
		foreach ( self::LEGACY['options'] as $option ) {
			delete_option( $option );
		}
		foreach ( self::LEGACY['hooks'] as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
		update_option( self::DONE, 1, true );
	}

	/**
	 * Whether the old table is still there.
	 */
	private static function table_exists(): bool {
		global $wpdb;
		// Querying the table, rather than SHOW TABLES, also sees temporary
		// tables, which is how the WordPress test suite creates them.
		$suppress = $wpdb->suppress_errors();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema probe for the one-off move.
		$count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', self::table() ) );
		$wpdb->suppress_errors( $suppress );

		return null !== $count;
	}

	/**
	 * The old table's full name.
	 */
	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}
}
