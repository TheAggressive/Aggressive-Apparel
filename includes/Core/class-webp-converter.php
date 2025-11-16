<?php
/**
 * WebP Converter
 *
 * Handles the actual conversion of images to WebP format
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
 * WebP Converter Class
 *
 * Handles conversion of images to WebP format with proper error handling.
 *
 * @since 1.0.0
 */
class WebP_Converter {

	/**
	 * Maximum image dimensions to convert
	 *
	 * @var int
	 */
	private int $max_dimension;

	/**
	 * Constructor
	 *
	 * @param int $max_dimension Maximum image dimension.
	 */
	public function __construct( int $max_dimension = 3000 ) {
		$this->max_dimension = $max_dimension;
	}

	/**
	 * Convert image to WebP
	 *
	 * @param string $file_path Path to the image file.
	 * @return bool Success status.
	 */
	public function convert_to_webp( string $file_path ): bool {
		// Validate file exists and is readable.
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return false;
		}

		// Security: Validate file is within uploads directory.
		$normalized_path = wp_normalize_path( $file_path );
		$upload_dir      = wp_upload_dir();
		$base_dir        = wp_normalize_path( $upload_dir['basedir'] );

		if ( 0 !== strpos( $normalized_path, $base_dir ) ) {
			return false;
		}

		// Get image info safely.
		$image_info = getimagesize( $file_path );
		if ( false === $image_info ) {
			return false;
		}

		list( $width, $height, $type ) = $image_info;

		// Validate image dimensions (performance/memory).
		if ( $width > $this->max_dimension || $height > $this->max_dimension ) {
			return false;
		}

		// Check memory limit.
		if ( ! $this->has_sufficient_memory( $width, $height ) ) {
			return false;
		}

		// Only process JPEG and PNG.
		if ( ! in_array( $type, array( IMAGETYPE_JPEG, IMAGETYPE_PNG ), true ) ) {
			return false;
		}

		// Load image with proper error handling.
		$image = $this->load_image( $file_path, $type );
		if ( ! $image || ! ( $image instanceof \GdImage ) ) {
			return false;
		}

		// Generate WebP path.
		$webp_path = $this->generate_webp_path( $file_path );

		// Allow filtering quality.
		$quality = apply_filters( 'aggressive_apparel_webp_quality', 90 );
		$quality = max( 0, min( 100, intval( $quality ) ) );

		// Convert to WebP.
		$result = imagewebp( $image, $webp_path, $quality );

		// Free memory.
		imagedestroy( $image );

		if ( $result && file_exists( $webp_path ) ) {
			// Clear cache.
			$cache_key = 'webp_exists_' . md5( $webp_path );
			delete_transient( $cache_key );

			return true;
		}

		return false;
	}

	/**
	 * Load image resource
	 *
	 * @param string $file_path File path.
	 * @param int    $type      Image type.
	 * @return \GdImage|false Image resource or false on failure.
	 */
	private function load_image( string $file_path, int $type ) {
		try {
			switch ( $type ) {
				case IMAGETYPE_JPEG:
					$image = imagecreatefromjpeg( $file_path );
					break;
				case IMAGETYPE_PNG:
					$image = imagecreatefrompng( $file_path );
					// Preserve transparency.
					if ( $image ) {
						imagepalettetotruecolor( $image );
						imagealphablending( $image, true );
						imagesavealpha( $image, true );
					}
					break;
				default:
					return false;
			}

			return $image;
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Aggressive Apparel] WebP conversion error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return false;
		}
	}

	/**
	 * Generate WebP file path
	 *
	 * @param string $file_path Original file path.
	 * @return string WebP file path.
	 */
	private function generate_webp_path( string $file_path ): string {
		$result = preg_replace( '/\.(jpg|jpeg|png)$/i', '.webp', $file_path );
		return $result ? $result : $file_path . '.webp';
	}

	/**
	 * Check if there's sufficient memory for conversion
	 *
	 * @param int $width  Image width.
	 * @param int $height Image height.
	 * @return bool True if sufficient memory.
	 */
	private function has_sufficient_memory( int $width, int $height ): bool {
		// Estimate memory needed (rough calculation).
		$estimated = ( $width * $height * 4 * 1.5 ) / 1024 / 1024; // MB.

		$memory_limit = ini_get( 'memory_limit' );
		if ( '-1' === $memory_limit ) {
			return true; // Unlimited.
		}

		$memory_limit  = wp_convert_hr_to_bytes( $memory_limit );
		$current_usage = memory_get_usage( true );
		$available     = ( $memory_limit - $current_usage ) / 1024 / 1024; // MB.

		return $available > ( $estimated * 1.5 ); // 50% safety margin.
	}
}
