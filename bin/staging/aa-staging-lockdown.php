<?php
/**
 * Plugin Name: Aggressive Apparel staging lockdown
 * Description: Staging only. Private behind an access key, unindexed, no outgoing email, no real payments, no PayPal webhook takeover. Installed by bin/staging/lockdown.php.
 *
 * Copied into wp-content/mu-plugins on a staging clone of production. It does
 * nothing on the production host, whatever copied it there.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether this request runs on the production host.
 */
function aa_staging_is_production_host(): bool {
	$host = strtolower( (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_HOST ) );
	return in_array( $host, array( 'theaggressive.com', 'www.theaggressive.com' ), true );
}

if ( aa_staging_is_production_host() ) {
	return;
}

/**
 * Whether PayPal Payments is connected to a sandbox account (new settings
 * store, or the legacy one).
 */
function aa_staging_paypal_is_sandbox(): bool {
	$common = get_option( 'woocommerce-ppcp-data-common' );
	$legacy = get_option( 'woocommerce-ppcp-settings' );
	return ( is_array( $common ) && ! empty( $common['sandbox_merchant'] ) )
		|| ( is_array( $legacy ) && ! empty( $legacy['sandbox_on'] ) );
}

// No outgoing email: orders, accounts, password resets, marketing. Reported
// as sent so nothing retries. Logged without recipients.
add_filter(
	'pre_wp_mail',
	static function ( $short_circuit, array $atts ) {
		error_log( 'Staging lockdown: suppressed email "' . (string) ( $atts['subject'] ?? '' ) . '"' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Staging diagnostics.
		return true;
	},
	PHP_INT_MAX,
	2
);

// Never indexed, even if the Reading setting is changed back.
add_filter( 'pre_option_blog_public', static fn() => '0' );
// Uncacheable everywhere: a page cache (Varnish on Cloudways) must never keep
// a response fetched with the access key and serve it to someone without it.
add_action(
	'send_headers',
	static function (): void {
		header( 'X-Robots-Tag: noindex, nofollow', true );
		nocache_headers();
		header( 'Cache-Control: private, no-store, max-age=0', true );
	}
);

// WooPayments processes in test mode regardless of its saved setting.
add_filter( 'wcpay_test_mode', '__return_true', PHP_INT_MAX );

// PayPal on a live connection: no PayPal gateway at checkout, and the button
// endpoints that create, approve or vault a payment answer 403.
add_filter(
	'woocommerce_available_payment_gateways',
	static function ( $gateways ) {
		if ( ! is_array( $gateways ) || aa_staging_paypal_is_sandbox() ) {
			return $gateways;
		}
		return array_filter(
			$gateways,
			static fn( $id ): bool => ! str_starts_with( (string) $id, 'ppcp' ),
			ARRAY_FILTER_USE_KEY
		);
	},
	PHP_INT_MAX
);

// Outgoing API calls a clone must never make with production's connections:
// re-registering PayPal webhooks would point production's PayPal events at
// staging. Reads are allowed; sandbox hosts are allowed.
add_filter(
	'pre_http_request',
	static function ( $response, array $args, string $url ) {
		$host   = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$path   = (string) wp_parse_url( $url, PHP_URL_PATH );
		$method = strtoupper( (string) ( $args['method'] ?? 'GET' ) );
		$live   = in_array( $host, array( 'api.paypal.com', 'api-m.paypal.com' ), true );
		if ( $live && 'GET' !== $method && str_contains( $path, '/notifications/webhooks' ) ) {
			return new WP_Error( 'aa_staging_lockdown', 'Staging lockdown: PayPal webhook changes are blocked.' );
		}
		return $response;
	},
	PHP_INT_MAX,
	3
);

add_action(
	'init',
	static function (): void {
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE, WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie -- Access key on a staging site whose responses are all uncacheable (send_headers above).
		$endpoint = isset( $_GET['wc-ajax'] ) ? sanitize_key( wp_unslash( $_GET['wc-ajax'] ) ) : '';
		$money    = array( 'ppc-create-order', 'ppc-approve-order', 'ppc-vault-create-order', 'ppc-create-payment-token', 'ppc-create-setup-token', 'ppc-approve-subscription', 'ppc-return-url' );
		$always   = array( 'ppc-webhooks-resubscribe', 'ppc-webhooks-simulate' );
		if ( in_array( $endpoint, $always, true ) || ( in_array( $endpoint, $money, true ) && ! aa_staging_paypal_is_sandbox() ) ) {
			wp_send_json_error( array( 'message' => 'Staging: live PayPal payments are disabled.' ), 403 );
		}

		// Private: logged-in users, or a browser holding the access key.
		$key = (string) get_option( 'aa_staging_key', '' );
		if ( '' === $key || is_user_logged_in() || 'wp-login.php' === ( $GLOBALS['pagenow'] ?? '' ) ) {
			return;
		}
		$given = isset( $_GET['aa_staging_key'] ) ? sanitize_text_field( wp_unslash( $_GET['aa_staging_key'] ) ) : '';
		if ( '' !== $given && hash_equals( $key, $given ) ) {
			setcookie( 'aa_staging_key', $key, time() + 30 * DAY_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
			wp_safe_redirect( remove_query_arg( 'aa_staging_key' ) );
			exit;
		}
		$cookie = isset( $_COOKIE['aa_staging_key'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['aa_staging_key'] ) ) : '';
		// phpcs:enable
		if ( '' !== $cookie && hash_equals( $key, $cookie ) ) {
			return;
		}

		status_header( 401 );
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo 'Private staging site. Log in at /wp-login.php or use the access link.';
		exit;
	},
	0
);
