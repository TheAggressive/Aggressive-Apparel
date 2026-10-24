<?php
/**
 * Back in Stock email links: scanner-safe verify and unsubscribe.
 *
 * WooCommerce's stock-notification emails link to `?email_link_action=verify`
 * and `=unsubscribe`, and WooCommerce acts on the GET. Email security scanners
 * (Outlook Safe Links, Mimecast, Proofpoint) fetch every URL before the reader
 * does, so a GET that changes state would confirm a double opt-in — or cancel
 * a waitlist entry — on the reader's behalf. Here a GET only renders a page
 * with a button that POSTs back to the same URL; WooCommerce's own handler
 * (template_redirect, priority 10) then processes that POST, since it reads
 * the action from the query string either way.
 *
 * Restock emails also gain RFC 8058 `List-Unsubscribe` /
 * `List-Unsubscribe-Post` headers. Mail clients send that one-click
 * unsubscribe as a POST, which passes straight through to WooCommerce.
 *
 * @package Aggressive_Apparel
 */

declare( strict_types=1 );

namespace Aggressive_Apparel\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Guards WooCommerce's stock-notification email links.
 */
class Back_In_Stock_Links {

	/**
	 * Unsubscribe URL in the email being built, keyed by WC_Email object id.
	 *
	 * Each restock email gets a fresh single-use key, so the header must reuse
	 * the URL already in the body; generating another would invalidate it.
	 *
	 * @var array<int, string>
	 */
	private array $unsubscribe_urls = array();

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'template_redirect', array( $this, 'guard_email_link' ), 9 );
		add_action( 'woocommerce_before_template_part', array( $this, 'capture_unsubscribe_url' ), 10, 4 );
		add_filter( 'woocommerce_email_headers', array( $this, 'add_list_unsubscribe' ), 10, 4 );
	}

	/**
	 * On a GET of a verify or unsubscribe link, render a confirm page instead
	 * of letting WooCommerce act on it.
	 *
	 * @return void
	 */
	public function guard_email_link(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Only reads which page to render; WooCommerce authorizes the action with its single-use link key.
		if ( $this->is_post() || ! isset( $_GET['notification_id'], $_GET['email_link_action_key'], $_GET['email_link_action'] ) ) {
			return;
		}
		$action = sanitize_key( wp_unslash( $_GET['email_link_action'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! in_array( $action, Back_In_Stock_Native::LINK_ACTIONS, true ) || ! Back_In_Stock_Native::is_enabled() ) {
			return;
		}

		if ( 'verify' === $action ) {
			$this->render(
				__( 'Confirm your alert', 'aggressive-apparel' ),
				__( 'Confirm that you want an email when this product is back in stock.', 'aggressive-apparel' ),
				__( 'Confirm', 'aggressive-apparel' )
			);
		}

		$this->render(
			__( 'Unsubscribe', 'aggressive-apparel' ),
			__( 'Stop back-in-stock emails for this product?', 'aggressive-apparel' ),
			__( 'Unsubscribe', 'aggressive-apparel' )
		);
	}

	/**
	 * Remember the unsubscribe URL WooCommerce puts in an email body.
	 *
	 * @param string $template_name Template name.
	 * @param string $template_path Template path.
	 * @param string $located       Resolved template file.
	 * @param mixed  $args          Template arguments.
	 * @return void
	 */
	public function capture_unsubscribe_url( $template_name, $template_path, $located, $args ): void {
		unset( $template_name, $template_path, $located );

		$email = is_array( $args ) ? ( $args['email'] ?? null ) : null;
		$url   = is_array( $args ) ? ( $args['unsubscribe_link'] ?? null ) : null;
		if ( $email instanceof \WC_Email && is_string( $url ) && '' !== $url && in_array( $email->id, Back_In_Stock_Native::UNSUBSCRIBE_EMAIL_IDS, true ) ) {
			$this->unsubscribe_urls[ spl_object_id( $email ) ] = $url;
		}
	}

	/**
	 * Add one-click unsubscribe headers to an email whose body carries a link.
	 *
	 * WooCommerce builds the body before the headers, so the URL captured
	 * from the body is the one to advertise. No URL, no header: an email
	 * rendered some other way just goes without.
	 *
	 * @param mixed  $headers  Email headers.
	 * @param string $email_id Email ID.
	 * @param mixed  $data     Email object data.
	 * @param mixed  $email    Email instance.
	 * @return mixed
	 */
	public function add_list_unsubscribe( $headers, $email_id = '', $data = null, $email = null ) {
		unset( $data );

		if ( ! is_string( $headers ) || ! $email instanceof \WC_Email || ! in_array( $email_id, Back_In_Stock_Native::UNSUBSCRIBE_EMAIL_IDS, true ) ) {
			return $headers;
		}

		$key = spl_object_id( $email );
		$url = $this->unsubscribe_urls[ $key ] ?? '';
		unset( $this->unsubscribe_urls[ $key ] );
		if ( '' === $url ) {
			return $headers;
		}

		return $headers
			. 'List-Unsubscribe: <' . esc_url_raw( $url ) . ">\r\n"
			. "List-Unsubscribe-Post: List-Unsubscribe=One-Click\r\n";
	}

	/**
	 * Whether the request is a POST.
	 *
	 * @return bool
	 */
	private function is_post(): bool {
		return isset( $_SERVER['REQUEST_METHOD'] )
			&& 'post' === sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) );
	}

	/**
	 * Render a small standalone page whose button POSTs to this same URL, and
	 * stop.
	 *
	 * @param string $title        Page title.
	 * @param string $message      Body copy (plain text).
	 * @param string $action_label Button label.
	 * @return never
	 */
	private function render( string $title, string $message, string $action_label ): void {
		nocache_headers();

		// A form with no action posts to the current URL, query string
		// included, which is exactly what WooCommerce's handler reads.
		$html = sprintf(
			'<h1>%1$s</h1><p>%2$s</p><form method="post"><p><button type="submit" class="button button-large">%3$s</button></p></form>',
			esc_html( $title ),
			esc_html( $message ),
			esc_html( $action_label )
		);

		// Every piece above is already escaped; the allowlist exists because
		// wp_kses_post() strips <form>, which leaves a button that posts nothing.
		wp_die(
			wp_kses(
				$html,
				array(
					'h1'     => array(),
					'p'      => array(),
					'form'   => array(
						'method' => true,
					),
					'button' => array(
						'type'  => true,
						'class' => true,
					),
				)
			),
			esc_html( $title ),
			array(
				'response'  => 200,
				'back_link' => false,
			)
		);
	}
}
