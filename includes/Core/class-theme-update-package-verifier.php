<?php
/**
 * Theme Update Package Verifier
 *
 * @package Aggressive_Apparel\Core
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves release checksums and verifies downloaded update packages.
 */
final class Theme_Update_Package_Verifier {

	/** Package checksum algorithm. */
	public const CHECKSUM_ALGORITHM = 'sha256';

	/** Cached update metadata key. */
	private const UPDATE_CACHE_KEY = 'aggressive_apparel_theme_update';

	/**
	 * Release repository.
	 *
	 * @var Theme_Update_Release_Repository
	 */
	private Theme_Update_Release_Repository $releases;

	/**
	 * HTTP client.
	 *
	 * @var Theme_Update_Http_Client
	 */
	private Theme_Update_Http_Client $http;

	/**
	 * Constructor.
	 *
	 * @param Theme_Update_Release_Repository $releases Release repository.
	 * @param Theme_Update_Http_Client        $http     HTTP client.
	 */
	public function __construct(
		Theme_Update_Release_Repository $releases,
		Theme_Update_Http_Client $http
	) {
		$this->releases = $releases;
		$this->http     = $http;
	}

	/**
	 * Download and verify this theme's update package before installation.
	 *
	 * @param false|\WP_Error|string $reply   Existing pre-download result.
	 * @param mixed                  $package Package URL.
	 * @return false|\WP_Error|string Verified package path, original reply, or error.
	 */
	public function verify_download( $reply, $package ) {
		if ( false !== $reply ) {
			return $reply;
		}

		if ( ! is_string( $package ) || ! $this->releases->is_allowed_package_url( $package ) ) {
			return $reply;
		}

		$checksum = $this->get_checksum( $package );
		if ( ! $checksum ) {
			return new \WP_Error(
				'aggressive_apparel_missing_package_checksum',
				__( 'Aggressive Apparel update package is missing a SHA-256 checksum.', 'aggressive-apparel' )
			);
		}

		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$downloaded = download_url( $package );
		if ( is_wp_error( $downloaded ) ) {
			return $downloaded;
		}

		$actual = hash_file( self::CHECKSUM_ALGORITHM, $downloaded );
		if ( ! is_string( $actual ) || ! hash_equals( strtolower( $checksum ), strtolower( $actual ) ) ) {
			wp_delete_file( $downloaded );

			return new \WP_Error(
				'aggressive_apparel_package_checksum_mismatch',
				__( 'Aggressive Apparel update package checksum verification failed.', 'aggressive-apparel' )
			);
		}

		return $downloaded;
	}

	/**
	 * Resolve the expected package checksum from cached or fresh release data.
	 *
	 * @param string                    $package_url  Package URL.
	 * @param array<string, mixed>|null $release_data Optional release data.
	 * @return string|false Lowercase SHA-256 hash, or false when unavailable.
	 */
	public function get_checksum( string $package_url, ?array $release_data = null ) {
		$cached_data = get_transient( self::UPDATE_CACHE_KEY );
		if (
			is_array( $cached_data )
			&& isset( $cached_data['download_url'], $cached_data['checksum'] )
			&& is_string( $cached_data['download_url'] )
			&& is_string( $cached_data['checksum'] )
			&& hash_equals( $cached_data['download_url'], $package_url )
			&& $this->is_valid_sha256( $cached_data['checksum'] )
		) {
			return strtolower( $cached_data['checksum'] );
		}

		$release_data = $release_data ?? $this->releases->get_release_data();
		if ( ! is_array( $release_data ) ) {
			return false;
		}

		$checksum_url = $this->get_checksum_asset_url( $package_url, $release_data );
		if ( ! $checksum_url ) {
			return false;
		}

		return $this->fetch_checksum( $checksum_url );
	}

	/**
	 * Find the checksum asset URL belonging to a package URL.
	 *
	 * @param string               $package_url  Package URL.
	 * @param array<string, mixed> $release_data GitHub release data.
	 * @return string|false Checksum asset URL.
	 */
	public function get_checksum_asset_url( string $package_url, array $release_data ) {
		if ( empty( $release_data['assets'] ) || ! is_array( $release_data['assets'] ) ) {
			return false;
		}

		$package_name = $this->get_asset_name_for_url( $package_url, $release_data );
		if ( ! $package_name ) {
			return false;
		}

		$candidates = array(
			$package_name . '.sha256',
			$package_name . '.sha256sum',
		);

		foreach ( $release_data['assets'] as $asset ) {
			if ( ! is_array( $asset ) ) {
				continue;
			}

			$name = isset( $asset['name'] ) && is_string( $asset['name'] ) ? $asset['name'] : '';
			$url  = isset( $asset['browser_download_url'] ) && is_string( $asset['browser_download_url'] ) ? $asset['browser_download_url'] : '';

			if ( in_array( $name, $candidates, true ) && $this->releases->is_allowed_checksum_url( $url ) ) {
				return $url;
			}
		}

		return false;
	}

	/**
	 * Resolve the release asset name for a package URL.
	 *
	 * @param string               $package_url  Package URL.
	 * @param array<string, mixed> $release_data GitHub release data.
	 * @return string|false Asset name.
	 */
	private function get_asset_name_for_url( string $package_url, array $release_data ) {
		foreach ( (array) ( $release_data['assets'] ?? array() ) as $asset ) {
			if ( ! is_array( $asset ) ) {
				continue;
			}

			$name = isset( $asset['name'] ) && is_string( $asset['name'] ) ? $asset['name'] : '';
			$url  = isset( $asset['browser_download_url'] ) && is_string( $asset['browser_download_url'] ) ? $asset['browser_download_url'] : '';

			if ( '' !== $name && hash_equals( $url, $package_url ) ) {
				return $name;
			}
		}

		$path = wp_parse_url( $package_url, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return false;
		}

		$name = basename( rawurldecode( $path ) );

		return str_ends_with( strtolower( $name ), '.zip' ) ? $name : false;
	}

	/**
	 * Fetch and parse a checksum asset.
	 *
	 * @param string $checksum_url Checksum asset URL.
	 * @return string|false Lowercase SHA-256 hash.
	 */
	private function fetch_checksum( string $checksum_url ) {
		$response = $this->http->get(
			$checksum_url,
			array(
				'headers' => array(
					'User-Agent' => 'Aggressive-Apparel-Updater',
				),
				'timeout' => 3,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( ! is_string( $body ) || ! preg_match( '/\b([a-f0-9]{64})\b/i', $body, $matches ) ) {
			return false;
		}

		$checksum = strtolower( $matches[1] );

		return $this->is_valid_sha256( $checksum ) ? $checksum : false;
	}

	/**
	 * Whether a checksum string is a valid SHA-256 digest.
	 *
	 * @param string $checksum Candidate checksum.
	 */
	private function is_valid_sha256( string $checksum ): bool {
		return 1 === preg_match( '/^[a-f0-9]{64}$/i', $checksum );
	}
}
