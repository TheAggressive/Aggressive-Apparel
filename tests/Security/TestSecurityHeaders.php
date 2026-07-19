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
	 * Test the theme defines the expected hardening headers with correct values.
	 */
	public function test_security_headers_map_contains_expected_values() {
		$headers = \Aggressive_Apparel\Bootstrap::get_security_headers();

		$this->assertIsArray( $headers );

		$expected = array(
			'X-Content-Type-Options' => 'nosniff',
			'X-Frame-Options'        => 'SAMEORIGIN',
			'X-XSS-Protection'       => '0',
			'Referrer-Policy'        => 'strict-origin-when-cross-origin',
			'Permissions-Policy'     => 'geolocation=(), microphone=(), camera=()',
		);

		foreach ( $expected as $name => $value ) {
			$this->assertArrayHasKey( $name, $headers, "Missing security header: {$name}" );
			$this->assertSame( $value, $headers[ $name ], "Unexpected value for header: {$name}" );
		}
	}

	/**
	 * Test the Permissions-Policy locks down powerful browser features.
	 */
	public function test_permissions_policy_restricts_sensitive_features() {
		$headers = \Aggressive_Apparel\Bootstrap::get_security_headers();
		$policy  = $headers['Permissions-Policy'] ?? '';

		foreach ( array( 'geolocation=()', 'microphone=()', 'camera=()' ) as $directive ) {
			$this->assertStringContainsString( $directive, $policy );
		}
	}

	/**
	 * The emitter is hooked to send_headers so the declared headers actually go
	 * out on every response.
	 *
	 * Unlike the removed placebo tests (which only proved esc_html/$wpdb->prepare
	 * work — i.e. that WordPress core works), this asserts the THEME wires its
	 * header emission into the real response lifecycle. Drop the hook and this
	 * fails, even though get_security_headers() would still return the map.
	 */
	public function test_headers_are_hooked_to_send_headers() {
		$bootstrap = \Aggressive_Apparel\Bootstrap::get_instance();

		$this->assertNotFalse(
			has_action( 'send_headers', array( $bootstrap, 'add_security_headers' ) ),
			'Security headers are not hooked to send_headers — they would never be emitted.'
		);
	}
}
