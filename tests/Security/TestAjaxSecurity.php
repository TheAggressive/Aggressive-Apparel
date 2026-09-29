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
	 * pa_color registration in place before this test, restored in tearDown.
	 *
	 * @var \WP_Taxonomy|null
	 */
	private ?\WP_Taxonomy $previous_color_taxonomy = null;

	/**
	 * Set up the handler.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->admin = new Color_Pattern_Admin();

		// Register pa_color with WooCommerce's product-term capabilities, as
		// production does. Other tests may have registered it with default caps
		// (manage_categories), which would let editors through; restored after.
		global $wp_taxonomies;
		$this->previous_color_taxonomy = $wp_taxonomies['pa_color'] ?? null;
		register_taxonomy(
			'pa_color',
			'product',
			array(
				'capabilities' => array(
					'manage_terms' => 'manage_product_terms',
					'edit_terms'   => 'edit_product_terms',
					'delete_terms' => 'delete_product_terms',
					'assign_terms' => 'assign_product_terms',
				),
			)
		);
	}

	/**
	 * Clear request superglobals between tests.
	 */
	public function tearDown(): void {
		unset( $_POST['nonce'], $_POST['term_id'], $_POST['attachment_id'] );
		remove_all_filters( 'wp_doing_ajax' );
		remove_all_filters( 'wp_die_ajax_handler' );

		global $wp_taxonomies;
		if ( $this->previous_color_taxonomy instanceof \WP_Taxonomy ) {
			$wp_taxonomies['pa_color'] = $this->previous_color_taxonomy; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the registration this test replaced.
		} else {
			unregister_taxonomy( 'pa_color' );
		}

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

	/**
	 * Editors hold manage_categories but not WooCommerce's product-term
	 * capabilities, so they must not change colour swatch patterns.
	 */
	public function test_pattern_handlers_reject_editor(): void {
		$this->login_as( 'editor' );
		$_POST['nonce'] = wp_create_nonce( self::NONCE_ACTION );

		$this->expectException( WPDieException::class );
		$this->expectExceptionMessage( 'Insufficient permissions' );
		$this->admin->handle_pattern_delete();
	}

	/**
	 * A term from another taxonomy is rejected even for an authorized user.
	 */
	public function test_pattern_handlers_reject_non_color_term(): void {
		$this->login_as( 'administrator' );
		wp_get_current_user()->add_cap( 'edit_product_terms' );
		$_POST['nonce']   = wp_create_nonce( self::NONCE_ACTION );
		$_POST['term_id'] = (string) self::factory()->category->create();

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static fn() => static function ( $message ): void {
				throw new WPDieException( is_string( $message ) ? $message : '' );
			}
		);

		ob_start();
		try {
			$this->admin->handle_pattern_delete();
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$reply = json_decode( (string) ob_get_clean(), true );

		$this->assertFalse( $reply['success'] ?? true );
		$this->assertSame( 'Invalid term ID.', $reply['data']['message'] ?? '' );
	}
}
