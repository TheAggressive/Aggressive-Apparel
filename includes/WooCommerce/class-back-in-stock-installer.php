<?php
/**
 * Back in Stock Installer Class
 *
 * Creates and manages the custom database table for stock notification
 * subscribers using WordPress dbDelta().
 *
 * @package Aggressive_Apparel
 * @since 1.18.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Back in Stock Installer
 *
 * @since 1.18.0
 */
class Back_In_Stock_Installer {

	/**
	 * Database version for schema tracking.
	 *
	 * @var string
	 */
	private const DB_VERSION = '1.0.0';

	/**
	 * Option key for tracking the installed DB version.
	 *
	 * @var string
	 */
	private const VERSION_OPTION = 'aggressive_apparel_bis_db_version';

	/**
	 * Install the database table if not already at the current version.
	 *
	 * @return void
	 */
	public function maybe_install(): void {
		if ( get_option( self::VERSION_OPTION ) === self::DB_VERSION ) {
			return;
		}

		$this->create_table();
		update_option( self::VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Create the subscribers table using dbDelta.
	 *
	 * @return void
	 */
	private function create_table(): void {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(100) NOT NULL,
			product_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			consent tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			notified_at datetime DEFAULT NULL,
			unsubscribe_token varchar(64) NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_product_status (product_id, status),
			KEY idx_email (email),
			KEY idx_token (unsubscribe_token)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get the full table name with prefix.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'aa_stock_subscribers';
	}
}
