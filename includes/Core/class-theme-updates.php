<?php
/**
 * Theme Updates
 *
 * Handles automatic theme updates from GitHub releases.
 *
 * @since 1.0.0
 * @package Aggressive_Apparel\Core
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme Updates Class
 *
 * Manages theme update checking, notifications, and installation from GitHub.
 *
 * @since 1.0.0
 */
class Theme_Updates {

	/**
	 * The single instance of the class.
	 *
	 * @var Theme_Updates|null
	 */
	private static ?Theme_Updates $instance = null;

	/**
	 * GitHub repository owner.
	 *
	 * @var string
	 */
	private string $repo_owner = 'TheAggressive';

	/**
	 * GitHub repository name.
	 *
	 * @var string
	 */
	private string $repo_name = 'Aggressive-Apparel';

	/**
	 * Hash algorithm used for release package verification.
	 *
	 * @var string
	 */
	private const PACKAGE_CHECKSUM_ALGORITHM = 'sha256';

	/**
	 * Private constructor for singleton.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing.
	 *
	 * @return void
	 * @throws \RuntimeException Cannot unserialize singleton.
	 */
	public function __wakeup() {
		throw new \RuntimeException( 'Cannot unserialize singleton.' );
	}

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return Theme_Updates
	 */
	public static function get_instance(): Theme_Updates {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the theme updater.
	 *
	 * Call this from theme bootstrap.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		add_filter( 'pre_set_site_transient_update_themes', array( $this, 'check_for_update' ), 100, 1 );
		add_filter( 'upgrader_pre_download', array( $this, 'verify_package_download' ), 10, 4 );
		add_filter( 'upgrader_source_selection', array( $this, 'rename_package' ), 10, 3 );
		add_filter( 'themes_api', array( $this, 'themes_api' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'admin_update_notice' ) );
		add_action( 'load-update-core.php', array( $this, 'force_fresh_check' ) );
	}

	/**
	 * Force a fresh check when visiting the update core page.
	 *
	 * @since 1.8.0
	 * @return void
	 */
	public function force_fresh_check(): void {
		// Clear our theme update cache when visiting update-core.php.
		delete_transient( 'aggressive_apparel_theme_update' );
		delete_transient( 'aggressive_apparel_theme_update_release' );

		// Force WordPress to refresh theme updates.
		wp_update_themes();
	}

	/**
	 * Check for updates by comparing the current version with the GitHub release.
	 *
	 * @since 1.0.0
	 * @param object $transient Transient update data.
	 * @return object Modified transient.
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$theme           = wp_get_theme();
		$theme_slug      = $theme->get_stylesheet();
		$current_version = $theme->get( 'Version' );
		$source_version  = $this->get_github_version();

		if ( ! $source_version || ! is_string( $source_version ) ) {
			return $transient;
		}

		if ( version_compare( $source_version, $current_version, '>' ) ) {

			$download_url = $this->get_download_url();

			// If we can't get a valid download URL, don't advertise an update.
			if ( ! $download_url || ! is_string( $download_url ) || ! $this->is_allowed_package_url( $download_url ) ) {
				return $transient;
			}

			$release_data = $this->get_github_release_data();
			$checksum     = $this->get_package_checksum( $download_url, is_array( $release_data ) ? $release_data : null );
			if ( ! $checksum ) {
				return $transient;
			}

			if ( ! isset( $transient->response ) ) {
				$transient->response = array(); // @phpstan-ignore property.notFound
			}

			$transient->response[ $theme_slug ] = array(
				'theme'       => $theme_slug,
				'new_version' => $source_version,
				'url'         => "https://github.com/{$this->repo_owner}/{$this->repo_name}",
				'package'     => $download_url,
				'checksum'    => self::PACKAGE_CHECKSUM_ALGORITHM . ':' . $checksum,
			);

			// Cache the update data and release info for changelog and fallback.
			if ( $release_data ) {
				set_transient(
					'aggressive_apparel_theme_update',
					array(
						'version'      => $source_version,
						'download_url' => $download_url,
						'checksum'     => $checksum,
						'release_data' => $release_data,
						'checked_at'   => time(),
					),
					HOUR_IN_SECONDS
				);
			}
		}

		return $transient;
	}

	/**
	 * Fetch the latest version from GitHub API.
	 *
	 * Always uses the latest stable (non-draft, non-prerelease) release
	 * with the highest semver tag.
	 *
	 * @since 1.0.0
	 * @return string|false Version string or false on error.
	 */
	private function get_github_version() {
		$release_data = $this->get_github_release_data();

		if ( ! $release_data ) {
			return false;
		}

		if ( isset( $release_data['tag_name'] ) && is_string( $release_data['tag_name'] ) ) {
			return ltrim( $release_data['tag_name'], 'v' );
		}

		return false;
	}

	/**
	 * Get the download URL for the latest GitHub release.
	 *
	 * @since 1.0.0
	 * @return string|false Download URL or false on error.
	 */
	private function get_download_url() {
		// Try to get from cached update data first.
		$cached_data = get_transient( 'aggressive_apparel_theme_update' );
		if ( $cached_data && isset( $cached_data['download_url'] ) && is_string( $cached_data['download_url'] ) ) {
			return $this->is_allowed_package_url( $cached_data['download_url'] ) ? $cached_data['download_url'] : false;
		}

		$release_data = $this->get_github_release_data();

		if ( ! $release_data ) {
			// Fallback: Try to construct URL from cached version data.
			return $this->get_fallback_download_url();
		}

		// First try to get a release ZIP asset uploaded by the release pipeline.
		$asset_url = $this->get_release_asset_download_url( $release_data );
		if ( $asset_url ) {
			return $asset_url;
		}

		// If no assets, use zipball_url as primary fallback (auto-generated ZIP).
		if ( isset( $release_data['zipball_url'] ) && is_string( $release_data['zipball_url'] ) ) {
			return $this->is_allowed_package_url( $release_data['zipball_url'] ) ? $release_data['zipball_url'] : false;
		}

		// Final fallback: construct URL based on tag name.
		if ( isset( $release_data['tag_name'] ) ) {
			$tag = ltrim( $release_data['tag_name'], 'v' );
			$url = "https://github.com/{$this->repo_owner}/{$this->repo_name}/releases/download/v{$tag}/aggressive-apparel-{$tag}.zip";
			return $this->is_allowed_package_url( $url ) ? $url : false;
		}

		return false;
	}

	/**
	 * Select a safe ZIP release asset from GitHub release data.
	 *
	 * Release metadata can contain checksum files, screenshots, or other assets;
	 * do not trust the first asset blindly. Prefer a ZIP with the theme/repo name
	 * in it, then fall back to the first allowed ZIP asset.
	 *
	 * @param array<string, mixed> $release_data GitHub release data.
	 * @return string|false Release asset URL, or false when none is suitable.
	 */
	private function get_release_asset_download_url( array $release_data ) {
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
			if ( str_contains( $name, sanitize_title( $this->repo_name ) ) || str_contains( $name, 'aggressive-apparel' ) ) {
				return $asset['url'];
			}
		}

		return $zip_assets[0]['url'] ?? false;
	}

	/**
	 * Whether a package URL belongs to the expected GitHub repository.
	 *
	 * @param string $url Candidate package URL.
	 * @return bool
	 */
	private function is_allowed_package_url( string $url ): bool {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return false;
		}

		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		$host   = strtolower( (string) ( $parts['host'] ?? '' ) );
		$path   = strtolower( rawurldecode( (string) ( $parts['path'] ?? '' ) ) );
		$owner  = strtolower( $this->repo_owner );
		$repo   = strtolower( $this->repo_name );

		if ( 'https' !== $scheme || '' === $path ) {
			return false;
		}

		if ( 'github.com' === $host ) {
			return str_starts_with( $path, "/{$owner}/{$repo}/releases/download/" )
				&& str_ends_with( $path, '.zip' );
		}

		if ( 'api.github.com' === $host ) {
			return str_starts_with( $path, "/repos/{$owner}/{$repo}/zipball" );
		}

		return false;
	}

	/**
	 * Download and verify this theme's update package before installation.
	 *
	 * WordPress calls this before downloading an update package. For our own
	 * GitHub packages, we take over the download, verify the SHA-256 checksum
	 * published beside the release asset, and return the verified local file.
	 * Other packages are left untouched.
	 *
	 * @param false|\WP_Error|string $reply      Existing pre-download result.
	 * @param string                 $package    Package URL.
	 * @param \WP_Upgrader|null      $_upgrader  Upgrader instance.
	 * @param array<string, mixed>   $_hook_extra Upgrader context.
	 * @return false|\WP_Error|string Verified package path, original reply, or error.
	 */
	public function verify_package_download( $reply, $package, $_upgrader = null, array $_hook_extra = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( false !== $reply ) {
			return $reply;
		}

		if ( ! is_string( $package ) || ! $this->is_allowed_package_url( $package ) ) {
			return $reply;
		}

		$checksum = $this->get_package_checksum( $package );
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

		$actual = hash_file( self::PACKAGE_CHECKSUM_ALGORITHM, $downloaded );
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
	private function get_package_checksum( string $package_url, ?array $release_data = null ) {
		$cached_data = get_transient( 'aggressive_apparel_theme_update' );
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

		$release_data = $release_data ?? $this->get_github_release_data();
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
	 * Find the checksum asset URL that belongs to a package URL.
	 *
	 * @param string               $package_url  Package URL.
	 * @param array<string, mixed> $release_data GitHub release data.
	 * @return string|false Checksum asset URL.
	 */
	private function get_checksum_asset_url( string $package_url, array $release_data ) {
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

			if ( in_array( $name, $candidates, true ) && $this->is_allowed_checksum_url( $url ) ) {
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
		$response = wp_remote_get(
			$checksum_url,
			array(
				'headers' => array(
					'User-Agent' => 'Aggressive-Apparel-Updater',
				),
				'timeout' => 15,
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
	 * @return bool
	 */
	private function is_valid_sha256( string $checksum ): bool {
		return 1 === preg_match( '/^[a-f0-9]{64}$/i', $checksum );
	}

	/**
	 * Whether a checksum URL belongs to the expected GitHub release.
	 *
	 * @param string $url Candidate checksum URL.
	 * @return bool
	 */
	private function is_allowed_checksum_url( string $url ): bool {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return false;
		}

		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		$host   = strtolower( (string) ( $parts['host'] ?? '' ) );
		$path   = strtolower( rawurldecode( (string) ( $parts['path'] ?? '' ) ) );
		$owner  = strtolower( $this->repo_owner );
		$repo   = strtolower( $this->repo_name );

		return 'https' === $scheme
			&& 'github.com' === $host
			&& str_starts_with( $path, "/{$owner}/{$repo}/releases/download/" )
			&& ( str_ends_with( $path, '.zip.sha256' ) || str_ends_with( $path, '.zip.sha256sum' ) );
	}

	/**
	 * Rename the downloaded folder to match the theme directory name.
	 *
	 * @since 1.0.0
	 * @param string       $source        Path to the source directory.
	 * @param string       $remote_source Path to the remote source.
	 * @param \WP_Upgrader $_upgrader     The upgrader instance.
	 * @return string Modified source path.
	 */
	public function rename_package( $source, $remote_source, $_upgrader ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		// Extract theme slug from the remote source path.
		// The path typically looks like: /path/to/upgrade/theme-slug-version/.
		$path_parts = explode( '/', trim( $remote_source, '/' ) );
		$filename   = end( $path_parts );

		// Extract theme slug from filename (remove version suffix).
		// Format: theme-slug-version or theme-slug.version.
		$theme_slug = preg_replace( '/-[\d\.]+$/', '', $filename );

		if ( empty( $theme_slug ) ) {
			return $source;
		}

		// Check if this is from our GitHub repo.
		$is_github_source = false !== strpos( $remote_source, $this->repo_owner )
			&& false !== strpos( $remote_source, $this->repo_name );

		if ( ! $is_github_source ) {
			// Not from our repo, return source unchanged.
			return $source;
		}

		// Get the actual theme slug from WordPress to ensure we use the correct one.
		$actual_theme_slug = wp_get_theme()->get_stylesheet();

		// Check if the extracted directory name matches the theme slug.
		$extracted_dir_name = basename( $source );

		// If the directory name matches the theme slug, no renaming needed.
		if ( $extracted_dir_name === $actual_theme_slug ) {
			return $source;
		}

		// Directory name doesn't match, we need to rename it.
		$parent_dir  = dirname( $source );
		$target_path = trailingslashit( $parent_dir ) . $actual_theme_slug;

		// Use WordPress filesystem API for file operations.
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			\WP_Filesystem();
		}

		if ( $wp_filesystem && $wp_filesystem->move( $source, $target_path ) ) {
			return $target_path;
		}

		return $source;
	}

	/**
	 * Provide theme information for WordPress themes API.
	 *
	 * @since 1.0.0
	 * @param false|object|array $result The result object or array. Default false.
	 * @param string             $action The type of information being requested from the Theme Installation API.
	 * @param object             $args   Arguments used to query for installer.
	 * @return false|object|array Modified result with theme information.
	 */
	public function themes_api( $result, $action, $args ) {
		// Only handle theme information requests for our theme.
		if ( 'theme_information' !== $action || ! isset( $args->slug ) ) {
			return $result;
		}

		$theme      = wp_get_theme();
		$theme_slug = $theme->get_stylesheet();

		if ( $args->slug !== $theme_slug ) {
			return $result;
		}

		// Fetch release data from GitHub.
		$release_data = $this->get_github_release_data();

		if ( ! $release_data ) {
			return $result;
		}

		$download_link = $this->get_download_url();
		if ( ! is_string( $download_link ) || ! $this->get_package_checksum( $download_link, $release_data ) ) {
			$download_link = '';
		}

		// Format the data for WordPress themes API.
		$theme_info = array(
			'name'           => $theme->get( 'Name' ),
			'slug'           => $theme_slug,
			'version'        => ltrim( $release_data['tag_name'], 'v' ),
			'author'         => $theme->get( 'Author' ),
			'author_profile' => $theme->get( 'AuthorURI' ),
			'contributors'   => array(),
			'requires'       => $theme->get( 'RequiresWP' ) ? $theme->get( 'RequiresWP' ) : '5.0',
			'tested'         => (string) ( $theme->get( 'TestedUpTo' ) ? $theme->get( 'TestedUpTo' ) : '6.4' ), // @phpstan-ignore ternary.alwaysFalse
			'requires_php'   => $theme->get( 'RequiresPHP' ) ? $theme->get( 'RequiresPHP' ) : '7.4',
			'rating'         => 100,
			'num_ratings'    => 1,
			'ratings'        => array(
				5 => 1,
			),
			'downloaded'     => 0,
			'last_updated'   => $release_data['published_at'],
			'homepage'       => $theme->get( 'ThemeURI' ) ? $theme->get( 'ThemeURI' ) : $release_data['html_url'],
			'sections'       => array(
				'description' => $theme->get( 'Description' ),
				'changelog'   => $this->format_changelog( $release_data ),
			),
			'download_link'  => $download_link,
			'tags'           => array(),
			'screenshots'    => array(),
		);

		return (object) $theme_info;
	}

	/**
	 * Get the latest stable GitHub release data by scanning releases.
	 *
	 * This:
	 * - Calls /releases?per_page=20
	 * - Ignores drafts and prereleases
	 * - Picks the highest semver tag
	 *
	 * @since 1.0.0
	 * @return array|false Release data or false on error.
	 */
	private function get_github_release_data() {
		// Try cached release first.
		$cached = get_transient( 'aggressive_apparel_theme_update_release' );
		if ( $cached && isset( $cached['release_data'], $cached['checked_at'] ) ) {
			// Consider cache fresh for 5 minutes.
			if ( ( time() - (int) $cached['checked_at'] ) < 300 ) {
				return $cached['release_data'];
			}
		}

		$url  = "https://api.github.com/repos/{$this->repo_owner}/{$this->repo_name}/releases?per_page=20";
		$args = array(
			'headers' => array(
				'User-Agent' => 'Aggressive-Apparel-Updater',
				'Accept'     => 'application/vnd.github.v3+json',
			),
		);

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			// On HTTP error, fall back to stale cache if available.
			if ( $cached && isset( $cached['release_data'] ) ) {
				return $cached['release_data'];
			}
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			// On non-200, also fall back to stale cache if available.
			if ( $cached && isset( $cached['release_data'] ) ) {
				return $cached['release_data'];
			}
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $body ) ) {
			if ( $cached && isset( $cached['release_data'] ) ) {
				return $cached['release_data'];
			}
			return false;
		}

		$best_release = null;
		$best_version = null;

		foreach ( $body as $release ) {
			// Skip drafts.
			if ( ! empty( $release['draft'] ) ) {
				continue;
			}

			// Skip prereleases.
			if ( ! empty( $release['prerelease'] ) ) {
				continue;
			}

			if ( empty( $release['tag_name'] ) || ! is_string( $release['tag_name'] ) ) {
				continue;
			}

			$tag = ltrim( $release['tag_name'], 'v' );

			// Basic semver-ish validation: require at least "x.y".
			if ( ! preg_match( '/^\d+\.\d+(\.\d+)?$/', $tag ) ) {
				continue;
			}

			if ( null === $best_version || version_compare( $tag, $best_version, '>' ) ) {
				$best_version = $tag;
				$best_release = $release;
			}
		}

		if ( null === $best_release || null === $best_version ) {
			// No suitable release found.
			if ( $cached && isset( $cached['release_data'] ) ) {
				return $cached['release_data'];
			}
			return false;
		}

		// Normalize tag_name and cache this result independently of the update transient.
		$best_release['tag_name'] = 'v' . $best_version;

		set_transient(
			'aggressive_apparel_theme_update_release',
			array(
				'release_data' => $best_release,
				'checked_at'   => time(),
			),
			HOUR_IN_SECONDS
		);

		return $best_release;
	}

	/**
	 * Get fallback download URL when GitHub API fails.
	 *
	 * @since 1.8.0
	 * @return string|false Fallback download URL or false if not available.
	 */
	private function get_fallback_download_url() {
		// Try to get version from cached update data first.
		$cached_data = get_transient( 'aggressive_apparel_theme_update' );
		if ( $cached_data && isset( $cached_data['version'] ) ) {
			$version = $cached_data['version']; // Already the "latest" we saw before.
			$tag     = ltrim( $version, 'v' );
			$url     = "https://github.com/{$this->repo_owner}/{$this->repo_name}/releases/download/v{$tag}/aggressive-apparel-{$tag}.zip";

			return $this->is_allowed_package_url( $url ) ? $url : false;
		}

		// No cached info + GitHub failed → safest is to not offer an update.
		return false;
	}

	/**
	 * Format changelog from GitHub release data.
	 *
	 * @since 1.0.0
	 * @param array $release_data GitHub release data.
	 * @return string Formatted changelog.
	 */
	private function format_changelog( array $release_data ): string {
		$changelog = '';

		// Add version header.
		$version    = ltrim( $release_data['tag_name'], 'v' );
		$date       = gmdate( 'F j, Y', strtotime( $release_data['published_at'] ) );
		$changelog .= "<h4>{$version} - {$date}</h4>\n";

		// Add release body/notes.
		if ( ! empty( $release_data['body'] ) && is_string( $release_data['body'] ) && null !== $release_data['body'] ) {
			$changelog .= '<p>' . $this->format_release_body( $release_data['body'] ) . "</p>\n";
		} else {
			$changelog .= '<p>No changelog available for this release.</p>';
		}

		return $changelog;
	}

	/**
	 * Format release body markdown to basic HTML.
	 *
	 * @since 1.0.0
	 * @param string $body Release body content.
	 * @return string Formatted HTML content.
	 */
	private function format_release_body( string $body ): string {
		// Escape first — body comes from an external API (GitHub).
		$body = esc_html( $body );

		// Basic markdown to HTML conversion (on already-escaped content).
		$body = (string) preg_replace( '/\*\*(.*?)\*\*/', '<strong>$1</strong>', $body );
		$body = (string) preg_replace( '/\*(.*?)\*/', '<em>$1</em>', $body );
		$body = (string) preg_replace( '/`(.*?)`/', '<code>$1</code>', $body );

		// Convert line breaks and sanitize the final HTML.
		return wp_kses(
			(string) nl2br( $body ),
			array(
				'strong' => array(),
				'em'     => array(),
				'code'   => array(),
				'br'     => array(),
			)
		);
	}

	/**
	 * Check if this request is a bulk theme update for this theme.
	 *
	 * This reads core's update-core.php POST state only to suppress duplicate notices.
	 * Core has its own nonce checks; we are not changing state here.
	 *
	 * @param string $theme_slug Theme stylesheet slug.
	 * @return bool
	 */
	private function is_bulk_theme_update_request( string $theme_slug ): bool {
		$pagenow = isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : '';

		if ( 'update-core.php' !== $pagenow ) {
			return false;
		}

		// Verify nonce if present, but don't fail if missing (core may handle it).
		if ( isset( $_POST['_wpnonce'] ) ) {
			$nonce_verified = wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'bulk-themes' );
			if ( ! $nonce_verified ) {
				return false;
			}
		}

		if ( ! isset( $_POST['action'], $_POST['checked'] ) ) {
			return false;
		}

		$action  = sanitize_text_field( wp_unslash( $_POST['action'] ) );
		$checked = array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['checked'] ) );

		return 'update-selected' === $action && in_array( $theme_slug, $checked, true );
	}

	/**
	 * Display admin notice when theme update is available.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function admin_update_notice(): void {
		$theme      = wp_get_theme();
		$theme_slug = $theme->get_stylesheet();
		$transient  = get_site_transient( 'update_themes' );

		if ( ! isset( $transient->response[ $theme_slug ] ) ) {
			return;
		}

		$update_data     = $transient->response[ $theme_slug ];
		$current_version = $theme->get( 'Version' );

		if ( ! current_user_can( 'update_themes' ) ) {
			return;
		}

		// Don't show update notice when actively updating themes or during upgrade process.
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $action ) {
			// Hide during individual theme updates.
			if ( 'upgrade-theme' === $action && isset( $_GET['theme'] ) && sanitize_text_field( wp_unslash( $_GET['theme'] ) ) === $theme_slug ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}
			// Hide during theme/core upgrade process.
			if ( in_array( $action, array( 'do-theme-upgrade', 'do-core-upgrade' ), true ) ) {
				return;
			}
		}

		// Don't show update notice on the updates page during bulk updates.
		if ( $this->is_bulk_theme_update_request( $theme_slug ) ) {
			return;
		}

		$message = sprintf(
			/* translators: 1: theme name, 2: current version, 3: new version */
			__( 'A new version of %1$s is available. You have version %2$s and the latest version is %3$s.', 'aggressive-apparel' ),
			'<strong>' . $theme->get( 'Name' ) . '</strong>',
			$current_version,
			$update_data['new_version']
		);

		$update_url = wp_nonce_url(
			admin_url( 'update.php?action=upgrade-theme&theme=' . $theme_slug ),
			'upgrade-theme_' . $theme_slug
		);

		printf(
			'<div class="notice notice-info is-dismissible">
				<p>%1$s <a href="%2$s">%3$s</a></p>
			</div>',
			wp_kses(
				$message,
				array(
					'strong' => array(),
				)
			),
			esc_url( $update_url ),
			esc_html__( 'Update now', 'aggressive-apparel' )
		);
	}
}
