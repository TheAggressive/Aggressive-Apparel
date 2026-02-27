<?php
/**
 * Image Sizes Class
 *
 * Handles custom image size registration
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image Sizes Class
 *
 * Registers custom image sizes for the theme.
 *
 * @since 1.0.0
 */
class Image_Sizes {

	/**
	 * Initialize image sizes
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'after_setup_theme', array( $this, 'register_image_sizes' ), 11 );
		add_filter( 'image_size_names_choose', array( $this, 'add_custom_sizes_to_media_library' ) );
	}

	/**
	 * Register custom image sizes
	 *
	 * Modern, retina-ready sizes optimized for 2024/2025 standards.
	 *
	 * @return void
	 */
	public function register_image_sizes() {
		// Product featured image (retina-ready, zoom-friendly).
		add_image_size( 'aggressive-apparel-product-featured', 1200, 1200, true );

		// Product thumbnail (sharp on all devices).
		add_image_size( 'aggressive-apparel-product-thumbnail', 400, 400, true );

		// Product gallery (matches featured for consistency).
		add_image_size( 'aggressive-apparel-product-gallery', 1200, 1200, true );

		// Blog featured image (16:9 ratio for modern layouts).
		add_image_size( 'aggressive-apparel-blog-featured', 1600, 900, true );

		// Blog thumbnail (3:2 ratio).
		add_image_size( 'aggressive-apparel-blog-thumbnail', 600, 400, true );

		/**
		 * Hook: After image sizes registration
		 *
		 * @since 1.0.0
		 */
		do_action( 'aggressive_apparel_after_image_sizes' );
	}

	/**
	 * Add custom image sizes to media library
	 *
	 * @param array $sizes Existing image sizes.
	 * @return array Modified image sizes.
	 */
	public function add_custom_sizes_to_media_library( $sizes ) {
		return array_merge(
			$sizes,
			array(
				'aggressive-apparel-product-featured'  => __( 'Product Featured', 'aggressive-apparel' ),
				'aggressive-apparel-product-thumbnail' => __( 'Product Thumbnail', 'aggressive-apparel' ),
				'aggressive-apparel-product-gallery'   => __( 'Product Gallery', 'aggressive-apparel' ),
				'aggressive-apparel-blog-featured'     => __( 'Blog Featured', 'aggressive-apparel' ),
				'aggressive-apparel-blog-thumbnail'    => __( 'Blog Thumbnail', 'aggressive-apparel' ),
			)
		);
	}
}
