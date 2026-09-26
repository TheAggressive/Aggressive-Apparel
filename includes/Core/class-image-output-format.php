<?php
/**
 * Image Output Format Class
 *
 * Saves the images WordPress generates for PNG and JPEG uploads as WebP: the
 * full-size copy it serves and every sub-size. The uploaded file is kept as
 * the attachment's `original_image`. Measured on a 2048px AI-generated PNG:
 * full size 9.3 MB → 0.9 MB, the 1024px copy 1.8 MB → 0.2 MB.
 *
 * Applies to new uploads only; existing media keeps its files. When the
 * server's image editor cannot write WebP, WordPress keeps the source format.
 *
 * @package Aggressive_Apparel
 * @since 1.183.5
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image Output Format Class
 *
 * @since 1.183.5
 */
class Image_Output_Format {

	/**
	 * Source MIME types whose generated images are saved as WebP.
	 *
	 * GIF is excluded so animated GIFs keep their animation.
	 *
	 * @var array<int, string>
	 */
	private const SOURCE_TYPES = array( 'image/png', 'image/jpeg' );

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'image_editor_output_format', array( $this, 'filter_output_format' ) );
	}

	/**
	 * Map PNG and JPEG sources to WebP output.
	 *
	 * Mappings set elsewhere (core or another plugin) are left untouched.
	 *
	 * @param array<string, string> $formats Source MIME type => output MIME type.
	 * @return array<string, string> Filtered mapping.
	 */
	public function filter_output_format( $formats ): array {
		$formats = is_array( $formats ) ? $formats : array();

		/**
		 * Filter whether generated PNG and JPEG images are saved as WebP.
		 *
		 * @since 1.183.5
		 *
		 * @param bool $convert True to save generated images as WebP.
		 */
		if ( ! apply_filters( 'aggressive_apparel_webp_image_output', true ) ) {
			return $formats;
		}

		foreach ( self::SOURCE_TYPES as $source ) {
			if ( ! isset( $formats[ $source ] ) ) {
				$formats[ $source ] = 'image/webp';
			}
		}

		return $formats;
	}
}
