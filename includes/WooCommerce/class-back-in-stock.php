<?php
/**
 * Back in Stock Class
 *
 * Main feature class that handles the subscribe form on out-of-stock products,
 * processes AJAX subscriptions, sends notifications when products restock,
 * and handles GDPR compliance.
 *
 * @package Aggressive_Apparel
 * @since 1.18.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Assets\Asset_Loader;
use Aggressive_Apparel\Core\Rate_Limiter;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Back in Stock
 *
 * @since 1.18.0
 */
class Back_In_Stock {

	/**
	 * Maximum active subscriptions per email address.
	 *
	 * @var int
	 */
	private const MAX_SUBSCRIPTIONS_PER_EMAIL = 3;

	/**
	 * Maximum subscribe attempts during the rate-limit window.
	 *
	 * @var int
	 */
	private const RATE_LIMIT_MAX_ATTEMPTS = 10;

	/**
	 * Subscribe attempt rate-limit window in seconds.
	 *
	 * @var int
	 */
	private const RATE_LIMIT_WINDOW = 600;

	/**
	 * Maximum notifications to send per batch.
	 *
	 * @var int
	 */
	private const BATCH_SIZE = 50;

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
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'render_block_woocommerce/add-to-cart-with-options', array( $this, 'inject_subscribe_form' ), 10, 2 );
		add_filter( 'render_block_woocommerce/product-button', array( $this, 'inject_subscribe_form' ), 10, 2 );
		add_action( 'wp_footer', array( $this, 'output_interactivity_state' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_aa_stock_subscribe', array( $this, 'handle_subscribe' ) );
		add_action( 'wp_ajax_nopriv_aa_stock_subscribe', array( $this, 'handle_subscribe' ) );

		// Stock change detection.
		add_action( 'woocommerce_product_set_stock_status', array( $this, 'maybe_send_notifications' ), 10, 3 );

		// Continuation batches for products whose waitlist exceeds one batch.
		add_action( 'aggressive_apparel_bis_send_batch', array( $this, 'send_notification_batch' ), 10, 2 );

		// Discontinued products should not keep active waitlist requests.
		add_action( 'wp_trash_post', array( $this, 'cleanup_discontinued_product_subscriptions' ) );
		add_action( 'before_delete_post', array( $this, 'cleanup_discontinued_product_subscriptions' ) );

		// Unsubscribe handler.
		add_action( 'init', array( $this, 'handle_unsubscribe' ) );

		// Privacy/retention cleanup for completed rows; active requests remain.
		add_action( 'aggressive_apparel_bis_cleanup', array( $this, 'run_cleanup_expired_subscriptions' ) );
		if ( ! wp_next_scheduled( 'aggressive_apparel_bis_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'aggressive_apparel_bis_cleanup' );
		}

		// GDPR hooks.
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
		add_action( 'admin_init', array( $this, 'register_privacy_policy_content' ) );

		// WooCommerce email class.
		add_filter( 'woocommerce_email_classes', array( $this, 'register_email_class' ) );
	}

	/**
	 * Enqueue styles and register Interactivity API script module.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( is_admin() ) {
			return;
		}

		Asset_Loader::enqueue_feature_style(
			'aggressive-apparel-back-in-stock',
			'build/styles/woocommerce/back-in-stock'
		);

		Asset_Loader::enqueue_interactivity_module(
			'@aggressive-apparel/back-in-stock',
			'build/interactivity/back-in-stock'
		);
	}

	/**
	 * Inject the subscription form on out-of-stock product blocks.
	 *
	 * @param string $block_content Block HTML.
	 * @param array  $block         Block data.
	 * @return string Modified HTML.
	 */
	public function inject_subscribe_form( string $block_content, array $block ): string {
		$block_name = $block['blockName'] ?? '';

		// Single product: replace add-to-cart with form.
		if ( 'woocommerce/add-to-cart-with-options' === $block_name ) {
			return $this->maybe_replace_single_product( $block_content );
		}

		// Archive: replace product button with "Notify Me" badge.
		if ( 'woocommerce/product-button' === $block_name ) {
			return $this->maybe_replace_archive_button( $block_content );
		}

		return $block_content;
	}

	/**
	 * Output Interactivity API state.
	 *
	 * @return void
	 */
	public function output_interactivity_state(): void {
		if ( is_admin() || ! function_exists( 'wp_interactivity_state' ) ) {
			return;
		}

		wp_interactivity_state(
			'aggressive-apparel/back-in-stock',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'aa_stock_subscribe' ),
				'isSubmitting' => false,
				'isSuccess'    => false,
				'hasError'     => false,
				'errorMessage' => '',
				'i18n'         => array(
					'invalidEmail'    => __( 'Please enter a valid email address.', 'aggressive-apparel' ),
					'consentRequired' => __( 'You must agree to receive the notification.', 'aggressive-apparel' ),
					'successFallback' => __( "We'll email you when this product is back in stock!", 'aggressive-apparel' ),
					'errorFallback'   => __( 'Something went wrong. Please try again.', 'aggressive-apparel' ),
				),
			),
		);
	}

	/**
	 * Handle the AJAX subscription request.
	 *
	 * @return void
	 */
	public function handle_subscribe(): void {
		check_ajax_referer( 'aa_stock_subscribe', 'nonce' );

		$email      = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$product_id = absint( $_POST['product_id'] ?? 0 );
		$consent    = ! empty( $_POST['consent'] );

		if ( $this->is_rate_limited( $email ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Too many requests. Please wait a few minutes and try again.', 'aggressive-apparel' ) ),
				429
			);
		}

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'aggressive-apparel' ) ) );
		}

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'aggressive-apparel' ) ) );
		}

		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
		if ( ! $product || 'publish' !== get_post_status( $product->get_id() ) || $product->is_in_stock() ) {
			wp_send_json_error( array( 'message' => __( 'This product is not available for stock notifications.', 'aggressive-apparel' ) ) );
		}

		if ( ! $consent ) {
			wp_send_json_error( array( 'message' => __( 'You must agree to receive the notification.', 'aggressive-apparel' ) ) );
		}

		// Active subscription cap.
		global $wpdb;
		$table = Back_In_Stock_Installer::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Subscription limits require current custom-table state before insertion.
		$active_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE email = %s AND status = 'active'",
				$table,
				$email
			)
		);

		if ( $active_count >= self::MAX_SUBSCRIPTIONS_PER_EMAIL ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %d: max subscriptions. */
						__( 'You can only subscribe to %d products at a time.', 'aggressive-apparel' ),
						self::MAX_SUBSCRIPTIONS_PER_EMAIL
					),
				)
			);
		}

		// Check for existing active subscription.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Duplicate prevention requires current custom-table state before insertion.
		$exists = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE email = %s AND product_id = %d AND status = 'active'",
				$table,
				$email,
				$product_id
			)
		);

		if ( $exists > 0 ) {
			// Return the same message as a fresh subscription so the response can't
			// be used to probe whether an arbitrary email is on a product waitlist.
			wp_send_json_success( array( 'message' => $this->subscription_confirmation_message() ) );
		}

		$token = wp_generate_password( 64, false );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom subscription-table mutation through wpdb's typed insert API.
		$wpdb->insert(
			$table,
			array(
				'email'             => $email,
				'product_id'        => $product_id,
				'status'            => 'active',
				'consent'           => 1,
				'unsubscribe_token' => $token,
			),
			array( '%s', '%d', '%s', '%d', '%s' )
		);

		if ( ! $wpdb->insert_id ) {
			wp_send_json_error( array( 'message' => __( 'Something went wrong. Please try again.', 'aggressive-apparel' ) ) );
		}

		wp_send_json_success( array( 'message' => $this->subscription_confirmation_message() ) );
	}

	/**
	 * The confirmation message shown for both a new and an existing subscription.
	 *
	 * Kept identical for the two cases so the endpoint doesn't leak whether an
	 * email is already on a given product's waitlist.
	 *
	 * @return string
	 */
	private function subscription_confirmation_message(): string {
		return __( "We'll email you when this product is back in stock!", 'aggressive-apparel' );
	}

	/**
	 * Check and increment subscribe attempt counters.
	 *
	 * IP throttling uses the shared Cloudflare-aware rate limiter used by other
	 * public endpoints. Email throttling remains local because it is specific to
	 * this feature and stops a single inbox from being hammered across rotating
	 * IPs.
	 *
	 * @param string $email Submitted email address.
	 * @return bool
	 */
	private function is_rate_limited( string $email ): bool {
		$max    = self::get_rate_limit_max_attempts();
		$window = self::get_rate_limit_window();
		if ( is_email( $email ) ) {
			$email = strtolower( $email );
			if ( self::get_rate_limit_count( 'email', $email ) >= $max ) {
				return true;
			}
		}

		if ( ! Rate_Limiter::allow( 'back_in_stock_subscribe', $max, $window ) ) {
			return true;
		}

		if ( is_email( $email ) ) {
			self::increment_rate_limit_count( 'email', $email );
		}

		return false;
	}

	/**
	 * Get the current attempt count for a rate-limit identifier.
	 *
	 * @param string $scope      Identifier scope.
	 * @param string $identifier Identifier value.
	 * @return int
	 */
	private static function get_rate_limit_count( string $scope, string $identifier ): int {
		return (int) get_transient( self::get_rate_limit_key( $scope, $identifier ) );
	}

	/**
	 * Increment the attempt count for a rate-limit identifier.
	 *
	 * @param string $scope      Identifier scope.
	 * @param string $identifier Identifier value.
	 * @return void
	 */
	private static function increment_rate_limit_count( string $scope, string $identifier ): void {
		$key   = self::get_rate_limit_key( $scope, $identifier );
		$count = self::get_rate_limit_count( $scope, $identifier );

		set_transient( $key, $count + 1, self::get_rate_limit_window() );
	}

	/**
	 * Build a privacy-preserving transient key for a rate-limit identifier.
	 *
	 * @param string $scope      Identifier scope.
	 * @param string $identifier Identifier value.
	 * @return string
	 */
	private static function get_rate_limit_key( string $scope, string $identifier ): string {
		return 'aa_bis_rate_limit_' . hash( 'sha256', $scope . '|' . strtolower( $identifier ) );
	}


	/**
	 * Get the subscribe attempt limit.
	 *
	 * @return int
	 */
	private static function get_rate_limit_max_attempts(): int {
		return max(
			1,
			(int) apply_filters(
				'aggressive_apparel_back_in_stock_rate_limit_max_attempts',
				self::RATE_LIMIT_MAX_ATTEMPTS
			)
		);
	}

	/**
	 * Get the subscribe attempt window in seconds.
	 *
	 * @return int
	 */
	private static function get_rate_limit_window(): int {
		return max(
			60,
			(int) apply_filters(
				'aggressive_apparel_back_in_stock_rate_limit_window',
				self::RATE_LIMIT_WINDOW
			)
		);
	}

	/**
	 * Handle unsubscribe requests via GET parameter.
	 *
	 * @return void
	 */
	public function handle_unsubscribe(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The 64-character random, single-purpose bearer token authorizes anonymous email unsubscribe links.
		if ( ! isset( $_GET['aa_unsubscribe'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The unguessable token is verified in the conditional update below.
		$token = sanitize_text_field( wp_unslash( $_GET['aa_unsubscribe'] ) );
		if ( empty( $token ) ) {
			return;
		}

		global $wpdb;
		$table = Back_In_Stock_Installer::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Token-authorized custom-table mutation; only active rows can transition.
		$updated = $wpdb->update(
			$table,
			array( 'status' => 'unsubscribed' ),
			array(
				'unsubscribe_token' => $token,
				'status'            => 'active',
			),
			array( '%s' ),
			array( '%s', '%s' )
		);

		if ( $updated ) {
			$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url();
			wp_safe_redirect( add_query_arg( 'aa_unsubscribed', '1', $shop_url ) );
			exit;
		}
	}

	/**
	 * Send notifications when a product comes back in stock.
	 *
	 * @param int         $product_id Product ID.
	 * @param string      $new_status New stock status.
	 * @param \WC_Product $product    Product object.
	 * @return void
	 */
	public function maybe_send_notifications( int $product_id, string $new_status, $product ): void {
		unset( $product );

		if ( 'instock' !== $new_status ) {
			return;
		}

		$this->send_notification_batch( $product_id, 0 );
	}

	/**
	 * Deliver one bounded batch of restock notifications and schedule the next.
	 *
	 * Also registered as the `aggressive_apparel_bis_send_batch` handler so
	 * waitlists larger than one batch are fully drained across scheduled runs —
	 * without a handler the continuation event fired into the void and every
	 * subscriber past the first batch was never notified.
	 *
	 * Batches page forward by row id (`id > $after_id`) rather than always
	 * reading the first N active rows, so the run always terminates: a row is
	 * only marked `notified` when the email is actually handed off to wp_mail,
	 * and a send failure leaves the row `active` to be retried on the next
	 * restock instead of blocking the cursor forever.
	 *
	 * @param int $product_id Product whose subscribers should be notified.
	 * @param int $after_id   Only send to rows with a larger id (keyset cursor).
	 * @return void
	 */
	public function send_notification_batch( int $product_id, int $after_id = 0 ): void {
		if ( $product_id <= 0 ) {
			return;
		}

		// Only notify while the product is genuinely purchasable; it may have
		// flipped back out of stock between batches.
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		if ( ! $product instanceof \WC_Product || ! $product->is_in_stock() ) {
			return;
		}

		global $wpdb;
		$table = Back_In_Stock_Installer::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Stock worker requires a current bounded delivery batch; cached recipients risk duplicate/missed mail.
		$subscribers = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, email, unsubscribe_token FROM %i WHERE product_id = %d AND status = 'active' AND id > %d ORDER BY id ASC LIMIT %d",
				$table,
				$product_id,
				$after_id,
				self::BATCH_SIZE
			)
		);

		if ( empty( $subscribers ) ) {
			return;
		}

		// Prime the post cache so the email trigger's wc_get_product() call is a cache hit.
		_prime_post_caches( array( $product_id ), false, false );

		$mailer = WC()->mailer();
		$emails = $mailer->get_emails();
		$email  = ( isset( $emails['Back_In_Stock_Email'] ) && $emails['Back_In_Stock_Email'] instanceof Back_In_Stock_Email )
			? $emails['Back_In_Stock_Email']
			: null;

		$last_id = $after_id;
		foreach ( $subscribers as $subscriber ) {
			$last_id = (int) $subscriber->id;

			$sent = $email instanceof Back_In_Stock_Email
				&& $email->trigger( $product_id, $subscriber->email, $subscriber->unsubscribe_token );

			if ( ! $sent ) {
				// Leave the row active so a later restock retries it, rather than
				// marking it delivered when nothing was sent.
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Persist delivery state immediately to prevent repeat notifications.
			$wpdb->update(
				$table,
				array(
					'status'      => 'notified',
					'notified_at' => current_time( 'mysql' ),
				),
				array( 'id' => $subscriber->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}

		// A full page means more rows may exist beyond this cursor; continue past
		// the last id we touched so the run advances and terminates.
		if ( count( $subscribers ) === self::BATCH_SIZE ) {
			wp_schedule_single_event(
				time() + 60,
				'aggressive_apparel_bis_send_batch',
				array( $product_id, $last_id )
			);
		}
	}

	/**
	 * Register the WooCommerce email class.
	 *
	 * @param array $emails Existing email classes.
	 * @return array Modified email classes.
	 */
	public function register_email_class( array $emails ): array {
		$emails['Back_In_Stock_Email'] = new Back_In_Stock_Email();
		return $emails;
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

		$content = wp_kses_post(
			wpautop(
				sprintf(
					/* translators: %s: retention policy sentence. */
					__( 'We use your email address to notify you when a requested product, size, or color is available again. Because some products are produced through print-on-demand suppliers, availability may depend on supplier stock, production capacity, or product status. Active notification requests are kept until the item becomes available, you unsubscribe, or the product is discontinued. %s', 'aggressive-apparel' ),
					$retention_text
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
	 * Delete active subscriptions for products that are trashed or deleted.
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
		$wpdb->delete(
			$table,
			array(
				'product_id' => $post_id,
				'status'     => 'active',
			),
			array( '%d', '%s' )
		);
	}

	/**
	 * Delete completed subscriptions older than the configured retention window.
	 *
	 * Active waitlist rows are intentionally retained so customers still receive
	 * the notification they requested. A retention value of 0 disables cleanup.
	 *
	 * @return int Number of deleted rows.
	 */
	public function cleanup_expired_subscriptions(): int {
		$retention_days = self::get_retention_days();
		if ( $retention_days <= 0 ) {
			return 0;
		}

		global $wpdb;
		$table  = Back_In_Stock_Installer::get_table_name();
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

		return false === $deleted ? 0 : (int) $deleted;
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

	/**
	 * Replace add-to-cart block with subscription form on single product pages.
	 *
	 * @param string $block_content Original block content.
	 * @return string Modified content.
	 */
	private function maybe_replace_single_product( string $block_content ): string {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return $block_content;
		}

		$product = wc_get_product( get_the_ID() );
		if ( ! $product || $product->is_in_stock() ) {
			return $block_content;
		}

		$product_id = $product->get_id();

		ob_start();
		?>
		<div
			class="aa-bis"
			data-wp-interactive="aggressive-apparel/back-in-stock"
			data-wp-context='<?php echo wp_json_encode( array( 'productId' => $product_id ) ); ?>'
		>
			<p class="aa-bis__out-of-stock"><?php esc_html_e( 'This product is currently out of stock.', 'aggressive-apparel' ); ?></p>

			<form class="aa-bis__form aggressive-apparel-stack aggressive-apparel-stack--md" data-wp-on--submit="actions.submit">
				<div class="aa-bis__field aggressive-apparel-field">
					<label for="aa-bis-email-<?php echo esc_attr( (string) $product_id ); ?>" class="screen-reader-text">
						<?php esc_html_e( 'Email address', 'aggressive-apparel' ); ?>
					</label>
					<input
						type="email"
						id="aa-bis-email-<?php echo esc_attr( (string) $product_id ); ?>"
						class="aa-bis__input aggressive-apparel-field__input"
						placeholder="<?php esc_attr_e( 'Enter your email', 'aggressive-apparel' ); ?>"
						required
						data-wp-on--input="actions.clearMessages"
					/>
				</div>

				<label class="aa-bis__consent aggressive-apparel-field aggressive-apparel-field--checkbox">
					<input type="checkbox" required data-wp-on--change="actions.clearMessages" />
					<?php esc_html_e( 'I agree to receive a one-time email when this product is restocked.', 'aggressive-apparel' ); ?>
				</label>

				<button
					type="submit"
					class="aa-bis__submit aggressive-apparel-button aggressive-apparel-button--accent wp-element-button"
					data-wp-class--is-loading="state.isSubmitting"
					data-wp-bind--disabled="state.isSubmitting"
				>
					<?php echo esc_html( Feature_Settings::get_back_in_stock_button_text() ); ?>
				</button>
			</form>

			<div class="aa-bis__messages" role="status" aria-live="polite">
				<p class="aa-bis__success aggressive-apparel-message aggressive-apparel-message--success" data-wp-bind--hidden="state.isNotSuccess" hidden data-wp-text="state.successMessage"></p>
				<p class="aa-bis__error aggressive-apparel-message aggressive-apparel-message--error" data-wp-bind--hidden="state.isNotError" hidden data-wp-text="state.errorMessage"></p>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Replace archive product button with "Notify Me" badge when out of stock.
	 *
	 * @param string $block_content Original block content.
	 * @return string Modified content.
	 */
	private function maybe_replace_archive_button( string $block_content ): string {
		if ( ! $this->is_listing_page() ) {
			return $block_content;
		}

		$product = wc_get_product( get_the_ID() );
		if ( ! $product || $product->is_in_stock() ) {
			return $block_content;
		}

		return sprintf(
			'<div class="aa-bis__badge"><span>%s</span></div>',
			esc_html( Feature_Settings::get_back_in_stock_button_text() )
		);
	}

	/**
	 * Check if current page is a product listing.
	 *
	 * @return bool
	 */
	private function is_listing_page(): bool {
		return Product_Context::is_product_listing();
	}
}
