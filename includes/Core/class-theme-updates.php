<?php
/**
 * Theme Updates
 *
 * Handles automatic theme updates from GitHub releases.
 *
 * @since 1.0.0
 * @package Aggressive_Apparel\Core
 */

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
	 * The single instance of the class
	 *
	 * @var Theme_Updates
	 */
	private static ?Theme_Updates $instance = null;

	/**
	 * GitHub repository owner
	 *
	 * @var string
	 */
	private string $repo_owner = 'TheAggressive';

	/**
	 * GitHub repository name
	 *
	 * @var string
	 */
	private string $repo_name = 'Aggressive-Apparel';

	/**
	 * ETag for conditional requests
	 *
	 * @var string
	 */
	private string $etag = '';

	/**
	 * Get singleton instance
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
	 * Initialize the theme updater
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		$this->etag = get_option( 'aggressive_apparel_etag', '' );

		add_filter( 'pre_set_site_transient_update_themes', array( $this, 'check_for_update' ), 100, 1 );
		add_filter( 'upgrader_source_selection', array( $this, 'rename_package' ), 10, 3 );
		add_filter( 'themes_api', array( $this, 'themes_api' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'admin_update_notice' ) );
		add_action( 'load-update-core.php', array( $this, 'force_fresh_check' ) );

		// Add admin action to clear update cache for debugging.
		add_action( 'wp_ajax_aggressive_apparel_clear_update_cache', array( $this, 'clear_update_cache' ) );
	}

	/**
	 * Force a fresh check when visiting the update core page
	 *
	 * @since 1.8.0
	 * @return void
	 */
	public function force_fresh_check(): void {
		// Clear our theme update cache and ETag when visiting update-core.php.
		delete_transient( 'aggressive_apparel_theme_update' );
		delete_option( 'aggressive_apparel_etag' );

		// Force WordPress to refresh theme updates.
		wp_update_themes();
	}

	/**
	 * Check for updates by comparing the current version with the GitHub release
	 *
	 * @since 1.0.0
	 * @param object $transient Transient update data.
	 * @return object Modified transient.
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$source_version  = $this->get_github_version();
		$theme_slug      = wp_get_theme()->get_stylesheet();
		$current_version = wp_get_theme()->get( 'Version' );

		if ( $source_version && version_compare( $source_version, $current_version, '>' ) ) {

			if ( ! isset( $transient->response ) ) {
				$transient->response = array(); // @phpstan-ignore property.notFound
			}
			$download_url = $this->get_download_url();

			$transient->response[ $theme_slug ] = array(
				'theme'       => $theme_slug,
				'new_version' => $source_version,
				'url'         => "https://github.com/{$this->repo_owner}/{$this->repo_name}",
				'package'     => $download_url,
			);

			// Cache the update data and release info for ETag fallback and changelog.
			// Only cache if we have complete data.
			if ( $download_url ) {
				$release_data = $this->get_github_release_data();
				if ( $release_data ) {
					set_transient(
						'aggressive_apparel_theme_update',
						array(
							'version'      => $source_version,
							'download_url' => $download_url,
							'release_data' => $release_data,
							'checked_at'   => time(),
						),
						HOUR_IN_SECONDS
					);
				}
			}
		}

		return $transient;
	}

	/**
	 * Fetch the latest version from GitHub API
	 *
	 * @since 1.0.0
	 * @return string|false Version string or false on error.
	 */
	private function get_github_version() {
		// Try to get from cache first.
		$cached_data = get_transient( 'aggressive_apparel_theme_update' );
		if ( $cached_data && isset( $cached_data['version'] ) && isset( $cached_data['checked_at'] ) ) {
			// Check if cache is still fresh (within 5 minutes for version checks).
			if ( ( time() - $cached_data['checked_at'] ) < 300 ) {
				return $cached_data['version'];
			}
		}

		$release_data = $this->get_github_release_data();

		if ( ! $release_data ) {
			return false;
		}

		if ( isset( $release_data['tag_name'] ) ) {
			return ltrim( $release_data['tag_name'], 'v' );
		}

		return false;
	}

	/**
	 * Get the download URL for the latest GitHub release
	 *
	 * @since 1.0.0
	 * @return string|false Download URL or false on error.
	 */
	private function get_download_url() {
		// Try to get from cache first.
		$cached_data = get_transient( 'aggressive_apparel_theme_update' );
		if ( $cached_data && isset( $cached_data['download_url'] ) ) {
			return $cached_data['download_url'];
		}

		$release_data = $this->get_github_release_data();

		if ( ! $release_data ) {
			// Fallback: Try to construct URL from cached version data.
			return $this->get_fallback_download_url();
		}

		// First try to get from assets (uploaded release files) - these are the actual theme ZIP files.
		if ( isset( $release_data['assets'][0]['browser_download_url'] ) ) {
			return $release_data['assets'][0]['browser_download_url'];
		}

		// If no assets, use zipball_url as primary fallback (auto-generated ZIP of the repository).
		// This is guaranteed to exist for any GitHub release.
		if ( isset( $release_data['zipball_url'] ) ) {
			return $release_data['zipball_url'];
		}

		// Final fallback: construct URL based on tag name.
		// This assumes the release has an asset named like "aggressive-apparel-{version}.zip".
		if ( isset( $release_data['tag_name'] ) ) {
			$tag = ltrim( $release_data['tag_name'], 'v' );
			return "https://github.com/{$this->repo_owner}/{$this->repo_name}/releases/download/v{$tag}/aggressive-apparel-{$tag}.zip";
		}

		return false;
	}

	/**
	 * Rename the downloaded folder to match the theme directory name
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
		$is_github_source = false !== strpos( $remote_source, $this->repo_owner ) && false !== strpos( $remote_source, $this->repo_name );

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
			WP_Filesystem();
		}

		if ( $wp_filesystem->move( $source, $target_path ) ) {
			return $target_path;
		}

		return $source;
	}


	/**
	 * Provide theme information for WordPress themes API
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

		$theme_slug = wp_get_theme()->get_stylesheet();
		if ( $args->slug !== $theme_slug ) {
			return $result;
		}

		// Fetch release data from GitHub.
		$release_data = $this->get_github_release_data();

		if ( ! $release_data ) {
			return $result;
		}

		// Format the data for WordPress themes API.
		$theme_info = array(
			'name'           => wp_get_theme()->get( 'Name' ),
			'slug'           => $theme_slug,
			'version'        => ltrim( $release_data['tag_name'], 'v' ),
			'author'         => wp_get_theme()->get( 'Author' ),
			'author_profile' => wp_get_theme()->get( 'AuthorURI' ),
			'contributors'   => array(),
			'requires'       => wp_get_theme()->get( 'RequiresWP' ) ? wp_get_theme()->get( 'RequiresWP' ) : '5.0',
			'tested'         => (string) ( wp_get_theme()->get( 'TestedUpTo' ) ? wp_get_theme()->get( 'TestedUpTo' ) : '6.4' ), // @phpstan-ignore ternary.alwaysFalse
			'requires_php'   => wp_get_theme()->get( 'RequiresPHP' ) ? wp_get_theme()->get( 'RequiresPHP' ) : '7.4',
			'rating'         => 100,
			'num_ratings'    => 1,
			'ratings'        => array(
				5 => 1,
			),
			'downloaded'     => 0,
			'last_updated'   => $release_data['published_at'],
			'homepage'       => wp_get_theme()->get( 'ThemeURI' ) ? wp_get_theme()->get( 'ThemeURI' ) : $release_data['html_url'],
			'sections'       => array(
				'description' => wp_get_theme()->get( 'Description' ),
				'changelog'   => $this->format_changelog( $release_data ),
			),
			'download_link'  => $release_data['zipball_url'],
			'tags'           => array(),
			'screenshots'    => array(),
		);

		return (object) $theme_info;
	}

	/**
	 * Get GitHub release data with caching
	 *
	 * @since 1.0.0
	 * @return array|false Release data or false on error.
	 */
	private function get_github_release_data() {
		$url  = "https://api.github.com/repos/{$this->repo_owner}/{$this->repo_name}/releases/latest";
		$args = array(
			'headers' => array(
				'User-Agent' => $this->repo_owner,
				'Accept'     => 'application/vnd.github.v3+json',
			),
		);

		// Add ETag for conditional requests.
		if ( ! empty( $this->etag ) ) {
			$args['headers']['If-None-Match'] = $this->etag;
		}

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		// Handle conditional requests (not modified).
		if ( 304 === $code ) {
			// Return cached data if available.
			$cached_data = get_transient( 'aggressive_apparel_theme_update' );
			if ( $cached_data && isset( $cached_data['release_data'] ) ) {
				return $cached_data['release_data'];
			}
			// Make a fresh request without ETag to get current data.
			return $this->get_github_release_data_fresh();
		}

		// Check for error response codes.
		if ( $code >= 400 ) {
			return false;
		}

		// Store new ETag for future requests.
		$new_etag = wp_remote_retrieve_header( $response, 'etag' );
		if ( ! empty( $new_etag ) && is_string( $new_etag ) ) {
			$this->etag = $new_etag;
			update_option( 'aggressive_apparel_etag', $new_etag );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return false;
		}

		if ( ! isset( $body['tag_name'] ) ) {
			return false;
		}

		return $body;
	}

	/**
	 * Get fresh GitHub release data without ETag caching
	 *
	 * @since 1.8.0
	 * @return array|false Release data or false on error.
	 */
	private function get_github_release_data_fresh() {
		$url  = "https://api.github.com/repos/{$this->repo_owner}/{$this->repo_name}/releases/latest";
		$args = array(
			'headers' => array(
				'User-Agent' => $this->repo_owner,
				'Accept'     => 'application/vnd.github.v3+json',
			),
			// Don't use ETag for fresh requests.
		);

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		// Check for error response codes.
		if ( $code >= 400 ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return false;
		}

		if ( ! isset( $body['tag_name'] ) ) {
			return false;
		}

		return $body;
	}

	/**
	 * Clear the update cache for debugging purposes
	 *
	 * @since 1.8.0
	 * @return void
	 */
	public function clear_update_cache(): void {
		// Verify user has permission.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		// Clear transients and options.
		delete_transient( 'aggressive_apparel_theme_update' );
		delete_option( 'aggressive_apparel_etag' );
		$this->etag = '';

		wp_send_json_success( array( 'message' => 'Update cache cleared successfully' ) );
	}

	/**
	 * Get fallback download URL when GitHub API fails
	 *
	 * @since 1.8.0
	 * @return string|false Fallback download URL or false if not available.
	 */
	private function get_fallback_download_url() {
		// Try to get version from cached data first.
		$cached_data = get_transient( 'aggressive_apparel_theme_update' );
		if ( $cached_data && isset( $cached_data['version'] ) ) {
			$version = $cached_data['version'];
			$tag     = ltrim( $version, 'v' );
			$url     = "https://github.com/{$this->repo_owner}/{$this->repo_name}/releases/download/v{$tag}/aggressive-apparel-{$tag}.zip";

			return $url;
		}

		// Last resort: Use the current theme version +1 as fallback.
		$current_version = wp_get_theme()->get( 'Version' );
		if ( $current_version ) {
			// Try to increment the version for potential update.
			$parts = explode( '.', $current_version );
			if ( count( $parts ) >= 2 ) {
				$parts[1]         = (int) $parts[1] + 1; // Increment minor version.
				$fallback_version = implode( '.', $parts );
				$url              = "https://github.com/{$this->repo_owner}/{$this->repo_name}/releases/download/v{$fallback_version}/aggressive-apparel-{$fallback_version}.zip";

				return $url;
			}
		}

		return false;
	}

	/**
	 * Format changelog from GitHub release data
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
		// Basic markdown to HTML conversion.
		$body = (string) preg_replace( '/\*\*(.*?)\*\*/', '<strong>$1</strong>', $body );
		$body = (string) preg_replace( '/\*(.*?)\*/', '<em>$1</em>', $body );
		$body = (string) preg_replace( '/`(.*?)`/', '<code>$1</code>', $body );

		// Convert line breaks.
		return (string) nl2br( $body );
	}

	/**
	 * Display admin notice when theme update is available
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function admin_update_notice(): void {
		$theme_slug = wp_get_theme()->get_stylesheet();
		$transient  = get_site_transient( 'update_themes' );

		if ( ! isset( $transient->response[ $theme_slug ] ) ) {
			return;
		}

		$update_data     = $transient->response[ $theme_slug ];
		$current_version = wp_get_theme()->get( 'Version' );

		if ( ! current_user_can( 'update_themes' ) ) {
			return;
		}

		$message = sprintf(
			/* translators: 1: theme name, 2: current version, 3: new version */
			__( 'A new version of %1$s is available. You have version %2$s and the latest version is %3$s.', 'aggressive-apparel' ),
			'<strong>' . wp_get_theme()->get( 'Name' ) . '</strong>',
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
