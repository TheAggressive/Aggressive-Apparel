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
	 * Image queue manager
	 *
	 * @var WebP_Queue_Manager
	 */
	private WebP_Queue_Manager $queue_manager;

	/**
	 * Image converter
	 *
	 * @var WebP_Converter
	 */
	private WebP_Converter $converter;

	/**
	 * Constructor
	 *
	 * @param WebP_Queue_Manager $queue_manager Queue manager instance.
	 * @param WebP_Converter     $converter     Converter instance.
	 */
	public function __construct(
		WebP_Queue_Manager $queue_manager,
		WebP_Converter $converter
	) {
		$this->queue_manager = $queue_manager;
		$this->converter     = $converter;
	}

	/**
	 * Initialize on-demand converter
	 *
	 * @return void
	 */
	public function init(): void {
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
		$this->queue_manager->queue_image( $attachment_id, $file_path );

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
				$this->queue_manager->queue_image( $attachment_id, $file_path );
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
	 * Process the conversion queue on shutdown
	 *
	 * Converts images after the page is sent to the user.
	 * Non-blocking, so doesn't slow down the page load.
	 *
	 * @return void
	 */
	public function process_queue(): void {
		if ( $this->queue_manager->get_queue_size() === 0 ) {
			return;
		}

		// Check if WebP is supported.
		if ( ! WebP_Utils::is_webp_supported() ) {
			return;
		}

		// Get next batch to process.
		$to_process     = $this->queue_manager->get_next_batch();
		$processed_keys = array();

		foreach ( $to_process as $key => $item ) {
			if ( $this->converter->convert_to_webp( $item['file_path'] ) ) {
				array_push( $processed_keys, $key );
			}
		}

		// Remove processed items from queue.
		if ( ! empty( $processed_keys ) ) {
			$this->queue_manager->remove_processed( $processed_keys );
		}

		// Log remaining queue (only if WP_DEBUG).
		$remaining = $this->queue_manager->get_queue_size();
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $remaining > 0 ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				sprintf(
					'[Aggressive Apparel] Converted %d images, %d queued for next request',
					count( $processed_keys ),
					$remaining
				)
			);
		}
	}
}
