<?php
/**
 * Scripts Class
 *
 * Handles frontend JavaScript enqueuing
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
 * Scripts Class
 *
 * Responsible for enqueuing all frontend JavaScript files.
 *
 * @since 1.0.0
 */
class Scripts {

	/**
	 * Theme version
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Constructor
	 *
	 * @param string|null $version Theme version. If null, gets from theme data.
	 */
	public function __construct( $version = null ) {
		$this->version = $version ? $version : \wp_get_theme()->get( 'Version' );
	}

	/**
	 * Initialize scripts
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );
	}

	/**
	 * Enqueue frontend scripts
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		$theme_uri = \get_template_directory_uri();

		// Main theme script.
		wp_enqueue_script(
			'aggressive-apparel-main',
			$theme_uri . '/build/scripts/main.js',
			array(),
			$this->version,
			true
		);

		// Navigation script (conditional).
		if ( \has_nav_menu( 'primary' ) ) {
			wp_enqueue_script(
				'aggressive-apparel-navigation',
				$theme_uri . '/build/scripts/navigation.js',
				array(),
				$this->version,
				true
			);
		}

		// Product script (conditional).
		if ( \is_singular( 'product' ) ) {
			wp_enqueue_script(
				'aggressive-apparel-product',
				$theme_uri . '/build/scripts/product.js',
				array(),
				$this->version,
				true
			);
		}

		// Localize script data.
		wp_localize_script(
			'aggressive-apparel-main',
			'aggressiveApparelData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'aggressive_apparel_nonce' ),
			)
		);

		/**
		 * Hook: After scripts enqueued
		 *
		 * @since 1.0.0
		 */
		do_action( 'aggressive_apparel_after_enqueue_scripts' );
	}
}
