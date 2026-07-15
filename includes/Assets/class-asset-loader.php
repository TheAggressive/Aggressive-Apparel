<?php
/**
 * Asset Loader Class
 *
 * Handles automatic enqueuing of compiled assets with .asset.php data
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Assets;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Asset Loader Class
 *
 * Provides helper methods for enqueuing scripts and styles
 * with automatic dependency and version management from .asset.php files
 *
 * @since 1.0.0
 */
class Asset_Loader {

	/**
	 * Global token stylesheet handle.
	 *
	 * @var string
	 */
	public const TOKENS_HANDLE = 'aggressive-apparel-tokens';

	/**
	 * Get asset data from .asset.php file
	 *
	 * @param string $asset_path Path to asset file (without extension).
	 * @return array Array with 'dependencies' and 'version' keys.
	 */
	public static function get_asset_data( $asset_path ) {
		$asset_file = aggressive_apparel_asset_path( $asset_path . '.asset.php' );

		if ( ! file_exists( $asset_file ) ) {
			// Log in production so misconfigured deploys are not silent.
			if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
				aggressive_apparel_debug_log( 'Missing asset manifest.', array( 'file' => $asset_file ) );
			}
			return array(
				'dependencies' => array(),
				'version'      => wp_get_theme()->get( 'Version' ),
			);
		}

		$asset_data = include $asset_file;

		if ( ! is_array( $asset_data ) || ! isset( $asset_data['dependencies'] ) || ! isset( $asset_data['version'] ) ) {
			return array(
				'dependencies' => array(),
				'version'      => wp_get_theme()->get( 'Version' ),
			);
		}

		return $asset_data;
	}

	/**
	 * Enqueue script with asset data
	 *
	 * Automatically reads dependencies and version from .asset.php file.
	 * Uses defer strategy for non-blocking loading (WordPress 6.3+).
	 *
	 * @param string $handle         Script handle.
	 * @param string $src            Path to script file relative to theme root.
	 * @param array  $additional_deps Additional dependencies not in asset file.
	 * @return void
	 */
	public static function enqueue_script( $handle, $src, $additional_deps = array() ) {
		$asset_data = self::get_asset_data( $src );

		$dependencies = array_merge( $asset_data['dependencies'], $additional_deps );

		wp_enqueue_script(
			$handle,
			aggressive_apparel_asset_uri( $src . '.js' ),
			$dependencies,
			$asset_data['version'],
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Enqueue style with asset data
	 *
	 * Uses the version hash from `.asset.php` when present. Style dependencies
	 * must be passed via `$additional_deps` — never taken from `.asset.php`.
	 *
	 * JS entry manifests list script handles (`react-jsx-runtime`, `wp-element`,
	 * etc.). Passing those as style deps makes WordPress silently drop the
	 * stylesheet, which is how extracted companion CSS (e.g. Adaptive Color
	 * editor chrome) can fail to load in production.
	 *
	 * Automatically serves the RTL variant on RTL sites when it exists.
	 *
	 * @param string $handle          Style handle.
	 * @param string $src             Path to style file relative to theme root (no extension).
	 * @param array  $additional_deps Style dependencies (registered style handles only).
	 * @param string $media           Media attribute.
	 * @return void
	 */
	public static function enqueue_style( $handle, $src, $additional_deps = array(), $media = 'all' ) {
		// Resolve RTL variant automatically.
		$resolved_src = ( function_exists( 'is_rtl' ) && is_rtl() )
			? self::resolve_rtl( $src )
			: $src;

		$asset_data   = self::get_asset_data( $src );
		$dependencies = $additional_deps;

		if ( self::should_depend_on_tokens( $handle, $src ) && ! in_array( self::TOKENS_HANDLE, $dependencies, true ) ) {
			$dependencies[] = self::TOKENS_HANDLE;
		}

		wp_enqueue_style(
			$handle,
			aggressive_apparel_asset_uri( $resolved_src . '.css' ),
			$dependencies,
			$asset_data['version'],
			$media
		);
	}

	/**
	 * Enqueue a feature stylesheet by relative build path.
	 *
	 * Versioned via `filemtime` (these standalone CSS bundles have no
	 * `.asset.php` manifest). The global token layer is added as a dependency
	 * automatically. Returns whether the file existed so callers can guard
	 * follow-up work such as `wp_add_inline_style()`.
	 *
	 * @param string $handle        Style handle.
	 * @param string $relative_path Path relative to theme root, without extension
	 *                              (e.g. 'build/styles/woocommerce/size-guide').
	 * @param array  $deps          Additional style dependencies.
	 * @return bool True when the file existed and was enqueued.
	 */
	public static function enqueue_feature_style( string $handle, string $relative_path, array $deps = array() ): bool {
		$file = AGGRESSIVE_APPAREL_DIR . '/' . $relative_path . '.css';

		if ( ! file_exists( $file ) ) {
			return false;
		}

		if ( self::TOKENS_HANDLE !== $handle && ! in_array( self::TOKENS_HANDLE, $deps, true ) ) {
			$deps[] = self::TOKENS_HANDLE;
		}

		wp_enqueue_style(
			$handle,
			AGGRESSIVE_APPAREL_URI . '/' . $relative_path . '.css',
			$deps,
			(string) filemtime( $file )
		);

		return true;
	}

	/**
	 * Register and enqueue an Interactivity API script module.
	 *
	 * `@wordpress/interactivity` is prepended to the dependency list
	 * automatically unless `$with_interactivity` is false. No-ops gracefully
	 * on WordPress versions without the Script Modules API or when the build
	 * artifact is missing.
	 *
	 * @param string $module_id          Module identifier (e.g. '@aggressive-apparel/size-guide').
	 * @param string $relative_path      Path relative to theme root, without extension
	 *                                   (e.g. 'build/interactivity/size-guide').
	 * @param array  $deps               Additional module dependencies.
	 * @param bool   $with_interactivity Whether to depend on the Interactivity API runtime.
	 * @return void
	 */
	public static function enqueue_interactivity_module( string $module_id, string $relative_path, array $deps = array(), bool $with_interactivity = true ): void {
		if ( ! self::register_interactivity_module( $module_id, $relative_path, $deps, $with_interactivity ) ) {
			return;
		}

		wp_enqueue_script_module( $module_id );
	}

	/**
	 * Register an Interactivity API script module without enqueuing it.
	 *
	 * Use when the module must be registered early (so the import map in
	 * wp_head can resolve it) but should only load on requests that actually
	 * enqueue it later — `wp_enqueue_script_module( $id )` supports
	 * enqueue-before-register, so either ordering works.
	 *
	 * @param string $module_id          Module identifier.
	 * @param string $relative_path      Path relative to theme root, without extension.
	 * @param array  $deps               Additional module dependencies.
	 * @param bool   $with_interactivity Whether to depend on the Interactivity API runtime.
	 * @return bool True when the module exists and was registered.
	 */
	public static function register_interactivity_module( string $module_id, string $relative_path, array $deps = array(), bool $with_interactivity = true ): bool {
		if ( ! function_exists( 'wp_register_script_module' ) ) {
			return false;
		}

		$file = AGGRESSIVE_APPAREL_DIR . '/' . $relative_path . '.js';

		if ( ! file_exists( $file ) ) {
			return false;
		}

		$asset_data = self::get_asset_data( $relative_path );

		if ( $with_interactivity && ! in_array( '@wordpress/interactivity', $deps, true ) ) {
			array_unshift( $deps, '@wordpress/interactivity' );
		}

		wp_register_script_module(
			$module_id,
			AGGRESSIVE_APPAREL_URI . '/' . $relative_path . '.js',
			$deps,
			(string) $asset_data['version']
		);

		return true;
	}

	/**
	 * Enqueue a compiled admin script by relative build path.
	 *
	 * Versioned via `filemtime` (admin bundles have no `.asset.php` manifest).
	 * Returns whether the file existed so callers can guard follow-up work such
	 * as `wp_localize_script()`.
	 *
	 * @param string $handle        Script handle.
	 * @param string $relative_path Path relative to theme root, without extension
	 *                              (e.g. 'build/scripts/admin/color-pattern-admin').
	 * @param array  $deps          Script dependencies.
	 * @param bool   $in_footer     Whether to print the script in the footer.
	 * @return bool True when the file existed and was enqueued.
	 */
	public static function enqueue_admin_script( string $handle, string $relative_path, array $deps = array(), bool $in_footer = true ): bool {
		$file = AGGRESSIVE_APPAREL_DIR . '/' . $relative_path . '.js';

		if ( ! file_exists( $file ) ) {
			return false;
		}

		wp_enqueue_script(
			$handle,
			AGGRESSIVE_APPAREL_URI . '/' . $relative_path . '.js',
			$deps,
			(string) filemtime( $file ),
			$in_footer
		);

		return true;
	}

	/**
	 * Enqueue the design token alias layer.
	 *
	 * @return void
	 */
	public static function enqueue_tokens(): void {
		$src          = 'build/styles/base/tokens';
		$resolved_src = ( function_exists( 'is_rtl' ) && is_rtl() )
			? self::resolve_rtl( $src )
			: $src;
		$asset_data   = self::get_asset_data( $src );

		wp_enqueue_style(
			self::TOKENS_HANDLE,
			aggressive_apparel_asset_uri( $resolved_src . '.css' ),
			$asset_data['dependencies'],
			$asset_data['version']
		);
	}

	/**
	 * Determine whether a style should depend on the global token layer.
	 *
	 * @param string $handle Style handle.
	 * @param string $src    Path to style file relative to theme root.
	 * @return bool
	 */
	private static function should_depend_on_tokens( string $handle, string $src ): bool {
		return self::TOKENS_HANDLE !== $handle
			&& 'build/styles/base/tokens' !== $src
			&& str_starts_with( $src, 'build/styles/' );
	}

	/**
	 * Return the RTL src path if an RTL file exists, otherwise return the original.
	 *
	 * @param string $src Path to style file (no extension).
	 * @return string Resolved path (no extension).
	 */
	private static function resolve_rtl( string $src ): string {
		$rtl = $src . '-rtl';
		return file_exists( aggressive_apparel_asset_path( $rtl . '.css' ) ) ? $rtl : $src;
	}
}
