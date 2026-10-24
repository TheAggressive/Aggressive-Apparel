<?php
/**
 * Back in stock signup endpoint and form tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Back_In_Stock;
use Automattic\WooCommerce\Internal\StockNotifications\Enums\NotificationStatus;
use Automattic\WooCommerce\Internal\StockNotifications\Factory;
use WPDieException;

/**
 * The theme form signs up through WooCommerce's native waitlist: one record
 * per signup, the same reply whether or not the address was already on it,
 * per-variation signups on variable products, and guests throttled by the
 * theme's real-IP limiter rather than WooCommerce's shared-proxy one.
 */
class TestBackInStockSignup extends BackInStockTestCase {

	/**
	 * Reset AJAX filters.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_all_filters( 'wp_doing_ajax' );
		remove_all_filters( 'wp_die_ajax_handler' );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * Submit the signup AJAX request and return the decoded JSON reply.
	 *
	 * @param int    $product_id Product or variation ID.
	 * @param string $email      Address to sign up.
	 * @param string $nonce      Optional nonce to send.
	 * @return array<string, mixed>
	 */
	private function subscribe( int $product_id, string $email, string $nonce = '' ): array {
		$_POST    = array(
			'email'      => $email,
			'product_id' => (string) $product_id,
			'consent'    => '1',
			'nonce'      => $nonce,
		);
		$_REQUEST = $_POST;
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static fn() => static function ( $message ): void {
				throw new WPDieException( is_scalar( $message ) ? (string) $message : '' );
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
	 * Render the add-to-cart block on a product's own page.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	private function render_add_to_cart( int $product_id ): string {
		$this->go_to( (string) get_permalink( $product_id ) );
		$GLOBALS['post'] = get_post( $product_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- get_the_ID() reads the loop's post.

		return ( new Back_In_Stock() )->inject_subscribe_form(
			'<form class="wp-block-woocommerce-add-to-cart-with-options">ADD TO CART</form>',
			array( 'blockName' => 'woocommerce/add-to-cart-with-options' )
		);
	}

	/**
	 * A guest signup becomes one active WooCommerce notification.
	 *
	 * @return void
	 */
	public function test_guest_signup_creates_a_native_notification(): void {
		$product = $this->sold_out_product();
		$email   = $this->new_email();

		$reply = $this->subscribe( $product, $email );

		$this->assertTrue( $reply['success'] ?? false, wp_json_encode( $reply ) );
		$ids = $this->notifications_for( $email, self::active() );
		$this->assertCount( 1, $ids );
		$notification = Factory::get_notification( $ids[0] );
		$this->assertNotFalse( $notification );
		$this->assertSame( $product, $notification->get_product_id() );
	}

	/**
	 * With double opt-in on, the signup waits on WooCommerce's verify email.
	 *
	 * @return void
	 */
	public function test_double_opt_in_signup_is_pending(): void {
		update_option( 'woocommerce_customer_stock_notifications_require_double_opt_in', 'yes' );
		$email = $this->new_email();

		$reply = $this->subscribe( $this->sold_out_product(), $email );

		$this->assertTrue( $reply['success'] ?? false, wp_json_encode( $reply ) );
		$this->assertStringContainsString( 'Check your inbox', (string) ( $reply['data']['message'] ?? '' ) );
		$this->assertCount( 1, $this->notifications_for( $email, array( NotificationStatus::PENDING ) ) );
	}

	/**
	 * Signing up again answers exactly like the first time and adds nothing,
	 * so the endpoint doesn't reveal who is on a waitlist.
	 *
	 * @return void
	 */
	public function test_repeat_signup_is_indistinguishable(): void {
		$product = $this->sold_out_product();
		$email   = $this->new_email();

		$first = $this->subscribe( $product, $email );
		// WooCommerce's per-email delay doesn't apply to an existing entry.
		$second = $this->subscribe( $product, $email );

		$this->assertSame( $first, $second );
		$this->assertCount( 1, $this->notifications_for( $email ) );
	}

	/**
	 * A sold-out size of an in-stock variable product takes its own signup.
	 *
	 * @return void
	 */
	public function test_sold_out_variation_takes_a_signup(): void {
		$ids   = $this->partly_sold_out_variable();
		$email = $this->new_email();

		$reply = $this->subscribe( $ids['sold_out'], $email );

		$this->assertTrue( $reply['success'] ?? false, wp_json_encode( $reply ) );
		$notification = Factory::get_notification( $this->notifications_for( $email )[0] ?? 0 );
		$this->assertNotFalse( $notification );
		$this->assertSame( $ids['sold_out'], $notification->get_product_id() );
	}

	/**
	 * An in-stock product or size is refused.
	 *
	 * @return void
	 */
	public function test_in_stock_variation_is_refused(): void {
		$ids   = $this->partly_sold_out_variable();
		$email = $this->new_email();

		$reply = $this->subscribe( $ids['in_stock'], $email );

		$this->assertFalse( $reply['success'] ?? true );
		$this->assertSame( array(), $this->notifications_for( $email ) );
	}

	/**
	 * One address can wait on only a few products at once.
	 *
	 * @return void
	 */
	public function test_open_signups_per_address_are_capped(): void {
		$email = $this->new_email();
		// Skip WooCommerce's per-email delay between this address's signups.
		add_filter(
			'woocommerce_customer_stock_notifications_signup_rate_limit_options',
			static fn( $options ) => array_merge( (array) $options, array( 'email_delay' => 0 ) )
		);

		for ( $i = 0; $i < 3; $i++ ) {
			$this->assertTrue( $this->subscribe( $this->sold_out_product(), $email )['success'] ?? false );
		}
		$reply = $this->subscribe( $this->sold_out_product(), $email );

		$this->assertFalse( $reply['success'] ?? true );
		$this->assertCount( 3, $this->notifications_for( $email ) );
	}

	/**
	 * Guests behind one proxy address aren't throttled as one client, while
	 * WooCommerce's per-email delay still holds.
	 *
	 * @return void
	 */
	public function test_shared_ip_guests_are_not_throttled_together(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		$product                = $this->sold_out_product();
		$first                  = $this->new_email();

		$this->assertTrue( $this->subscribe( $product, $first )['success'] ?? false );
		$this->assertTrue( $this->subscribe( $product, $this->new_email() )['success'] ?? false, 'A second shopper on the same proxy IP must not be told to wait.' );

		$again = $this->subscribe( $this->sold_out_product(), $first );
		$this->assertFalse( $again['success'] ?? true, 'The same address signing up again at once is still delayed.' );
	}

	/**
	 * Logged-in signups must carry the theme nonce.
	 *
	 * @return void
	 */
	public function test_logged_in_signup_requires_a_nonce(): void {
		$account = $this->new_email();
		wp_set_current_user( self::factory()->user->create( array( 'user_email' => $account ) ) );

		$reply = $this->subscribe( $this->sold_out_product(), $account );

		$this->assertArrayNotHasKey( 'success', $reply, 'check_ajax_referer() must end the request before any reply.' );
		$this->assertSame( array(), $this->notifications_for( $account ) );
	}

	/**
	 * A logged-in customer signs up with the account address.
	 *
	 * @return void
	 */
	public function test_logged_in_signup_uses_the_account_address(): void {
		$account = $this->new_email();
		wp_set_current_user( self::factory()->user->create( array( 'user_email' => $account ) ) );

		$reply = $this->subscribe( $this->sold_out_product(), $this->new_email(), wp_create_nonce( 'aa_stock_subscribe' ) );

		$this->assertTrue( $reply['success'] ?? false, wp_json_encode( $reply ) );
		$this->assertCount( 1, $this->notifications_for( $account ) );
	}

	/**
	 * A sold-out simple product's add-to-cart is replaced by the form.
	 *
	 * @return void
	 */
	public function test_sold_out_product_renders_the_form_instead_of_add_to_cart(): void {
		$html = $this->render_add_to_cart( $this->sold_out_product() );

		$this->assertStringNotContainsString( 'ADD TO CART', $html );
		$this->assertStringContainsString( 'aa-bis__form', $html );
		$this->assertStringNotContainsString( 'variation_id', $html );
	}

	/**
	 * A partly sold-out variable product keeps add-to-cart and gains a form
	 * that follows the chosen variation through WooCommerce's bindings.
	 *
	 * @return void
	 */
	public function test_partly_sold_out_variable_adds_a_variation_form(): void {
		$html = $this->render_add_to_cart( $this->partly_sold_out_variable()['parent'] );

		$this->assertStringStartsWith( '<form class="wp-block-woocommerce-add-to-cart-with-options">ADD TO CART</form>', $html );
		$this->assertStringContainsString( 'data-wp-bind--hidden="woocommerce/products::!state.productVariationInContext"', $html );
		$this->assertStringContainsString( 'data-wp-bind--hidden="woocommerce/products::state.productVariationInContext.is_in_stock"', $html );
		$this->assertStringContainsString( 'data-wp-bind--value="woocommerce/products::state.productVariationInContext.id"', $html );
	}

	/**
	 * Without WooCommerce taking signups there is no waitlist behind the
	 * form, so nothing renders and the endpoint refuses.
	 *
	 * @return void
	 */
	public function test_nothing_renders_while_woocommerce_refuses_signups(): void {
		update_option( 'woocommerce_customer_stock_notifications_allow_signups', 'no' );
		$product = $this->sold_out_product();
		$email   = $this->new_email();

		$this->assertSame( '<form class="wp-block-woocommerce-add-to-cart-with-options">ADD TO CART</form>', $this->render_add_to_cart( $product ) );
		$this->assertFalse( $this->subscribe( $product, $email )['success'] ?? true );
		$this->assertSame( array(), $this->notifications_for( $email ) );
	}

	/**
	 * When WooCommerce requires an account, guests get a login link instead
	 * of a form that would only fail.
	 *
	 * @return void
	 */
	public function test_account_required_shows_guests_a_login_link(): void {
		update_option( 'woocommerce_customer_stock_notifications_require_account', 'yes' );

		$html = $this->render_add_to_cart( $this->sold_out_product() );

		$this->assertStringContainsString( 'aa-bis__login', $html );
		$this->assertStringNotContainsString( 'aa-bis__form', $html );
	}
}
