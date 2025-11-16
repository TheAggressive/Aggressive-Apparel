<?php
/**
 * Theme Updates Handler
 *
 * Manages theme update checking and integration with WordPress update system.
 * Follows WordPress best practices for theme updates.
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

namespace Aggressive_Apparel\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme Updates Class
 *
 * Handles automatic theme update checking and WordPress integration.
 * Implements instant notifications through admin interface integration.
 *
 * @since 1.0.0
 */
class Theme_Updates {

	/**
	 * The single instance of the class
	 *
	 * @var Theme_Updates
	 */
	private static $instance = null;

	/**
	 * Theme and GitHub repository information
	 */
	private const THEME_SLUG     = 'aggressive-apparel';
	private const GITHUB_USER    = 'TheAggressive';
	private const GITHUB_REPO    = 'Aggressive-Apparel';
	private const GITHUB_API_URL = 'https://api.github.com/repos/' . self::GITHUB_USER . '/' . self::GITHUB_REPO . '/releases/latest';

	/**
	 * Cache and API constants
	 */
	private const CACHE_KEY_PREFIX = 'aggressive_apparel_theme_update';
	private const CACHE_FOUND      = 15 * MINUTE_IN_SECONDS;  // Updates available: check every 15 min.
	private const CACHE_NONE       = 30 * MINUTE_IN_SECONDS;   // No updates: check every 30 min.
	private const API_TIMEOUT      = 10;


	/**
	 * Get the singleton instance
	 *
	 * @return Theme_Updates
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 *
	 * Initializes the theme update system.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->init_hooks();
		$this->schedule_maintenance();
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * Sets up all necessary WordPress hooks for theme updates.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_hooks(): void {
		// WordPress update system integration.
		add_filter( 'pre_set_site_transient_update_themes', array( $this, 'inject_update_data' ) );
		add_filter( 'themes_api', array( $this, 'provide_theme_details' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'handle_download_rename' ), 10, 3 );

		// Admin interface integration for instant checking.
		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'check_on_admin_visit' ) );
			add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
			add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_notification' ), 1000 );

			// AJAX endpoint for manual checks.
			add_action( 'wp_ajax_aggressive_apparel_check_updates', array( $this, 'ajax_check_updates' ) );
		}

		// Background update checking.
		add_action( 'aggressive_apparel_check_updates', array( $this, 'perform_update_check' ) );
	}

	/**
	 * Schedule maintenance tasks
	 *
	 * Sets up WordPress cron jobs for regular update checking.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function schedule_maintenance(): void {
		if ( ! wp_next_scheduled( 'aggressive_apparel_check_updates' ) ) {
			wp_schedule_event( time() + 60, '10min', 'aggressive_apparel_check_updates' );
		}
	}

	/**
	 * Inject update data into WordPress transient
	 *
	 * Called by WordPress update system to check for theme updates.
	 *
	 * @since 1.0.0
	 * @param \stdClass $transient Update transient object.
	 * @return \stdClass Modified transient.
	 */
	public function inject_update_data( \stdClass $transient ) {
		// Initialize response property if it doesn't exist.
		if ( ! isset( $transient->response ) ) {
			$transient->response = array();
		}

		$update_data = $this->get_update_data();

		if ( $update_data && isset( $update_data['version'] ) ) {
			$current_version = wp_get_theme( self::THEME_SLUG )->get( 'Version' );

			if ( version_compare( $update_data['version'], $current_version, '>' ) ) {
				$transient->response[ self::THEME_SLUG ] = (object) array(
					'theme'       => self::THEME_SLUG,
					'new_version' => $update_data['version'],
					'url'         => $update_data['url'] ?? '',
					'package'     => $update_data['package'] ?? '',
				);
			}
		}

		return $transient;
	}

	/**
	 * Provide theme details for update popup
	 *
	 * Supplies detailed information when user clicks "View version X details".
	 *
	 * @since 1.0.0
	 * @param false|object|array $result The result object or array.
	 * @param string             $action The type of information being requested.
	 * @param object             $args   Plugin API arguments.
	 * @return false|object|array Modified result.
	 */
	public function provide_theme_details( $result, $action, $args ) {
		if ( 'theme_information' !== $action || ! isset( $args->slug ) || self::THEME_SLUG !== $args->slug ) {
			return $result;
		}

		$update_data = $this->get_update_data();

		if ( ! $update_data ) {
			return $result;
		}

		$theme_data = wp_get_theme( self::THEME_SLUG );

		$requires_wp  = $theme_data->get( 'RequiresWP' );
		$requires_php = $theme_data->get( 'RequiresPHP' );

		return (object) array(
			'name'          => $theme_data->get( 'Name' ),
			'slug'          => self::THEME_SLUG,
			'version'       => $update_data['version'],
			'author'        => $theme_data->get( 'Author' ),
			'homepage'      => $theme_data->get( 'ThemeURI' ),
			'requires'      => ! empty( $requires_wp ) ? $requires_wp : '6.5.0',
			'tested'        => '6.4',
			'requires_php'  => ! empty( $requires_php ) ? $requires_php : '8.2.0',
			'sections'      => array(
				'description' => $theme_data->get( 'Description' ),
				'changelog'   => $update_data['changelog'] ?? $this->get_changelog_fallback(),
			),
			'download_link' => $update_data['package'] ?? '',
		);
	}

	/**
	 * Handle theme download and directory renaming
	 *
	 * WordPress downloads ZIP files with release names, but themes need specific directory names.
	 *
	 * @since 1.0.0
	 * @param string $source        File source location.
	 * @param string $remote_source Remote file source location.
	 * @param object $_upgrader     WP_Upgrader instance (unused).
	 * @return string Modified source location.
	 */
	public function handle_download_rename( $source, $remote_source, object $_upgrader ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! strpos( $remote_source, self::THEME_SLUG ) ) {
			return $source;
		}

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$source_files = $wp_filesystem->dirlist( $source );

		foreach ( $source_files as $file => $file_info ) {
			if ( 'd' === $file_info['type'] && 0 === strpos( $file, 'aggressive-apparel' ) ) {
				$correct_name = self::THEME_SLUG;
				if ( $file !== $correct_name ) {
					$wp_filesystem->move( $source . '/' . $file, $source . '/' . $correct_name );
				}
				break;
			}
		}

		return $source;
	}

	/**
	 * Check for updates on admin page visits
	 *
	 * Provides instant feedback when admins visit relevant pages.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function check_on_admin_visit(): void {
		$current_screen = get_current_screen();

		if ( ! $current_screen ) {
			return;
		}

		$update_screens = array( 'themes', 'update-core', 'dashboard' );

		if ( in_array( $current_screen->id, $update_screens, true ) && $this->cache_is_stale() ) {
			// Schedule immediate background check (non-blocking).
			wp_schedule_single_event( time(), 'aggressive_apparel_check_updates' );
		}
	}

	/**
	 * Add dashboard widget for theme updates
	 *
	 * Provides a dedicated space for theme update information.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_dashboard_widget(): void {
		wp_add_dashboard_widget(
			'aggressive_apparel_updates',
			__( 'Theme Updates', 'aggressive-apparel' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render dashboard widget content
	 *
	 * Shows current theme status and update information.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_dashboard_widget(): void {
		$update_data     = $this->get_update_data();
		$current_version = wp_get_theme( self::THEME_SLUG )->get( 'Version' );

		echo '<div id="aggressive-apparel-updates-widget">';

		if ( $update_data && isset( $update_data['version'] ) ) {
			if ( version_compare( $update_data['version'], $current_version, '>' ) ) {
				echo '<div class="notice notice-info inline">';
				echo '<p><strong>' . esc_html__( 'Update Available!', 'aggressive-apparel' ) . '</strong></p>';
				echo '<p>' . sprintf(
					/* translators: %s: version number */
					esc_html__( 'Version %s is available.', 'aggressive-apparel' ),
					esc_html( $update_data['version'] )
				) . '</p>';
				echo '<p><a href="' . esc_url( admin_url( 'update-core.php' ) ) . '" class="button button-primary">' . esc_html__( 'Update Now', 'aggressive-apparel' ) . '</a></p>';
				echo '</div>';
			} else {
				echo '<p>' . esc_html__( 'Your theme is up to date.', 'aggressive-apparel' ) . '</p>';
			}
		} else {
			echo '<p>' . esc_html__( 'Checking for updates...', 'aggressive-apparel' ) . '</p>';
			echo '<button id="aggressive-apparel-check-now" class="button">' . esc_html__( 'Check Now', 'aggressive-apparel' ) . '</button>';
		}

		echo '</div>';

		// Enqueue widget JavaScript.
		wp_enqueue_script(
			'aggressive-apparel-updates-widget',
			get_template_directory_uri() . '/assets/js/updates-widget.js',
			array( 'jquery' ),
			wp_get_theme()->get( 'Version' ),
			true
		);

		wp_localize_script(
			'aggressive-apparel-updates-widget',
			'aggressiveApparelUpdates',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'aggressive_apparel_check_updates' ),
				'strings' => array(
					'checking' => __( 'Checking...', 'aggressive-apparel' ),
					'checkNow' => __( 'Check Now', 'aggressive-apparel' ),
					'error'    => __( 'Error checking for updates.', 'aggressive-apparel' ),
				),
			)
		);
	}

	/**
	 * Add notification to admin bar
	 *
	 * Shows update notifications in the WordPress admin bar.
	 *
	 * @since 1.0.0
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar object.
	 * @return void
	 */
	public function add_admin_bar_notification( \WP_Admin_Bar $wp_admin_bar ): void {
		$update_data = $this->get_update_data();

		if ( ! $update_data || ! isset( $update_data['version'] ) ) {
			return;
		}

		$current_version = wp_get_theme( self::THEME_SLUG )->get( 'Version' );

		if ( version_compare( $update_data['version'], $current_version, '<=' ) ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'aggressive-apparel-update',
				'title' => '<span class="ab-icon dashicons dashicons-update"></span> ' . esc_html__( 'Theme Update Available', 'aggressive-apparel' ),
				'href'  => admin_url( 'update-core.php' ),
				'meta'  => array(
					'class' => 'aggressive-apparel-update-notification',
				),
			)
		);
	}

	/**
	 * Handle AJAX update checks
	 *
	 * Processes manual update check requests from admin interface.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_check_updates(): void {
		// Verify nonce and capabilities.
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'aggressive_apparel_check_updates' ) ||
			! current_user_can( 'update_themes' ) ) {
			$error_response = wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'Security check failed.', 'aggressive-apparel' ),
				)
			);

			$output = $error_response ? $error_response : '{"success":false,"message":"Security check failed."}';
			wp_die( wp_kses( $output, array() ) );
		}

		// Force fresh check by clearing cache.
		$this->clear_cache();

		// Perform immediate check.
		$update_data = $this->fetch_update_data();

		if ( is_wp_error( $update_data ) ) {
			$error_response = wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html( $update_data->get_error_message() ),
				)
			);

			$output = $error_response ? $error_response : '{"success":false,"message":"An error occurred."}';
			wp_die( wp_kses( $output, array() ) );
		}

		if ( $update_data ) {
			$success_response = wp_json_encode(
				array(
					'success' => true,
					'message' => esc_html(
						sprintf(
						/* translators: %s: version number */
							__( 'Version %s is available!', 'aggressive-apparel' ),
							$update_data['version']
						)
					),
					'version' => $update_data['version'],
				)
			);

			$output = $success_response ? $success_response : '{"success":true,"message":"Update available."}';
			wp_die( wp_kses( $output, array() ) );
		} else {
			$uptodate_response = wp_json_encode(
				array(
					'success' => true,
					'message' => esc_html__( 'Theme is up to date.', 'aggressive-apparel' ),
				)
			);

			$output = $uptodate_response ? $uptodate_response : '{"success":true,"message":"Theme is up to date."}';
			wp_die( wp_kses( $output, array() ) );
		}
	}

	/**
	 * Perform background update check
	 *
	 * Fetches update data and caches it for WordPress to use.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function perform_update_check(): void {
		$update_data = $this->fetch_update_data();

		if ( is_wp_error( $update_data ) ) {
			// Log error but don't fail - cache error state.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log(
					sprintf(
						'[THEME UPDATE] Background check failed: %s',
						$update_data->get_error_message()
					)
				);
			}
			return;
		}

		// Cache the successful data.
		$this->cache_update_data( $update_data );
	}

	/**
	 * Get cached update data
	 *
	 * Retrieves update information from cache or fetches fresh data.
	 *
	 * @since 1.0.0
	 * @return array|null Update data or null if none available.
	 */
	private function get_update_data(): ?array {
		$cache_key   = self::CACHE_KEY_PREFIX;
		$cached_data = get_transient( $cache_key );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		// Cache miss - fetch fresh data.
		$update_data = $this->fetch_update_data();

		if ( ! is_wp_error( $update_data ) ) {
			$this->cache_update_data( $update_data );
			return $update_data;
		}

		return null;
	}

	/**
	 * Fetch update data from GitHub API
	 *
	 * Retrieves latest release information from GitHub.
	 *
	 * @since 1.0.0
	 * @return array|\WP_Error Update data or error.
	 */
	private function fetch_update_data() {
		$url = add_query_arg(
			array(
				't' => time(), // Cache busting.
			),
			self::GITHUB_API_URL
		);

		$headers = array(
			'Accept'     => 'application/vnd.github.v3+json',
			'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
		);

		// Add ETag for conditional requests.
		$etag = get_option( self::CACHE_KEY_PREFIX . '_etag', '' );
		if ( ! empty( $etag ) ) {
			$headers['If-None-Match'] = $etag;
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => self::API_TIMEOUT,
				'headers'   => $headers,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		// Handle conditional requests (not modified).
		if ( 304 === $code ) {
			// Return cached data if available.
			$cached_data = get_transient( self::CACHE_KEY_PREFIX );
			return false !== $cached_data ? $cached_data : new \WP_Error( 'no_cached_data', 'No cached data available' );
		}

		if ( 200 !== $code ) {
			return new \WP_Error(
				'api_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'GitHub API returned status %d', 'aggressive-apparel' ),
					$code
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error( 'json_error', __( 'Invalid JSON response from GitHub', 'aggressive-apparel' ) );
		}

		// Cache ETag for future requests.
		$new_etag = wp_remote_retrieve_header( $response, 'etag' );
		if ( ! empty( $new_etag ) ) {
			update_option( self::CACHE_KEY_PREFIX . '_etag', $new_etag );
		}

		return $this->parse_github_response( $data );
	}

	/**
	 * Parse GitHub API response
	 *
	 * Converts GitHub release data into WordPress update format.
	 *
	 * @since 1.0.0
	 * @param array $data GitHub API response data.
	 * @return array|\WP_Error Parsed update data or error.
	 */
	private function parse_github_response( array $data ) {
		if ( ! isset( $data['tag_name'] ) ) {
			return new \WP_Error( 'invalid_response', __( 'Invalid GitHub response: missing tag_name', 'aggressive-apparel' ) );
		}

		$version = ltrim( $data['tag_name'], 'v' );

		// Find ZIP asset.
		$zip_url = null;
		if ( isset( $data['assets'] ) && is_array( $data['assets'] ) ) {
			foreach ( $data['assets'] as $asset ) {
				if ( isset( $asset['name'] ) && strpos( $asset['name'], '.zip' ) !== false ) {
					$zip_url = $asset['browser_download_url'] ?? null;
					break;
				}
			}
		}

		// Fallback to generated ZIP URL if no asset found.
		if ( ! $zip_url && isset( $data['zipball_url'] ) ) {
			$zip_url = $data['zipball_url'];
		}

		if ( ! $zip_url ) {
			return new \WP_Error( 'no_download', __( 'No download URL found in GitHub release', 'aggressive-apparel' ) );
		}

		return array(
			'version'   => $version,
			'package'   => $zip_url,
			'url'       => $data['html_url'] ?? '',
			'changelog' => $data['body'] ?? '',
		);
	}

	/**
	 * Cache update data
	 *
	 * Stores update data in WordPress transients with appropriate TTL.
	 *
	 * @since 1.0.0
	 * @param array $data Update data to cache.
	 * @return void
	 */
	private function cache_update_data( array $data ): void {
		$cache_key = self::CACHE_KEY_PREFIX;
		$ttl       = self::CACHE_NONE; // Default to longer cache.

		// Adjust TTL based on update status.
		if ( isset( $data['version'] ) ) {
			$current_version = wp_get_theme( self::THEME_SLUG )->get( 'Version' );
			if ( version_compare( $data['version'], $current_version, '>' ) ) {
				$ttl = self::CACHE_FOUND; // Shorter cache when updates available.
			}
		}

		set_transient( $cache_key, $data, $ttl );
	}

	/**
	 * Check if cache is stale
	 *
	 * Determines if cached data should be refreshed.
	 *
	 * @since 1.0.0
	 * @return bool True if cache needs refresh.
	 */
	private function cache_is_stale(): bool {
		$cache_key = self::CACHE_KEY_PREFIX;
		return false === get_transient( $cache_key );
	}

	/**
	 * Clear update cache
	 *
	 * Forces fresh data on next check.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function clear_cache(): void {
		delete_transient( self::CACHE_KEY_PREFIX );
	}

	/**
	 * Get changelog fallback
	 *
	 * Provides changelog content when GitHub doesn't have release notes.
	 *
	 * @since 1.0.0
	 * @return string Changelog HTML.
	 */
	private function get_changelog_fallback(): string {
		$changelog_file = get_template_directory() . '/CHANGELOG.md';

		if ( file_exists( $changelog_file ) && is_readable( $changelog_file ) ) {
			// Use WP_Filesystem for reading local files.
			global $wp_filesystem;
			if ( ! $wp_filesystem ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}

			$content = $wp_filesystem->get_contents( $changelog_file );
			if ( false !== $content ) {
				return wpautop( esc_html( $content ) );
			}
		}

		return esc_html__( 'See the changelog on GitHub for detailed changes.', 'aggressive-apparel' );
	}
}
