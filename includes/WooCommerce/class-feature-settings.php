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
	 * Option key for the product filter layout.
	 *
	 * @var string
	 */
	public const FILTER_LAYOUT_OPTION = 'aggressive_apparel_filter_layout';

	/**
	 * Feature definitions with metadata.
	 *
	 * @return array<string, array{label: string, description: string, section: string}>
	 */
	public static function get_feature_definitions(): array {
		return array(
			'product_badges'             => array(
				'label'       => __( 'Product Badges', 'aggressive-apparel' ),
				'description' => __( 'Show sale percentage, "New", "Low Stock", and "Bestseller" badges on product cards.', 'aggressive-apparel' ),
				'section'     => 'server',
			),
			'price_display'              => array(
				'label'       => __( 'Smart Price Display', 'aggressive-apparel' ),
				'description' => __( 'Show "From $X" on archives, "Save X%" on sale items.', 'aggressive-apparel' ),
				'section'     => 'server',
			),
			'product_tabs'               => array(
				'label'       => __( 'Product Tabs Manager', 'aggressive-apparel' ),
				'description' => __( 'Replace default WooCommerce tabs with 4 display styles (accordion, inline, modern tabs, scrollspy) and add custom tabs.', 'aggressive-apparel' ),
				'section'     => 'server',
			),
			'free_shipping_bar'          => array(
				'label'       => __( 'Free Shipping Progress Bar', 'aggressive-apparel' ),
				'description' => __( 'Show progress toward free shipping threshold in the cart.', 'aggressive-apparel' ),
				'section'     => 'server',
			),
			'swatch_tooltips'            => array(
				'label'       => __( 'Swatch Tooltips', 'aggressive-apparel' ),
				'description' => __( 'Show fabric name and composition on color swatch hover.', 'aggressive-apparel' ),
				'section'     => 'css',
			),
			'mini_cart_styling'          => array(
				'label'       => __( 'Mini Cart Styling', 'aggressive-apparel' ),
				'description' => __( 'Style the native WooCommerce mini-cart to match the theme design.', 'aggressive-apparel' ),
				'section'     => 'css',
			),
			'product_filters'            => array(
				'label'       => __( 'Product Filters', 'aggressive-apparel' ),
				'description' => __( 'AJAX product filters with categories, color swatches, sizes, price range, and stock status.', 'aggressive-apparel' ),
				'section'     => 'interactive',
			),
			'page_transitions'           => array(
				'label'       => __( 'Page Transitions', 'aggressive-apparel' ),
				'description' => __( 'Smooth crossfade between pages with product image morphing (Chrome/Safari).', 'aggressive-apparel' ),
				'section'     => 'css',
			),
			'size_guide'                 => array(
				'label'       => __( 'Size Guide', 'aggressive-apparel' ),
				'description' => __( 'Manage reusable size guides and assign them to products or categories.', 'aggressive-apparel' ),
				'section'     => 'interactive',
			),
			'countdown_timer'            => array(
				'label'       => __( 'Sale Countdown Timer', 'aggressive-apparel' ),
				'description' => __( 'Live countdown for products with scheduled sale end dates.', 'aggressive-apparel' ),
				'section'     => 'interactive',
			),
			'recently_viewed'            => array(
				'label'       => __( 'Recently Viewed Products', 'aggressive-apparel' ),
				'description' => __( 'Show customers their recently viewed products using browser storage.', 'aggressive-apparel' ),
				'section'     => 'interactive',
			),
			'predictive_search'          => array(
				'label'       => __( 'Predictive Search', 'aggressive-apparel' ),
				'description' => __( 'Show live product search results with thumbnails and prices as users type.', 'aggressive-apparel' ),
				'section'     => 'interactive',
			),
			'sticky_add_to_cart'         => array(
				'label'       => __( 'Sticky Add to Cart', 'aggressive-apparel' ),
				'description' => __( 'Fixed bar with product info and add-to-cart when main button scrolls out of view.', 'aggressive-apparel' ),
				'section'     => 'interactive',
			),
			'mobile_bottom_nav'          => array(
				'label'       => __( 'Mobile Bottom Navigation', 'aggressive-apparel' ),
				'description' => __( 'Fixed bottom bar on mobile with Home, Search, Cart, and Account.', 'aggressive-apparel' ),
				'section'     => 'interactive',
			),
			'stock_status'               => array(
				'label'       => __( 'Stock Status', 'aggressive-apparel' ),
				'description' => __( 'Show stock availability indicator (In Stock, Low Stock, Out of Stock) in Quick View.', 'aggressive-apparel' ),
				'section'     => 'rich',
			),
			'quick_view'                 => array(
				'label'       => __( 'Quick View', 'aggressive-apparel' ),
				'description' => __( 'Preview products in a modal overlay from shop pages.', 'aggressive-apparel' ),
				'section'     => 'rich',
			),
			'wishlist'                   => array(
				'label'       => __( 'Wishlist', 'aggressive-apparel' ),
				'description' => __( 'Save-for-later with heart icon toggle and dedicated wishlist page.', 'aggressive-apparel' ),
				'section'     => 'rich',
			),
			'social_proof'               => array(
				'label'       => __( 'Social Proof Notifications', 'aggressive-apparel' ),
				'description' => __( 'Show recent purchase toast notifications to build urgency.', 'aggressive-apparel' ),
				'section'     => 'rich',
			),
			'frequently_bought_together' => array(
				'label'       => __( 'Frequently Bought Together', 'aggressive-apparel' ),
				'description' => __( 'Show recommended products with checkboxes and combined add-to-cart on product pages.', 'aggressive-apparel' ),
				'section'     => 'rich',
			),
			'back_in_stock'              => array(
				'label'       => __( 'Back in Stock Notifications', 'aggressive-apparel' ),
				'description' => __( 'Let customers subscribe to out-of-stock products and get notified when restocked.', 'aggressive-apparel' ),
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

		// Product filter layout sub-setting.
		register_setting(
			self::SETTINGS_GROUP,
			self::FILTER_LAYOUT_OPTION,
			array(
				'type'              => 'string',
				'default'           => 'drawer',
				'sanitize_callback' => array( $this, 'sanitize_filter_layout' ),
			)
		);

		add_settings_field(
			'filter_layout',
			__( 'Filter Layout', 'aggressive-apparel' ),
			array( $this, 'render_filter_layout_field' ),
			self::PAGE_SLUG,
			'aggressive_apparel_features_interactive',
		);
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
	 * Sanitize the filter layout option.
	 *
	 * @param mixed $input Raw input.
	 * @return string Sanitized layout value.
	 */
	public function sanitize_filter_layout( $input ): string {
		$valid = array( 'drawer', 'sidebar', 'horizontal' );
		return in_array( $input, $valid, true ) ? $input : 'drawer';
	}

	/**
	 * Render the filter layout select field.
	 *
	 * @return void
	 */
	public function render_filter_layout_field(): void {
		$layout  = get_option( self::FILTER_LAYOUT_OPTION, 'drawer' );
		$options = array(
			'drawer'     => __( 'Drawer (slide-out panel)', 'aggressive-apparel' ),
			'sidebar'    => __( 'Sidebar (persistent column)', 'aggressive-apparel' ),
			'horizontal' => __( 'Horizontal Bar (dropdown filters)', 'aggressive-apparel' ),
		);

		printf( '<select name="%s">', esc_attr( self::FILTER_LAYOUT_OPTION ) );
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $value ),
				selected( $layout, $value, false ),
				esc_html( $label ),
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Choose how filters are displayed on shop pages. Sidebar and Horizontal Bar fall back to Drawer on mobile.', 'aggressive-apparel' ) . '</p>';
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
