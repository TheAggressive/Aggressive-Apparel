<?php
/**
 * Styles Class
 *
 * Handles frontend stylesheet enqueuing
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
 * Styles Class
 *
 * Responsible for enqueuing all frontend stylesheets.
 *
 * @since 1.0.0
 */
class Styles {

	/**
	 * Initialize styles
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ), 10 );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_block_assets' ), 10 );
	}

	/**
	 * Enqueue frontend styles
	 *
	 * @return void
	 */
	public function enqueue_styles() {
		// Main theme stylesheet.
		Asset_Loader::enqueue_style(
			'aggressive-apparel-main',
			'build/styles/main'
		);

		// Navigation styles (conditional).
		if ( has_nav_menu( 'primary' ) ) {
			Asset_Loader::enqueue_style(
				'aggressive-apparel-navigation',
				'build/styles/navigation',
				array( 'aggressive-apparel-main' )
			);
		}

		// Block styles.
		Asset_Loader::enqueue_style(
			'aggressive-apparel-blocks',
			'build/styles/blocks',
			array( 'aggressive-apparel-main' )
		);

		if ( is_singular( 'product' ) ) {
			Asset_Loader::enqueue_style(
				'aggressive-apparel-product',
				'build/styles/woocommerce/color-swatches',
				array()
			);
			Asset_Loader::enqueue_style(
				'aggressive-apparel-variation-pills',
				'build/styles/woocommerce/variation-pills',
				array()
			);
		}

		/**
		 * Hook: After styles enqueued
		 *
		 * @since 1.0.0
		 */
		do_action( 'aggressive_apparel_after_enqueue_styles' );
	}

	/**
	 * Enqueue block assets (available in both frontend and editor)
	 *
	 * @return void
	 */
	public function enqueue_block_assets() {
		// Main theme stylesheet for editor context.
		Asset_Loader::enqueue_style(
			'aggressive-apparel-main',
			'build/styles/main'
		);

		// Block styles for editor context.
		Asset_Loader::enqueue_style(
			'aggressive-apparel-blocks',
			'build/styles/blocks',
			array( 'aggressive-apparel-main' )
		);

		/**
		 * Hook: After block assets enqueued
		 *
		 * @since 1.0.0
		 */
		do_action( 'aggressive_apparel_after_enqueue_block_assets' );
	}
}
