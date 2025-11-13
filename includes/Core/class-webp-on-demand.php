<?php
/**
 * WebP On-Demand Converter
 *
 * Converts images to WebP when they're requested, not in batches
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
 * WebP On-Demand Class
 *
 * Converts images to WebP on first page view.
 * Next visitor gets the WebP version automatically.
 *
 * @since 1.0.0
 */
class WebP_On_Demand {

	/**
	 * Queue of images to convert
	 *
	 * @var array
	 */
	private $conversion_queue = array();


	/**
	 * Maximum conversions per request
	 *
	 * @var int
	 */
	private $max_per_request = 5;

	/**
	 * Maximum image dimensions to convert
	 *
	 * @var int
	 */
	private $max_dimension = 3000;

	/**
	 * Initialize on-demand converter
	 *
	 * @return void
	 */
	public function init() {
		// Intercept image URLs and queue for conversion if needed.
		add_filter( 'wp_get_attachment_image_src', array( $this, 'check_and_queue_image' ), 10, 4 );
		add_filter( 'wp_calculate_image_srcset', array( $this, 'check_srcset_images' ), 10, 5 );

		// Convert queued images on shutdown (non-blocking).
		add_action( 'shutdown', array( $this, 'process_queue' ), 999 );
	}

	/**
	 * Check if image needs WebP conversion and queue it
	 *
	 * @param array|false  $image         Array of image data, or boolean false if no image is available.
	 * @param int          $attachment_id Image attachment ID.
	 * @param string|array $size          Size of image.
	 * @param bool         $icon          Whether the image should be treated as an icon.
	 * @return array|false Unchanged image data.
	 */
	public function check_and_queue_image( $image, $attachment_id, $size, $icon ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! $image || ! is_array( $image ) || empty( $image[0] ) ) {
			return $image;
		}

		$image_url = $image[0];

		// Check if this is a JPEG or PNG.
		if ( ! preg_match( '/\.(jpg|jpeg|png)$/i', $image_url ) ) {
			return $image;
		}

		// Validate URL is from our uploads directory.
		if ( ! WebP_Utils::is_valid_upload_url( $image_url ) ) {
			return $image;
		}

		// Get file path securely.
		$file_path = WebP_Utils::url_to_path( $image_url );
		if ( ! $file_path ) {
			return $image;
		}

		// Check if WebP exists (with caching).
		if ( ! $this->needs_webp_conversion( $file_path ) ) {
			return $image;
		}

		// Queue for conversion.
		$this->queue_image( $attachment_id, $file_path );

		return $image;
	}

	/**
	 * Check srcset images and queue missing WebP versions
	 *
	 * @param array  $sources       Array of image sources.
	 * @param array  $size_array    Array of width and height values.
	 * @param string $image_src     Image source URL.
	 * @param array  $image_meta    Image metadata.
	 * @param int    $attachment_id Attachment ID.
	 * @return array Unchanged sources.
	 */
	public function check_srcset_images( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		if ( empty( $sources ) || ! is_array( $sources ) ) {
			return $sources;
		}

		foreach ( $sources as $source ) {
			if ( empty( $source['url'] ) ) {
				continue;
			}

			$url = $source['url'];

			// Skip if not JPEG/PNG.
			if ( ! preg_match( '/\.(jpg|jpeg|png)$/i', $url ) ) {
				continue;
			}

			// Validate URL.
			if ( ! WebP_Utils::is_valid_upload_url( $url ) ) {
				continue;
			}

			$file_path = WebP_Utils::url_to_path( $url );
			if ( ! $file_path ) {
				continue;
			}

			if ( $this->needs_webp_conversion( $file_path ) ) {
				$this->queue_image( $attachment_id, $file_path );
			}
		}

		return $sources;
	}


	/**
	 * Check if image needs WebP conversion
	 *
	 * @param string $file_path File path.
	 * @return bool True if needs conversion.
	 */
	private function needs_webp_conversion( $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return false;
		}

		$webp_path = preg_replace( '/\.(jpg|jpeg|png)$/i', '.webp', $file_path );

		// Validate WebP path was generated.
		if ( ! is_string( $webp_path ) ) {
			return false;
		}

		// Check transient cache first (performance).
		$cache_key = 'webp_exists_' . md5( $webp_path );
		$exists    = get_transient( $cache_key );

		if ( false !== $exists ) {
			return ! $exists; // If exists in cache, doesn't need conversion.
		}

		// Check filesystem.
		$webp_exists = file_exists( $webp_path );

		// Cache for 5 minutes.
		set_transient( $cache_key, $webp_exists, 5 * MINUTE_IN_SECONDS );

		return ! $webp_exists;
	}

	/**
	 * Queue an image for conversion
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file_path     File path.
	 * @return void
	 */
	private function queue_image( $attachment_id, $file_path ) {
		// Avoid duplicates.
		$key = md5( $file_path );

		if ( isset( $this->conversion_queue[ $key ] ) ) {
			return;
		}

		// Limit queue size (performance).
		if ( count( $this->conversion_queue ) >= $this->max_per_request * 2 ) {
			return;
		}

		$this->conversion_queue[ $key ] = array(
			'attachment_id' => absint( $attachment_id ),
			'file_path'     => $file_path,
		);
	}

	/**
	 * Process the conversion queue on shutdown
	 *
	 * Converts images after the page is sent to the user.
	 * Non-blocking, so doesn't slow down the page load.
	 *
	 * @return void
	 */
	public function process_queue() {
		if ( empty( $this->conversion_queue ) ) {
			return;
		}

		// Check if WebP is supported.
		if ( ! WebP_Utils::is_webp_supported() ) {
			return;
		}

		// Allow filtering max per request.
		$max_per_request = apply_filters( 'aggressive_apparel_webp_max_per_request', $this->max_per_request );

		// Limit conversions per request to avoid overload.
		$to_process = array_slice( $this->conversion_queue, 0, $max_per_request, true );

		foreach ( $to_process as $item ) {
			$this->convert_to_webp( $item['file_path'] );
		}

		// Log remaining queue (only if WP_DEBUG).
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && count( $this->conversion_queue ) > $max_per_request ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				sprintf(
					'[Aggressive Apparel] Converted %d images, %d queued for next request',
					$max_per_request,
					count( $this->conversion_queue ) - $max_per_request
				)
			);
		}
	}

	/**
	 * Convert a single image to WebP
	 *
	 * @param string $file_path Path to the image file.
	 * @return bool Success status.
	 */
	private function convert_to_webp( $file_path ) {
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
		$max_dimension = apply_filters( 'aggressive_apparel_webp_max_dimension', $this->max_dimension );
		if ( $width > $max_dimension || $height > $max_dimension ) {
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
		$image = null;

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
			}
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Aggressive Apparel] WebP conversion error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return false;
		}

		if ( ! $image || ! ( $image instanceof \GdImage ) ) {
			return false;
		}

		// Generate WebP path.
		$webp_path = preg_replace( '/\.(jpg|jpeg|png)$/i', '.webp', $file_path );

		// Validate WebP path was generated.
		if ( ! is_string( $webp_path ) ) {
			return false;
		}

		// Allow filtering quality.
		$quality = apply_filters( 'aggressive_apparel_webp_quality', 90 );
		$quality = max( 0, min( 100, intval( $quality ) ) ); // Clamp 0-100.

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
	 * Check if there's sufficient memory for conversion
	 *
	 * Performance: Prevents memory exhaustion.
	 *
	 * @param int $width  Image width.
	 * @param int $height Image height.
	 * @return bool True if sufficient memory.
	 */
	private function has_sufficient_memory( $width, $height ) {
		// Estimate memory needed (rough calculation).
		// Uncompressed image = width * height * 4 bytes (RGBA).
		// Plus 50% overhead for processing.
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
