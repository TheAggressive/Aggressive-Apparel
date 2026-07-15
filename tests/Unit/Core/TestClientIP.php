<?php
/**
 * Client IP boundary tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\Core
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\Core;

use Aggressive_Apparel\Core\Client_IP;
use WP_UnitTestCase;

/** Covers proxy trust and validation at the request boundary. */
class TestClientIP extends WP_UnitTestCase {
	/** @var null|\Closure(string, string): string */
	private ?\Closure $server_filter = null;

	/** Remove request overrides installed by an individual test. */
	protected function tearDown(): void {
		if ( $this->server_filter instanceof \Closure ) {
			remove_filter( 'aggressive_apparel_server_value', $this->server_filter, 20 );
			$this->server_filter = null;
		}
		parent::tearDown();
	}

	/** An arbitrary peer cannot spoof Cloudflare's connecting-IP header. */
	public function test_cloudflare_header_is_ignored_for_untrusted_peer(): void {
		$this->set_server_values( '203.0.113.10', '198.51.100.25' );

		$this->assertSame( '203.0.113.10', Client_IP::get() );
	}

	/** A Cloudflare edge may supply the validated originating address. */
	public function test_cloudflare_header_is_used_for_trusted_peer(): void {
		$this->set_server_values( '173.245.48.5', '198.51.100.25' );

		$this->assertSame( '198.51.100.25', Client_IP::get() );
	}

	/** Invalid direct and forwarded addresses never become rate-limit keys. */
	public function test_invalid_addresses_are_rejected(): void {
		$this->set_server_values( 'not-an-ip', 'also-not-an-ip' );

		$this->assertSame( '', Client_IP::get() );
	}

	/** Install deterministic request metadata for one test. */
	private function set_server_values( string $remote, string $forwarded ): void {
		$this->server_filter = static function ( string $value, string $key ) use ( $remote, $forwarded ): string {
			return 'REMOTE_ADDR' === $key ? $remote : $forwarded;
		};
		add_filter(
			'aggressive_apparel_server_value',
			$this->server_filter,
			20,
			2
		);
	}
}
