<?php
/**
 * WebP Utilities Class
 *
 * Shared utilities for WebP functionality
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
 * WebP Utils Class
 *
 * Shared utilities for WebP conversion and validation.
 *
 * @since 1.0.0
 */
class WebP_Utils {

	/**
	 * Uploads directory info (cached)
	 *
	 * @var array|null
	 */
	private static $upload_dir = null;

	/**
	 * Browser WebP support (cached per request)
	 *
	 * @var bool|null
	 */
	private static $browser_supports = null;

	/**
	 * Get cached upload directory
	 *
	 * @return array Upload directory info.
	 */
	public static function get_upload_dir() {
		if ( null === self::$upload_dir ) {
			self::$upload_dir = wp_get_upload_dir();
		}
		return self::$upload_dir;
	}

	/**
	 * Check if URL is from uploads directory
	 *
	 * Security: Prevents serving arbitrary files.
	 *
	 * @param string $url Image URL.
	 * @return bool True if valid.
	 */
	public static function is_valid_upload_url( $url ) {
		if ( empty( $url ) || ! is_string( $url ) ) {
			return false;
		}

		$upload_dir = self::get_upload_dir();
		$base_url   = trailingslashit( $upload_dir['baseurl'] );

		return 0 === strpos( $url, $base_url );
	}

	/**
	 * Convert URL to filesystem path securely
	 *
	 * @param string $url Image URL.
	 * @return string|false File path or false if invalid.
	 */
	public static function url_to_path( $url ) {
		if ( ! self::is_valid_upload_url( $url ) ) {
			return false;
		}

		$upload_dir = self::get_upload_dir();
		$file_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );

		// Normalize path.
		$file_path = wp_normalize_path( $file_path );

		// Security: Ensure path is within uploads directory.
		$base_dir = wp_normalize_path( $upload_dir['basedir'] );
		if ( 0 !== strpos( $file_path, $base_dir ) ) {
			return false;
		}

		// Security: Block directory traversal.
		if ( false !== strpos( $file_path, '..' ) ) {
			return false;
		}

		return $file_path;
	}

	/**
	 * Check if browser supports WebP
	 *
	 * Performance: Cached per request.
	 * Security: Sanitized server variables.
	 *
	 * @return bool True if browser supports WebP.
	 */
	public static function browser_supports_webp() {
		// Return cached result if available.
		if ( null !== self::$browser_supports ) {
			return self::$browser_supports;
		}

		$supported = false;

		// Check Accept header for WebP support (most reliable).
		if ( isset( $_SERVER['HTTP_ACCEPT'] ) ) {
			$accept = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) );
			if ( false !== strpos( $accept, 'image/webp' ) ) {
				$supported = true;
			}
		}

		// Fallback: Check user agent.
		if ( ! $supported && isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );

			// Modern browsers that support WebP.
			$webp_browsers = array( 'Chrome', 'Firefox', 'Edge', 'Opera', 'Safari' );

			foreach ( $webp_browsers as $browser ) {
				if ( false !== strpos( $user_agent, $browser ) ) {
					// Safari only supports WebP since version 14.
					if ( 'Safari' === $browser ) {
						// Simple version check (Safari 14+).
						if ( preg_match( '/Version\/(\d+)/', $user_agent, $matches ) ) {
							$safari_version = intval( $matches[1] );
							$supported      = $safari_version >= 14;
						}
					} else {
						$supported = true;
					}
					break;
				}
			}
		}

		// Cache result for this request.
		self::$browser_supports = $supported;

		return $supported;
	}

	/**
	 * Get WebP URL for an image
	 *
	 * Performance: Uses transient caching.
	 * Security: Validates paths.
	 *
	 * @param string $url Original image URL.
	 * @return string|false WebP URL or false if not found.
	 */
	public static function get_webp_url( $url ) {
		if ( empty( $url ) || ! is_string( $url ) ) {
			return false;
		}

		// Validate URL is from uploads.
		if ( ! self::is_valid_upload_url( $url ) ) {
			return false;
		}

		// Generate WebP URL.
		$webp_url = preg_replace( '/\.(jpg|jpeg|png)$/i', '.webp', $url );

		// Validate WebP URL was generated.
		if ( ! is_string( $webp_url ) || $webp_url === $url ) {
			return false; // No replacement made or invalid result.
		}

		// Check cache first (performance).
		$cache_key = 'webp_exists_' . md5( $webp_url );
		$exists    = get_transient( $cache_key );

		if ( false !== $exists ) {
			return $exists ? $webp_url : false;
		}

		// Convert URL to path securely.
		$upload_dir = self::get_upload_dir();
		$webp_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $webp_url );

		// Normalize and validate path (security).
		$webp_path = wp_normalize_path( $webp_path );
		$base_dir  = wp_normalize_path( $upload_dir['basedir'] );

		// Must be within uploads directory.
		if ( 0 !== strpos( $webp_path, $base_dir ) ) {
			return false;
		}

		// Check if file exists.
		$webp_exists = file_exists( $webp_path ) && is_readable( $webp_path );

		// Cache result for 5 minutes (performance).
		set_transient( $cache_key, $webp_exists, 5 * MINUTE_IN_SECONDS );

		return $webp_exists ? $webp_url : false;
	}

	/**
	 * Check if WebP is supported
	 *
	 * @return bool True if supported.
	 */
	public static function is_webp_supported() {
		// Check transient cache (performance).
		$cached = get_transient( 'aggressive_apparel_webp_supported' );
		if ( false !== $cached ) {
			return (bool) $cached;
		}

		$supported = false;

		if ( function_exists( 'gd_info' ) ) {
			$gd_info   = gd_info();
			$supported = ! empty( $gd_info['WebP Support'] );
		}

		// Cache for 1 hour.
		set_transient( 'aggressive_apparel_webp_supported', $supported, HOUR_IN_SECONDS );

		return $supported;
	}
}
