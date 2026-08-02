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
	 * GitHub release repository.
	 *
	 * @var Theme_Update_Release_Repository
	 */
	private Theme_Update_Release_Repository $releases;

	/**
	 * Package integrity verifier.
	 *
	 * @var Theme_Update_Package_Verifier
	 */
	private Theme_Update_Package_Verifier $packages;

	/**
	 * Private constructor for singleton.
	 */
	private function __construct() {
		$http           = new Theme_Update_Http_Client();
		$this->releases = new Theme_Update_Release_Repository( $http );
		$this->packages = new Theme_Update_Package_Verifier( $this->releases, $http );
	}

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
		$source_version  = $this->releases->get_version();

		if ( ! $source_version || ! is_string( $source_version ) ) {
			return $transient;
		}

		if ( version_compare( $source_version, $current_version, '>' ) ) {

			$download_url = $this->releases->get_download_url();

			// If we can't get a valid download URL, don't advertise an update.
			if ( ! $download_url || ! is_string( $download_url ) || ! $this->releases->is_allowed_package_url( $download_url ) ) {
				return $transient;
			}

			$release_data = $this->releases->get_release_data();
			$checksum     = $this->packages->get_checksum( $download_url, is_array( $release_data ) ? $release_data : null );
			if ( ! $checksum ) {
				return $transient;
			}

			if ( ! isset( $transient->response ) ) {
				$transient->response = array(); // @phpstan-ignore property.notFound
			}

			$transient->response[ $theme_slug ] = array(
				'theme'       => $theme_slug,
				'new_version' => $source_version,
				'url'         => $this->releases->get_repository_url(),
				'package'     => $download_url,
				'checksum'    => Theme_Update_Package_Verifier::CHECKSUM_ALGORITHM . ':' . $checksum,
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
	public function verify_package_download( $reply, $package, $_upgrader = null, array $_hook_extra = array() ) {
		return $this->packages->verify_download( $reply, $package );
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
	public function rename_package( $source, $remote_source, $_upgrader ) {

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
		$is_github_source = $this->releases->is_repository_source( $remote_source );

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
		$release_data = $this->releases->get_release_data();

		if ( ! $release_data ) {
			return $result;
		}

		$download_link = $this->releases->get_download_url();
		if ( ! is_string( $download_link ) || ! $this->packages->get_checksum( $download_link, $release_data ) ) {
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
			'tested'         => (string) ( $theme->get( 'TestedUpTo' ) ? $theme->get( 'TestedUpTo' ) : '6.4' ),
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
		if ( ! empty( $release_data['body'] ) && is_string( $release_data['body'] ) ) {
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen state; update execution is handled and verified by WordPress core.
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
		if ( $action ) {
			// Hide during individual theme updates.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen state; update execution is handled and verified by WordPress core.
			if ( 'upgrade-theme' === $action && isset( $_GET['theme'] ) && sanitize_text_field( wp_unslash( $_GET['theme'] ) ) === $theme_slug ) {
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
