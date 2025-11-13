<?php
/**
 * Security Tests
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Security;

use WP_UnitTestCase;

/**
 * Security Headers Test Case
 */
class TestSecurityHeaders extends WP_UnitTestCase {
	/**
	 * Test content type is properly set
	 */
	public function test_content_type_header() {
		$this->expectOutputRegex( '/Content-Type:/' );
		header( 'Content-Type: text/html; charset=UTF-8' );
	}

	/**
	 * Test XSS protection
	 */
	public function test_xss_protection() {
		$malicious_input = '<script>alert("XSS")</script>';
		$sanitized       = esc_html( $malicious_input );

		$this->assertStringNotContainsString( '<script>', $sanitized );
		$this->assertStringContainsString( '&lt;script&gt;', $sanitized );
	}

	/**
	 * Test SQL injection protection
	 */
	public function test_sql_injection_protection() {
		global $wpdb;

		$malicious_input = "' OR '1'='1";
		$prepared        = $wpdb->prepare( 'SELECT * FROM wp_posts WHERE post_title = %s', $malicious_input );

		$this->assertStringNotContainsString( "' OR '1'='1", $prepared );
	}

	/**
	 * Test file path traversal protection
	 */
	public function test_path_traversal_protection() {
		$malicious_path = '../../wp-config.php';
		$sanitized_path = basename( $malicious_path );

		$this->assertEquals( 'wp-config.php', $sanitized_path );
		$this->assertStringNotContainsString( '..', $sanitized_path );
	}

	/**
	 * Test nonce verification
	 */
	public function test_nonce_creation_and_verification() {
		$action = 'test_action';
		$nonce  = wp_create_nonce( $action );

		$this->assertIsString( $nonce );
		$this->assertNotEmpty( $nonce );
		$this->assertEquals( 1, wp_verify_nonce( $nonce, $action ) );
	}

	/**
	 * Test user capabilities check
	 */
	public function test_user_capabilities() {
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$this->assertFalse( current_user_can( 'manage_options' ) );
		$this->assertTrue( current_user_can( 'read' ) );
	}
}
