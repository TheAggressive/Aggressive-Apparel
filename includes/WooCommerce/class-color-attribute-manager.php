<?php
/**
 * Color Attribute Manager Class
 *
 * Manages WooCommerce product color attributes and terms
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Color Attribute Manager Class
 *
 * Handles the management of color options for WooCommerce product attributes.
 * Adds predefined color terms to the color attribute taxonomy.
 *
 * @since 1.0.0
 */
class Color_Attribute_Manager {

	/**
	 * Color attribute name
	 */
	private const ATTRIBUTE_NAME = 'pa_color';

	/**
	 * Color attribute label
	 */
	private const ATTRIBUTE_LABEL = 'Color';

	/**
	 * Data manager instance
	 *
	 * @var Color_Data_Manager
	 */
	private Color_Data_Manager $data_manager;

	/**
	 * Admin UI instance
	 *
	 * @var Color_Admin_UI
	 */
	private Color_Admin_UI $admin_ui;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->data_manager = new Color_Data_Manager();
		$this->admin_ui     = new Color_Admin_UI();
	}

	/**
	 * Initialize the color attribute manager
	 *
	 * @return void
	 */
	public function init(): void {
		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		add_action( 'init', array( $this, 'ensure_color_attribute_exists' ), 10 );
		add_action( 'init', array( $this, 'add_default_color_terms' ), 15 );
		add_filter( 'woocommerce_attribute_taxonomies', array( $this, 'register_color_attribute' ), 10, 1 );

		// Initialize admin UI.
		$this->admin_ui->register_hooks();
	}

	/**
	 * Ensure the color attribute exists in WooCommerce
	 *
	 * @return void
	 */
	public function ensure_color_attribute_exists(): void {
		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) || ! function_exists( 'wc_create_attribute' ) ) {
			return;
		}

		$attribute_name  = self::ATTRIBUTE_NAME;
		$attribute_label = self::ATTRIBUTE_LABEL;
		$attribute_slug  = str_replace( 'pa_', '', $attribute_name );

		// Check if color attribute exists using WooCommerce API.
		$existing_attributes = wc_get_attribute_taxonomies();
		$attribute_exists    = false;

		foreach ( $existing_attributes as $attribute ) {
			if ( $attribute->attribute_name === $attribute_slug ) {
				$attribute_exists = true;
				break;
			}
		}

		if ( ! $attribute_exists ) {
			// Create the WooCommerce attribute using API.
			$attribute_id = wc_create_attribute(
				array(
					'name'         => $attribute_label,
					'slug'         => $attribute_slug,
					'type'         => 'select',
					'order_by'     => 'menu_order',
					'has_archives' => true,
				)
			);

			if ( ! is_wp_error( $attribute_id ) ) {
				// Register the taxonomy.
				if ( ! taxonomy_exists( $attribute_name ) ) {
					register_taxonomy(
						$attribute_name,
						array( 'product' ),
						array(
							'hierarchical' => false,
							'label'        => $attribute_label,
							'public'       => true,
							'show_ui'      => true,
						)
					);
				}
			}
		}
	}

	/**
	 * Register color attribute with WooCommerce
	 *
	 * @param array $attribute_taxonomies Existing attribute taxonomies.
	 * @return array Modified attribute taxonomies.
	 */
	public function register_color_attribute( array $attribute_taxonomies ): array {
		$attribute_name  = str_replace( 'pa_', '', self::ATTRIBUTE_NAME );
		$attribute_label = self::ATTRIBUTE_LABEL;

		$attribute_taxonomies[] = (object) array(
			'attribute_id'      => 0, // Will be set by WooCommerce.
			'attribute_name'    => $attribute_name,
			'attribute_label'   => $attribute_label,
			'attribute_type'    => 'select',
			'attribute_orderby' => 'menu_order',
			'attribute_public'  => 1,
		);

		return $attribute_taxonomies;
	}

	/**
	 * Add default color terms
	 *
	 * @return void
	 */
	public function add_default_color_terms(): void {
		$this->data_manager->add_default_color_terms();
	}

	/**
	 * Get all color terms with their data
	 *
	 * @return array Array of color terms with data.
	 */
	public function get_color_terms(): array {
		return $this->data_manager->get_color_terms();
	}

	/**
	 * Add custom color term
	 *
	 * @param string $name   Color name.
	 * @param string $value  Color value.
	 * @param string $format Color format.
	 * @return int|\WP_Error Term ID on success, WP_Error on failure.
	 */
	public function add_custom_color( string $name, string $value, string $format = 'hex' ): int|\WP_Error {
		return $this->data_manager->add_custom_color( $name, $value, $format );
	}
}
