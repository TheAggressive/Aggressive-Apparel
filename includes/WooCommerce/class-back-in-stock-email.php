<?php
/**
 * Back in Stock Email Class
 *
 * WooCommerce email that notifies subscribers when an out-of-stock product
 * becomes available again. Integrates with WooCommerce > Settings > Emails.
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
 * Back in Stock Email
 *
 * @since 1.18.0
 */
class Back_In_Stock_Email extends \WC_Email {

	/**
	 * Product being notified about.
	 *
	 * @var \WC_Product|null
	 */
	public $product;

	/**
	 * Unsubscribe token for the recipient.
	 *
	 * @var string
	 */
	public $unsubscribe_token = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'back_in_stock';
		$this->customer_email = true;
		$this->title          = __( 'Back in Stock', 'aggressive-apparel' );
		$this->description    = __( 'Sent to customers who subscribed to out-of-stock product notifications.', 'aggressive-apparel' );
		$this->heading        = __( 'Good news — {product_name} is back!', 'aggressive-apparel' );
		$this->subject        = __( '{product_name} is back in stock!', 'aggressive-apparel' );

		$this->template_base  = AGGRESSIVE_APPAREL_DIR . '/templates/';
		$this->template_html  = 'emails/back-in-stock.php';
		$this->template_plain = 'emails/back-in-stock-plain.php';

		// Call parent constructor.
		parent::__construct();

		// Remove default admin recipient since this is a customer email.
		$this->recipient = '';
	}

	/**
	 * Trigger the email.
	 *
	 * @param int    $product_id        Product ID.
	 * @param string $recipient_email   Recipient email address.
	 * @param string $unsubscribe_token Unsubscribe token.
	 * @return void
	 */
	public function trigger( $product_id, $recipient_email = '', $unsubscribe_token = '' ): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$this->recipient         = $recipient_email;
		$this->unsubscribe_token = $unsubscribe_token;
		$product                 = wc_get_product( $product_id );
		$this->product           = $product instanceof \WC_Product ? $product : null;

		if ( ! $this->product || ! $this->recipient ) {
			return;
		}

		$this->placeholders['{product_name}'] = $this->product->get_name();

		$this->send(
			$this->get_recipient(),
			$this->get_subject(),
			$this->get_content(),
			$this->get_headers(),
			$this->get_attachments()
		);
	}

	/**
	 * Get email HTML content.
	 *
	 * @return string
	 */
	public function get_content_html(): string {
		return wc_get_template_html(
			$this->template_html,
			array(
				'product'           => $this->product,
				'email_heading'     => $this->get_heading(),
				'unsubscribe_token' => $this->unsubscribe_token,
				'email'             => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Get email plain text content.
	 *
	 * @return string
	 */
	public function get_content_plain(): string {
		return wc_get_template_html(
			$this->template_plain,
			array(
				'product'           => $this->product,
				'email_heading'     => $this->get_heading(),
				'unsubscribe_token' => $this->unsubscribe_token,
				'email'             => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Get default heading.
	 *
	 * @return string
	 */
	public function get_default_heading(): string {
		return __( 'Good news — {product_name} is back!', 'aggressive-apparel' );
	}

	/**
	 * Get default subject.
	 *
	 * @return string
	 */
	public function get_default_subject(): string {
		return __( '{product_name} is back in stock!', 'aggressive-apparel' );
	}
}
