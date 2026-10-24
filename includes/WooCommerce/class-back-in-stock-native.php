<?php
/**
 * Back in Stock: adapter onto WooCommerce's native stock notifications.
 *
 * WooCommerce (11.2+) owns the waitlist — storage, double opt-in, the verify /
 * restock / unsubscribe emails, Action Scheduler sending, the admin screens,
 * My Account management, retention and erasure. The theme only adds what a
 * block product page lacks: the signup form itself (WooCommerce's own form
 * skips `woocommerce/add-to-cart-with-options`), scanner-safe email links, and
 * a privacy exporter.
 *
 * Every call into WooCommerce's `Internal` namespace goes through this class.
 * That namespace carries no compatibility promise, so the surface is kept
 * small, listed in stubs/woocommerce-stock-notifications.php, and checked
 * against the pinned WooCommerce by TestBackInStockNativeContract.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Automattic\WooCommerce\Internal\StockNotifications\Config;
use Automattic\WooCommerce\Internal\StockNotifications\Enums\NotificationStatus;
use Automattic\WooCommerce\Internal\StockNotifications\Factory;
use Automattic\WooCommerce\Internal\StockNotifications\Frontend\SignupService;
use Automattic\WooCommerce\Internal\StockNotifications\Notification;
use Automattic\WooCommerce\Internal\StockNotifications\NotificationQuery;
use Automattic\WooCommerce\Internal\StockNotifications\StockNotifications;
use Automattic\WooCommerce\Internal\StockNotifications\Utilities\EligibilityService;
use Automattic\WooCommerce\Internal\StockNotifications\Utilities\EmailNormalizer;
use Automattic\WooCommerce\Utilities\FeaturesUtil;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The theme's only door into WooCommerce's stock-notification internals.
 */
final class Back_In_Stock_Native {

	/**
	 * First WooCommerce release whose API matches this adapter: the in-block
	 * form skip, the signup rate limiter and NotificationQuery counts.
	 */
	public const MIN_WC_VERSION = '11.2.0';

	/**
	 * Emails that carry an unsubscribe link: the restock alert, and the
	 * "you're on the list" email sent after verification.
	 */
	public const UNSUBSCRIBE_EMAIL_IDS = array( 'customer_stock_notification', 'customer_stock_notification_verified' );

	/**
	 * Values of the `email_link_action` query arg on WooCommerce's email links.
	 */
	public const LINK_ACTIONS = array( 'verify', 'unsubscribe' );

	/**
	 * Signup code: an existing waitlist entry already covers this signup.
	 */
	public const ALREADY_JOINED = 'already_joined';

	/**
	 * Signup code: the address is on the waitlist but still unverified.
	 */
	public const ALREADY_JOINED_DOUBLE_OPT_IN = 'already_joined_double_opt_in';

	/**
	 * Signup code: joined; the restock email will follow.
	 */
	public const SUCCESS = 'success';

	/**
	 * Signup code: joined; a verification email was sent first.
	 */
	public const SUCCESS_DOUBLE_OPT_IN = 'success_double_opt_in';

	/**
	 * Whether the running WooCommerce has the API this adapter targets.
	 */
	public static function is_supported(): bool {
		return defined( 'WC_VERSION' )
			&& version_compare( (string) WC_VERSION, self::MIN_WC_VERSION, '>=' )
			&& class_exists( SignupService::class )
			&& class_exists( FeaturesUtil::class );
	}

	/**
	 * Whether the store turned on WooCommerce's Back in Stock Notifications
	 * feature (WooCommerce → Settings → Advanced → Features).
	 */
	public static function is_enabled(): bool {
		return self::is_supported() && FeaturesUtil::feature_is_enabled( StockNotifications::FEATURE_NAME );
	}

	/**
	 * Whether shoppers may sign up right now: the feature is on and the store
	 * allows signups (WooCommerce → Settings → Products → Customer stock
	 * notifications).
	 */
	public static function accepts_signups(): bool {
		return self::is_enabled() && Config::allows_signups();
	}

	/**
	 * Whether WooCommerce only takes signups from logged-in customers.
	 */
	public static function requires_account(): bool {
		return Config::requires_account();
	}

	/**
	 * Whether a signup waits on an email verification link.
	 */
	public static function requires_double_opt_in(): bool {
		return Config::requires_double_opt_in();
	}

	/**
	 * Days before WooCommerce deletes an unverified signup; 0 keeps it.
	 */
	public static function unverified_retention_days(): int {
		return Config::get_unverified_deletion_days_threshold();
	}

	/**
	 * Whether a product or variation can take a signup now: a supported type
	 * and status, not opted out on the product, and not in stock.
	 *
	 * @param \WC_Product $product Product or variation.
	 */
	public static function product_accepts_signups( \WC_Product $product ): bool {
		$eligibility = self::service( EligibilityService::class );

		return $eligibility instanceof EligibilityService
			&& $eligibility->is_product_eligible( $product )
			&& ! $eligibility->is_stock_status_eligible( $product->get_stock_status() )
			&& $eligibility->product_allows_signups( $product );
	}

	/**
	 * Sign the current visitor up, exactly as WooCommerce's own form does.
	 *
	 * Logged-in customers sign up with their account address; `$email` only
	 * applies to guests.
	 *
	 * @param int    $product_id Product or variation ID.
	 * @param string $email      Address a guest entered.
	 * @return string A SignupResult code on success, else the WP_Error code.
	 */
	public static function signup( int $product_id, string $email ): string {
		$service = self::service( SignupService::class );
		if ( ! $service instanceof SignupService ) {
			return SignupService::ERROR_FAILED;
		}

		// The theme endpoint already throttles guests per real client IP
		// (Rate_Limiter is Cloudflare-aware). WooCommerce's per-client delay
		// keys on raw REMOTE_ADDR, which behind a proxy is one address shared
		// by every shopper, so it is off for this call only; its per-email and
		// per-account delays still apply.
		$no_ip_delay = static function ( $options ) {
			if ( is_array( $options ) && ! is_user_logged_in() ) {
				$options['client_delay'] = 0;
			}
			return $options;
		};
		add_filter( 'woocommerce_customer_stock_notifications_signup_rate_limit_options', $no_ip_delay );

		try {
			$data = $service->parse(
				array(
					'wc_bis_email'      => $email,
					'wc_bis_product_id' => $product_id,
				)
			);
			if ( is_wp_error( $data ) ) {
				return (string) $data->get_error_code();
			}

			$result = $service->signup( $data['product_id'], $data['user_id'], $data['user_email'], $data['posted_attributes'] ?? array() );

			return is_wp_error( $result ) ? (string) $result->get_error_code() : $result->get_code();
		} catch ( \Throwable $e ) {
			wc_get_logger()->error( $e->getMessage(), array( 'source' => 'aggressive-apparel-back-in-stock' ) );
			return SignupService::ERROR_FAILED;
		} finally {
			remove_filter( 'woocommerce_customer_stock_notifications_signup_rate_limit_options', $no_ip_delay );
		}
	}

	/**
	 * Waitlist entries an address holds that are still waiting: verified or
	 * awaiting verification.
	 *
	 * @param string $email Email address.
	 */
	public static function count_open( string $email ): int {
		$email = EmailNormalizer::normalize( $email );
		if ( '' === $email ) {
			return 0;
		}

		return NotificationQuery::count_notifications(
			array(
				'user_email' => $email,
				'status'     => array( NotificationStatus::ACTIVE, NotificationStatus::PENDING ),
			)
		);
	}

	/**
	 * One page of an address's waitlist entries, for the privacy exporter.
	 *
	 * @param string $email  Email address.
	 * @param int    $limit  Page size.
	 * @param int    $offset Rows to skip.
	 * @return list<array{id: int, product: string, status: string, created: string}>
	 */
	public static function entries_for_email( string $email, int $limit, int $offset ): array {
		$email = EmailNormalizer::normalize( $email );
		if ( '' === $email ) {
			return array();
		}

		$entries = array();
		$found   = NotificationQuery::get_notifications(
			array(
				'user_email' => $email,
				'order_by'   => array( 'id' => 'ASC' ),
				'limit'      => $limit,
				'offset'     => $offset,
			)
		);
		foreach ( $found as $id ) {
			$notification = $id instanceof Notification ? $id : Factory::get_notification( $id );
			if ( ! $notification instanceof Notification ) {
				continue;
			}
			$created   = $notification->get_date_created();
			$entries[] = array(
				'id'      => (int) $notification->get_id(),
				'product' => $notification->get_product_name(),
				'status'  => $notification->get_status(),
				'created' => $created instanceof \WC_DateTime ? $created->date( 'Y-m-d H:i:s' ) : '',
			);
		}

		return $entries;
	}

	/**
	 * Add an already-confirmed signup, as moved from the theme's old table.
	 * A no-op when the address is already waiting on the product.
	 *
	 * @param int    $product_id   Product or variation ID.
	 * @param string $email        Email address.
	 * @param string $confirmed_at When the signup was confirmed (MySQL datetime, UTC).
	 * @return bool Whether the address now waits on the product.
	 */
	public static function import_active( int $product_id, string $email, string $confirmed_at ): bool {
		$email = EmailNormalizer::normalize( $email );
		if ( $product_id <= 0 || '' === $email ) {
			return false;
		}

		$existing = NotificationQuery::count_notifications(
			array(
				'product_id' => array( $product_id ),
				'user_email' => $email,
				'status'     => array( NotificationStatus::ACTIVE, NotificationStatus::PENDING ),
			)
		);
		if ( $existing > 0 ) {
			return true;
		}

		$user         = get_user_by( 'email', $email );
		$notification = new Notification();
		$notification->set_product_id( $product_id );
		$notification->set_user_id( $user instanceof \WP_User ? $user->ID : 0 );
		$notification->set_user_email( $email );
		$notification->set_status( NotificationStatus::ACTIVE );
		$confirmed = strtotime( $confirmed_at . ' UTC' );
		$notification->set_date_confirmed( false !== $confirmed ? $confirmed : time() );
		$saved = $notification->save();

		return ! is_wp_error( $saved ) && (bool) $saved;
	}

	/**
	 * Resolve a WooCommerce container service, or null when it can't be built.
	 *
	 * @template T of object
	 * @param string $id Service class.
	 * @phpstan-param class-string<T> $id
	 * @return object|null
	 * @phpstan-return T|null
	 */
	private static function service( string $id ): ?object {
		try {
			$service = wc_get_container()->get( $id );
		} catch ( \Throwable $e ) {
			return null;
		}

		return $service instanceof $id ? $service : null;
	}
}
