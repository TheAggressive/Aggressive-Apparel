<?php
/**
 * Back in stock double opt-in, email-link and page-cache tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Back_In_Stock;
use Aggressive_Apparel\WooCommerce\Back_In_Stock_Confirmation_Email;
use Aggressive_Apparel\WooCommerce\Back_In_Stock_Email;
use Aggressive_Apparel\WooCommerce\Back_In_Stock_Installer;
use Aggressive_Apparel\WooCommerce\Back_In_Stock_Links;
use Aggressive_Apparel\WooCommerce\Back_In_Stock_Privacy;
use WP_UnitTestCase;
use WPDieException;

/**
 * Signups stay pending until the address owner confirms; confirm and
 * unsubscribe links change state only on POST, so email security scanners that
 * prefetch links cannot act for the reader; guests need no nonce, since a page
 * cache would serve an expired one.
 */
class TestBackInStockDoubleOptIn extends WP_UnitTestCase {

	/**
	 * Email prefix used for test rows.
	 *
	 * @var string
	 */
	private string $email_prefix = '';

	/**
	 * Out-of-stock product that accepts signups.
	 *
	 * @var int
	 */
	private int $product_id = 0;

	/**
	 * Install the table, create the product, and register both emails.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->email_prefix = 'optin-' . wp_generate_uuid4();
		( new Back_In_Stock_Installer() )->maybe_install();

		$product = new \WC_Product_Simple();
		$product->set_name( 'Waitlisted Tee' );
		$product->set_stock_status( 'outofstock' );
		$product->save();
		$this->product_id = $product->get_id();

		// The mailer may already be built by earlier tests, before the
		// woocommerce_email_classes filter ran, so register the emails directly.
		$mailer = WC()->mailer();
		$mailer->emails['Back_In_Stock_Email']              = new Back_In_Stock_Email();
		$mailer->emails['Back_In_Stock_Confirmation_Email'] = new Back_In_Stock_Confirmation_Email();

		reset_phpmailer_instance();
	}

	/**
	 * Remove rows and request state.
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

		$_GET  = array();
		$_POST = array();
		unset( $_SERVER['REQUEST_METHOD'] );
		remove_all_filters( 'woocommerce_email_enabled_back_in_stock_confirmation' );
		remove_all_filters( 'wp_doing_ajax' );
		remove_all_filters( 'wp_die_ajax_handler' );
		reset_phpmailer_instance();

		parent::tearDown();
	}

	/**
	 * A unique test address.
	 *
	 * @return string
	 */
	private function new_email(): string {
		return $this->email_prefix . '-' . wp_generate_uuid4() . '@example.com';
	}

	/**
	 * Submit the signup AJAX request and return the decoded JSON reply.
	 *
	 * @param string $email Address to sign up.
	 * @param string $nonce Optional nonce to send.
	 * @return array<string, mixed>
	 */
	private function subscribe( string $email, string $nonce = '' ): array {
		$_POST = array(
			'email'      => $email,
			'product_id' => (string) $this->product_id,
			'consent'    => '1',
			'nonce'      => $nonce,
		);
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static fn() => static function ( $message ): void {
				throw new WPDieException( is_string( $message ) ? $message : '' );
			}
		);

		ob_start();
		try {
			( new Back_In_Stock() )->handle_subscribe();
		} catch ( WPDieException $e ) {
			// wp_send_json_*() ends the request via wp_die().
			unset( $e );
		}
		$output = (string) ob_get_clean();

		$reply = json_decode( $output, true );
		return is_array( $reply ) ? $reply : array( 'raw' => $output );
	}

	/**
	 * Read the row for an address.
	 *
	 * @param string $email Address.
	 * @return array{status: string, unsubscribe_token: string}|null
	 */
	private function row_for( string $email ): ?array {
		global $wpdb;
		$table = Back_In_Stock_Installer::get_table_name();

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Test assertion for custom table.
			$wpdb->prepare(
				"SELECT status, unsubscribe_token FROM {$table} WHERE email = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table name.
				$email
			),
			ARRAY_A
		);

		return is_array( $row )
			? array(
				'status'            => (string) $row['status'],
				'unsubscribe_token' => (string) $row['unsubscribe_token'],
			)
			: null;
	}

	/**
	 * Insert a row with a given status and creation time.
	 *
	 * @param string $status     Row status.
	 * @param string $created_at MySQL datetime.
	 * @return string The row's token.
	 */
	private function insert_row( string $status, string $created_at = '' ): string {
		global $wpdb;
		$token = wp_generate_password( 64, false );

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Test fixture for custom table.
			Back_In_Stock_Installer::get_table_name(),
			array(
				'email'             => $this->new_email(),
				'product_id'        => $this->product_id,
				'status'            => $status,
				'consent'           => 1,
				'unsubscribe_token' => $token,
				'created_at'        => '' !== $created_at ? $created_at : current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%d', '%s', '%s' )
		);

		return $token;
	}

	/**
	 * Status of the row holding a token.
	 *
	 * @param string $token Token.
	 * @return string
	 */
	private function status_by_token( string $token ): string {
		global $wpdb;
		$table = Back_In_Stock_Installer::get_table_name();

		return (string) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Test assertion for custom table.
			$wpdb->prepare(
				"SELECT status FROM {$table} WHERE unsubscribe_token = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table name.
				$token
			)
		);
	}

	/**
	 * Open an email link and return the rendered page.
	 *
	 * @param string              $param  Query arg.
	 * @param string              $token  Token.
	 * @param string              $method HTTP method.
	 * @param array<string,mixed> $post   POST body.
	 * @return string Page HTML passed to wp_die().
	 */
	private function open_link( string $param, string $token, string $method = 'GET', array $post = array() ): string {
		$_GET                      = array( $param => $token );
		$_POST                     = $post;
		$_SERVER['REQUEST_METHOD'] = $method;

		try {
			( new Back_In_Stock_Links() )->handle_request();
		} catch ( WPDieException $e ) {
			return $e->getMessage();
		}

		$this->fail( 'Email link requests must end with a rendered page.' );
	}

	/**
	 * A guest signup needs no nonce, stays pending, and emails a confirm link.
	 *
	 * @return void
	 */
	public function test_guest_signup_is_pending_and_sends_confirmation(): void {
		$email = $this->new_email();

		$reply = $this->subscribe( $email );

		$this->assertTrue( $reply['success'] ?? false, 'A guest without a nonce must be able to sign up.' );
		$row = $this->row_for( $email );
		$this->assertNotNull( $row );
		$this->assertSame( 'pending', $row['status'] );

		$sent = tests_retrieve_phpmailer_instance()->get_sent();
		$this->assertNotFalse( $sent, 'A confirmation email must be sent.' );
		$this->assertStringContainsString( $email, (string) $sent->to[0][0] );
		$this->assertStringContainsString( Back_In_Stock_Links::CONFIRM_PARAM . '=' . $row['unsubscribe_token'], (string) $sent->body );
	}

	/**
	 * A logged-in shopper is still nonce-checked.
	 *
	 * @return void
	 */
	public function test_logged_in_signup_still_requires_a_nonce(): void {
		wp_set_current_user( self::factory()->user->create() );
		$email = $this->new_email();

		$reply = $this->subscribe( $email, 'not-a-valid-nonce' );

		$this->assertArrayNotHasKey( 'success', $reply );
		$this->assertNull( $this->row_for( $email ) );
	}

	/**
	 * Disabling the confirmation email falls back to single opt-in.
	 *
	 * @return void
	 */
	public function test_disabled_confirmation_email_activates_immediately(): void {
		add_filter( 'woocommerce_email_enabled_back_in_stock_confirmation', '__return_false' );
		$email = $this->new_email();

		$reply = $this->subscribe( $email );

		$this->assertTrue( $reply['success'] ?? false );
		$this->assertSame( 'active', $this->row_for( $email )['status'] ?? '' );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent() );
	}

	/**
	 * Signing up again while pending resends the same link and answers the same.
	 *
	 * @return void
	 */
	public function test_repeat_pending_signup_resends_same_link(): void {
		$email = $this->new_email();
		$first = $this->subscribe( $email );
		$token = $this->row_for( $email )['unsubscribe_token'] ?? '';
		reset_phpmailer_instance();

		$second = $this->subscribe( $email );

		$this->assertSame( $first['data']['message'] ?? null, $second['data']['message'] ?? null );
		$sent = tests_retrieve_phpmailer_instance()->get_sent();
		$this->assertNotFalse( $sent );
		$this->assertStringContainsString( $token, (string) $sent->body );
	}

	/**
	 * Opening the confirm link (a GET, as a mail scanner would) changes nothing.
	 *
	 * @return void
	 */
	public function test_confirm_link_get_only_renders_a_button(): void {
		$token = $this->insert_row( 'pending' );

		$page = $this->open_link( Back_In_Stock_Links::CONFIRM_PARAM, $token );

		$this->assertStringContainsString( '<form method="post"', $page );
		$this->assertSame( 'pending', $this->status_by_token( $token ) );
	}

	/**
	 * Pressing the confirm button activates the signup.
	 *
	 * @return void
	 */
	public function test_confirm_post_activates_signup(): void {
		$token = $this->insert_row( 'pending' );

		$this->open_link( Back_In_Stock_Links::CONFIRM_PARAM, $token, 'POST' );

		$this->assertSame( 'active', $this->status_by_token( $token ) );
	}

	/**
	 * An unknown token renders the expired page and changes nothing.
	 *
	 * @return void
	 */
	public function test_unknown_confirm_token_renders_expired_page(): void {
		$page = $this->open_link( Back_In_Stock_Links::CONFIRM_PARAM, str_repeat( 'a', 64 ), 'POST' );

		$this->assertStringContainsString( 'invalid or has expired', $page );
	}

	/**
	 * Opening the unsubscribe link (a GET) changes nothing.
	 *
	 * @return void
	 */
	public function test_unsubscribe_link_get_only_renders_a_button(): void {
		$token = $this->insert_row( 'active' );

		$page = $this->open_link( Back_In_Stock_Links::UNSUBSCRIBE_PARAM, $token );

		$this->assertStringContainsString( '<form method="post"', $page );
		$this->assertSame( 'active', $this->status_by_token( $token ) );
	}

	/**
	 * Both the unsubscribe button and an RFC 8058 one-click POST unsubscribe.
	 *
	 * @return void
	 */
	public function test_unsubscribe_post_and_one_click_unsubscribe(): void {
		$button    = $this->insert_row( 'active' );
		$one_click = $this->insert_row( 'pending' );

		$this->open_link( Back_In_Stock_Links::UNSUBSCRIBE_PARAM, $button, 'POST' );
		$this->open_link( Back_In_Stock_Links::UNSUBSCRIBE_PARAM, $one_click, 'POST', array( 'List-Unsubscribe' => 'One-Click' ) );

		$this->assertSame( 'unsubscribed', $this->status_by_token( $button ) );
		$this->assertSame( 'unsubscribed', $this->status_by_token( $one_click ) );
	}

	/**
	 * Unconfirmed signups expire after the confirmation window; recent ones stay.
	 *
	 * @return void
	 */
	public function test_unconfirmed_signups_expire(): void {
		$stale = $this->insert_row( 'pending', gmdate( 'Y-m-d H:i:s', time() - 8 * DAY_IN_SECONDS ) );
		$fresh = $this->insert_row( 'pending' );

		( new Back_In_Stock_Privacy() )->cleanup_expired_subscriptions();

		$this->assertSame( '', $this->status_by_token( $stale ) );
		$this->assertSame( 'pending', $this->status_by_token( $fresh ) );
	}

	/**
	 * Restock emails carry the one-click unsubscribe headers.
	 *
	 * @return void
	 */
	public function test_restock_email_has_one_click_unsubscribe_headers(): void {
		$email                    = new Back_In_Stock_Email();
		$email->unsubscribe_token = str_repeat( 'b', 64 );

		$headers = $email->get_headers();

		$this->assertStringContainsString( 'List-Unsubscribe: <' . Back_In_Stock_Links::unsubscribe_url( $email->unsubscribe_token ) . '>', $headers );
		$this->assertStringContainsString( 'List-Unsubscribe-Post: List-Unsubscribe=One-Click', $headers );
	}
}
