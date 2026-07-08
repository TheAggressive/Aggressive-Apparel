<?php
/**
 * Test Theme Updates Class
 *
 * Tests for the simplified theme update functionality based on LAAO updater.
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Unit\Core;

use WP_UnitTestCase;
use Aggressive_Apparel\Core\Theme_Updates;

/**
 * Theme Updates Test Case
 */
class TestThemeUpdates extends WP_UnitTestCase {

	/**
	 * Theme updates instance
	 *
	 * @var Theme_Updates
	 */
	private $theme_updates;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();
		$this->theme_updates = Theme_Updates::get_instance();
	}

	/**
	 * Tear down test environment
	 */
	public function tearDown(): void {
		delete_transient( 'aggressive_apparel_theme_update' );
		delete_transient( 'aggressive_apparel_theme_update_release' );
		parent::tearDown();
	}

	/**
	 * Test singleton pattern
	 */
	public function test_singleton_pattern(): void {
		$instance1 = Theme_Updates::get_instance();
		$instance2 = Theme_Updates::get_instance();

		$this->assertSame( $instance1, $instance2 );
		$this->assertInstanceOf( Theme_Updates::class, $instance1 );
	}

	/**
	 * Test class has required methods
	 */
	public function test_class_has_required_methods(): void {
		$this->assertTrue( method_exists( $this->theme_updates, 'init' ) );
		$this->assertTrue( method_exists( $this->theme_updates, 'check_for_update' ) );
		$this->assertTrue( method_exists( $this->theme_updates, 'rename_package' ) );
	}

	/**
	 * Invoke a private Theme_Updates method.
	 *
	 * @param string       $method Method name.
	 * @param array<mixed> $args   Arguments.
	 * @return mixed
	 */
	private function invoke_private( string $method, array $args = array() ) {
		$reflection = new \ReflectionClass( Theme_Updates::class );
		$method_ref = $reflection->getMethod( $method );
		$method_ref->setAccessible( true );

		return $method_ref->invokeArgs( $this->theme_updates, $args );
	}

	/**
	 * Build release data for updater tests.
	 *
	 * @param array<int, array<string, string>> $assets Release assets.
	 * @return array<string, mixed>
	 */
	private function release_data( array $assets = array() ): array {
		return array(
			'tag_name'     => 'v1.2.3',
			'published_at' => '2026-01-01T00:00:00Z',
			'html_url'     => 'https://github.com/TheAggressive/Aggressive-Apparel/releases/tag/v1.2.3',
			'zipball_url'  => 'https://api.github.com/repos/TheAggressive/Aggressive-Apparel/zipball/v1.2.3',
			'assets'       => $assets,
		);
	}

	/**
	 * Cache release data as if GitHub had returned it recently.
	 *
	 * @param array<string, mixed> $release_data Release data.
	 * @return void
	 */
	private function cache_release_data( array $release_data ): void {
		set_transient(
			'aggressive_apparel_theme_update_release',
			array(
				'release_data' => $release_data,
				'checked_at'   => time(),
			),
			HOUR_IN_SECONDS
		);
	}

	/**
	 * Test check_for_update method with empty transient
	 */
	public function test_check_for_update_empty_transient(): void {
		$transient = (object) [];
		$result = $this->theme_updates->check_for_update( $transient );

		$this->assertSame( $transient, $result );
	}

	/**
	 * Test check_for_update method returns transient unchanged when no updates
	 */
	public function test_check_for_update_no_updates(): void {
		$this->cache_release_data( $this->release_data() );

		$transient = (object) [
			'checked' => [
				'aggressive-apparel' => '9.9.9' // Higher version than any possible
			]
		];

		$result = $this->theme_updates->check_for_update( $transient );

		$this->assertSame( $transient, $result );
	}

	/**
	 * Test rename_package method with non-matching remote source
	 */
	public function test_rename_package_no_match(): void {
		$source = '/tmp/test-source';
		$remote_source = 'https://example.com/some-other-repo.zip';

		// Mock WP_Upgrader object
		$upgrader = $this->getMockBuilder( 'WP_Upgrader' )
			->disableOriginalConstructor()
			->getMock();

		$result = $this->theme_updates->rename_package( $source, $remote_source, $upgrader );

		// Should return the original source when repo name doesn't match
		$this->assertEquals( $source, $result );
	}

	/**
	 * Package URLs are limited to expected GitHub release/zipball URLs.
	 */
	public function test_package_url_validation_limits_update_sources(): void {
		$this->assertTrue(
			$this->invoke_private(
				'is_allowed_package_url',
				array( 'https://github.com/TheAggressive/Aggressive-Apparel/releases/download/v1.2.3/aggressive-apparel-1.2.3.zip' )
			)
		);
		$this->assertTrue(
			$this->invoke_private(
				'is_allowed_package_url',
				array( 'https://api.github.com/repos/TheAggressive/Aggressive-Apparel/zipball/v1.2.3' )
			)
		);
		$this->assertFalse(
			$this->invoke_private(
				'is_allowed_package_url',
				array( 'http://github.com/TheAggressive/Aggressive-Apparel/releases/download/v1.2.3/aggressive-apparel-1.2.3.zip' )
			)
		);
		$this->assertFalse(
			$this->invoke_private(
				'is_allowed_package_url',
				array( 'https://example.com/aggressive-apparel-1.2.3.zip' )
			)
		);
		$this->assertFalse(
			$this->invoke_private(
				'is_allowed_package_url',
				array( 'https://github.com/TheAggressive/Other-Theme/releases/download/v1.2.3/aggressive-apparel-1.2.3.zip' )
			)
		);
	}

	/**
	 * Release asset selection ignores checksum files and unrelated assets.
	 */
	public function test_release_asset_selection_prefers_theme_zip(): void {
		$url = $this->invoke_private(
			'get_release_asset_download_url',
			array(
				$this->release_data(
					array(
						array(
							'name'                 => 'aggressive-apparel-1.2.3.zip.sha256',
							'browser_download_url' => 'https://github.com/TheAggressive/Aggressive-Apparel/releases/download/v1.2.3/aggressive-apparel-1.2.3.zip.sha256',
						),
						array(
							'name'                 => 'notes.txt',
							'browser_download_url' => 'https://github.com/TheAggressive/Aggressive-Apparel/releases/download/v1.2.3/notes.txt',
						),
						array(
							'name'                 => 'aggressive-apparel-1.2.3.zip',
							'browser_download_url' => 'https://github.com/TheAggressive/Aggressive-Apparel/releases/download/v1.2.3/aggressive-apparel-1.2.3.zip',
						),
					),
				),
			)
		);

		$this->assertSame(
			'https://github.com/TheAggressive/Aggressive-Apparel/releases/download/v1.2.3/aggressive-apparel-1.2.3.zip',
			$url
		);
	}

	/**
	 * Checksum asset selection matches the selected package name.
	 */
	public function test_checksum_asset_url_matches_release_zip(): void {
		$package_url  = 'https://github.com/TheAggressive/Aggressive-Apparel/releases/download/v1.2.3/aggressive-apparel-1.2.3.zip';
		$checksum_url = 'https://github.com/TheAggressive/Aggressive-Apparel/releases/download/v1.2.3/aggressive-apparel-1.2.3.zip.sha256';

		$url = $this->invoke_private(
			'get_checksum_asset_url',
			array(
				$package_url,
				$this->release_data(
					array(
						array(
							'name'                 => 'aggressive-apparel-1.2.3.zip',
							'browser_download_url' => $package_url,
						),
						array(
							'name'                 => 'aggressive-apparel-1.2.3.zip.sha256',
							'browser_download_url' => $checksum_url,
						),
					)
				),
			)
		);

		$this->assertSame( $checksum_url, $url );
	}

	/**
	 * Checksum files are parsed in standard sha256sum format.
	 */
	public function test_fetch_checksum_parses_sha256sum_output(): void {
		$checksum    = strtoupper( str_repeat( 'a', 64 ) );
		$callback    = static function () use ( $checksum ) {
			return array(
				'headers'  => array(),
				'body'     => $checksum . '  aggressive-apparel-1.2.3.zip',
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
			);
		};
		$remote_hook = 'pre_http_request';

		add_filter( $remote_hook, $callback );

		try {
			$result = $this->invoke_private(
				'fetch_checksum',
				array( 'https://github.com/TheAggressive/Aggressive-Apparel/releases/download/v1.2.3/aggressive-apparel-1.2.3.zip.sha256' )
			);

			$this->assertSame( strtolower( $checksum ), $result );
		} finally {
			remove_filter( $remote_hook, $callback );
		}
	}

	/**
	 * Package downloads fail closed when no matching checksum asset exists.
	 */
	public function test_package_download_fails_when_checksum_missing(): void {
		$package_url = 'https://github.com/TheAggressive/Aggressive-Apparel/releases/download/v1.2.3/aggressive-apparel-1.2.3.zip';

		$this->cache_release_data(
			$this->release_data(
				array(
					array(
						'name'                 => 'aggressive-apparel-1.2.3.zip',
						'browser_download_url' => $package_url,
					),
				)
			)
		);

		$result = $this->theme_updates->verify_package_download( false, $package_url );

		$this->assertWPError( $result );
		$this->assertSame( 'aggressive_apparel_missing_package_checksum', $result->get_error_code() );
	}

	/**
	 * Package downloads fail closed when the downloaded bytes do not match.
	 */
	public function test_package_download_fails_when_checksum_mismatches(): void {
		$package_url = 'https://github.com/TheAggressive/Aggressive-Apparel/releases/download/v1.2.3/aggressive-apparel-1.2.3.zip';
		$checksum    = hash( 'sha256', 'expected package bytes' );
		$callback    = static function ( $preempt, $parsed_args, $url ) use ( $package_url ) {
			if ( $package_url === $url && ! empty( $parsed_args['filename'] ) ) {
				file_put_contents( $parsed_args['filename'], 'unexpected package bytes' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

				return array(
					'headers'  => array(),
					'body'     => '',
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
				);
			}

			return $preempt;
		};

		set_transient(
			'aggressive_apparel_theme_update',
			array(
				'download_url' => $package_url,
				'checksum'     => $checksum,
			),
			HOUR_IN_SECONDS
		);

		add_filter( 'pre_http_request', $callback, 10, 3 );

		try {
			$result = $this->theme_updates->verify_package_download( false, $package_url );

			$this->assertWPError( $result );
			$this->assertSame( 'aggressive_apparel_package_checksum_mismatch', $result->get_error_code() );
		} finally {
			remove_filter( 'pre_http_request', $callback, 10 );
		}
	}

	/**
	 * Package downloads return the verified local file when checksums match.
	 */
	public function test_package_download_returns_verified_file_when_checksum_matches(): void {
		$package_url  = 'https://github.com/TheAggressive/Aggressive-Apparel/releases/download/v1.2.3/aggressive-apparel-1.2.3.zip';
		$checksum_url = 'https://github.com/TheAggressive/Aggressive-Apparel/releases/download/v1.2.3/aggressive-apparel-1.2.3.zip.sha256';
		$body         = 'verified package bytes';
		$checksum     = hash( 'sha256', $body );
		$callback     = static function ( $preempt, $parsed_args, $url ) use ( $package_url, $checksum_url, $body, $checksum ) {
			if ( $checksum_url === $url ) {
				return array(
					'headers'  => array(),
					'body'     => $checksum . '  aggressive-apparel-1.2.3.zip',
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
				);
			}

			if ( $package_url === $url && ! empty( $parsed_args['filename'] ) ) {
				file_put_contents( $parsed_args['filename'], $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

				return array(
					'headers'  => array(),
					'body'     => '',
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
				);
			}

			return $preempt;
		};

		$this->cache_release_data(
			$this->release_data(
				array(
					array(
						'name'                 => 'aggressive-apparel-1.2.3.zip',
						'browser_download_url' => $package_url,
					),
					array(
						'name'                 => 'aggressive-apparel-1.2.3.zip.sha256',
						'browser_download_url' => $checksum_url,
					),
				)
			)
		);

		add_filter( 'pre_http_request', $callback, 10, 3 );

		try {
			$result = $this->theme_updates->verify_package_download( false, $package_url );

			$this->assertIsString( $result );
			$this->assertFileExists( $result );
			$this->assertSame( $body, file_get_contents( $result ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			wp_delete_file( $result );
		} finally {
			remove_filter( 'pre_http_request', $callback, 10 );
		}
	}
}
