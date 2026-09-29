<?php
/**
 * Back in Stock signup confirmation email (double opt-in).
 *
 * @package Aggressive_Apparel
 */

declare( strict_types=1 );

namespace Aggressive_Apparel\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Asks a new subscriber to confirm before any restock email is sent.
 *
 * Disabling this email in WooCommerce → Settings → Emails turns signups back
 * into single opt-in: Back_In_Stock activates them immediately rather than
 * leaving them pending with no way to confirm.
 */
class Back_In_Stock_Confirmation_Email extends \WC_Email {

	/**
	 * Product the subscriber asked about.
	 *
	 * @var \WC_Product|null
	 */
	public $product;

	/**
	 * Subscription token used in the confirm link.
	 *
	 * @var string
	 */
	public $token = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'back_in_stock_confirmation';
		$this->customer_email = true;
		$this->title          = __( 'Back in Stock confirmation', 'aggressive-apparel' );
		$this->description    = __( 'Asks shoppers to confirm a back-in-stock signup before they are notified. Disable to activate signups without confirmation.', 'aggressive-apparel' );
		$this->heading        = $this->get_default_heading();
		$this->subject        = $this->get_default_subject();
		$this->template_base  = AGGRESSIVE_APPAREL_DIR . '/templates/';
		$this->template_html  = 'emails/back-in-stock-confirmation.php';
		$this->template_plain = 'emails/back-in-stock-confirmation-plain.php';

		parent::__construct();

		$this->recipient = '';
	}

	/**
	 * Send the confirmation request.
	 *
	 * @param int    $product_id      Product ID.
	 * @param string $recipient_email Subscriber email.
	 * @param string $token           Subscription token.
	 * @return bool True when the message was handed off to wp_mail.
	 */
	public function trigger( $product_id, $recipient_email = '', $token = '' ): bool {
		$this->recipient = $recipient_email;
		$this->token     = $token;
		$product         = wc_get_product( $product_id );
		$this->product   = $product instanceof \WC_Product ? $product : null;

		if ( ! $this->product || ! $this->recipient || '' === $this->token ) {
			return false;
		}

		$this->placeholders['{product_name}'] = $this->product->get_name();

		return (bool) $this->send(
			$this->get_recipient(),
			$this->get_subject(),
			$this->get_content(),
			$this->get_headers(),
			$this->get_attachments()
		);
	}

	/**
	 * Template arguments shared by the HTML and plain versions.
	 *
	 * @return array<string, mixed>
	 */
	private function template_args(): array {
		return array(
			'product'       => $this->product,
			'email_heading' => $this->get_heading(),
			'confirm_url'   => Back_In_Stock_Links::confirm_url( $this->token ),
			'email'         => $this,
		);
	}

	/**
	 * HTML content.
	 *
	 * @return string
	 */
	public function get_content_html(): string {
		return wc_get_template_html( $this->template_html, $this->template_args(), '', $this->template_base );
	}

	/**
	 * Plain text content.
	 *
	 * @return string
	 */
	public function get_content_plain(): string {
		return wc_get_template_html( $this->template_plain, $this->template_args(), '', $this->template_base );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading(): string {
		return __( 'Confirm your back-in-stock alert', 'aggressive-apparel' );
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject(): string {
		return __( 'Confirm your alert for {product_name}', 'aggressive-apparel' );
	}
}
