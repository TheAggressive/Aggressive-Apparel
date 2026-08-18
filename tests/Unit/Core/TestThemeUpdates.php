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
use Aggressive_Apparel\Core\Theme_Update_Http_Client;
use Aggressive_Apparel\Core\Theme_Update_Package_Verifier;
use Aggressive_Apparel\Core\Theme_Update_Release_Repository;
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
	 * Release repository.
	 *
	 * @var Theme_Update_Release_Repository
	 */
	private $releases;

	/**
	 * Package verifier.
	 *
	 * @var Theme_Update_Package_Verifier
	 */
	private $packages;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();
		$this->theme_updates = Theme_Updates::get_instance();
		$http                = new Theme_Update_Http_Client();
		$this->releases      = new Theme_Update_Release_Repository( $http );
		$this->packages      = new Theme_Update_Package_Verifier( $this->releases, $http );
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
			$this->releases->is_allowed_package_url(
				'https://github.com/TheAggressive/Aggressive-Apparel/releases/download/v1.2.3/aggressive-apparel-1.2.3.zip'
			)
		);
		$this->assertTrue(
			$this->releases->is_allowed_package_url(
				'https://api.github.com/repos/TheAggressive/Aggressive-Apparel/zipball/v1.2.3'
			)
		);
		$this->assertFalse(
			$this->releases->is_allowed_package_url(
				'http://github.com/TheAggressive/Aggressive-Apparel/releases/download/v1.2.3/aggressive-apparel-1.2.3.zip'
			)
		);
		$this->assertFalse(
			$this->releases->is_allowed_package_url( 'https://example.com/aggressive-apparel-1.2.3.zip' )
		);
		$this->assertFalse(
			$this->releases->is_allowed_package_url(
				'https://github.com/TheAggressive/Other-Theme/releases/download/v1.2.3/aggressive-apparel-1.2.3.zip'
			)
		);
		$this->assertFalse(
			$this->releases->is_allowed_package_url(
				'https://github.com:8443/TheAggressive/Aggressive-Apparel/releases/download/v1.2.3/aggressive-apparel-1.2.3.zip'
			)
		);
		$this->assertFalse(
			$this->releases->is_allowed_package_url(
				'https://github.com/TheAggressive/Aggressive-Apparel/releases/download/v1.2.3/%2E%2E/other.zip'
			)
		);
		$this->assertFalse(
			$this->releases->is_allowed_package_url(
				'https://api.github.com/repos/TheAggressive/Aggressive-Apparel/zipball-redirect/v1.2.3'
			)
		);
	}

	/**
	 * Release asset selection ignores checksum files and unrelated assets.
	 */
	public function test_release_asset_selection_prefers_theme_zip(): void {
		$url = $this->releases->get_release_asset_download_url(
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
				)
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

		$url = $this->packages->get_checksum_asset_url(
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
			)
		);

		$this->assertSame( $checksum_url, $url );
	}

	/**
	 * Checksum files are parsed in standard sha256sum format.
	 */
	public function test_fetch_checksum_parses_sha256sum_output(): void {
		$package_url  = 'https://github.com/TheAggressive/Aggressive-Apparel/releases/download/v1.2.3/aggressive-apparel-1.2.3.zip';
		$checksum_url = $package_url . '.sha256';
		$checksum     = strtoupper( str_repeat( 'a', 64 ) );
		$callback     = static function () use ( $checksum ) {
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
			$result = $this->packages->get_checksum(
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
				)
			);

			$this->assertSame( strtolower( $checksum ), $result );
		} finally {
			remove_filter( $remote_hook, $callback );
		}
	}

	/**
	 * Release selection ignores malformed, draft, and prerelease entries.
	 */
	public function test_release_repository_selects_highest_stable_semver(): void {
		$releases = array(
			null,
			array(
				'tag_name'  => 'v3.0.0',
				'draft'     => true,
				'prerelease' => false,
			),
			array(
				'tag_name'  => 'v2.0.0',
				'draft'     => false,
				'prerelease' => true,
			),
			array(
				'tag_name'  => 'not-semver',
				'draft'     => false,
				'prerelease' => false,
			),
			array(
				'tag_name'  => 'v1.9.0',
				'draft'     => false,
				'prerelease' => false,
			),
			array(
				'tag_name'  => '1.10.0',
				'draft'     => false,
				'prerelease' => false,
			),
		);
		$callback = static function () use ( $releases ) {
			return array(
				'headers'  => array(),
				'body'     => wp_json_encode( $releases ),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
			);
		};

		add_filter( 'pre_http_request', $callback );

		try {
			$release = $this->releases->get_release_data();

			$this->assertIsArray( $release );
			$this->assertSame( 'v1.10.0', $release['tag_name'] );
			$this->assertSame( '1.10.0', $this->releases->get_version() );
		} finally {
			remove_filter( 'pre_http_request', $callback );
		}
	}

	/**
	 * Stale release data remains available during a transient API failure.
	 */
	public function test_release_repository_uses_stale_cache_on_http_failure(): void {
		$stale_release = $this->release_data();
		set_transient(
			'aggressive_apparel_theme_update_release',
			array(
				'release_data' => $stale_release,
				'checked_at'   => time() - 301,
			),
			HOUR_IN_SECONDS
		);

		$callback = static function () {
			return new \WP_Error( 'github_unavailable', 'GitHub is unavailable.' );
		};

		add_filter( 'pre_http_request', $callback );

		try {
			$this->assertSame( $stale_release, $this->releases->get_release_data() );
		} finally {
			remove_filter( 'pre_http_request', $callback );
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

	/**
	 * The updater must be unhookable, in both directions, via its filter.
	 */
	public function test_is_enabled_is_controlled_by_filter(): void {
		add_filter( 'aggressive_apparel_enable_theme_updates', '__return_false' );
		try {
			$this->assertFalse( Theme_Updates::is_enabled() );
		} finally {
			remove_filter( 'aggressive_apparel_enable_theme_updates', '__return_false' );
		}

		add_filter( 'aggressive_apparel_enable_theme_updates', '__return_true' );
		try {
			$this->assertTrue( Theme_Updates::is_enabled() );
		} finally {
			remove_filter( 'aggressive_apparel_enable_theme_updates', '__return_true' );
		}
	}

	/**
	 * The filter receives the real checkout state, so a caller can re-enable
	 * updates for a checkout deliberately rather than by accident.
	 */
	public function test_is_enabled_reports_checkout_state_to_filter(): void {
		$expected = is_dir( trailingslashit( get_template_directory() ) . '.git' );
		$observed = null;

		$callback = static function ( $enabled, $is_checkout ) use ( &$observed ) {
			$observed = $is_checkout;
			return $enabled;
		};

		add_filter( 'aggressive_apparel_enable_theme_updates', $callback, 10, 2 );
		try {
			$enabled = Theme_Updates::is_enabled();
		} finally {
			remove_filter( 'aggressive_apparel_enable_theme_updates', $callback, 10 );
		}

		$this->assertSame( $expected, $observed );
		$this->assertSame( ! $expected, $enabled );
	}

	/**
	 * A disabled updater must register nothing at all. Advertising an update it
	 * refuses to install, or installing over a checkout, are both failures.
	 */
	public function test_init_registers_no_hooks_when_disabled(): void {
		$hooks = array(
			'pre_set_site_transient_update_themes' => 'check_for_update',
			'upgrader_pre_download'                => 'verify_package_download',
			'upgrader_source_selection'            => 'rename_package',
			'themes_api'                           => 'themes_api',
		);

		foreach ( $hooks as $hook => $method ) {
			remove_filter( $hook, array( $this->theme_updates, $method ), 'pre_set_site_transient_update_themes' === $hook ? 100 : 10 );
		}

		add_filter( 'aggressive_apparel_enable_theme_updates', '__return_false' );
		try {
			$this->theme_updates->init();

			foreach ( $hooks as $hook => $method ) {
				$this->assertFalse(
					has_filter( $hook, array( $this->theme_updates, $method ) ),
					"init() registered {$hook} while the updater was disabled."
				);
			}
		} finally {
			remove_filter( 'aggressive_apparel_enable_theme_updates', '__return_false' );
		}
	}
}
