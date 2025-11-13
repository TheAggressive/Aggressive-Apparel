<?php
/**
 * WebP Support Class
 *
 * Ensures WebP images are served when available
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
 * WebP Support Class
 *
 * Handles WebP image serving for theme and WooCommerce.
 *
 * @since 1.0.0
 */
class WebP_Support {


	/**
	 * Initialize WebP support
	 *
	 * @return void
	 */
	public function init() {
		// Ensure WordPress uses WebP in image tags.
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'add_webp_srcset' ), 10, 3 );
		add_filter( 'wp_get_attachment_image_src', array( $this, 'replace_image_src' ), 10, 4 );

		// Enable WebP for WooCommerce product images.
		if ( class_exists( 'WooCommerce' ) ) {
			add_filter( 'woocommerce_product_get_image', array( $this, 'use_webp_in_woocommerce' ), 10, 2 );
			add_filter( 'woocommerce_single_product_image_html', array( $this, 'use_webp_in_single_product' ), 10, 2 );
			add_filter( 'woocommerce_single_product_image_thumbnail_html', array( $this, 'use_webp_in_single_product' ), 10, 2 );
		}

		// Add WebP to responsive images.
		add_filter( 'wp_calculate_image_srcset', array( $this, 'add_webp_to_srcset' ), 10, 5 );

		// Add WebP to direct img tags (theme images).
		add_filter( 'the_content', array( $this, 'replace_img_tags_in_content' ), 10, 1 );
	}

	/**
	 * Add WebP support to image attributes
	 *
	 * @param array        $attr       Image attributes.
	 * @param \WP_Post     $attachment Image attachment post.
	 * @param string|array $size       Image size.
	 * @return array Modified attributes.
	 */
	/**
	 * Replace image src with WebP version
	 *
	 * @param array|false  $image         Array of image data, or boolean false if no image is available.
	 * @param int          $attachment_id Image attachment ID.
	 * @param string|array $size          Size of image.
	 * @param bool         $icon          Whether the image should be treated as an icon.
	 * @return array|false Unchanged image data.
	 */
	public function replace_image_src( $image, $attachment_id, $size, $icon ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// Only if browser supports WebP.
		if ( ! WebP_Utils::browser_supports_webp() || ! is_array( $image ) || empty( $image[0] ) ) {
			return $image;
		}

		// Get WebP URL.
		$webp_url = WebP_Utils::get_webp_url( $image[0] );
		if ( $webp_url ) {
			$image[0] = $webp_url; // Replace the src directly!
		}

		return $image;
	}

	/**
	 * Add WebP support to image attributes
	 *
	 * @param array        $attr       Image attributes.
	 * @param \WP_Post     $attachment Image attachment post.
	 * @param string|array $size       Image size.
	 * @return array Modified attributes.
	 */
	public function add_webp_srcset( $attr, $attachment, $size ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// Only if browser supports WebP.
		if ( ! WebP_Utils::browser_supports_webp() ) {
			return $attr;
		}

		// Check if WebP version exists.
		if ( ! empty( $attr['src'] ) ) {
			$webp_url = WebP_Utils::get_webp_url( $attr['src'] );
			if ( $webp_url ) {
				$attr['src'] = esc_url( $webp_url ); // Replace src directly!
			}
		}

		return $attr;
	}

	/**
	 * Use WebP in WooCommerce images
	 *
	 * @param string $image   HTML img tag.
	 * @param mixed  $product Product object.
	 * @return string Modified HTML.
	 */
	/**
	 * Use WebP in WooCommerce images
	 *
	 * @param string $image   HTML img tag.
	 * @param mixed  $product Product object.
	 * @return string Modified HTML.
	 */
	public function use_webp_in_woocommerce( $image, $product ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! WebP_Utils::browser_supports_webp() || empty( $image ) ) {
			return $image;
		}

		// Replace JPEG/PNG sources with WebP if available.
		return $this->replace_image_sources( $image );
	}

	/**
	 * Use WebP in single product images
	 *
	 * @param string $html    HTML img tag.
	 * @param int    $post_id Post ID.
	 * @return string Modified HTML.
	 */
	public function use_webp_in_single_product( $html, $post_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! WebP_Utils::browser_supports_webp() || empty( $html ) ) {
			return $html;
		}

		// Replace JPEG/PNG sources with WebP if available.
		return $this->replace_image_sources( $html );
	}

	/**
	 * Replace image sources with WebP versions
	 *
	 * @param string $html HTML containing img tags.
	 * @return string Modified HTML.
	 */
	private function replace_image_sources( $html ) {
		$result = preg_replace_callback(
			'/src=["\']([^"\']+\.(jpg|jpeg|png))["\']/',
			function ( $matches ) {
				// Validate URL is from uploads.
				if ( ! WebP_Utils::is_valid_upload_url( $matches[1] ) ) {
					return $matches[0];
				}

				$webp_url = WebP_Utils::get_webp_url( $matches[1] );
				if ( $webp_url ) {
					return 'src="' . esc_url( $webp_url ) . '"';
				}
				return $matches[0];
			},
			$html
		);

		// Ensure we always return a string.
		return is_string( $result ) ? $result : $html;
	}

	/**
	 * Add WebP URLs to srcset
	 *
	 * @param array  $sources       Array of image sources.
	 * @param array  $size_array    Array of width and height values.
	 * @param string $image_src     Image source URL.
	 * @param array  $image_meta    Image metadata.
	 * @param int    $attachment_id Attachment ID.
	 * @return array Modified sources.
	 */
	/**
	 * Add WebP URLs to srcset
	 *
	 * @param array  $sources       Array of image sources.
	 * @param array  $size_array    Array of width and height values.
	 * @param string $image_src     Image source URL.
	 * @param array  $image_meta    Image metadata.
	 * @param int    $attachment_id Attachment ID.
	 * @return array Modified sources.
	 */
	public function add_webp_to_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! WebP_Utils::browser_supports_webp() || empty( $sources ) || ! is_array( $sources ) ) {
			return $sources;
		}

		foreach ( $sources as $width => $source ) {
			if ( empty( $source['url'] ) ) {
				continue;
			}

			// Validate URL.
			if ( ! WebP_Utils::is_valid_upload_url( $source['url'] ) ) {
				continue;
			}

			$webp_url = WebP_Utils::get_webp_url( $source['url'] );
			if ( $webp_url ) {
				$sources[ $width ]['url'] = $webp_url;
			}
		}

		return $sources;
	}

	/**
	 * Replace img tags in content with WebP versions
	 *
	 * @param string $content Post content.
	 * @return string Modified content.
	 */
	public function replace_img_tags_in_content( $content ) {
		if ( ! WebP_Utils::browser_supports_webp() || empty( $content ) ) {
			return $content;
		}

		// Replace JPEG/PNG sources in img tags.
		$result = preg_replace_callback(
			'/<img([^>]+)src=["\']([^"\']+\.(jpg|jpeg|png))["\']([^>]*)>/i',
			function ( $matches ) {
				$before_src = $matches[1];
				$image_url  = $matches[2];
				$after_src  = $matches[4];

				// Validate URL is from uploads.
				if ( ! WebP_Utils::is_valid_upload_url( $image_url ) ) {
					return $matches[0];
				}

				$webp_url = WebP_Utils::get_webp_url( $image_url );
				if ( $webp_url ) {
					return '<img' . $before_src . 'src="' . esc_url( $webp_url ) . '"' . $after_src . '>';
				}

				return $matches[0];
			},
			$content
		);

		// Ensure we always return a string.
		return is_string( $result ) ? $result : $content;
	}
}
