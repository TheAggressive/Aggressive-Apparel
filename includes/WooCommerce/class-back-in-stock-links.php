<?php
/**
 * Back in Stock email links: signup confirmation and unsubscribe.
 *
 * Both links are opened from email, where security scanners (Outlook Safe
 * Links, Mimecast, Proofpoint) fetch every URL before the reader does. A GET
 * therefore only renders a page with a button; the state change happens on
 * POST, which scanners do not send. The unsubscribe URL also accepts the
 * RFC 8058 one-click POST that mail clients send from the List-Unsubscribe
 * header.
 *
 * @package Aggressive_Apparel
 */

declare( strict_types=1 );

namespace Aggressive_Apparel\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Serves the confirm and unsubscribe endpoints for Back in Stock rows.
 */
class Back_In_Stock_Links {

	/**
	 * Query arg carrying the token of a signup awaiting confirmation.
	 */
	public const CONFIRM_PARAM = 'aa_bis_confirm';

	/**
	 * Query arg carrying the token of a subscription to cancel.
	 */
	public const UNSUBSCRIBE_PARAM = 'aa_unsubscribe';

	/**
	 * Register the request handler.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'handle_request' ) );
	}

	/**
	 * URL that confirms a pending signup.
	 *
	 * @param string $token Subscription token.
	 * @return string
	 */
	public static function confirm_url( string $token ): string {
		return add_query_arg( self::CONFIRM_PARAM, rawurlencode( $token ), home_url( '/' ) );
	}

	/**
	 * URL that cancels a subscription.
	 *
	 * @param string $token Subscription token.
	 * @return string
	 */
	public static function unsubscribe_url( string $token ): string {
		return add_query_arg( self::UNSUBSCRIBE_PARAM, rawurlencode( $token ), home_url( '/' ) );
	}

	/**
	 * Route a confirm or unsubscribe request.
	 *
	 * @return void
	 */
	public function handle_request(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- The 64-character random, single-purpose token authorizes these anonymous email links.
		if ( isset( $_GET[ self::CONFIRM_PARAM ] ) ) {
			$this->confirm( sanitize_text_field( wp_unslash( $_GET[ self::CONFIRM_PARAM ] ) ) );
		} elseif ( isset( $_GET[ self::UNSUBSCRIBE_PARAM ] ) ) {
			$this->unsubscribe( sanitize_text_field( wp_unslash( $_GET[ self::UNSUBSCRIBE_PARAM ] ) ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Confirm a pending signup.
	 *
	 * @param string $token Subscription token.
	 * @return void
	 */
	private function confirm( string $token ): void {
		$row = $this->find( $token );

		if ( null === $row || ! in_array( $row['status'], array( 'pending', 'active' ), true ) ) {
			$this->render(
				__( 'Link expired', 'aggressive-apparel' ),
				__( 'This confirmation link is invalid or has expired. Please sign up again from the product page.', 'aggressive-apparel' )
			);
		}

		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $row['product_id'] ) : null;
		$product = $product instanceof \WC_Product ? $product : null;
		$name    = null !== $product ? $product->get_name() : '';

		if ( 'active' === $row['status'] ) {
			$this->render(
				__( 'Already confirmed', 'aggressive-apparel' ),
				__( 'You are already on the list. We will email you when it is back in stock.', 'aggressive-apparel' ),
				$product
			);
		}

		if ( ! $this->is_post() ) {
			$this->render(
				__( 'Confirm your alert', 'aggressive-apparel' ),
				'' !== $name
					/* translators: %s: product name. */
					? sprintf( __( 'Confirm that you want an email when %s is back in stock.', 'aggressive-apparel' ), $name )
					: __( 'Confirm that you want an email when this product is back in stock.', 'aggressive-apparel' ),
				null,
				self::confirm_url( $token ),
				__( 'Confirm', 'aggressive-apparel' )
			);
		}

		$this->transition( $token, array( 'pending' ), 'active' );

		$this->render(
			__( 'You are on the list', 'aggressive-apparel' ),
			null !== $product && $product->is_in_stock()
				? __( 'Good news: it is already back in stock.', 'aggressive-apparel' )
				: __( 'Thanks for confirming. We will email you when it is back in stock.', 'aggressive-apparel' ),
			$product
		);
	}

	/**
	 * Cancel a subscription.
	 *
	 * @param string $token Subscription token.
	 * @return void
	 */
	private function unsubscribe( string $token ): void {
		$row = $this->find( $token );

		if ( $this->is_post() ) {
			if ( null !== $row ) {
				$this->transition( $token, array( 'pending', 'active' ), 'unsubscribed' );
			}

			// RFC 8058 one-click: the mail client needs a 2xx, not a page.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Token-authorized anonymous email link; see handle_request().
			if ( isset( $_POST['List-Unsubscribe'] ) && 'One-Click' === sanitize_text_field( wp_unslash( $_POST['List-Unsubscribe'] ) ) ) {
				nocache_headers();
				wp_die( '', '', array( 'response' => 200 ) );
			}

			$this->render(
				__( 'Unsubscribed', 'aggressive-apparel' ),
				__( 'You will not receive back-in-stock emails for this product.', 'aggressive-apparel' )
			);
		}

		if ( null === $row || ! in_array( $row['status'], array( 'pending', 'active' ), true ) ) {
			$this->render(
				__( 'Unsubscribed', 'aggressive-apparel' ),
				__( 'You are not subscribed to back-in-stock emails for this product.', 'aggressive-apparel' )
			);
		}

		$this->render(
			__( 'Unsubscribe', 'aggressive-apparel' ),
			__( 'Stop back-in-stock emails for this product?', 'aggressive-apparel' ),
			null,
			self::unsubscribe_url( $token ),
			__( 'Unsubscribe', 'aggressive-apparel' )
		);
	}

	/**
	 * Look up a subscription by token.
	 *
	 * @param string $token Subscription token.
	 * @return array{product_id: int, status: string}|null
	 */
	private function find( string $token ): ?array {
		if ( 1 !== preg_match( '/^[A-Za-z0-9]{32,64}$/', $token ) ) {
			return null;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Token lookup on an indexed custom-table column; state changes must not read a stale cache.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT product_id, status FROM %i WHERE unsubscribe_token = %s',
				Back_In_Stock_Installer::get_table_name(),
				$token
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		return array(
			'product_id' => (int) ( $row['product_id'] ?? 0 ),
			'status'     => (string) ( $row['status'] ?? '' ),
		);
	}

	/**
	 * Move a row between statuses, only from the expected ones.
	 *
	 * @param string        $token Subscription token.
	 * @param array<string> $from  Statuses the row may currently hold.
	 * @param string        $to    New status.
	 * @return void
	 */
	private function transition( string $token, array $from, string $to ): void {
		global $wpdb;
		$table = Back_In_Stock_Installer::get_table_name();
		$marks = implode( ',', array_fill( 0, count( $from ), '%s' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Token-authorized custom-table mutation; $marks is one %s per $from value, all passed via the spread.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET status = %s WHERE unsubscribe_token = %s AND status IN ({$marks})",
				...array_merge( array( $table, $to, $token ), $from )
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
	}

	/**
	 * Whether the current request is a POST.
	 *
	 * @return bool
	 */
	private function is_post(): bool {
		return isset( $_SERVER['REQUEST_METHOD'] )
			&& 'post' === sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) );
	}

	/**
	 * Render a small standalone page and stop.
	 *
	 * @param string           $title        Page title.
	 * @param string           $message      Body copy (plain text).
	 * @param \WC_Product|null $product      Optional product to link to.
	 * @param string           $action_url   Optional URL the button POSTs to.
	 * @param string           $action_label Button label.
	 * @return never
	 */
	private function render( string $title, string $message, $product = null, string $action_url = '', string $action_label = '' ): void {
		nocache_headers();

		$html = '<h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $message ) . '</p>';

		if ( '' !== $action_url ) {
			$html .= sprintf(
				'<form method="post" action="%1$s"><p><button type="submit" class="button button-large">%2$s</button></p></form>',
				esc_url( $action_url ),
				esc_html( $action_label )
			);
		} elseif ( $product instanceof \WC_Product ) {
			$html .= sprintf(
				'<p><a class="button button-large" href="%1$s">%2$s</a></p>',
				esc_url( $product->get_permalink() ),
				/* translators: %s: product name. */
				esc_html( sprintf( __( 'View %s', 'aggressive-apparel' ), $product->get_name() ) )
			);
		}

		// Every piece above is already escaped; the allowlist exists because
		// wp_kses_post() strips <form>, which leaves a button that posts nothing.
		wp_die(
			wp_kses(
				$html,
				array(
					'h1'     => array(),
					'p'      => array(),
					'a'      => array(
						'class' => true,
						'href'  => true,
					),
					'form'   => array(
						'method' => true,
						'action' => true,
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
