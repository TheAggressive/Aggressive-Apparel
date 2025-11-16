<?php
/**
 * WebP Queue Manager
 *
 * Manages the queue of images waiting for WebP conversion
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
 * WebP Queue Manager Class
 *
 * Handles queuing and processing of images for WebP conversion.
 *
 * @since 1.0.0
 */
class WebP_Queue_Manager {

	/**
	 * Queue of images to convert
	 *
	 * @var array
	 */
	private array $conversion_queue = array();

	/**
	 * Maximum conversions per request
	 *
	 * @var int
	 */
	private int $max_per_request;

	/**
	 * Constructor
	 *
	 * @param int $max_per_request Maximum conversions per request.
	 */
	public function __construct( int $max_per_request = 5 ) {
		$this->max_per_request = $max_per_request;
	}

	/**
	 * Add image to conversion queue
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file_path     File path.
	 * @return void
	 */
	public function queue_image( int $attachment_id, string $file_path ): void {
		$key = md5( $file_path );

		if ( isset( $this->conversion_queue[ $key ] ) ) {
			return;
		}

		// Limit queue size.
		if ( count( $this->conversion_queue ) >= $this->max_per_request * 2 ) {
			return;
		}

		$this->conversion_queue[ $key ] = array(
			'attachment_id' => $attachment_id,
			'file_path'     => $file_path,
		);
	}

	/**
	 * Get next batch of images to process
	 *
	 * @return array Array of queued images.
	 */
	public function get_next_batch(): array {
		return array_slice( $this->conversion_queue, 0, $this->max_per_request, true );
	}

	/**
	 * Remove processed images from queue
	 *
	 * @param array $processed_keys Array of processed image keys.
	 * @return void
	 */
	public function remove_processed( array $processed_keys ): void {
		foreach ( $processed_keys as $key ) {
			unset( $this->conversion_queue[ $key ] );
		}
	}

	/**
	 * Get queue size
	 *
	 * @return int Number of queued images.
	 */
	public function get_queue_size(): int {
		return count( $this->conversion_queue );
	}
}
