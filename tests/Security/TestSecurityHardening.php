<?php
/**
 * Security Hardening Tests
 *
 * Covers username-enumeration hardening: REST user-endpoint restriction,
 * author-archive blocking, generic login errors, and display-name defaulting.
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Security;

use Aggressive_Apparel\Core\Security_Hardening;
use WP_UnitTestCase;
use WP_Query;

/**
 * Security Hardening Test Case
 */
class TestSecurityHardening extends WP_UnitTestCase {

	/**
	 * System under test.
	 *
	 * @var Security_Hardening
	 */
	private Security_Hardening $hardening;

	/**
	 * Set up a fresh instance for each test.
	 */
	public function set_up() {
		parent::set_up();
		$this->hardening = new Security_Hardening();
	}

	/**
	 * Clean up request superglobals between tests.
	 */
	public function tear_down() {
		unset( $_GET['author'] );
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// REST API
	// -------------------------------------------------------------------------

	/**
	 * The users endpoints are removed for callers without list_users.
	 */
	public function test_rest_user_endpoints_removed_for_unprivileged() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$endpoints = array(
			'/wp/v2/users'                 => 'users-collection',
			'/wp/v2/users/(?P<id>[\d]+)'   => 'users-single',
			'/wp/v2/users/me'              => 'users-me',
			'/wp/v2/posts'                 => 'posts',
		);

		$result = $this->hardening->restrict_rest_user_endpoints( $endpoints );

		$this->assertArrayNotHasKey( '/wp/v2/users', $result );
		$this->assertArrayNotHasKey( '/wp/v2/users/(?P<id>[\d]+)', $result );

		// Unrelated routes — and /users/me — are untouched.
		$this->assertArrayHasKey( '/wp/v2/posts', $result );
		$this->assertArrayHasKey( '/wp/v2/users/me', $result );
	}

	/**
	 * The users endpoints survive for an editor/admin (so Gutenberg keeps working).
	 */
	public function test_rest_user_endpoints_preserved_for_privileged() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$endpoints = array(
			'/wp/v2/users'               => 'users-collection',
			'/wp/v2/users/(?P<id>[\d]+)' => 'users-single',
		);

		$result = $this->hardening->restrict_rest_user_endpoints( $endpoints );

		$this->assertArrayHasKey( '/wp/v2/users', $result );
		$this->assertArrayHasKey( '/wp/v2/users/(?P<id>[\d]+)', $result );
	}

	// -------------------------------------------------------------------------
	// Author enumeration
	// -------------------------------------------------------------------------

	/**
	 * /?author=N is forced to a 404 for the public.
	 */
	public function test_author_query_is_blocked_for_anonymous() {
		wp_set_current_user( 0 );
		$_GET['author'] = '1';

		global $wp_query;
		$wp_query = new WP_Query( array() );
		$this->assertFalse( $wp_query->is_404() );

		$this->hardening->block_author_archives();

		$this->assertTrue( $wp_query->is_404() );
	}

	/**
	 * Privileged users (list_users) can still reach author URLs.
	 */
	public function test_author_query_is_allowed_for_privileged() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_GET['author'] = '1';

		global $wp_query;
		$wp_query = new WP_Query( array() );

		$this->hardening->block_author_archives();

		$this->assertFalse( $wp_query->is_404() );
	}

	/**
	 * Ordinary (non-author) requests are left alone.
	 */
	public function test_non_author_request_is_untouched() {
		wp_set_current_user( 0 );

		global $wp_query;
		$wp_query = new WP_Query( array() );

		$this->hardening->block_author_archives();

		$this->assertFalse( $wp_query->is_404() );
	}

	// -------------------------------------------------------------------------
	// Login
	// -------------------------------------------------------------------------

	/**
	 * The login error is generic and never names which field was wrong.
	 */
	public function test_login_error_is_generic() {
		$message = $this->hardening->generic_login_error( '<strong>Error</strong>: The username admin is not registered.' );

		$this->assertNotEmpty( $message );
		$this->assertStringNotContainsStringIgnoringCase( 'username', $message );
		$this->assertStringNotContainsStringIgnoringCase( 'password', $message );
	}

	// -------------------------------------------------------------------------
	// Display names
	// -------------------------------------------------------------------------

	/**
	 * A display name that equals the login is replaced with "First Last".
	 */
	public function test_display_name_defaults_to_first_last() {
		$user_id = self::factory()->user->create(
			array(
				'user_login'   => 'jsmith',
				'display_name' => 'jsmith',
				'first_name'   => 'John',
				'last_name'    => 'Smith',
			)
		);

		$this->hardening->set_default_display_name( $user_id );

		$this->assertSame( 'John Smith', get_userdata( $user_id )->display_name );
	}

	/**
	 * With no first/last, it falls back to the nickname — never the username.
	 */
	public function test_display_name_falls_back_to_nickname() {
		$user_id = self::factory()->user->create(
			array(
				'user_login'   => 'bob',
				'display_name' => 'bob',
			)
		);
		update_user_meta( $user_id, 'nickname', 'Bobby' );

		$this->hardening->set_default_display_name( $user_id );

		$this->assertSame( 'Bobby', get_userdata( $user_id )->display_name );
	}

	/**
	 * It never rewrites a display name to the login username.
	 */
	public function test_display_name_never_becomes_username() {
		$user_id = self::factory()->user->create(
			array(
				'user_login'   => 'kate',
				'display_name' => 'kate',
			)
		);
		// Nickname defaults to the login; with no name data there is nothing safe
		// to use, so the name must be left as-is rather than forced to the login.
		update_user_meta( $user_id, 'nickname', 'kate' );

		$this->hardening->set_default_display_name( $user_id );

		$this->assertSame( 'kate', get_userdata( $user_id )->display_name );
	}

	/**
	 * A deliberately-customised display name is preserved.
	 */
	public function test_custom_display_name_is_preserved() {
		$user_id = self::factory()->user->create(
			array(
				'user_login'   => 'mjones',
				'display_name' => 'The Real MJ',
				'first_name'   => 'Mary',
				'last_name'    => 'Jones',
			)
		);

		$this->hardening->set_default_display_name( $user_id );

		$this->assertSame( 'The Real MJ', get_userdata( $user_id )->display_name );
	}

	// -------------------------------------------------------------------------
	// Wiring
	// -------------------------------------------------------------------------

	/**
	 * init() registers every hook and removes the generator tag.
	 */
	public function test_init_registers_hooks() {
		$hardening = new Security_Hardening();
		$hardening->init();

		$this->assertNotFalse( has_action( 'template_redirect', array( $hardening, 'block_author_archives' ) ) );
		$this->assertNotFalse( has_filter( 'rest_endpoints', array( $hardening, 'restrict_rest_user_endpoints' ) ) );
		$this->assertNotFalse( has_filter( 'login_errors', array( $hardening, 'generic_login_error' ) ) );
		$this->assertNotFalse( has_action( 'user_register', array( $hardening, 'set_default_display_name' ) ) );
		$this->assertNotFalse( has_action( 'profile_update', array( $hardening, 'set_default_display_name' ) ) );
		$this->assertFalse( has_action( 'wp_head', 'wp_generator' ) );

		// Leave global hook state clean for other tests.
		remove_action( 'template_redirect', array( $hardening, 'block_author_archives' ) );
		remove_filter( 'rest_endpoints', array( $hardening, 'restrict_rest_user_endpoints' ) );
		remove_filter( 'login_errors', array( $hardening, 'generic_login_error' ) );
		remove_action( 'user_register', array( $hardening, 'set_default_display_name' ) );
		remove_action( 'profile_update', array( $hardening, 'set_default_display_name' ) );
	}
}
