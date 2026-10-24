<?php
/**
 * WooCommerce Back in Stock Notifications (Internal namespace) signatures for
 * static analysis.
 *
 * This file is loaded by PHPStan only and is not part of the theme runtime.
 * php-stubs/woocommerce-stubs omits WooCommerce's `Internal` namespace, so this
 * lists exactly the surface Back_In_Stock_Native calls — nothing more. It is
 * the contract: TestBackInStockNativeContract asserts every symbol here still
 * exists in the pinned WooCommerce, so an upstream rename fails CI instead of
 * the storefront.
 */

namespace Automattic\WooCommerce\Internal\StockNotifications {

	class StockNotifications {
		public const FEATURE_NAME = 'customer_stock_notifications';
	}

	class Config {
		public static function allows_signups(): bool {}
		public static function requires_double_opt_in(): bool {}
		public static function requires_account(): bool {}
		public static function get_unverified_deletion_days_threshold(): int {}
	}

	class Notification extends \WC_Data {
		public function get_product_id( string $context = 'view' ): int {}
		public function get_status( string $context = 'view' ): string {}
		public function get_date_created( string $context = 'view' ): ?\WC_DateTime {}
		public function get_product_name(): string {}
		public function set_product_id( int $product_id ): void {}
		public function set_user_id( int $user_id ): void {}
		public function set_user_email( string $user_email ): void {}
		public function set_status( string $status ): void {}
		/** @param string|int|\WC_DateTime|null $date_confirmed */
		public function set_date_confirmed( $date_confirmed ): void {}
	}

	class NotificationQuery {
		/**
		 * @param array<string, mixed> $args
		 * @return array<int, int|Notification>
		 */
		public static function get_notifications( array $args ): array {}
		/** @param array<string, mixed> $args */
		public static function count_notifications( array $args ): int {}
	}

	class Factory {
		/** @return Notification|false */
		public static function get_notification( int $notification_id ) {}
	}
}

namespace Automattic\WooCommerce\Internal\StockNotifications\Enums {

	class NotificationStatus {
		public const PENDING   = 'pending';
		public const ACTIVE    = 'active';
		public const SENT      = 'sent';
		public const CANCELLED = 'cancelled';
	}
}

namespace Automattic\WooCommerce\Internal\StockNotifications\Utilities {

	class EligibilityService {
		public function is_product_eligible( ?\WC_Product $product ): bool {}
		public function product_allows_signups( \WC_Product $product ): bool {}
		public function is_stock_status_eligible( string $stock_status ): bool {}
	}

	class EmailNormalizer {
		public static function normalize( string $email ): string {}
	}
}

namespace Automattic\WooCommerce\Internal\StockNotifications\Frontend {

	class SignupResult {
		public function get_code(): string {}
	}

	class SignupService {
		public const ERROR_FAILED = 'failed_to_signup';
		/**
		 * @param array<string, mixed> $source
		 * @return array{user_id: int, user_email: string, product_id: int, posted_attributes?: array<string, string>}|\WP_Error
		 */
		public function parse( array $source ) {}
		/**
		 * @param array<string, string> $posted_attributes
		 * @return SignupResult|\WP_Error
		 */
		public function signup( int $product_id, int $user_id, string $user_email, array $posted_attributes = array() ) {}
	}
}
