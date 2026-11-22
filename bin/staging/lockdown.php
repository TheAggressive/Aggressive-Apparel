<?php
/**
 * Lock a staging clone of production down before anyone uses it.
 *
 * Run with WP-CLI from the WordPress root:
 *
 *     wp eval-file bin/staging/lockdown.php          # dry run: what it would do
 *     wp eval-file bin/staging/lockdown.php apply    # lock down
 *     wp eval-file bin/staging/lockdown.php revert   # undo (disposable copies, local tests)
 *
 * Refuses to run on the production host. Apply:
 * - installs the aa-staging-lockdown must-use plugin (access key, noindex, no
 *   email, WooPayments test mode, live PayPal blocked, PayPal webhooks safe);
 * - creates the access key and prints the access link;
 * - turns off search engine visibility;
 * - deactivates marketing, feed and ad-sync plugins;
 * - pauses every WooCommerce webhook (fulfilment and marketing order pushes).
 * What it changed is recorded, so revert restores exactly that.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPressVIPMinimum.Functions.RestrictedFunctions -- A WP-CLI script copying one file.

$aa_flags  = isset( $args ) && is_array( $args ) ? $args : array();
$aa_mode   = in_array( 'revert', $aa_flags, true ) ? 'revert' : ( in_array( 'apply', $aa_flags, true ) ? 'apply' : 'dry-run' );
$aa_host   = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
$aa_source = __DIR__ . '/aa-staging-lockdown.php';
$aa_target = WPMU_PLUGIN_DIR . '/aa-staging-lockdown.php';
$aa_state  = 'aa_staging_lockdown_state';

if ( in_array( $aa_host, array( 'theaggressive.com', 'www.theaggressive.com' ), true ) ) {
	WP_CLI::error( "This is the production host ({$aa_host}). Staging lockdown refuses to run here." );
}
if ( 'dry-run' === $aa_mode && ! file_exists( $aa_source ) ) {
	WP_CLI::error( "Missing {$aa_source}." );
}

// Plugins that send customer data or orders to a third party, by directory.
$aa_sync_plugins = array(
	'omnisend',
	'omnisend-connect',
	'google-listings-and-ads',
	'pinterest-for-woocommerce',
	'tiktok-for-business',
	'facebook-for-woocommerce',
	'mailchimp-for-woocommerce',
	'klaviyo',
);

if ( 'revert' === $aa_mode ) {
	$aa_saved = get_option( $aa_state );
	if ( ! is_array( $aa_saved ) ) {
		WP_CLI::error( 'No lockdown record on this site; nothing to revert.' );
	}
	activate_plugins( $aa_saved['plugins'] );
	foreach ( $aa_saved['webhooks'] as $aa_id ) {
		$aa_hook = wc_get_webhook( (int) $aa_id );
		if ( $aa_hook ) {
			$aa_hook->set_status( 'active' );
			$aa_hook->save();
		}
	}
	update_option( 'blog_public', $aa_saved['blog_public'] );
	if ( file_exists( $aa_target ) ) {
		unlink( $aa_target );
	}
	delete_option( 'aa_staging_key' );
	delete_option( $aa_state );
	WP_CLI::success( sprintf( 'Reverted: %d plugin(s) reactivated, %d webhook(s) resumed, lockdown plugin removed.', count( $aa_saved['plugins'] ), count( $aa_saved['webhooks'] ) ) );
	return;
}

$aa_plugins = array();
foreach ( array_keys( get_plugins() ) as $aa_file ) {
	if ( in_array( dirname( $aa_file ), $aa_sync_plugins, true ) && is_plugin_active( $aa_file ) ) {
		$aa_plugins[] = $aa_file;
	}
}

$aa_webhooks = array();
if ( function_exists( 'wc_get_webhook' ) ) {
	$aa_store = WC_Data_Store::load( 'webhook' );
	foreach ( $aa_store->search_webhooks(
		array(
			'status' => 'active',
			'limit'  => -1,
		) 
	) as $aa_id ) {
		$aa_webhooks[] = (int) $aa_id;
	}
}

$aa_paypal  = get_option( 'woocommerce-ppcp-data-common' );
$aa_legacy  = get_option( 'woocommerce-ppcp-settings' );
$aa_sandbox = ( is_array( $aa_paypal ) && ! empty( $aa_paypal['sandbox_merchant'] ) ) || ( is_array( $aa_legacy ) && ! empty( $aa_legacy['sandbox_on'] ) );

WP_CLI::log( "Site: {$aa_host}" );
WP_CLI::log( 'Install must-use plugin: ' . $aa_target );
WP_CLI::log( 'Search engine visibility: off (was ' . ( get_option( 'blog_public' ) ? 'on' : 'off' ) . ')' );
WP_CLI::log( 'Deactivate: ' . ( $aa_plugins ? implode( ', ', $aa_plugins ) : 'none active' ) );
WP_CLI::log( sprintf( 'Pause %d active WooCommerce webhook(s)', count( $aa_webhooks ) ) );
WP_CLI::log( 'WooPayments: forced to test mode' );
WP_CLI::log( 'PayPal: ' . ( $aa_sandbox ? 'sandbox connection, left working' : 'LIVE connection: checkout gateway and payment endpoints blocked. Connect a sandbox account to test PayPal.' ) );

if ( 'dry-run' === $aa_mode ) {
	WP_CLI::log( 'Dry run: nothing was changed. Re-run with "apply".' );
	return;
}

if ( is_array( get_option( $aa_state ) ) ) {
	WP_CLI::error( 'Already locked down (record exists). Revert first to re-apply.' );
}

wp_mkdir_p( WPMU_PLUGIN_DIR );
if ( ! copy( $aa_source, $aa_target ) ) {
	WP_CLI::error( "Could not write {$aa_target}." );
}
update_option(
	$aa_state,
	array(
		'plugins'     => $aa_plugins,
		'webhooks'    => $aa_webhooks,
		'blog_public' => (string) get_option( 'blog_public' ),
	),
	false
);
update_option( 'blog_public', '0' );
deactivate_plugins( $aa_plugins );
foreach ( $aa_webhooks as $aa_id ) {
	$aa_hook = wc_get_webhook( $aa_id );
	if ( $aa_hook ) {
		$aa_hook->set_status( 'paused' );
		$aa_hook->save();
	}
}
$aa_key = wp_generate_password( 32, false );
update_option( 'aa_staging_key', $aa_key, false );

WP_CLI::success( 'Locked down.' );
WP_CLI::log( 'Access link (sets a 30-day cookie): ' . add_query_arg( 'aa_staging_key', $aa_key, home_url( '/' ) ) );
