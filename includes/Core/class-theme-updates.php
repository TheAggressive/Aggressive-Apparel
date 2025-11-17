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
		add_action( 'admin_notices', array( $this, 'admin_update_notice' ) );
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
			$download_url                       = $this->get_download_url();
			$transient->response[ $theme_slug ] = array(
				'theme'       => $theme_slug,
				'new_version' => $source_version,
				'url'         => "https://github.com/{$this->repo_owner}/{$this->repo_name}",
				'package'     => $download_url,
			);

			// Cache the update data for ETag fallback.
			set_transient(
				'aggressive_apparel_theme_update',
				array(
					'version'      => $source_version,
					'download_url' => $download_url,
					'checked_at'   => time(),
				),
				HOUR_IN_SECONDS
			);
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
		$url  = "https://api.github.com/repos/{$this->repo_owner}/{$this->repo_name}/releases/latest";
		$args = array(
			'headers' => array(
				'User-Agent' => $this->repo_owner,
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
			return $cached_data && isset( $cached_data['version'] ) ? $cached_data['version'] : false;
		}

		// Store new ETag for future requests.
		$new_etag = wp_remote_retrieve_header( $response, 'etag' );
		if ( ! empty( $new_etag ) && is_string( $new_etag ) ) {
			$this->etag = $new_etag;
			update_option( 'aggressive_apparel_etag', $new_etag );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return isset( $body['tag_name'] ) ? ltrim( $body['tag_name'], 'v' ) : false;
	}

	/**
	 * Get the download URL for the latest GitHub release
	 *
	 * @since 1.0.0
	 * @return string|false Download URL or false on error.
	 */
	private function get_download_url() {
		$url  = "https://api.github.com/repos/{$this->repo_owner}/{$this->repo_name}/releases/latest";
		$args = array(
			'headers' => array(
				'User-Agent' => $this->repo_owner,
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
			return $cached_data && isset( $cached_data['download_url'] ) ? $cached_data['download_url'] : false;
		}

		// Store new ETag for future requests.
		$new_etag = wp_remote_retrieve_header( $response, 'etag' );
		if ( ! empty( $new_etag ) && is_string( $new_etag ) ) {
			$this->etag = $new_etag;
			update_option( 'aggressive_apparel_etag', $new_etag );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return isset( $body['assets'][0]['browser_download_url'] ) ? $body['assets'][0]['browser_download_url'] : false;
	}

	/**
	 * Rename the downloaded folder to match the theme directory name
	 *
	 * @since 1.0.0
	 * @param string $source        Path to the source directory.
	 * @param string $remote_source Path to the remote source.
	 * @param string $theme_slug    Theme slug.
	 * @return string Modified source path.
	 */
	public function rename_package( $source, $remote_source, $theme_slug ) {
		if ( strpos( $remote_source, $this->repo_name ) !== false ) {
			$corrected_source = trailingslashit( $theme_slug ) . wp_get_theme()->get_stylesheet();

			// Use WordPress filesystem API for file operations.
			global $wp_filesystem;
			if ( ! $wp_filesystem ) {
				require_once ABSPATH . '/wp-admin/includes/file.php';
				WP_Filesystem();
			}

			if ( $wp_filesystem->move( $source, $corrected_source ) ) {
				return $corrected_source;
			}
		}

		return $source;
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
