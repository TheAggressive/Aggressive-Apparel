<?php
/**
 * Color Attribute Manager Class
 *
 * Manages WooCommerce product color attributes and terms
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

declare(strict_types=1);

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
	 * Option used to record the completed color-data setup version.
	 */
	public const SETUP_VERSION_OPTION = 'aggressive_apparel_color_setup_version';

	/**
	 * Bump when the color attribute or seeded term schema changes.
	 */
	private const SETUP_VERSION = '1.0.0';

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
	 * Color Pattern Admin instance
	 *
	 * @var Color_Pattern_Admin
	 */
	private Color_Pattern_Admin $pattern_admin;

	/**
	 * Constructor
	 *
	 * @param Color_Pattern_Admin $pattern_admin Shared pattern admin service.
	 */
	public function __construct( Color_Pattern_Admin $pattern_admin ) {
		$this->data_manager  = new Color_Data_Manager();
		$this->pattern_admin = $pattern_admin;
		$this->admin_ui      = new Color_Admin_UI( $this->pattern_admin );
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
		// Database provisioning belongs to the admin lifecycle. Running these
		// checks on `init` added direct SQL and term lookups to every storefront
		// request, even after setup had completed successfully.
		add_action( 'admin_init', array( $this, 'maybe_run_setup' ), 5 );
		add_filter( 'woocommerce_attribute_taxonomies', array( $this, 'register_color_attribute' ), 10, 1 );

		$this->pattern_admin->register_hooks();

		// Initialize admin UI.
		$this->admin_ui->register_hooks();
	}

	/**
	 * Provision the color attribute and default terms once per setup version.
	 *
	 * This runs only during admin requests. If provisioning fails, the version
	 * marker is deliberately left untouched so a later admin request can retry.
	 *
	 * @return void
	 */
	public function maybe_run_setup(): void {
		if ( get_option( self::SETUP_VERSION_OPTION ) === self::SETUP_VERSION ) {
			return;
		}

		$this->ensure_color_attribute_exists();

		if ( ! $this->color_attribute_exists() ) {
			return;
		}

		$this->add_default_color_terms();

		if ( ! $this->default_color_terms_exist() ) {
			return;
		}

		update_option( self::SETUP_VERSION_OPTION, self::SETUP_VERSION );
	}

	/**
	 * Ensure the color attribute exists in WooCommerce
	 *
	 * @return void
	 */
	public function ensure_color_attribute_exists(): void {
		if ( ! function_exists( 'wc_create_attribute' ) ) {
			return;
		}

		$attribute_name  = self::ATTRIBUTE_NAME;
		$attribute_label = self::ATTRIBUTE_LABEL;
		$attribute_slug  = str_replace( 'pa_', '', $attribute_name );

		if ( ! $this->color_attribute_exists() ) {
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
	 * Check the stored WooCommerce attribute table without applying the theme's
	 * virtual attribute filter.
	 *
	 * @return bool Whether the color attribute exists in the database.
	 */
	private function color_attribute_exists(): bool {
		global $wpdb;

		$attribute_slug = str_replace( 'pa_', '', self::ATTRIBUTE_NAME );

		// Direct SQL is intentional here: the filtered WooCommerce attribute list
		// always includes the theme's virtual fallback. This method is now called
		// only by the versioned admin setup path, never on storefront requests.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$attribute_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s LIMIT 1",
				$attribute_slug
			)
		);

		return ! empty( $attribute_id );
	}

	/**
	 * Verify the required seed data before recording setup as complete.
	 *
	 * @return bool Whether the default black color term is ready for use.
	 */
	private function default_color_terms_exist(): bool {
		$term = get_term_by( 'slug', 'black', self::ATTRIBUTE_NAME );

		if ( ! $term instanceof \WP_Term ) {
			return false;
		}

		return '#000000' === get_term_meta( $term->term_id, 'color_value', true );
	}

	/**
	 * Register color attribute with WooCommerce
	 *
	 * @param array $attribute_taxonomies Existing attribute taxonomies.
	 * @return array Modified attribute taxonomies.
	 */
	public function register_color_attribute( array $attribute_taxonomies ): array {
		$attribute_slug  = str_replace( 'pa_', '', self::ATTRIBUTE_NAME );
		$attribute_label = self::ATTRIBUTE_LABEL;

		// Don't add a duplicate if the attribute already exists in the
		// list (e.g. from the database). A duplicate with attribute_id 0
		// confuses WooCommerce's REST API validation and breaks imports
		// from services like Printful.
		foreach ( $attribute_taxonomies as $attr ) {
			if ( isset( $attr->attribute_name ) && $attr->attribute_name === $attribute_slug ) {
				return $attribute_taxonomies;
			}
		}

		$attribute_taxonomies[] = (object) array(
			'attribute_id'      => 0,
			'attribute_name'    => $attribute_slug,
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
