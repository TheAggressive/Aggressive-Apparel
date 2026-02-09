<?php
/**
 * Swatch Tooltips Class
 *
 * Adds CSS-only tooltips to color swatches showing fabric name and composition.
 * Data comes from additional term meta on the pa_color taxonomy.
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
 * Swatch Tooltips
 *
 * @since 1.17.0
 */
class Swatch_Tooltips {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Inject tooltip data attributes into already-rendered swatch HTML.
		add_filter( 'render_block', array( $this, 'add_tooltip_data' ), 15, 2 );

		// Admin: add extra fields to color term forms.
		add_action( 'pa_color_add_form_fields', array( $this, 'add_term_fields' ) );
		add_action( 'pa_color_edit_form_fields', array( $this, 'edit_term_fields' ), 10, 2 );
		add_action( 'created_pa_color', array( $this, 'save_term_fields' ) );
		add_action( 'edited_pa_color', array( $this, 'save_term_fields' ) );

		// Enqueue tooltip CSS.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Enqueue tooltip styles on single product pages.
	 *
	 * @return void
	 */
	public function enqueue_styles(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$css_file = AGGRESSIVE_APPAREL_DIR . '/build/styles/woocommerce/swatch-tooltips.css';
		if ( ! file_exists( $css_file ) ) {
			return;
		}

		wp_enqueue_style(
			'aggressive-apparel-swatch-tooltips',
			AGGRESSIVE_APPAREL_URI . '/build/styles/woocommerce/swatch-tooltips.css',
			array(),
			(string) filemtime( $css_file ),
		);
	}

	/**
	 * Inject data-tooltip attributes onto swatch elements.
	 *
	 * @param string $block_content Block HTML.
	 * @param array  $block         Block data.
	 * @return string Modified HTML.
	 */
	public function add_tooltip_data( string $block_content, array $block ): string {
		if ( ! isset( $block['blockName'] ) ||
			'woocommerce/add-to-cart-with-options-variation-selector-attribute-options' !== $block['blockName'] ) {
			return $block_content;
		}

		if ( false === strpos( $block_content, 'aggressive-apparel-color-swatch' ) ) {
			return $block_content;
		}

		// Find each swatch span and add tooltip data attribute.
		return (string) preg_replace_callback(
			'/data-color-name="([^"]+)"/',
			function ( $matches ) {
				$color_name = $matches[1];
				$term       = get_term_by( 'name', $color_name, 'pa_color' );
				if ( ! $term ) {
					return $matches[0];
				}

				$fabric = get_term_meta( $term->term_id, 'fabric_name', true );
				$comp   = get_term_meta( $term->term_id, 'fabric_composition', true );

				$tooltip = '';
				if ( $fabric ) {
					$tooltip .= esc_attr( $fabric );
				}
				if ( $comp ) {
					$tooltip .= $tooltip ? ' — ' . esc_attr( $comp ) : esc_attr( $comp );
				}

				if ( '' === $tooltip ) {
					return $matches[0];
				}

				return $matches[0] . ' data-tooltip="' . $tooltip . '"';
			},
			$block_content,
		);
	}

	/**
	 * Render fields when adding a new color term.
	 *
	 * @return void
	 */
	public function add_term_fields(): void {
		wp_nonce_field( 'aggressive_apparel_swatch_tooltips', 'aggressive_apparel_swatch_nonce' );
		echo '<div class="form-field">';
		echo '<label for="fabric_name">' . esc_html__( 'Fabric Name', 'aggressive-apparel' ) . '</label>';
		echo '<input type="text" name="fabric_name" id="fabric_name" />';
		echo '</div>';
		echo '<div class="form-field">';
		echo '<label for="fabric_composition">' . esc_html__( 'Fabric Composition', 'aggressive-apparel' ) . '</label>';
		echo '<input type="text" name="fabric_composition" id="fabric_composition" placeholder="95% Cotton, 5% Elastane" />';
		echo '</div>';
	}

	/**
	 * Render fields when editing an existing color term.
	 *
	 * @param \WP_Term $term     Current term.
	 * @param string   $taxonomy Taxonomy slug.
	 * @return void
	 */
	public function edit_term_fields( \WP_Term $term, string $taxonomy ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by WordPress hook signature.

		$fabric = get_term_meta( $term->term_id, 'fabric_name', true );
		$comp   = get_term_meta( $term->term_id, 'fabric_composition', true );

		wp_nonce_field( 'aggressive_apparel_swatch_tooltips', 'aggressive_apparel_swatch_nonce' );
		echo '<tr class="form-field">';
		echo '<th><label for="fabric_name">' . esc_html__( 'Fabric Name', 'aggressive-apparel' ) . '</label></th>';
		echo '<td><input type="text" name="fabric_name" id="fabric_name" value="' . esc_attr( $fabric ) . '" /></td>';
		echo '</tr>';
		echo '<tr class="form-field">';
		echo '<th><label for="fabric_composition">' . esc_html__( 'Fabric Composition', 'aggressive-apparel' ) . '</label></th>';
		echo '<td><input type="text" name="fabric_composition" id="fabric_composition" value="' . esc_attr( $comp ) . '" placeholder="95% Cotton, 5% Elastane" /></td>';
		echo '</tr>';
	}

	/**
	 * Save fabric meta fields.
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function save_term_fields( int $term_id ): void {
		if ( ! isset( $_POST['aggressive_apparel_swatch_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aggressive_apparel_swatch_nonce'] ) ), 'aggressive_apparel_swatch_tooltips' ) ) {
			return;
		}

		if ( isset( $_POST['fabric_name'] ) ) {
			update_term_meta( $term_id, 'fabric_name', sanitize_text_field( wp_unslash( $_POST['fabric_name'] ) ) );
		}
		if ( isset( $_POST['fabric_composition'] ) ) {
			update_term_meta( $term_id, 'fabric_composition', sanitize_text_field( wp_unslash( $_POST['fabric_composition'] ) ) );
		}
	}
}
