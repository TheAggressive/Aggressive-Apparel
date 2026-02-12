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
		add_filter( 'render_block', array( $this, 'inject_subscribe_form' ), 10, 2 );
		add_action( 'wp_footer', array( $this, 'output_interactivity_state' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_aa_stock_subscribe', array( $this, 'handle_subscribe' ) );
		add_action( 'wp_ajax_nopriv_aa_stock_subscribe', array( $this, 'handle_subscribe' ) );

		// Stock change detection.
		add_action( 'woocommerce_product_set_stock_status', array( $this, 'maybe_send_notifications' ), 10, 3 );

		// Unsubscribe handler.
		add_action( 'init', array( $this, 'handle_unsubscribe' ) );

		// GDPR hooks.
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );

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

		$css_file = AGGRESSIVE_APPAREL_DIR . '/build/styles/woocommerce/back-in-stock.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'aggressive-apparel-back-in-stock',
				AGGRESSIVE_APPAREL_URI . '/build/styles/woocommerce/back-in-stock.css',
				array(),
				(string) filemtime( $css_file ),
			);
		}

		if ( function_exists( 'wp_register_script_module' ) ) {
			wp_register_script_module(
				'@aggressive-apparel/back-in-stock',
				AGGRESSIVE_APPAREL_URI . '/assets/interactivity/back-in-stock.js',
				array( '@wordpress/interactivity' ),
				AGGRESSIVE_APPAREL_VERSION,
			);
			wp_enqueue_script_module( '@aggressive-apparel/back-in-stock' );
		}
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

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'aggressive-apparel' ) ) );
		}

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'aggressive-apparel' ) ) );
		}

		if ( ! $consent ) {
			wp_send_json_error( array( 'message' => __( 'You must agree to receive the notification.', 'aggressive-apparel' ) ) );
		}

		// Rate limit check.
		global $wpdb;
		$table = Back_In_Stock_Installer::get_table_name();

		$active_count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE email = %s AND status = 'active'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
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
		$exists = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE email = %s AND product_id = %d AND status = 'active'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$email,
				$product_id
			)
		);

		if ( $exists > 0 ) {
			wp_send_json_success( array( 'message' => __( "You're already subscribed to this product.", 'aggressive-apparel' ) ) );
		}

		$token = wp_generate_password( 64, false );

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table.
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

		wp_send_json_success( array( 'message' => __( "We'll email you when this product is back in stock!", 'aggressive-apparel' ) ) );
	}

	/**
	 * Handle unsubscribe requests via GET parameter.
	 *
	 * @return void
	 */
	public function handle_unsubscribe(): void {
		if ( ! isset( $_GET['aa_unsubscribe'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Token-based unsubscribe, no nonce needed.
			return;
		}

		$token = sanitize_text_field( wp_unslash( $_GET['aa_unsubscribe'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $token ) ) {
			return;
		}

		global $wpdb;
		$table = Back_In_Stock_Installer::get_table_name();

		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
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
	public function maybe_send_notifications( int $product_id, string $new_status, $product ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WooCommerce hook signature.
		if ( 'instock' !== $new_status ) {
			return;
		}

		global $wpdb;
		$table = Back_In_Stock_Installer::get_table_name();

		$subscribers = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
			$wpdb->prepare(
				"SELECT id, email, unsubscribe_token FROM {$table} WHERE product_id = %d AND status = 'active' LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$product_id,
				self::BATCH_SIZE
			)
		);

		if ( empty( $subscribers ) ) {
			return;
		}

		$mailer = WC()->mailer();
		$emails = $mailer->get_emails();

		foreach ( $subscribers as $subscriber ) {
			if ( isset( $emails['Back_In_Stock_Email'] ) && $emails['Back_In_Stock_Email'] instanceof Back_In_Stock_Email ) {
				$emails['Back_In_Stock_Email']->trigger( $product_id, $subscriber->email, $subscriber->unsubscribe_token );
			}

			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
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

		// Schedule next batch if more remain.
		$remaining = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE product_id = %d AND status = 'active'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$product_id
			)
		);

		if ( $remaining > 0 ) {
			wp_schedule_single_event(
				time() + 60,
				'aggressive_apparel_bis_send_batch',
				array( $product_id )
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
	 * Export personal data for GDPR.
	 *
	 * @param string $email_address Email to export data for.
	 * @param int    $page          Page number.
	 * @return array Export data.
	 */
	public function export_personal_data( string $email_address, int $page = 1 ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress GDPR callback signature.
		global $wpdb;
		$table = Back_In_Stock_Installer::get_table_name();

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
			$wpdb->prepare(
				"SELECT product_id, status, created_at, notified_at FROM {$table} WHERE email = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
	public function erase_personal_data( string $email_address, int $page = 1 ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress GDPR callback signature.
		global $wpdb;
		$table = Back_In_Stock_Installer::get_table_name();

		$deleted = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
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

			<form class="aa-bis__form" data-wp-on--submit="actions.submit">
				<div class="aa-bis__field">
					<label for="aa-bis-email-<?php echo esc_attr( (string) $product_id ); ?>" class="screen-reader-text">
						<?php esc_html_e( 'Email address', 'aggressive-apparel' ); ?>
					</label>
					<input
						type="email"
						id="aa-bis-email-<?php echo esc_attr( (string) $product_id ); ?>"
						class="aa-bis__input"
						placeholder="<?php esc_attr_e( 'Enter your email', 'aggressive-apparel' ); ?>"
						required
						data-wp-on--input="actions.clearMessages"
					/>
				</div>

				<label class="aa-bis__consent">
					<input type="checkbox" required data-wp-on--change="actions.clearMessages" />
					<?php esc_html_e( 'I agree to receive a one-time email when this product is restocked.', 'aggressive-apparel' ); ?>
				</label>

				<button
					type="submit"
					class="aa-bis__submit"
					data-wp-class--is-loading="state.isSubmitting"
					data-wp-bind--disabled="state.isSubmitting"
				>
					<?php esc_html_e( 'Notify Me', 'aggressive-apparel' ); ?>
				</button>
			</form>

			<div class="aa-bis__messages" role="status" aria-live="polite">
				<p class="aa-bis__success" data-wp-bind--hidden="state.isNotSuccess" hidden data-wp-text="state.successMessage"></p>
				<p class="aa-bis__error" data-wp-bind--hidden="state.isNotError" hidden data-wp-text="state.errorMessage"></p>
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
			esc_html__( 'Notify Me', 'aggressive-apparel' )
		);
	}

	/**
	 * Check if current page is a product listing.
	 *
	 * @return bool
	 */
	private function is_listing_page(): bool {
		if ( ! function_exists( 'is_shop' ) ) {
			return false;
		}
		return is_shop() || is_product_category() || is_product_tag() || is_search();
	}
}
