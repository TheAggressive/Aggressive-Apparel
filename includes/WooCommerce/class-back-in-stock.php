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
				'nonce'        => is_user_logged_in() ? wp_create_nonce( 'aa_stock_subscribe' ) : '',
				'isSubmitting' => false,
				'isSuccess'    => false,
				'hasError'     => false,
				'errorMessage' => '',
				'i18n'         => array(
					'invalidEmail'    => __( 'Please enter a valid email address.', 'aggressive-apparel' ),
					'consentRequired' => __( 'You must agree to receive the notification.', 'aggressive-apparel' ),
					'successFallback' => __( 'Thanks! Check your inbox for next steps.', 'aggressive-apparel' ),
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
		// Guests are not nonce-checked: a nonce baked into a page-cached copy
		// expires and would reject every signup, and CSRF protection means
		// nothing for an anonymous form (rate limits and consent still apply).
		if ( is_user_logged_in() ) {
			check_ajax_referer( 'aa_stock_subscribe', 'nonce' );
		}

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

		// Subscription cap. Pending signups count, so never confirming can't be
		// used to get around it.
		global $wpdb;
		$table        = Back_In_Stock_Installer::get_table_name();
		$confirmation = $this->confirmation_email();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Subscription limits require current custom-table state before insertion.
		$open_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE email = %s AND status IN ('pending', 'active')",
				$table,
				$email
			)
		);

		if ( $open_count >= self::MAX_SUBSCRIPTIONS_PER_EMAIL ) {
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Duplicate prevention requires current custom-table state before insertion.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT status, unsubscribe_token FROM %i WHERE email = %s AND product_id = %d AND status IN ('pending', 'active') ORDER BY id DESC LIMIT 1",
				$table,
				$email,
				$product_id
			),
			ARRAY_A
		);

		if ( is_array( $existing ) ) {
			// Resend a pending confirmation (the attempt counter above throttles
			// this). Either way, answer exactly as for a new signup so the
			// response can't reveal whether an email is on a product's waitlist.
			if ( 'pending' === ( $existing['status'] ?? '' ) && null !== $confirmation ) {
				$confirmation->trigger( $product_id, $email, (string) ( $existing['unsubscribe_token'] ?? '' ) );
			}
			wp_send_json_success( array( 'message' => $this->subscription_message( null !== $confirmation ) ) );
		}

		$token = wp_generate_password( 64, false );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom subscription-table mutation through wpdb's typed insert API.
		$wpdb->insert(
			$table,
			array(
				'email'             => $email,
				'product_id'        => $product_id,
				'status'            => null !== $confirmation ? 'pending' : 'active',
				'consent'           => 1,
				'unsubscribe_token' => $token,
			),
			array( '%s', '%d', '%s', '%d', '%s' )
		);

		if ( ! $wpdb->insert_id ) {
			wp_send_json_error( array( 'message' => __( 'Something went wrong. Please try again.', 'aggressive-apparel' ) ) );
		}

		if ( null !== $confirmation && ! $confirmation->trigger( $product_id, $email, $token ) ) {
			// No confirmation email means no way to confirm; drop the row so the
			// shopper can retry instead of holding a dead pending signup.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Roll back the row inserted above.
			$wpdb->delete( $table, array( 'id' => $wpdb->insert_id ), array( '%d' ) );
			wp_send_json_error( array( 'message' => __( 'We could not send the confirmation email. Please try again.', 'aggressive-apparel' ) ) );
		}

		wp_send_json_success( array( 'message' => $this->subscription_message( null !== $confirmation ) ) );
	}

	/**
	 * The enabled signup-confirmation email, or null for single opt-in.
	 *
	 * Double opt-in is on unless the store disables this email in WooCommerce;
	 * then signups activate immediately rather than waiting on an email that
	 * will never be sent.
	 *
	 * @return Back_In_Stock_Confirmation_Email|null
	 */
	private function confirmation_email(): ?Back_In_Stock_Confirmation_Email {
		if ( ! function_exists( 'WC' ) ) {
			return null;
		}

		$emails = WC()->mailer()->get_emails();
		$email  = $emails['Back_In_Stock_Confirmation_Email'] ?? null;

		return $email instanceof Back_In_Stock_Confirmation_Email && $email->is_enabled() ? $email : null;
	}

	/**
	 * The message shown after a signup, new or existing.
	 *
	 * Identical for both cases so the endpoint doesn't leak whether an email is
	 * already on a given product's waitlist.
	 *
	 * @param bool $needs_confirmation Whether signups await email confirmation.
	 * @return string
	 */
	private function subscription_message( bool $needs_confirmation ): string {
		return $needs_confirmation
			? __( 'Almost done! Check your inbox and confirm to get your alert.', 'aggressive-apparel' )
			: __( "We'll email you when this product is back in stock!", 'aggressive-apparel' );
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
		$emails['Back_In_Stock_Email']              = new Back_In_Stock_Email();
		$emails['Back_In_Stock_Confirmation_Email'] = new Back_In_Stock_Confirmation_Email();
		return $emails;
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
					class="aa-bis__submit aggressive-apparel-button aggressive-apparel-button--primary wp-element-button"
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
