<?php
/**
 * Asset Loader Class
 *
 * Handles automatic enqueuing of compiled assets with .asset.php data
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

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
	 * Get asset data from .asset.php file
	 *
	 * @param string $asset_path Path to asset file (without extension).
	 * @return array Array with 'dependencies' and 'version' keys.
	 */
	public static function get_asset_data( $asset_path ) {
		$asset_file = aggressive_apparel_asset_path( $asset_path . '.asset.php' );

		if ( ! file_exists( $asset_file ) ) {
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
	 * Automatically reads dependencies and version from .asset.php file
	 *
	 * @param string $handle         Style handle.
	 * @param string $src            Path to style file relative to theme root.
	 * @param array  $additional_deps Additional dependencies not in asset file.
	 * @param string $media          Media attribute.
	 * @return void
	 */
	public static function enqueue_style( $handle, $src, $additional_deps = array(), $media = 'all' ) {
		$asset_data = self::get_asset_data( $src );

		$dependencies = array_merge( $asset_data['dependencies'], $additional_deps );

		wp_enqueue_style(
			$handle,
			aggressive_apparel_asset_uri( $src . '.css' ),
			$dependencies,
			$asset_data['version'],
			$media
		);
	}
}
