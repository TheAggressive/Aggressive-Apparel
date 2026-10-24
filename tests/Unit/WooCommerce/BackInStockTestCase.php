<?php
/**
 * Shared setup for tests running against WooCommerce's native Back in Stock
 * Notifications.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Automattic\WooCommerce\Internal\StockNotifications\Emails\EmailManager;
use Automattic\WooCommerce\Internal\StockNotifications\Enums\NotificationStatus;
use Automattic\WooCommerce\Internal\StockNotifications\NotificationQuery;
use Automattic\WooCommerce\Internal\StockNotifications\StockNotifications;
use WP_UnitTestCase;

/**
 * Turns WooCommerce's feature on for each test.
 *
 * WooCommerce boots the feature's services on `init`, long before a test can
 * enable it, and the test framework restores every hook after each test. So
 * each test re-runs the boot: it re-adds the data-store filter, and the email
 * manager's hooks are re-registered so the mailer knows the feature's emails.
 */
abstract class BackInStockTestCase extends WP_UnitTestCase {

	/**
	 * Email prefix that keeps this test's addresses apart.
	 *
	 * @var string
	 */
	protected string $email_prefix = '';

	/**
	 * Request-server values a test may change, as they were before it.
	 *
	 * @var array<string, mixed>
	 */
	private array $server = array();

	/**
	 * Enable the feature and signups (single opt-in by default).
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->server       = array_intersect_key( $_SERVER, array_flip( array( 'REQUEST_METHOD', 'REMOTE_ADDR' ) ) );
		$this->email_prefix = 'bis-' . substr( md5( wp_generate_uuid4() ), 0, 10 );
		update_option( 'woocommerce_feature_customer_stock_notifications_enabled', 'yes' );
		update_option( 'woocommerce_customer_stock_notifications_allow_signups', 'yes' );
		update_option( 'woocommerce_customer_stock_notifications_require_double_opt_in', 'no' );
		update_option( 'woocommerce_customer_stock_notifications_require_account', 'no' );

		wc_get_container()->get( StockNotifications::class )->maybe_init_services();
		wc_get_container()->get( EmailManager::class )->init();
		WC()->mailer()->init();
		reset_phpmailer_instance();
	}

	/**
	 * Reset request globals.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
		// Restore rather than unset: the test bootstrap sets REMOTE_ADDR, and
		// later suites (the search rate limit) depend on it.
		unset( $_SERVER['REQUEST_METHOD'], $_SERVER['REMOTE_ADDR'] );
		$_SERVER = array_merge( $_SERVER, $this->server );
		reset_phpmailer_instance();

		parent::tearDown();
	}

	/**
	 * A unique test address. WooCommerce validates with FILTER_VALIDATE_EMAIL,
	 * which caps the local part at 64 characters, so it stays short.
	 *
	 * @return string
	 */
	protected function new_email(): string {
		return $this->email_prefix . '-' . substr( md5( wp_generate_uuid4() ), 0, 12 ) . '@example.com';
	}

	/**
	 * An out-of-stock simple product.
	 *
	 * @return int Product ID.
	 */
	protected function sold_out_product(): int {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Waitlisted Tee' );
		$product->set_regular_price( '25' );
		$product->set_stock_status( 'outofstock' );

		return $product->save();
	}

	/**
	 * A variable product with size S in stock and M sold out.
	 *
	 * @return array{parent: int, in_stock: int, sold_out: int}
	 */
	protected function partly_sold_out_variable(): array {
		$attribute = new \WC_Product_Attribute();
		$attribute->set_name( 'Size' );
		$attribute->set_options( array( 'S', 'M' ) );
		$attribute->set_visible( true );
		$attribute->set_variation( true );

		$product = new \WC_Product_Variable();
		$product->set_name( 'Sized Tee' );
		$product->set_attributes( array( $attribute ) );
		$parent = $product->save();

		$ids = array();
		foreach ( array(
			'S' => 'instock',
			'M' => 'outofstock',
		) as $size => $status ) {
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $parent );
			$variation->set_attributes( array( 'size' => $size ) );
			$variation->set_regular_price( '25' );
			$variation->set_stock_status( $status );
			$ids[ $status ] = $variation->save();
		}
		\WC_Product_Variable::sync( $parent );

		return array(
			'parent'   => $parent,
			'in_stock' => $ids['instock'],
			'sold_out' => $ids['outofstock'],
		);
	}

	/**
	 * WooCommerce notification IDs for an address, optionally by status.
	 *
	 * @param string            $email  Email address.
	 * @param array<int,string> $status Statuses to match; all when empty.
	 * @return array<int, int>
	 */
	protected function notifications_for( string $email, array $status = array() ): array {
		$args = array( 'user_email' => $email );
		if ( array() !== $status ) {
			$args['status'] = $status;
		}

		return array_map( 'intval', NotificationQuery::get_notifications( $args ) );
	}

	/**
	 * Active (verified) notification statuses.
	 *
	 * @return array<int, string>
	 */
	protected static function active(): array {
		return array( NotificationStatus::ACTIVE );
	}
}
