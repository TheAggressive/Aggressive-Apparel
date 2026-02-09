<?php
/**
 * Size Guide Class
 *
 * Renders a measurement-chart modal on single product pages.
 * Data can be stored per-product, per-category, or as a global fallback.
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
 * Size Guide
 *
 * @since 1.17.0
 */
class Size_Guide {

	/**
	 * Option key for the global size guide.
	 *
	 * @var string
	 */
	private const OPTION_KEY = 'aggressive_apparel_size_guide';

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'render_trigger_and_modal' ) );

		// Admin meta box for per-product size guides.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_product', array( $this, 'save_meta' ) );
	}

	/**
	 * Enqueue CSS and register Interactivity API script module on single product pages.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$css_file = AGGRESSIVE_APPAREL_DIR . '/build/styles/woocommerce/size-guide.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'aggressive-apparel-size-guide',
				AGGRESSIVE_APPAREL_URI . '/build/styles/woocommerce/size-guide.css',
				array(),
				(string) filemtime( $css_file ),
			);
		}

		if ( function_exists( 'wp_register_script_module' ) ) {
			wp_register_script_module(
				'@aggressive-apparel/size-guide',
				AGGRESSIVE_APPAREL_URI . '/assets/interactivity/size-guide.js',
				array( '@wordpress/interactivity' ),
				AGGRESSIVE_APPAREL_VERSION,
			);
			wp_enqueue_script_module( '@aggressive-apparel/size-guide' );
		}
	}

	/**
	 * Render the "Size Guide" link and the hidden modal.
	 *
	 * @return void
	 */
	public function render_trigger_and_modal(): void {
		$guide = $this->get_size_guide_for_product( get_the_ID() );
		if ( empty( $guide ) ) {
			return;
		}

		// Trigger link.
		echo '<div data-wp-interactive="aggressive-apparel/size-guide" data-wp-context=\'{"isOpen":false}\'>';
		echo '<button type="button" class="aggressive-apparel-size-guide__trigger" data-wp-on--click="actions.toggle" aria-haspopup="dialog">';
		echo esc_html__( 'Size Guide', 'aggressive-apparel' );
		echo '</button>';

		// Modal.
		echo '<div class="aggressive-apparel-size-guide__overlay" role="dialog" aria-modal="true" aria-label="' . esc_attr__( 'Size Guide', 'aggressive-apparel' ) . '" data-wp-bind--hidden="!context.isOpen" data-wp-class--is-open="context.isOpen" hidden>';
		echo '<div class="aggressive-apparel-size-guide__backdrop" data-wp-on--click="actions.close"></div>';
		echo '<div class="aggressive-apparel-size-guide__modal">';
		echo '<div class="aggressive-apparel-size-guide__header">';
		echo '<h2 class="aggressive-apparel-size-guide__title">' . esc_html__( 'Size Guide', 'aggressive-apparel' ) . '</h2>';
		echo '<button type="button" class="aggressive-apparel-size-guide__close" data-wp-on--click="actions.close" aria-label="' . esc_attr__( 'Close', 'aggressive-apparel' ) . '">&times;</button>';
		echo '</div>';
		echo '<div class="aggressive-apparel-size-guide__body">';
		echo wp_kses_post( $guide );
		echo '</div>';
		echo '</div>'; // modal.
		echo '</div>'; // overlay.
		echo '</div>'; // interactive root.
	}

	/**
	 * Retrieve the size guide content for a product.
	 *
	 * Priority: per-product meta → category term meta → global option.
	 *
	 * @param int|false $product_id Product ID.
	 * @return string HTML content or empty string.
	 */
	private function get_size_guide_for_product( $product_id ): string {
		if ( $product_id ) {
			$per_product = (string) get_post_meta( $product_id, '_aggressive_apparel_size_guide', true );
			if ( '' !== $per_product ) {
				return $per_product;
			}

			// Check product category term meta.
			$cats = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
			if ( is_array( $cats ) ) {
				foreach ( $cats as $cat_id ) {
					$cat_guide = (string) get_term_meta( $cat_id, '_aggressive_apparel_size_guide', true );
					if ( '' !== $cat_guide ) {
						return $cat_guide;
					}
				}
			}
		}

		return (string) get_option( self::OPTION_KEY, '' );
	}

	/**
	 * Add per-product meta box.
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		add_meta_box(
			'aggressive_apparel_size_guide',
			__( 'Size Guide', 'aggressive-apparel' ),
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
		wp_nonce_field( 'aa_size_guide_nonce', '_aa_sg_nonce' );
		$value = (string) get_post_meta( $post->ID, '_aggressive_apparel_size_guide', true );

		wp_editor(
			$value,
			'aa_size_guide_editor',
			array(
				'textarea_name' => 'aa_size_guide',
				'textarea_rows' => 8,
				'media_buttons' => false,
			),
		);
		echo '<p class="description">' . esc_html__( 'Paste or build a measurement table here. Leave empty to inherit from category or global setting.', 'aggressive-apparel' ) . '</p>';
	}

	/**
	 * Save meta box.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_meta( int $post_id ): void {
		if ( ! isset( $_POST['_aa_sg_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_aa_sg_nonce'] ) ), 'aa_size_guide_nonce' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$value = isset( $_POST['aa_size_guide'] )
			? wp_kses_post( wp_unslash( $_POST['aa_size_guide'] ) )
			: '';

		update_post_meta( $post_id, '_aggressive_apparel_size_guide', $value );
	}
}
