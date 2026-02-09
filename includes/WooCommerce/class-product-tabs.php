<?php
/**
 * Product Tabs Class
 *
 * Adds custom global and per-product tabs to single product pages.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product Tabs Manager
 *
 * @since 1.17.0
 */
class Product_Tabs {

	/**
	 * Option key for global tab content.
	 *
	 * @var string
	 */
	private const OPTION_KEY = 'aggressive_apparel_product_tabs';

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'woocommerce_product_tabs', array( $this, 'add_custom_tabs' ), 20 );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_product', array( $this, 'save_meta' ) );
		add_action( 'admin_init', array( $this, 'register_global_settings' ) );
	}

	/**
	 * Register global tab-content settings under Appearance > Store Enhancements.
	 *
	 * @return void
	 */
	public function register_global_settings(): void {
		register_setting(
			'aggressive_apparel_features_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_global_tabs' ),
				'default'           => array(),
			),
		);
	}

	/**
	 * Sanitize global tab content.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, string>
	 */
	public function sanitize_global_tabs( $input ): array {
		$output = array();
		if ( is_array( $input ) ) {
			foreach ( $input as $key => $value ) {
				$output[ sanitize_key( $key ) ] = wp_kses_post( $value );
			}
		}
		return $output;
	}

	/**
	 * Add custom tabs to the product page.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array Modified tabs.
	 */
	public function add_custom_tabs( array $tabs ): array {
		$global  = get_option( self::OPTION_KEY, array() );
		$post_id = get_the_ID();

		// Care Instructions (per-product meta or global fallback).
		$care = '';
		if ( $post_id ) {
			$care = (string) get_post_meta( $post_id, '_aggressive_apparel_care_instructions', true );
		}
		if ( '' === $care && ! empty( $global['care_instructions'] ) ) {
			$care = $global['care_instructions'];
		}
		if ( '' !== $care ) {
			$tabs['care_instructions'] = array(
				'title'    => __( 'Care Instructions', 'aggressive-apparel' ),
				'priority' => 25,
				'callback' => function () use ( $care ) {
					echo wp_kses_post( wpautop( $care ) );
				},
			);
		}

		// Shipping & Returns (global only).
		if ( ! empty( $global['shipping_returns'] ) ) {
			$tabs['shipping_returns'] = array(
				'title'    => __( 'Shipping & Returns', 'aggressive-apparel' ),
				'priority' => 30,
				'callback' => function () use ( $global ) {
					echo wp_kses_post( wpautop( $global['shipping_returns'] ) );
				},
			);
		}

		// Sustainability (global only).
		if ( ! empty( $global['sustainability'] ) ) {
			$tabs['sustainability'] = array(
				'title'    => __( 'Sustainability', 'aggressive-apparel' ),
				'priority' => 35,
				'callback' => function () use ( $global ) {
					echo wp_kses_post( wpautop( $global['sustainability'] ) );
				},
			);
		}

		return $tabs;
	}

	/**
	 * Add meta box for per-product tab content.
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		add_meta_box(
			'aggressive_apparel_product_tabs',
			__( 'Extra Product Tabs', 'aggressive-apparel' ),
			array( $this, 'render_meta_box' ),
			'product',
			'normal',
			'default',
		);
	}

	/**
	 * Render the meta box.
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'aggressive_apparel_tabs_nonce', '_aa_tabs_nonce' );

		$care = (string) get_post_meta( $post->ID, '_aggressive_apparel_care_instructions', true );

		echo '<p><label for="aa_care_instructions"><strong>' . esc_html__( 'Care Instructions', 'aggressive-apparel' ) . '</strong></label></p>';
		echo '<textarea id="aa_care_instructions" name="aa_care_instructions" rows="4" class="widefat">' . esc_textarea( $care ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Leave empty to use the global default from Appearance → Store Enhancements.', 'aggressive-apparel' ) . '</p>';
	}

	/**
	 * Save meta box data.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_meta( int $post_id ): void {
		if ( ! isset( $_POST['_aa_tabs_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_aa_tabs_nonce'] ) ), 'aggressive_apparel_tabs_nonce' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$care = isset( $_POST['aa_care_instructions'] )
			? wp_kses_post( wp_unslash( $_POST['aa_care_instructions'] ) )
			: '';

		update_post_meta( $post_id, '_aggressive_apparel_care_instructions', $care );
	}
}
