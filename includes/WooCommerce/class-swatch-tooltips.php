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
		// Color_Block_Swatch_Manager decorates every chip, server- or
		// browser-rendered, from this map.
		add_filter( 'aggressive_apparel_color_swatch_map', array( $this, 'add_tooltips_to_map' ) );

		// Admin: add extra fields to color term forms.
		add_action( 'pa_color_add_form_fields', array( $this, 'add_term_fields' ) );
		add_action( 'pa_color_edit_form_fields', array( $this, 'edit_term_fields' ), 10, 2 );
		add_action( 'created_pa_color', array( $this, 'save_term_fields' ) );
		add_action( 'edited_pa_color', array( $this, 'save_term_fields' ) );

		// Tooltip styling lives in color-swatches.css (always loaded on product
		// pages) so the colour-name tooltip works even when this optional feature
		// is off; this class only enriches it with fabric details.
	}

	/**
	 * Add each colour's tooltip to a colour selector's swatch map.
	 *
	 * @param array $map Swatch data keyed by term slug.
	 * @return array
	 */
	public function add_tooltips_to_map( array $map ): array {
		if ( array() === $map ) {
			return $map;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'pa_color',
				'slug'       => array_map( 'strval', array_keys( $map ) ),
				'hide_empty' => false,
			)
		);
		if ( ! is_array( $terms ) ) {
			return $map;
		}

		// get_terms() primes the term meta cache, so tooltip() doesn't query.
		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term && is_array( $map[ $term->slug ] ?? null ) ) {
				$map[ $term->slug ]['tooltip'] = self::tooltip( $term );
			}
		}

		return $map;
	}

	/**
	 * A colour's tooltip: its name, then any fabric name / composition.
	 *
	 * @param \WP_Term $term Colour term.
	 * @return string
	 */
	private static function tooltip( \WP_Term $term ): string {
		$details = implode(
			' · ',
			array_filter(
				array(
					(string) get_term_meta( $term->term_id, 'fabric_name', true ),
					(string) get_term_meta( $term->term_id, 'fabric_composition', true ),
				)
			)
		);

		return '' !== $details ? $term->name . ' — ' . $details : $term->name;
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
	public function edit_term_fields( \WP_Term $term, string $taxonomy ): void {

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
