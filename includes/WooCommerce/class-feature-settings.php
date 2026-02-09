<?php
/**
 * Feature Settings Class
 *
 * Manages WooCommerce enhancement feature toggles.
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
 * Feature Settings Class
 *
 * Provides a settings page and helper to toggle individual WooCommerce
 * enhancements on or off. Stores all flags in a single option row.
 *
 * @since 1.17.0
 */
class Feature_Settings {

	/**
	 * Option key for all feature flags.
	 *
	 * @var string
	 */
	public const OPTION_KEY = 'aggressive_apparel_wc_features';

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	private const PAGE_SLUG = 'aggressive-apparel-features';

	/**
	 * Settings group name.
	 *
	 * @var string
	 */
	private const SETTINGS_GROUP = 'aggressive_apparel_features_group';

	/**
	 * Feature definitions with metadata.
	 *
	 * @return array<string, array{label: string, description: string, section: string}>
	 */
	public static function get_feature_definitions(): array {
		return array(
			'product_badges'    => array(
				'label'       => __( 'Product Badges', 'aggressive-apparel' ),
				'description' => __( 'Show sale percentage, "New", "Low Stock", and "Bestseller" badges on product cards.', 'aggressive-apparel' ),
				'section'     => 'server',
			),
			'price_display'     => array(
				'label'       => __( 'Smart Price Display', 'aggressive-apparel' ),
				'description' => __( 'Show "From $X" on archives, "Save X%" on sale items.', 'aggressive-apparel' ),
				'section'     => 'server',
			),
			'product_tabs'      => array(
				'label'       => __( 'Product Tabs Manager', 'aggressive-apparel' ),
				'description' => __( 'Add custom tabs (Care Instructions, Shipping, Sustainability) to product pages.', 'aggressive-apparel' ),
				'section'     => 'server',
			),
			'free_shipping_bar' => array(
				'label'       => __( 'Free Shipping Progress Bar', 'aggressive-apparel' ),
				'description' => __( 'Show progress toward free shipping threshold in the cart.', 'aggressive-apparel' ),
				'section'     => 'server',
			),
			'swatch_tooltips'   => array(
				'label'       => __( 'Swatch Tooltips', 'aggressive-apparel' ),
				'description' => __( 'Show fabric name and composition on color swatch hover.', 'aggressive-apparel' ),
				'section'     => 'css',
			),
			'mini_cart_styling' => array(
				'label'       => __( 'Mini Cart Styling', 'aggressive-apparel' ),
				'description' => __( 'Style the native WooCommerce mini-cart to match the theme design.', 'aggressive-apparel' ),
				'section'     => 'css',
			),
			'filter_styling'    => array(
				'label'       => __( 'Product Filter Styling', 'aggressive-apparel' ),
				'description' => __( 'Style native product filter blocks with color swatch integration.', 'aggressive-apparel' ),
				'section'     => 'css',
			),
			'size_guide'        => array(
				'label'       => __( 'Size Guide', 'aggressive-apparel' ),
				'description' => __( 'Per-product or per-category measurement chart modal.', 'aggressive-apparel' ),
				'section'     => 'interactive',
			),
			'countdown_timer'   => array(
				'label'       => __( 'Sale Countdown Timer', 'aggressive-apparel' ),
				'description' => __( 'Live countdown for products with scheduled sale end dates.', 'aggressive-apparel' ),
				'section'     => 'interactive',
			),
			'recently_viewed'   => array(
				'label'       => __( 'Recently Viewed Products', 'aggressive-apparel' ),
				'description' => __( 'Show customers their recently viewed products using browser storage.', 'aggressive-apparel' ),
				'section'     => 'interactive',
			),
			'quick_view'        => array(
				'label'       => __( 'Quick View', 'aggressive-apparel' ),
				'description' => __( 'Preview products in a modal overlay from shop pages.', 'aggressive-apparel' ),
				'section'     => 'rich',
			),
			'wishlist'          => array(
				'label'       => __( 'Wishlist', 'aggressive-apparel' ),
				'description' => __( 'Save-for-later with heart icon toggle and dedicated wishlist page.', 'aggressive-apparel' ),
				'section'     => 'rich',
			),
			'social_proof'      => array(
				'label'       => __( 'Social Proof Notifications', 'aggressive-apparel' ),
				'description' => __( 'Show recent purchase toast notifications to build urgency.', 'aggressive-apparel' ),
				'section'     => 'rich',
			),
		);
	}

	/**
	 * Initialize settings hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add the settings page under Appearance.
	 *
	 * @return void
	 */
	public function add_settings_page(): void {
		add_theme_page(
			__( 'Store Enhancements', 'aggressive-apparel' ),
			__( 'Store Enhancements', 'aggressive-apparel' ),
			'edit_theme_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' ),
		);
	}

	/**
	 * Register the single option and settings sections.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_features' ),
			)
		);

		$sections = array(
			'server'      => __( 'Server-Side Features', 'aggressive-apparel' ),
			'css'         => __( 'Style Enhancements', 'aggressive-apparel' ),
			'interactive' => __( 'Interactive Features', 'aggressive-apparel' ),
			'rich'        => __( 'Rich Interactivity', 'aggressive-apparel' ),
		);

		foreach ( $sections as $id => $title ) {
			add_settings_section(
				'aggressive_apparel_features_' . $id,
				$title,
				'__return_false',
				self::PAGE_SLUG,
			);
		}

		foreach ( self::get_feature_definitions() as $key => $feature ) {
			add_settings_field(
				'feature_' . $key,
				$feature['label'],
				array( $this, 'render_toggle_field' ),
				self::PAGE_SLUG,
				'aggressive_apparel_features_' . $feature['section'],
				array(
					'key'         => $key,
					'description' => $feature['description'],
				),
			);
		}
	}

	/**
	 * Sanitize the feature flags array.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, bool> Sanitized flags.
	 */
	public function sanitize_features( $input ): array {
		$valid     = array_keys( self::get_feature_definitions() );
		$sanitized = array();

		foreach ( $valid as $key ) {
			$sanitized[ $key ] = ! empty( $input[ $key ] );
		}

		return $sanitized;
	}

	/**
	 * Render a single toggle checkbox field.
	 *
	 * @param array $args Field arguments containing key and description.
	 * @return void
	 */
	public function render_toggle_field( array $args ): void {
		$key     = $args['key'];
		$enabled = self::is_enabled( $key );

		printf(
			'<label><input type="checkbox" name="%s[%s]" value="1" %s /> %s</label>',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $key ),
			checked( $enabled, true, false ),
			esc_html( $args['description'] )
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		echo '<p>' . esc_html__( 'Enable or disable individual WooCommerce enhancements. Disabled features have zero performance overhead.', 'aggressive-apparel' ) . '</p>';
		echo '<form method="post" action="options.php">';

		settings_fields( self::SETTINGS_GROUP );
		do_settings_sections( self::PAGE_SLUG );
		submit_button( __( 'Save Changes', 'aggressive-apparel' ) );

		echo '</form>';
		echo '</div>';
	}

	/**
	 * Check whether a specific feature is enabled.
	 *
	 * All features default to OFF. The admin must explicitly enable
	 * each feature via Appearance → Store Enhancements.
	 *
	 * @param string $feature Feature key.
	 * @return bool True if enabled.
	 */
	public static function is_enabled( string $feature ): bool {
		$options = get_option( self::OPTION_KEY, array() );

		return ! empty( $options[ $feature ] );
	}
}
