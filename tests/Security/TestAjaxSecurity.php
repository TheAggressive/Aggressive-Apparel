<?php
/**
 * AJAX authorization tests.
 *
 * The privileged color-pattern admin handlers must reject requests that lack a
 * valid nonce (CSRF) or the required capability (privilege escalation). These
 * assert the guards actually fire — a dropped check during a refactor turns a
 * green test red instead of silently opening a hole.
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Security;

use Aggressive_Apparel\WooCommerce\Color_Pattern_Admin;
use WP_UnitTestCase;
use WPDieException;

/**
 * AJAX security test case.
 */
class TestAjaxSecurity extends WP_UnitTestCase {

	/** Nonce action shared by the color-pattern admin handlers. */
	private const NONCE_ACTION = 'color_pattern_admin';

	/**
	 * Handler under test.
	 *
	 * @var Color_Pattern_Admin
	 */
	private Color_Pattern_Admin $admin;

	/**
	 * Set up the handler.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->admin = new Color_Pattern_Admin();
	}

	/**
	 * Clear request superglobals between tests.
	 */
	public function tearDown(): void {
		unset( $_POST['nonce'], $_POST['term_id'], $_POST['attachment_id'] );
		parent::tearDown();
	}

	/**
	 * Become a user of the given role.
	 *
	 * @param string $role WordPress role.
	 */
	private function login_as( string $role ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}

	/**
	 * Delete handler dies with no nonce (CSRF guard).
	 */
	public function test_pattern_delete_rejects_missing_nonce(): void {
		$this->login_as( 'administrator' );
		unset( $_POST['nonce'] );

		$this->expectException( WPDieException::class );
		$this->expectExceptionMessage( 'Security check failed' );
		$this->admin->handle_pattern_delete();
	}

	/**
	 * Delete handler dies with a forged nonce (CSRF guard).
	 */
	public function test_pattern_delete_rejects_forged_nonce(): void {
		$this->login_as( 'administrator' );
		$_POST['nonce'] = 'not-a-real-nonce';

		$this->expectException( WPDieException::class );
		$this->expectExceptionMessage( 'Security check failed' );
		$this->admin->handle_pattern_delete();
	}

	/**
	 * Delete handler dies for an under-privileged user even with a valid nonce
	 * (authorization guard — a valid session is not enough).
	 */
	public function test_pattern_delete_rejects_insufficient_capability(): void {
		$this->login_as( 'subscriber' );
		$_POST['nonce'] = wp_create_nonce( self::NONCE_ACTION );

		$this->expectException( WPDieException::class );
		$this->expectExceptionMessage( 'Insufficient permissions' );
		$this->admin->handle_pattern_delete();
	}

	/**
	 * Upload handler dies with no nonce (CSRF guard).
	 */
	public function test_pattern_upload_rejects_missing_nonce(): void {
		$this->login_as( 'administrator' );
		unset( $_POST['nonce'] );

		$this->expectException( WPDieException::class );
		$this->expectExceptionMessage( 'Security check failed' );
		$this->admin->handle_pattern_upload();
	}

	/**
	 * Upload handler dies for an under-privileged user with a valid nonce.
	 */
	public function test_pattern_upload_rejects_insufficient_capability(): void {
		$this->login_as( 'subscriber' );
		$_POST['nonce'] = wp_create_nonce( self::NONCE_ACTION );

		$this->expectException( WPDieException::class );
		$this->expectExceptionMessage( 'Insufficient permissions' );
		$this->admin->handle_pattern_upload();
	}
}
