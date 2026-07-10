<?php
/**
 * Security Hardening Class
 *
 * Reduces username enumeration and related information disclosure:
 *
 * - Blocks `/?author=N` redirects and disables author archive pages so the
 *   login username can't be derived from an author URL slug.
 * - Restricts the REST `wp/v2/users` collection to requests that can actually
 *   list users (editors/admins), so anonymous scripts can't dump the user list.
 *   Logged-in editors keep full access, so the block editor is unaffected.
 * - Removes the WordPress generator/version meta tag.
 * - Makes login error messages generic so they don't confirm valid usernames.
 * - Defaults a user's public display name to "First Last" (never the username),
 *   for new users and for anyone whose display name still equals their login.
 *
 * Note: this hardening lives in the theme, so it stops applying if the theme is
 * deactivated. It raises the cost of enumeration but is not a substitute for
 * strong passwords, 2FA, and login rate-limiting.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Security Hardening
 *
 * @since 1.17.0
 */
class Security_Hardening {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Username enumeration via author URLs / archives.
		add_action( 'template_redirect', array( $this, 'block_author_archives' ) );

		// Username enumeration via the REST API.
		add_filter( 'rest_endpoints', array( $this, 'restrict_rest_user_endpoints' ) );

		// Information disclosure.
		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'the_generator', '__return_empty_string' );

		// Don't confirm which half of the credentials was wrong.
		add_filter( 'login_errors', array( $this, 'generic_login_error' ) );

		// Keep public display names off the login username.
		add_action( 'user_register', array( $this, 'set_default_display_name' ) );
		add_action( 'profile_update', array( $this, 'set_default_display_name' ) );
	}

	// -------------------------------------------------------------------------
	// Author enumeration
	// -------------------------------------------------------------------------

	/**
	 * Block author archive pages and `/?author=N` enumeration.
	 *
	 * A request to `/?author=1` normally 301-redirects to `/author/<slug>/`,
	 * leaking the login username in the slug. We send such requests (and any
	 * author archive view) to a 404 instead. The admin is exempt so author
	 * screens in wp-admin keep working.
	 *
	 * @return void
	 */
	public function block_author_archives(): void {
		if ( is_admin() || current_user_can( 'list_users' ) ) {
			return;
		}

		$author_param      = isset( $_GET['author'] ) ? sanitize_text_field( wp_unslash( $_GET['author'] ) ) : '';
		$is_author_request = is_author() || '' !== trim( $author_param );

		if ( ! $is_author_request ) {
			return;
		}

		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	// -------------------------------------------------------------------------
	// REST API
	// -------------------------------------------------------------------------

	/**
	 * Remove the public REST `users` collection/single endpoints for callers who
	 * can't list users.
	 *
	 * Logged-in users with the `list_users` capability (editors/admins) keep the
	 * endpoints, so Gutenberg's author controls continue to function. Everyone
	 * else (including anonymous requests) gets a 404 for these routes.
	 *
	 * @param array<string, mixed> $endpoints REST endpoints keyed by route.
	 * @return array<string, mixed> Filtered endpoints.
	 */
	public function restrict_rest_user_endpoints( array $endpoints ): array {
		if ( current_user_can( 'list_users' ) ) {
			return $endpoints;
		}

		unset( $endpoints['/wp/v2/users'] );
		unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );

		return $endpoints;
	}

	// -------------------------------------------------------------------------
	// Login
	// -------------------------------------------------------------------------

	/**
	 * Replace login error messages with a single generic message.
	 *
	 * Prevents "unknown username" vs "incorrect password" from confirming which
	 * usernames exist. Always returns a non-empty string so WordPress still shows
	 * feedback.
	 *
	 * @param string $error The original error markup (unused).
	 * @return string Generic error message.
	 */
	public function generic_login_error( $error ): string {
		unset( $error );

		return __( 'Login failed: please check your credentials and try again.', 'aggressive-apparel' );
	}

	// -------------------------------------------------------------------------
	// Display names
	// -------------------------------------------------------------------------

	/**
	 * Default a user's public display name away from the login username.
	 *
	 * Runs on registration and profile updates. Only acts when the current
	 * display name is empty or still equals the login username — so a display
	 * name a user deliberately set is never overwritten. Falls back to the
	 * nickname when no first/last name is available; never falls back to the
	 * username.
	 *
	 * @param int $user_id The user being created or updated.
	 * @return void
	 */
	public function set_default_display_name( int $user_id ): void {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$current = (string) $user->display_name;

		// Respect a deliberately-customised display name.
		if ( '' !== $current && $current !== $user->user_login ) {
			return;
		}

		$first = trim( (string) get_user_meta( $user_id, 'first_name', true ) );
		$last  = trim( (string) get_user_meta( $user_id, 'last_name', true ) );
		$full  = trim( "$first $last" );

		$nickname = trim( (string) $user->nickname );

		// Prefer First Last, then nickname; never the login username.
		$display = '' !== $full ? $full : $nickname;

		if ( '' === $display || $display === $user->user_login ) {
			return;
		}

		if ( $display === $current ) {
			return;
		}

		// Avoid recursion: profile_update fires again on wp_update_user.
		remove_action( 'profile_update', array( $this, 'set_default_display_name' ) );
		wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => $display,
			)
		);
		add_action( 'profile_update', array( $this, 'set_default_display_name' ) );
	}
}
