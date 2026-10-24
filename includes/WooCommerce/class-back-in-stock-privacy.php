<?php
/**
 * Back in Stock privacy: data export and suggested policy text.
 *
 * WooCommerce's stock notifications register a personal-data eraser and run
 * their own retention, but no exporter and no privacy-policy text. This
 * fills those two gaps for the same records.
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
 * Privacy exporter and policy text for WooCommerce stock notifications.
 */
class Back_In_Stock_Privacy {

	/**
	 * Waitlist entries exported per page.
	 *
	 * @var int
	 */
	private const EXPORT_PAGE_SIZE = 50;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_action( 'admin_init', array( $this, 'register_privacy_policy_content' ) );
	}

	/**
	 * Register the personal data exporter.
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
	 * Register suggested privacy policy text for back-in-stock subscriptions.
	 *
	 * @return void
	 */
	public function register_privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$sentences = array();
		if ( Back_In_Stock_Native::requires_double_opt_in() ) {
			$days        = Back_In_Stock_Native::unverified_retention_days();
			$sentences[] = $days > 0
				? sprintf(
					/* translators: %d: days an unconfirmed request is kept. */
					__( 'We email you once to confirm each request, and only confirmed requests are notified; unconfirmed requests are deleted after %d days.', 'aggressive-apparel' ),
					$days
				)
				: __( 'We email you once to confirm each request, and only confirmed requests are notified.', 'aggressive-apparel' );
		}
		$sentences[] = __( 'After a notification is sent or you unsubscribe, related back-in-stock records are retained until they are manually deleted by the store.', 'aggressive-apparel' );

		$content = wp_kses_post(
			wpautop(
				sprintf(
					/* translators: %s: retention policy sentence. */
					__( 'We use your email address to notify you when a requested product, size, or color is available again. Because some products are produced through print-on-demand suppliers, availability may depend on supplier stock, production capacity, or product status. Active notification requests are kept until the item becomes available, you unsubscribe, or the product is discontinued. %s', 'aggressive-apparel' ),
					implode( ' ', $sentences )
				)
			)
		);

		wp_add_privacy_policy_content(
			__( 'Aggressive Apparel Back in Stock Notifications', 'aggressive-apparel' ),
			$content
		);
	}

	/**
	 * Export personal data for one page of an address's waitlist entries.
	 *
	 * @param string $email_address Email to export data for.
	 * @param int    $page          Page number, from 1.
	 * @return array Export data.
	 */
	public function export_personal_data( string $email_address, int $page = 1 ): array {
		$entries = Back_In_Stock_Native::is_enabled()
			? Back_In_Stock_Native::entries_for_email( $email_address, self::EXPORT_PAGE_SIZE, ( max( 1, $page ) - 1 ) * self::EXPORT_PAGE_SIZE )
			: array();

		$export_items = array();
		foreach ( $entries as $entry ) {
			$export_items[] = array(
				'group_id'    => 'bis-subscriptions',
				'group_label' => __( 'Stock Notification Subscriptions', 'aggressive-apparel' ),
				'item_id'     => 'bis-' . $entry['id'],
				'data'        => array(
					array(
						'name'  => __( 'Product', 'aggressive-apparel' ),
						'value' => $entry['product'],
					),
					array(
						'name'  => __( 'Status', 'aggressive-apparel' ),
						'value' => $entry['status'],
					),
					array(
						'name'  => __( 'Subscribed', 'aggressive-apparel' ),
						'value' => $entry['created'],
					),
				),
			);
		}

		return array(
			'data' => $export_items,
			'done' => count( $entries ) < self::EXPORT_PAGE_SIZE,
		);
	}
}
