<?php
/**
 * Back in stock email-link and privacy tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Back_In_Stock_Links;
use Aggressive_Apparel\WooCommerce\Back_In_Stock_Privacy;
use Automattic\WooCommerce\Internal\StockNotifications\Emails\EmailManager;
use Automattic\WooCommerce\Internal\StockNotifications\Enums\NotificationStatus;
use Automattic\WooCommerce\Internal\StockNotifications\Factory;
use Automattic\WooCommerce\Internal\StockNotifications\Notification;
use WPDieException;

/**
 * WooCommerce's verify and unsubscribe links act on GET, which email security
 * scanners fetch; the theme turns that GET into a confirm page whose POST
 * goes on to WooCommerce. Restock emails carry one-click unsubscribe headers
 * built from the body's own (single-use) link.
 */
class TestBackInStockLinks extends BackInStockTestCase {

	/**
	 * A saved notification for a sold-out product.
	 *
	 * @param string $status Notification status.
	 * @return Notification
	 */
	private function notification( string $status ): Notification {
		$notification = new Notification();
		$notification->set_product_id( $this->sold_out_product() );
		$notification->set_user_email( $this->new_email() );
		$notification->set_status( $status );
		$notification->save();

		return $notification;
	}

	/**
	 * Run the link guard as a request would, returning the page it renders,
	 * or null when it lets the request through.
	 *
	 * @param array<string, string> $query  Query args.
	 * @param string                $method HTTP method.
	 * @return string|null
	 */
	private function open_link( array $query, string $method ): ?string {
		$_GET                      = $query;
		$_SERVER['REQUEST_METHOD'] = $method;

		try {
			( new Back_In_Stock_Links() )->guard_email_link();
		} catch ( WPDieException $e ) {
			return $e->getMessage();
		}

		return null;
	}

	/**
	 * A GET of the verify link only renders a button that POSTs back.
	 *
	 * @return void
	 */
	public function test_verify_get_renders_a_confirm_button_and_changes_nothing(): void {
		$notification = $this->notification( NotificationStatus::PENDING );
		$query        = array(
			'notification_id'       => (string) $notification->get_id(),
			'email_link_action_key' => $notification->get_verification_key( true ),
			'email_link_action'     => 'verify',
		);

		$page = $this->open_link( $query, 'GET' );

		$this->assertIsString( $page );
		$this->assertStringContainsString( '<form method="post">', $page );
		$this->assertStringContainsString( 'Confirm', $page );
		$fresh = Factory::get_notification( $notification->get_id() );
		$this->assertNotFalse( $fresh );
		$this->assertSame( NotificationStatus::PENDING, $fresh->get_status() );
	}

	/**
	 * A GET of the unsubscribe link renders the unsubscribe button.
	 *
	 * @return void
	 */
	public function test_unsubscribe_get_renders_an_unsubscribe_button(): void {
		$notification = $this->notification( NotificationStatus::ACTIVE );

		$page = $this->open_link(
			array(
				'notification_id'       => (string) $notification->get_id(),
				'email_link_action_key' => $notification->get_unsubscribe_key( true ),
				'email_link_action'     => 'unsubscribe',
			),
			'GET'
		);

		$this->assertIsString( $page );
		$this->assertStringContainsString( 'Unsubscribe', $page );
	}

	/**
	 * The button's POST (and a mail client's one-click POST) passes through
	 * to WooCommerce's own handler, as do unrelated requests.
	 *
	 * @return void
	 */
	public function test_post_and_unrelated_requests_pass_through(): void {
		$notification = $this->notification( NotificationStatus::ACTIVE );
		$query        = array(
			'notification_id'       => (string) $notification->get_id(),
			'email_link_action_key' => 'key',
			'email_link_action'     => 'unsubscribe',
		);

		$this->assertNull( $this->open_link( $query, 'POST' ) );
		$this->assertNull( $this->open_link( array( 'p' => '1' ), 'GET' ) );
		$this->assertNull( $this->open_link( array_merge( $query, array( 'email_link_action' => 'other' ) ), 'GET' ) );
	}

	/**
	 * The restock email advertises one-click unsubscribe with the very URL in
	 * its body, and that URL's key is the one WooCommerce will accept.
	 *
	 * Sent over SMTP: PHPMailer's mail() transport RFC 2047-encodes any header
	 * past ~45 characters, which mail clients don't decode in
	 * List-Unsubscribe (see docs/woocommerce.md).
	 *
	 * @return void
	 */
	public function test_restock_email_has_one_click_unsubscribe_headers(): void {
		( new Back_In_Stock_Links() )->init();
		add_action(
			'phpmailer_init',
			static function ( $mailer ): void {
				$mailer->Mailer = 'smtp'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
			}
		);
		$notification = $this->notification( NotificationStatus::ACTIVE );

		wc_get_container()->get( EmailManager::class )->send_stock_notification_email( $notification );

		$sent = tests_retrieve_phpmailer_instance()->get_sent();
		$this->assertNotFalse( $sent, 'The restock email should be sent.' );
		$this->assertMatchesRegularExpression( '/^List-Unsubscribe: <([^>]+)>\r?$/m', $sent->header );
		$this->assertStringContainsString( 'List-Unsubscribe-Post: List-Unsubscribe=One-Click', $sent->header );

		preg_match( '/^List-Unsubscribe: <([^>]+)>\r?$/m', $sent->header, $match );
		wp_parse_str( (string) wp_parse_url( html_entity_decode( $match[1] ), PHP_URL_QUERY ), $query );
		// Each restock email mints a fresh key, so a matching key means the
		// header reuses the body's link rather than building another.
		$this->assertStringContainsString( (string) $query['email_link_action_key'], $sent->body, 'Header and body must carry the same link.' );

		$fresh = Factory::get_notification( (int) $query['notification_id'] );
		$this->assertNotFalse( $fresh );
		$this->assertTrue( $fresh->check_unsubscribe_key( (string) $query['email_link_action_key'] ), 'The advertised key must still be valid.' );
	}

	/**
	 * The exporter returns an address's waitlist entries.
	 *
	 * @return void
	 */
	public function test_exporter_returns_waitlist_entries(): void {
		$notification = $this->notification( NotificationStatus::ACTIVE );

		$export = ( new Back_In_Stock_Privacy() )->export_personal_data( $notification->get_user_email() );

		$this->assertTrue( $export['done'] );
		$this->assertCount( 1, $export['data'] );
		$this->assertSame( 'Waitlisted Tee', $export['data'][0]['data'][0]['value'] );
		$this->assertSame( NotificationStatus::ACTIVE, $export['data'][0]['data'][1]['value'] );
	}
}
