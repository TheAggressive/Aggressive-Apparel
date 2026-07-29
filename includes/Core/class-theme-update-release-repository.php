<?php
/**
 * Theme Update Release Repository
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
 * Retrieves, validates, and caches stable GitHub release metadata.
 */
final class Theme_Update_Release_Repository {

	/** Cached update metadata used by WordPress update checks. */
	private const UPDATE_CACHE_KEY = 'aggressive_apparel_theme_update';

	/** Cached GitHub release metadata. */
	private const RELEASE_CACHE_KEY = 'aggressive_apparel_theme_update_release';

	/** Fresh release metadata lifetime in seconds. */
	private const RELEASE_CACHE_FRESHNESS = 300;

	/**
	 * GitHub repository owner.
	 *
	 * @var string
	 */
	private string $owner;

	/**
	 * GitHub repository name.
	 *
	 * @var string
	 */
	private string $repository;

	/**
	 * HTTP client.
	 *
	 * @var Theme_Update_Http_Client
	 */
	private Theme_Update_Http_Client $http;

	/**
	 * Constructor.
	 *
	 * @param Theme_Update_Http_Client $http       HTTP client.
	 * @param string                   $owner      GitHub repository owner.
	 * @param string                   $repository GitHub repository name.
	 */
	public function __construct(
		Theme_Update_Http_Client $http,
		string $owner = 'TheAggressive',
		string $repository = 'Aggressive-Apparel'
	) {
		$this->http       = $http;
		$this->owner      = $owner;
		$this->repository = $repository;
	}

	/**
	 * Repository homepage URL.
	 */
	public function get_repository_url(): string {
		return "https://github.com/{$this->owner}/{$this->repository}";
	}

	/**
	 * Whether a source path references this repository.
	 *
	 * @param string $source Source URL or path.
	 */
	public function is_repository_source( string $source ): bool {
		return str_contains( $source, $this->owner )
			&& str_contains( $source, $this->repository );
	}

	/**
	 * Get the latest stable release version.
	 *
	 * @return string|false Version string or false when unavailable.
	 */
	public function get_version() {
		$release_data = $this->get_release_data();

		if ( ! is_array( $release_data ) || ! isset( $release_data['tag_name'] ) || ! is_string( $release_data['tag_name'] ) ) {
			return false;
		}

		return ltrim( $release_data['tag_name'], 'v' );
	}

	/**
	 * Get a trusted package URL for the latest stable release.
	 *
	 * @return string|false Download URL or false when unavailable.
	 */
	public function get_download_url() {
		$cached_data = get_transient( self::UPDATE_CACHE_KEY );
		if (
			is_array( $cached_data )
			&& isset( $cached_data['download_url'] )
			&& is_string( $cached_data['download_url'] )
		) {
			return $this->is_allowed_package_url( $cached_data['download_url'] )
				? $cached_data['download_url']
				: false;
		}

		$release_data = $this->get_release_data();
		if ( ! is_array( $release_data ) ) {
			return $this->get_fallback_download_url();
		}

		$asset_url = $this->get_release_asset_download_url( $release_data );
		if ( $asset_url ) {
			return $asset_url;
		}

		if ( isset( $release_data['zipball_url'] ) && is_string( $release_data['zipball_url'] ) ) {
			return $this->is_allowed_package_url( $release_data['zipball_url'] )
				? $release_data['zipball_url']
				: false;
		}

		if ( isset( $release_data['tag_name'] ) && is_string( $release_data['tag_name'] ) ) {
			$tag = ltrim( $release_data['tag_name'], 'v' );
			$url = "{$this->get_repository_url()}/releases/download/v{$tag}/aggressive-apparel-{$tag}.zip";

			return $this->is_allowed_package_url( $url ) ? $url : false;
		}

		return false;
	}

	/**
	 * Select a safe ZIP asset from release metadata.
	 *
	 * @param array<string, mixed> $release_data GitHub release data.
	 * @return string|false Release asset URL, or false when none is suitable.
	 */
	public function get_release_asset_download_url( array $release_data ) {
		if ( empty( $release_data['assets'] ) || ! is_array( $release_data['assets'] ) ) {
			return false;
		}

		$zip_assets = array();
		foreach ( $release_data['assets'] as $asset ) {
			if ( ! is_array( $asset ) ) {
				continue;
			}

			$name = isset( $asset['name'] ) && is_string( $asset['name'] ) ? $asset['name'] : '';
			$url  = isset( $asset['browser_download_url'] ) && is_string( $asset['browser_download_url'] ) ? $asset['browser_download_url'] : '';

			if ( '' === $name || '' === $url || ! str_ends_with( strtolower( $name ), '.zip' ) || ! $this->is_allowed_package_url( $url ) ) {
				continue;
			}

			$zip_assets[] = array(
				'name' => $name,
				'url'  => $url,
			);
		}

		foreach ( $zip_assets as $asset ) {
			$name = sanitize_title( $asset['name'] );
			if ( str_contains( $name, sanitize_title( $this->repository ) ) || str_contains( $name, 'aggressive-apparel' ) ) {
				return $asset['url'];
			}
		}

		return $zip_assets[0]['url'] ?? false;
	}

	/**
	 * Whether a package URL belongs to the expected GitHub repository.
	 *
	 * @param string $url Candidate package URL.
	 */
	public function is_allowed_package_url( string $url ): bool {
		$parts = wp_parse_url( $url );
		if ( ! $this->has_trusted_origin_and_path( $parts ) ) {
			return false;
		}

		$host  = strtolower( (string) ( $parts['host'] ?? '' ) );
		$path  = strtolower( rawurldecode( (string) ( $parts['path'] ?? '' ) ) );
		$owner = strtolower( $this->owner );
		$repo  = strtolower( $this->repository );

		if ( 'github.com' === $host ) {
			return str_starts_with( $path, "/{$owner}/{$repo}/releases/download/" )
				&& str_ends_with( $path, '.zip' );
		}

		if ( 'api.github.com' === $host ) {
			return str_starts_with( $path, "/repos/{$owner}/{$repo}/zipball/" );
		}

		return false;
	}

	/**
	 * Whether a checksum URL belongs to the expected GitHub release.
	 *
	 * @param string $url Candidate checksum URL.
	 */
	public function is_allowed_checksum_url( string $url ): bool {
		$parts = wp_parse_url( $url );
		if ( ! $this->has_trusted_origin_and_path( $parts ) ) {
			return false;
		}

		$host  = strtolower( (string) ( $parts['host'] ?? '' ) );
		$path  = strtolower( rawurldecode( (string) ( $parts['path'] ?? '' ) ) );
		$owner = strtolower( $this->owner );
		$repo  = strtolower( $this->repository );

		return 'github.com' === $host
			&& str_starts_with( $path, "/{$owner}/{$repo}/releases/download/" )
			&& ( str_ends_with( $path, '.zip.sha256' ) || str_ends_with( $path, '.zip.sha256sum' ) );
	}

	/**
	 * Validate the immutable origin and normalized path constraints.
	 *
	 * @param array<string, int|string>|false $parts Parsed URL parts.
	 */
	private function has_trusted_origin_and_path( $parts ): bool {
		if ( ! is_array( $parts ) ) {
			return false;
		}

		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		$host   = strtolower( (string) ( $parts['host'] ?? '' ) );
		$path   = rawurldecode( (string) ( $parts['path'] ?? '' ) );
		$port   = (int) ( $parts['port'] ?? 443 );

		if (
			'https' !== $scheme
			|| ! in_array( $host, array( 'github.com', 'api.github.com' ), true )
			|| 443 !== $port
			|| '' === $path
			|| isset( $parts['user'] )
			|| isset( $parts['pass'] )
		) {
			return false;
		}

		$segments = explode( '/', $path );

		return ! in_array( '.', $segments, true ) && ! in_array( '..', $segments, true );
	}

	/**
	 * Get the latest stable GitHub release, with stale-cache fallback.
	 *
	 * @return array<string, mixed>|false Release data or false on error.
	 */
	public function get_release_data() {
		$cached         = get_transient( self::RELEASE_CACHE_KEY );
		$cached_release = $this->get_cached_release( $cached );

		if (
			is_array( $cached )
			&& null !== $cached_release
			&& isset( $cached['checked_at'] )
			&& ( time() - (int) $cached['checked_at'] ) < self::RELEASE_CACHE_FRESHNESS
		) {
			return $cached_release;
		}

		$url      = "https://api.github.com/repos/{$this->owner}/{$this->repository}/releases?per_page=20";
		$response = $this->http->get(
			$url,
			array(
				'headers' => array(
					'User-Agent' => 'Aggressive-Apparel-Updater',
					'Accept'     => 'application/vnd.github.v3+json',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $cached_release ?? false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $body ) ) {
			return $cached_release ?? false;
		}

		$best_release = $this->select_latest_stable_release( $body );
		if ( null === $best_release ) {
			return $cached_release ?? false;
		}

		set_transient(
			self::RELEASE_CACHE_KEY,
			array(
				'release_data' => $best_release,
				'checked_at'   => time(),
			),
			HOUR_IN_SECONDS
		);

		return $best_release;
	}

	/**
	 * Select the highest stable semver release.
	 *
	 * @param array<mixed> $releases GitHub release list.
	 * @return array<string, mixed>|null
	 */
	private function select_latest_stable_release( array $releases ): ?array {
		$best_release = null;
		$best_version = null;

		foreach ( $releases as $release ) {
			if (
				! is_array( $release )
				|| ! empty( $release['draft'] )
				|| ! empty( $release['prerelease'] )
				|| empty( $release['tag_name'] )
				|| ! is_string( $release['tag_name'] )
			) {
				continue;
			}

			$tag = ltrim( $release['tag_name'], 'v' );
			if ( ! preg_match( '/^\d+\.\d+(\.\d+)?$/', $tag ) ) {
				continue;
			}

			if ( null === $best_version || version_compare( $tag, $best_version, '>' ) ) {
				$best_version = $tag;
				$best_release = $release;
			}
		}

		if ( null === $best_release || null === $best_version ) {
			return null;
		}

		$best_release['tag_name'] = 'v' . $best_version;

		return $best_release;
	}

	/**
	 * Read validated release data from a transient value.
	 *
	 * @param mixed $cached Cached transient value.
	 * @return array<string, mixed>|null
	 */
	private function get_cached_release( $cached ): ?array {
		if (
			! is_array( $cached )
			|| ! isset( $cached['release_data'] )
			|| ! is_array( $cached['release_data'] )
		) {
			return null;
		}

		return $cached['release_data'];
	}

	/**
	 * Build a trusted fallback URL from cached update metadata.
	 *
	 * @return string|false
	 */
	private function get_fallback_download_url() {
		$cached_data = get_transient( self::UPDATE_CACHE_KEY );
		if ( ! is_array( $cached_data ) || ! isset( $cached_data['version'] ) || ! is_string( $cached_data['version'] ) ) {
			return false;
		}

		$tag = ltrim( $cached_data['version'], 'v' );
		$url = "{$this->get_repository_url()}/releases/download/v{$tag}/aggressive-apparel-{$tag}.zip";

		return $this->is_allowed_package_url( $url ) ? $url : false;
	}
}
