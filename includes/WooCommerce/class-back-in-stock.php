<?php
/**
 * Back in Stock Class
 *
 * The signup form for out-of-stock products and its AJAX endpoint. The
 * waitlist itself — storage, double opt-in, emails, sending, admin — is
 * WooCommerce's native Back in Stock Notifications (Back_In_Stock_Native);
 * WooCommerce's own form skips the block add-to-cart, so the theme renders
 * this one in its place.
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
	 * Maximum waiting signups (verified or not) per email address. Each
	 * double opt-in signup sends a verification email, so this caps how much
	 * mail one address can be made to receive.
	 *
	 * @var int
	 */
	private const MAX_SUBSCRIPTIONS_PER_EMAIL = 3;

	/**
	 * Maximum subscribe attempts per client IP in one rate-limit window.
	 *
	 * @var int
	 */
	private const RATE_LIMIT_MAX_ATTEMPTS = 10;

	/**
	 * Rate-limit window in seconds.
	 *
	 * @var int
	 */
	private const RATE_LIMIT_WINDOW = 600;

	/**
	 * Whether WooCommerce takes signups, resolved once per request.
	 *
	 * @var bool|null
	 */
	private ?bool $ready = null;

	/**
	 * Initialize hooks.
	 *
	 * This runs before `init`, when WooCommerce's feature flags can't be read
	 * yet, so each callback checks {@see self::ready()} instead.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_notices', array( $this, 'render_setup_notice' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'render_block_woocommerce/add-to-cart-with-options', array( $this, 'inject_subscribe_form' ), 10, 2 );
		add_filter( 'render_block_woocommerce/product-button', array( $this, 'inject_subscribe_form' ), 10, 2 );
		add_action( 'wp_footer', array( $this, 'output_interactivity_state' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_aa_stock_subscribe', array( $this, 'handle_subscribe' ) );
		add_action( 'wp_ajax_nopriv_aa_stock_subscribe', array( $this, 'handle_subscribe' ) );
	}

	/**
	 * Tell a store manager what WooCommerce setting the form still needs.
	 *
	 * The theme toggle only adds the form; without WooCommerce's feature and
	 * its signup switch there is no waitlist behind it, so nothing renders.
	 *
	 * @return void
	 */
	public function render_setup_notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( null === $screen || ! current_user_can( 'manage_woocommerce' ) || Back_In_Stock_Native::accepts_signups() ) {
			return;
		}
		if ( ! in_array( $screen->id, array( 'dashboard', 'plugins', 'woocommerce_page_wc-settings' ), true ) && ! str_contains( $screen->id, Feature_Settings::PAGE_SLUG ) ) {
			return;
		}

		if ( ! Back_In_Stock_Native::is_supported() ) {
			/* translators: %s: minimum WooCommerce version. */
			$message = sprintf( __( 'Back in Stock Notifications needs WooCommerce %s or later; the signup form is hidden until then.', 'aggressive-apparel' ), Back_In_Stock_Native::MIN_WC_VERSION );
			$url     = '';
		} elseif ( ! Back_In_Stock_Native::is_enabled() ) {
			$message = __( 'Back in Stock Notifications uses WooCommerce\'s own waitlist. Turn on "Back in Stock Notifications" under WooCommerce → Settings → Advanced → Features to show the signup form.', 'aggressive-apparel' );
			$url     = admin_url( 'admin.php?page=wc-settings&tab=advanced&section=features' );
		} else {
			$message = __( 'Back in Stock Notifications is on, but WooCommerce is not accepting signups. Allow signups under WooCommerce → Settings → Products → Customer stock notifications to show the form.', 'aggressive-apparel' );
			$url     = admin_url( 'admin.php?page=wc-settings&tab=products&section=customer_stock_notifications' );
		}

		printf(
			'<div class="notice notice-warning"><p><strong>Aggressive Apparel:</strong> %1$s%2$s</p></div>',
			esc_html( $message ),
			'' !== $url ? ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Open settings', 'aggressive-apparel' ) . '</a>' : ''
		);
	}

	/**
	 * Enqueue styles and register Interactivity API script module.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		// The form only renders on single products and the badge only on
		// listings (see maybe_replace_*), so other pages need neither.
		if ( is_admin() || ( ! $this->is_single_product() && ! $this->is_listing_page() ) || ! $this->ready() ) {
			return;
		}

		Asset_Loader::enqueue_feature_style(
			'aggressive-apparel-back-in-stock',
			'build/styles/woocommerce/back-in-stock'
		);

		// The listing badge is static markup; only the form is interactive.
		if ( ! $this->is_single_product() ) {
			return;
		}

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
		if ( ! $this->ready() ) {
			return $block_content;
		}

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
		if ( is_admin() || ! $this->is_single_product() || ! $this->ready() ) {
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
	 * Guests need no nonce: a page cache would serve an expired one, so they
	 * are covered by the per-IP limit, WooCommerce's per-email delay and the
	 * per-address cap instead.
	 *
	 * @return void
	 */
	public function handle_subscribe(): void {
		if ( is_user_logged_in() ) {
			check_ajax_referer( 'aa_stock_subscribe', 'nonce' );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Guests are nonce-free by design (see above); logged-in requests were verified.
		// WooCommerce signs logged-in customers up with their account address.
		$email      = is_user_logged_in() ? (string) wp_get_current_user()->user_email : sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$product_id = absint( $_POST['product_id'] ?? 0 );
		$consent    = ! empty( $_POST['consent'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! $this->ready() ) {
			wp_send_json_error( array( 'message' => __( 'This product is not available for stock notifications.', 'aggressive-apparel' ) ) );
		}

		if ( ! Rate_Limiter::allow( 'back_in_stock_subscribe', self::get_rate_limit_max_attempts(), self::get_rate_limit_window() ) ) {
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

		if ( ! $consent ) {
			wp_send_json_error( array( 'message' => __( 'You must agree to receive the notification.', 'aggressive-apparel' ) ) );
		}

		if ( Back_In_Stock_Native::count_open( $email ) >= self::MAX_SUBSCRIPTIONS_PER_EMAIL ) {
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

		$code    = Back_In_Stock_Native::signup( $product_id, $email );
		$success = array(
			// "Already joined" answers exactly like a new signup, so the
			// endpoint never reveals whether an address is on a waitlist.
			Back_In_Stock_Native::SUCCESS               => __( "We'll email you when this product is back in stock!", 'aggressive-apparel' ),
			Back_In_Stock_Native::ALREADY_JOINED        => __( "We'll email you when this product is back in stock!", 'aggressive-apparel' ),
			Back_In_Stock_Native::SUCCESS_DOUBLE_OPT_IN => __( 'Almost done! Check your inbox and confirm to get your alert.', 'aggressive-apparel' ),
			Back_In_Stock_Native::ALREADY_JOINED_DOUBLE_OPT_IN => __( 'Almost done! Check your inbox and confirm to get your alert.', 'aggressive-apparel' ),
		);
		if ( isset( $success[ $code ] ) ) {
			wp_send_json_success( array( 'message' => $success[ $code ] ) );
		}

		$errors = array(
			'rate_limited'     => __( 'Too many requests. Please wait a few minutes and try again.', 'aggressive-apparel' ),
			'invalid_email'    => __( 'Please enter a valid email address.', 'aggressive-apparel' ),
			'requires_account' => __( 'Please log in to get back-in-stock alerts.', 'aggressive-apparel' ),
			'invalid_product'  => __( 'This product is not available for stock notifications.', 'aggressive-apparel' ),
			'invalid_request'  => __( 'This product is not available for stock notifications.', 'aggressive-apparel' ),
		);
		wp_send_json_error(
			array( 'message' => $errors[ $code ] ?? __( 'Something went wrong. Please try again.', 'aggressive-apparel' ) ),
			'rate_limited' === $code ? 429 : null
		);
	}

	/**
	 * Whether WooCommerce takes signups (its feature on, signups allowed).
	 *
	 * @return bool
	 */
	private function ready(): bool {
		$this->ready ??= Back_In_Stock_Native::accepts_signups();

		return $this->ready;
	}

	/**
	 * Max subscribe attempts per client IP in one window.
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
	 * Rate-limit window in seconds.
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
	 * Single product page: replace the add-to-cart block with the form when
	 * the whole product is out of stock, or add a form for sold-out variations.
	 *
	 * @param string $block_content Block HTML.
	 * @return string
	 */
	private function maybe_replace_single_product( string $block_content ): string {
		if ( ! $this->is_single_product() ) {
			return $block_content;
		}

		$product = wc_get_product( get_the_ID() );
		if ( ! $product instanceof \WC_Product ) {
			return $block_content;
		}

		if ( Back_In_Stock_Native::product_accepts_signups( $product ) ) {
			return $this->render_form( $product->get_id(), false );
		}

		if ( $product->is_type( 'variable' ) && $this->has_sold_out_variation( $product ) ) {
			return $block_content . $this->render_form( $product->get_id(), true );
		}

		return $block_content;
	}

	/**
	 * Whether a variable product has a variation that takes signups.
	 *
	 * @param \WC_Product $product Variable product.
	 */
	private function has_sold_out_variation( \WC_Product $product ): bool {
		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( $variation instanceof \WC_Product && Back_In_Stock_Native::product_accepts_signups( $variation ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Render the signup form.
	 *
	 * The variation form sits after WooCommerce's add-to-cart form (it is a
	 * <form>, so it can't go inside) and follows the shopper's choice through
	 * the same `woocommerce/products` bindings WooCommerce's own markup uses:
	 * hidden until a variation is chosen, then shown only while that variation
	 * is out of stock, posting its ID. No theme script reads WooCommerce's
	 * private store.
	 *
	 * @param int  $product_id   Product ID.
	 * @param bool $by_variation Whether the form follows the chosen variation.
	 * @return string
	 */
	private function render_form( int $product_id, bool $by_variation ): string {
		$field_id = 'aa-bis-email-' . $product_id;
		// Guests can't sign up when the store requires an account; send them
		// to log in instead of showing a form that would only error.
		$account_url = ! is_user_logged_in() && Back_In_Stock_Native::requires_account() ? wc_get_page_permalink( 'myaccount' ) : '';

		ob_start();
		?>
		<div
			class="aa-bis<?php echo $by_variation ? ' aa-bis--variation' : ''; ?>"
			data-wp-interactive="aggressive-apparel/back-in-stock"
			data-wp-context='<?php echo wp_json_encode( array( 'productId' => $product_id ) ); ?>'
			<?php if ( $by_variation ) : ?>
				hidden
				data-wp-bind--hidden="woocommerce/products::!state.productVariationInContext"
			<?php endif; ?>
		>
			<div<?php echo $by_variation ? ' data-wp-bind--hidden="woocommerce/products::state.productVariationInContext.is_in_stock"' : ''; ?>>
				<p class="aa-bis__out-of-stock">
					<?php
					echo esc_html(
						$by_variation
							? __( 'This option is currently out of stock.', 'aggressive-apparel' )
							: __( 'This product is currently out of stock.', 'aggressive-apparel' )
					);
					?>
				</p>
				<?php if ( '' !== $account_url ) : ?>
					<p class="aa-bis__login">
						<a href="<?php echo esc_url( $account_url ); ?>"><?php esc_html_e( 'Log in to get an email when it is back in stock.', 'aggressive-apparel' ); ?></a>
					</p>
				<?php else : ?>
					<form class="aa-bis__form aggressive-apparel-stack aggressive-apparel-stack--md" data-wp-on--submit="actions.submit">
						<?php if ( $by_variation ) : ?>
							<input type="hidden" name="variation_id" data-wp-bind--value="woocommerce/products::state.productVariationInContext.id" />
						<?php endif; ?>
						<?php if ( is_user_logged_in() ) : ?>
							<p class="aa-bis__account"><?php esc_html_e( "We'll email the address on your account.", 'aggressive-apparel' ); ?></p>
						<?php else : ?>
							<div class="aa-bis__field aggressive-apparel-field">
								<label for="<?php echo esc_attr( $field_id ); ?>" class="screen-reader-text">
									<?php esc_html_e( 'Email address', 'aggressive-apparel' ); ?>
								</label>
								<input
									type="email"
									id="<?php echo esc_attr( $field_id ); ?>"
									class="aa-bis__input aggressive-apparel-field__input"
									placeholder="<?php esc_attr_e( 'Enter your email', 'aggressive-apparel' ); ?>"
									required
									data-wp-on--input="actions.clearMessages"
								/>
							</div>
						<?php endif; ?>
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
				<?php endif; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Archive: replace the product button with a "Notify Me" badge.
	 *
	 * @param string $block_content Block HTML.
	 * @return string
	 */
	private function maybe_replace_archive_button( string $block_content ): string {
		if ( ! $this->is_listing_page() ) {
			return $block_content;
		}

		$product = wc_get_product( get_the_ID() );
		if ( ! $product instanceof \WC_Product || ! Back_In_Stock_Native::product_accepts_signups( $product ) ) {
			return $block_content;
		}

		return sprintf(
			'<div class="aa-bis__badge"><span>%s</span></div>',
			esc_html( Feature_Settings::get_back_in_stock_button_text() )
		);
	}

	/**
	 * Whether the current request is a product listing.
	 *
	 * @return bool
	 */
	private function is_listing_page(): bool {
		return Product_Context::is_product_listing();
	}

	/**
	 * Whether the current request is a single product page.
	 *
	 * @return bool
	 */
	private function is_single_product(): bool {
		return function_exists( 'is_product' ) && is_product();
	}
}
