<?php
/**
 * Exit Intent Email Capture Class
 *
 * Displays a modal popup when exit intent is detected, capturing
 * email addresses for newsletter/discount offers.
 *
 * @package Aggressive_Apparel
 * @since 1.51.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Core\Icons;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exit Intent
 *
 * Detects exit intent (mouse leaving viewport on desktop, rapid scroll-up
 * on mobile) and shows a configurable email capture popup. Follows the
 * established modal pattern with backdrop blur, scroll lock, and focus trap.
 *
 * @since 1.51.0
 */
class Exit_Intent {

	/**
	 * Option key for exit intent settings.
	 *
	 * @var string
	 */
	public const SETTINGS_OPTION = 'aggressive_apparel_exit_intent_settings';

	/**
	 * Option key for captured email addresses.
	 *
	 * @var string
	 */
	private const SUBSCRIBERS_OPTION = 'aa_exit_intent_subscribers';

	/**
	 * Maximum stored subscribers.
	 *
	 * @var int
	 */
	private const MAX_SUBSCRIBERS = 1000;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_modal' ) );

		// AJAX handlers for email submission.
		add_action( 'wp_ajax_aa_exit_intent_subscribe', array( $this, 'handle_subscribe' ) );
		add_action( 'wp_ajax_nopriv_aa_exit_intent_subscribe', array( $this, 'handle_subscribe' ) );

		// Admin settings.
		add_action( 'admin_init', array( $this, 'register_admin_settings' ) );
	}

	/**
	 * Enqueue CSS and register JS module.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! $this->should_show() ) {
			return;
		}

		$css_file = AGGRESSIVE_APPAREL_DIR . '/build/styles/woocommerce/exit-intent.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'aggressive-apparel-exit-intent',
				AGGRESSIVE_APPAREL_URI . '/build/styles/woocommerce/exit-intent.css',
				array(),
				(string) filemtime( $css_file ),
			);
		}

		if ( function_exists( 'wp_register_script_module' ) ) {
			wp_register_script_module(
				'@aggressive-apparel/exit-intent',
				AGGRESSIVE_APPAREL_URI . '/assets/interactivity/exit-intent.js',
				array(
					'@wordpress/interactivity',
					'@aggressive-apparel/scroll-lock',
					'@aggressive-apparel/helpers',
				),
				AGGRESSIVE_APPAREL_VERSION,
			);
			wp_enqueue_script_module( '@aggressive-apparel/exit-intent' );
		}
	}

	/**
	 * Render the exit intent modal shell in the footer.
	 *
	 * @return void
	 */
	public function render_modal(): void {
		if ( ! $this->should_show() ) {
			return;
		}

		if ( ! function_exists( 'wp_interactivity_state' ) ) {
			return;
		}

		$settings = $this->get_settings();

		wp_interactivity_state(
			'aggressive-apparel/exit-intent',
			array(
				'isOpen'         => false,
				'isSubmitting'   => false,
				'isSuccess'      => false,
				'hasError'       => false,
				'errorMessage'   => '',
				'successMessage' => '',
				'announcement'   => '',
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'aa_exit_intent' ),
				'reshowDays'     => (int) $settings['reshow_days'],
			)
		);

		$close_icon = Icons::get(
			'close',
			array(
				'width'       => 20,
				'height'      => 20,
				'aria-hidden' => 'true',
			)
		);
		?>
		<div id="aa-exit-intent"
			class="aa-exit-intent"
			data-wp-interactive="aggressive-apparel/exit-intent"
			data-wp-class--is-open="state.isOpen"
			data-wp-on--keydown="actions.handleKeydown"
			hidden>
			<div class="aa-exit-intent__backdrop" data-wp-on--click="actions.close"></div>
			<div class="aa-exit-intent__modal"
				role="dialog"
				aria-modal="true"
				aria-labelledby="aa-exit-intent-heading">
				<button class="aa-exit-intent__close"
					data-wp-on--click="actions.close"
					aria-label="<?php esc_attr_e( 'Close popup', 'aggressive-apparel' ); ?>">
					<?php echo $close_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG from Icons class. ?>
				</button>

				<div class="aa-exit-intent__content" data-wp-bind--hidden="state.isSuccess">
					<h2 id="aa-exit-intent-heading" class="aa-exit-intent__heading">
						<?php echo esc_html( (string) $settings['heading'] ); ?>
					</h2>
					<p class="aa-exit-intent__body"><?php echo esc_html( (string) $settings['body'] ); ?></p>
					<form class="aa-exit-intent__form" data-wp-on--submit="actions.submit">
						<input type="email"
							class="aa-exit-intent__input"
							placeholder="<?php esc_attr_e( 'Enter your email', 'aggressive-apparel' ); ?>"
							required />
						<button type="submit"
							class="aa-exit-intent__submit"
							data-wp-bind--disabled="state.isSubmitting"
							data-wp-class--is-loading="state.isSubmitting">
							<?php echo esc_html( (string) $settings['button_text'] ); ?>
						</button>
					</form>
					<p class="aa-exit-intent__error"
						data-wp-bind--hidden="!state.hasError"
						data-wp-text="state.errorMessage"></p>
				</div>

				<div class="aa-exit-intent__success" data-wp-bind--hidden="!state.isSuccess">
					<div class="aa-exit-intent__success-icon" aria-hidden="true">
						<?php
						echo Icons::get( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							'check',
							array(
								'width'  => 48,
								'height' => 48,
							)
						);
						?>
					</div>
					<p class="aa-exit-intent__success-message" data-wp-text="state.successMessage"></p>
				</div>
			</div>
		</div>

		<div class="aa-exit-intent__announcer screen-reader-text"
			data-wp-interactive="aggressive-apparel/exit-intent"
			role="status"
			aria-live="polite"
			data-wp-text="state.announcement"></div>
		<?php
	}

	/**
	 * Handle the AJAX email subscription.
	 *
	 * @return void
	 */
	public function handle_subscribe(): void {
		check_ajax_referer( 'aa_exit_intent', 'nonce' );

		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );

		if ( ! is_email( $email ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Please enter a valid email address.', 'aggressive-apparel' ) )
			);
		}

		/**
		 * Fires when an exit intent email is captured.
		 *
		 * Hook this to send to Mailchimp, Klaviyo, or other services.
		 *
		 * @since 1.51.0
		 *
		 * @param string $email Subscriber email address.
		 */
		do_action( 'aggressive_apparel_exit_intent_subscribe', $email );

		// Store locally for basic admin visibility.
		$subscribers = get_option( self::SUBSCRIBERS_OPTION, array() );

		if ( ! is_array( $subscribers ) ) {
			$subscribers = array();
		}

		if ( ! in_array( $email, $subscribers, true ) ) {
			$subscribers[] = $email;

			// Cap at MAX_SUBSCRIBERS to prevent unbounded growth.
			if ( count( $subscribers ) > self::MAX_SUBSCRIBERS ) {
				$subscribers = array_slice( $subscribers, -self::MAX_SUBSCRIBERS );
			}

			update_option( self::SUBSCRIBERS_OPTION, $subscribers, false );
		}

		$settings    = $this->get_settings();
		$success_msg = $settings['success_message'];

		wp_send_json_success( array( 'message' => $success_msg ) );
	}

	/**
	 * Register admin settings for exit intent configuration.
	 *
	 * @return void
	 */
	public function register_admin_settings(): void {
		register_setting(
			'aggressive_apparel_features_group',
			self::SETTINGS_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		add_settings_field(
			'exit_intent_settings',
			__( 'Exit Intent Settings', 'aggressive-apparel' ),
			array( $this, 'render_settings_fields' ),
			'aggressive-apparel-features',
			'aggressive_apparel_features_interactive',
		);
	}

	/**
	 * Sanitize exit intent settings.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, string|int> Sanitized settings.
	 */
	public function sanitize_settings( $input ): array {
		if ( ! is_array( $input ) ) {
			return $this->get_defaults();
		}

		return array(
			'heading'         => sanitize_text_field( $input['heading'] ?? '' ),
			'body'            => sanitize_text_field( $input['body'] ?? '' ),
			'button_text'     => sanitize_text_field( $input['button_text'] ?? '' ),
			'success_message' => sanitize_text_field( $input['success_message'] ?? '' ),
			'reshow_days'     => max( 1, min( 365, absint( $input['reshow_days'] ?? 7 ) ) ),
		);
	}

	/**
	 * Render the exit intent settings fields.
	 *
	 * @return void
	 */
	public function render_settings_fields(): void {
		$settings = $this->get_settings();
		$name     = self::SETTINGS_OPTION;

		printf(
			'<fieldset><legend class="screen-reader-text">%s</legend>',
			esc_html__( 'Exit Intent Settings', 'aggressive-apparel' )
		);

		$fields = array(
			'heading'         => __( 'Heading', 'aggressive-apparel' ),
			'body'            => __( 'Body Text', 'aggressive-apparel' ),
			'button_text'     => __( 'Button Text', 'aggressive-apparel' ),
			'success_message' => __( 'Success Message', 'aggressive-apparel' ),
		);

		foreach ( $fields as $key => $label ) {
			printf(
				'<p><label>%s<br><input type="text" name="%s[%s]" value="%s" class="regular-text" /></label></p>',
				esc_html( $label ),
				esc_attr( $name ),
				esc_attr( $key ),
				esc_attr( (string) $settings[ $key ] )
			);
		}

		printf(
			'<p><label>%s<br><input type="number" name="%s[reshow_days]" value="%d" min="1" max="365" class="small-text" /> %s</label></p>',
			esc_html__( 'Re-show After', 'aggressive-apparel' ),
			esc_attr( $name ),
			(int) $settings['reshow_days'],
			esc_html__( 'days', 'aggressive-apparel' )
		);

		echo '</fieldset>';
	}

	/**
	 * Get the current settings merged with defaults.
	 *
	 * @return array<string, string|int>
	 */
	private function get_settings(): array {
		$saved = get_option( self::SETTINGS_OPTION, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return array_merge( $this->get_defaults(), $saved );
	}

	/**
	 * Get default settings.
	 *
	 * @return array<string, string|int>
	 */
	private function get_defaults(): array {
		return array(
			'heading'         => __( 'Wait! Before you go...', 'aggressive-apparel' ),
			'body'            => __( 'Sign up and get 10% off your first order.', 'aggressive-apparel' ),
			'button_text'     => __( 'Get My Discount', 'aggressive-apparel' ),
			'success_message' => __( 'Check your inbox for your discount code.', 'aggressive-apparel' ),
			'reshow_days'     => 7,
		);
	}

	/**
	 * Check if the exit intent modal should render on this page.
	 *
	 * @return bool
	 */
	private function should_show(): bool {
		if ( is_admin() ) {
			return false;
		}

		// Never show on checkout — don't interrupt the purchase flow.
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return false;
		}

		// Show on shop, product, and cart pages.
		if ( function_exists( 'is_shop' ) && is_shop() ) {
			return true;
		}

		if ( function_exists( 'is_product_category' ) && is_product_category() ) {
			return true;
		}

		if ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
			return true;
		}

		if ( function_exists( 'is_product' ) && is_product() ) {
			return true;
		}

		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return true;
		}

		return false;
	}
}
