<?php
/**
 * Back in Stock privacy and retention.
 *
 * Personal-data export and erasure, the suggested privacy-policy text, and the
 * cleanup that bounds how long subscription rows (and unconfirmed addresses)
 * are kept.
 *
 * @package Aggressive_Apparel
 */

declare( strict_types=1 );

namespace Aggressive_Apparel\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Privacy tooling and data retention for Back in Stock subscriptions.
 */
class Back_In_Stock_Privacy {

	/**
	 * Default retention period for completed subscription rows.
	 *
	 * Print-on-demand supplier availability can shift over weeks, so active
	 * requests remain until notified/unsubscribed/discontinued. Completed rows
	 * are retained briefly for support, abuse prevention, and delivery debugging.
	 *
	 * @var int
	 */
	private const DEFAULT_RETENTION_DAYS = 90;

	/**
	 * Days an unconfirmed signup waits for confirmation before it is deleted.
	 *
	 * @var int
	 */
	private const PENDING_CONFIRMATION_DAYS = 7;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Discontinued products should not keep open waitlist requests.
		add_action( 'wp_trash_post', array( $this, 'cleanup_discontinued_product_subscriptions' ) );
		add_action( 'before_delete_post', array( $this, 'cleanup_discontinued_product_subscriptions' ) );

		// Retention cleanup: unconfirmed signups expire; completed rows follow the
		// retention window; active requests remain.
		add_action( 'aggressive_apparel_bis_cleanup', array( $this, 'run_cleanup_expired_subscriptions' ) );
		if ( ! wp_next_scheduled( 'aggressive_apparel_bis_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'aggressive_apparel_bis_cleanup' );
		}

		// GDPR hooks.
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
		add_action( 'admin_init', array( $this, 'register_privacy_policy_content' ) );
	}

	/**
	 * Register GDPR personal data exporter.
	 *
	 * @param array $exporters Existing exporters.
	 * @return array Modified exporters.
	 */
	public function register_exporter( array $exporters ): array {
		$exporters['aggressive-apparel-bis'] = array(
			'exporter_friendly_name' => __( 'Back in Stock Subscriptions', 'aggressive-apparel' ),
			'callback'               => array( $this, 'export_personal_data' ),
		);
		return $exporters;
	}

	/**
	 * Register GDPR personal data eraser.
	 *
	 * @param array $erasers Existing erasers.
	 * @return array Modified erasers.
	 */
	public function register_eraser( array $erasers ): array {
		$erasers['aggressive-apparel-bis'] = array(
			'eraser_friendly_name' => __( 'Back in Stock Subscriptions', 'aggressive-apparel' ),
			'callback'             => array( $this, 'erase_personal_data' ),
		);
		return $erasers;
	}

	/**
	 * Register suggested privacy policy text for back-in-stock subscriptions.
	 *
	 * @return void
	 */
	public function register_privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$retention_days = self::get_retention_days();
		$retention_text = $retention_days > 0
			? sprintf(
				/* translators: %d: retention period in days. */
				__( 'After a notification is sent or you unsubscribe, related back-in-stock records are retained for up to %d days for support, abuse prevention, and delivery troubleshooting, then deleted.', 'aggressive-apparel' ),
				$retention_days
			)
			: __( 'After a notification is sent or you unsubscribe, related back-in-stock records are retained until they are manually deleted by the store.', 'aggressive-apparel' );

		$confirm_text = sprintf(
			/* translators: %d: days an unconfirmed request is kept. */
			__( 'We email you once to confirm each request, and only confirmed requests are notified; unconfirmed requests are deleted after %d days.', 'aggressive-apparel' ),
			self::PENDING_CONFIRMATION_DAYS
		);

		$content = wp_kses_post(
			wpautop(
				sprintf(
					/* translators: %s: retention policy sentence. */
					__( 'We use your email address to notify you when a requested product, size, or color is available again. Because some products are produced through print-on-demand suppliers, availability may depend on supplier stock, production capacity, or product status. Active notification requests are kept until the item becomes available, you unsubscribe, or the product is discontinued. %s', 'aggressive-apparel' ),
					$confirm_text . ' ' . $retention_text
				)
			)
		);

		wp_add_privacy_policy_content(
			__( 'Aggressive Apparel Back in Stock Notifications', 'aggressive-apparel' ),
			$content
		);
	}

	/**
	 * Export personal data for GDPR.
	 *
	 * @param string $email_address Email to export data for.
	 * @param int    $page          Page number.
	 * @return array Export data.
	 */
	public function export_personal_data( string $email_address, int $page = 1 ): array {
		global $wpdb;
		$table = Back_In_Stock_Installer::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Privacy exports must reflect complete current user data and must not use shared caches.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT product_id, status, created_at, notified_at FROM %i WHERE email = %s',
				$table,
				$email_address
			)
		);

		$export_items = array();
		foreach ( $rows as $row ) {
			$product        = wc_get_product( $row->product_id );
			$export_items[] = array(
				'group_id'    => 'bis-subscriptions',
				'group_label' => __( 'Stock Notification Subscriptions', 'aggressive-apparel' ),
				'item_id'     => 'bis-' . $row->product_id,
				'data'        => array(
					array(
						'name'  => __( 'Product', 'aggressive-apparel' ),
						'value' => $product ? $product->get_name() : '#' . $row->product_id,
					),
					array(
						'name'  => __( 'Status', 'aggressive-apparel' ),
						'value' => $row->status,
					),
					array(
						'name'  => __( 'Subscribed', 'aggressive-apparel' ),
						'value' => $row->created_at,
					),
				),
			);
		}

		return array(
			'data' => $export_items,
			'done' => true,
		);
	}

	/**
	 * Erase personal data for GDPR.
	 *
	 * @param string $email_address Email to erase data for.
	 * @param int    $page          Page number.
	 * @return array Erase result.
	 */
	public function erase_personal_data( string $email_address, int $page = 1 ): array {
		global $wpdb;
		$table = Back_In_Stock_Installer::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Privacy erasure is an immediate custom-table mutation.
		$deleted = $wpdb->delete(
			$table,
			array( 'email' => $email_address ),
			array( '%s' )
		);

		return array(
			'items_removed'  => $deleted ? (int) $deleted : 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Run retention cleanup from WP-Cron.
	 *
	 * @return void
	 */
	public function run_cleanup_expired_subscriptions(): void {
		$this->cleanup_expired_subscriptions();
	}

	/**
	 * Delete open (pending or active) subscriptions for products that are trashed or deleted.
	 *
	 * Completed rows keep following the configured retention window for support
	 * and delivery troubleshooting.
	 *
	 * @param int $post_id Product or variation post ID.
	 * @return void
	 */
	public function cleanup_discontinued_product_subscriptions( int $post_id ): void {
		if ( ! in_array( get_post_type( $post_id ), array( 'product', 'product_variation' ), true ) ) {
			return;
		}

		global $wpdb;
		$table = Back_In_Stock_Installer::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Product lifecycle cleanup is an immediate custom-table mutation.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM %i WHERE product_id = %d AND status IN ('pending', 'active')",
				$table,
				$post_id
			)
		);
	}

	/**
	 * Delete unconfirmed signups past the confirmation window, and completed
	 * subscriptions older than the configured retention window.
	 *
	 * Active waitlist rows are intentionally retained so customers still receive
	 * the notification they requested. Unconfirmed signups always expire: they
	 * hold an address that never consented. A retention value of 0 disables
	 * only the completed-row cleanup.
	 *
	 * @return int Number of deleted rows.
	 */
	public function cleanup_expired_subscriptions(): int {
		global $wpdb;
		$table = Back_In_Stock_Installer::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Scheduled expiry deletion mutates custom-table data; caching is inapplicable.
		$expired = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM %i WHERE status = 'pending' AND created_at < %s",
				$table,
				current_datetime()->modify( '-' . self::PENDING_CONFIRMATION_DAYS . ' days' )->format( 'Y-m-d H:i:s' )
			)
		);
		$expired = false === $expired ? 0 : (int) $expired;

		$retention_days = self::get_retention_days();
		if ( $retention_days <= 0 ) {
			return $expired;
		}

		$cutoff = current_datetime()
			->modify( '-' . $retention_days . ' days' )
			->format( 'Y-m-d H:i:s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Scheduled retention deletion mutates custom-table data; caching is inapplicable.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM %i WHERE status IN ('notified', 'unsubscribed') AND created_at < %s",
				$table,
				$cutoff
			)
		);

		return $expired + ( false === $deleted ? 0 : (int) $deleted );
	}

	/**
	 * Configured retention period for completed back-in-stock rows.
	 *
	 * @return int Retention period in days; 0 disables automatic cleanup.
	 */
	private static function get_retention_days(): int {
		return max(
			0,
			(int) apply_filters(
				'aggressive_apparel_back_in_stock_retention_days',
				self::DEFAULT_RETENTION_DAYS
			)
		);
	}
}
