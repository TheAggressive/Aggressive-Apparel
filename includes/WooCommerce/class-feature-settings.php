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

use Aggressive_Apparel\Core\Icons;

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
	 * Option key for the load more mode.
	 *
	 * @var string
	 */
	public const LOAD_MORE_MODE_OPTION = 'aggressive_apparel_load_more_mode';

	/**
	 * Option key for the filter trigger placement.
	 *
	 * `auto`  → Theme injects the trigger before the catalog sorting dropdown
	 *           (legacy behavior).
	 * `block` → Theme suppresses the automatic injection; the user is expected
	 *           to place the `aggressive-apparel/filter-toggle` block wherever
	 *           they want the trigger to appear.
	 *
	 * @var string
	 */
	public const FILTER_TRIGGER_PLACEMENT_OPTION = 'aggressive_apparel_filter_trigger_placement';

	/**
	 * Option key for the wishlist button placement.
	 *
	 * `auto`  → Theme injects the heart on product cards (top-right of the
	 *           featured image) and on the single product summary
	 *           (above the title) automatically (legacy behavior).
	 * `block` → Theme suppresses both auto-injections; the user is
	 *           expected to place the `aggressive-apparel/wishlist-button`
	 *           block wherever they want the heart to appear.
	 *
	 * @var string
	 */
	public const WISHLIST_BUTTON_PLACEMENT_OPTION = 'aggressive_apparel_wishlist_button_placement';

	/**
	 * Option key for the social proof source mix.
	 *
	 * Stored as an associative array of `source_key => weight (int 0–10)`.
	 * Weight 0 disables a source. Higher weights make a source appear more
	 * often in the rotation. Recognised source keys:
	 *
	 *   - `trust`         → admin-edited trust messages
	 *   - `purchases`     → real anonymized recent orders
	 *   - `announcements` → admin-edited promotional / seasonal messages
	 *
	 * @var string
	 */
	public const SOCIAL_PROOF_SOURCES_OPTION = 'aggressive_apparel_social_proof_sources';

	/**
	 * Option key for the trust messages list.
	 *
	 * Stored as a single string with one message per line. Empty lines and
	 * lines starting with `#` are ignored at render time so admins can
	 * leave comments / blank groupings.
	 *
	 * @var string
	 */
	public const SOCIAL_PROOF_TRUST_MESSAGES_OPTION = 'aggressive_apparel_social_proof_trust_messages';

	/**
	 * Option key for the custom announcements list.
	 *
	 * Same parsing rules as trust messages. Intended for short-term
	 * promos / seasonal copy you want to manage separately from the
	 * always-on Trust Messages list.
	 *
	 * @var string
	 */
	public const SOCIAL_PROOF_ANNOUNCEMENTS_OPTION = 'aggressive_apparel_social_proof_announcements';

	/**
	 * Option key for the admin-only demo preview toggle.
	 *
	 * When true AND the current viewer has `edit_theme_options`, the
	 * social proof toast renders a sample notification first so the
	 * admin can preview the design without waiting for real orders.
	 * Customers never see this even when the toggle is on.
	 *
	 * @var string
	 */
	public const SOCIAL_PROOF_DEMO_OPTION = 'aggressive_apparel_social_proof_demo';

	/**
	 * Option key for the minimum order age (minutes) before a real
	 * purchase is eligible for the social proof rotation.
	 *
	 * Defaults to 5 minutes. Higher values make individual purchases
	 * harder to cross-reference (city + product + exact time can
	 * uniquely identify a customer in small markets).
	 *
	 * @var string
	 */
	public const SOCIAL_PROOF_MIN_ORDER_AGE_OPTION = 'aggressive_apparel_social_proof_min_order_age';

	/**
	 * Option key for the social proof display mode.
	 *
	 *   - `anonymous`  → "Someone in [Location] purchased X" (default, recommended)
	 *   - `initial`    → "S. in [Location] purchased X"
	 *   - `first_name` → "Sarah from [Location] purchased X"
	 *
	 * @var string
	 */
	public const SOCIAL_PROOF_DISPLAY_MODE_OPTION = 'aggressive_apparel_social_proof_display_mode';

	/**
	 * Option key for how much location granularity to expose.
	 *
	 *   - `city`    → "Portland" (default)
	 *   - `state`   → "Oregon"
	 *   - `country` → "United States"
	 *   - `hidden`  → no location at all
	 *
	 * @var string
	 */
	public const SOCIAL_PROOF_LOCATION_GRANULARITY_OPTION = 'aggressive_apparel_social_proof_location_granularity';

	/**
	 * Optional theme icon slug shown as a badge on purchase / demo thumbnails.
	 *
	 * Empty string disables the badge after saving "None".
	 *
	 * If the option has never been saved yet, Social Proof treats the slug as
	 * `SOCIAL_PROOF_PURCHASE_BADGE_FALLBACK_SLUG` instead of hiding the badge.
	 * Trust messages and announcements use PREFIX| lines in textareas instead.
	 *
	 * @var string
	 */
	public const SOCIAL_PROOF_PURCHASE_BADGE_ICON_OPTION = 'aggressive_apparel_social_proof_purchase_badge_icon';

	/**
	 * Default badge slug on first load when the merchant has not saved Features yet.
	 *
	 * @var string
	 */
	public const SOCIAL_PROOF_PURCHASE_BADGE_FALLBACK_SLUG = 'check';

	/**
	 * Minimum lifetime unit sales (`WC_Product::get_total_sales()`) required
	 * before a product can surface in Engagement rotation.
	 *
	 * Honest POD guardrail — avoids calling one-off orders a "favorite".
	 *
	 * @var string
	 */
	public const SOCIAL_PROOF_ENGAGEMENT_MIN_SALES_OPTION = 'aggressive_apparel_social_proof_engagement_min_sales';

	/**
	 * Default trust messages shipped with new installs.
	 *
	 * Intentionally generic and true for any POD apparel brand — no
	 * location claims (production may vary), no specific blank brands,
	 * no dollar amounts (shipping policy is store-specific).
	 *
	 * @var string
	 */
	private const SOCIAL_PROOF_DEFAULT_TRUST_MESSAGES = "check|Made to order with care\ninfo|Print quality guaranteed\nheart|Premium quality materials\ninfo|Honest sizing guide on every product\nhome|Independent brand\ngrid-view|Designed with attention to detail\ncheck|Quality you can feel";

	/**
	 * Settings page sections with tab metadata.
	 *
	 * @var array<string, array{label: string, icon: string}>
	 */
	private const SECTIONS = array(
		'catalog'      => array(
			'label' => 'Catalog & Browsing',
			'icon'  => 'dashicons-store',
		),
		'product'      => array(
			'label' => 'Product Page',
			'icon'  => 'dashicons-products',
		),
		'cart'         => array(
			'label' => 'Cart & Mini Cart',
			'icon'  => 'dashicons-cart',
		),
		'engagement'   => array(
			'label' => 'Customer Engagement',
			'icon'  => 'dashicons-groups',
		),
		'ui'           => array(
			'label' => 'Mobile & UI',
			'icon'  => 'dashicons-smartphone',
		),
		'experimental' => array(
			'label' => 'Experimental',
			'icon'  => 'dashicons-admin-tools',
		),
	);

	/**
	 * Feature definitions with metadata.
	 *
	 * @return array<string, array{label: string, description: string, section: string}>
	 */
	public static function get_feature_definitions(): array {
		return array(
			// ── Catalog & Browsing ──────────────────────────────.
			'product_badges'             => array(
				'label'       => __( 'Product Badges', 'aggressive-apparel' ),
				'description' => __( 'Show sale percentage, "New", "Low Stock", and "Bestseller" badges on product cards.', 'aggressive-apparel' ),
				'section'     => 'catalog',
			),
			'price_display'              => array(
				'label'       => __( 'Smart Price Display', 'aggressive-apparel' ),
				'description' => __( 'Show "From $X" on archives, "Save X%" on sale items.', 'aggressive-apparel' ),
				'section'     => 'catalog',
			),
			'advanced_sorting'           => array(
				'label'       => __( 'Advanced Sorting Options', 'aggressive-apparel' ),
				'description' => __( 'Add Featured, Biggest Savings, and A-Z/Z-A sorting to the product catalog.', 'aggressive-apparel' ),
				'section'     => 'catalog',
			),
			'grid_list_toggle'           => array(
				'label'       => __( 'Grid/List View Toggle', 'aggressive-apparel' ),
				'description' => __( 'Toggle between grid and list view on shop archive pages.', 'aggressive-apparel' ),
				'section'     => 'catalog',
			),
			'product_filters'            => array(
				'label'       => __( 'Product Filters', 'aggressive-apparel' ),
				'description' => __( 'AJAX product filters with categories, color swatches, sizes, price range, and stock status.', 'aggressive-apparel' ),
				'section'     => 'catalog',
			),
			'load_more'                  => array(
				'label'       => __( 'Load More / Infinite Scroll', 'aggressive-apparel' ),
				'description' => __( 'Replace pagination with a Load More button or automatic infinite scroll.', 'aggressive-apparel' ),
				'section'     => 'catalog',
			),
			'predictive_search'          => array(
				'label'       => __( 'Predictive Search', 'aggressive-apparel' ),
				'description' => __( 'Show live product search results with thumbnails and prices as users type.', 'aggressive-apparel' ),
				'section'     => 'catalog',
			),
			'page_transitions'           => array(
				'label'       => __( 'Page Transitions', 'aggressive-apparel' ),
				'description' => __( 'Smooth crossfade between pages with product image morphing (Chrome/Safari).', 'aggressive-apparel' ),
				'section'     => 'catalog',
			),

			// ── Product Page ────────────────────────────────────.
			'product_tabs'               => array(
				'label'       => __( 'Product Tabs Manager', 'aggressive-apparel' ),
				'description' => __( 'Replace default WooCommerce tabs with 4 display styles (accordion, inline, modern tabs, scrollspy) and add custom tabs.', 'aggressive-apparel' ),
				'section'     => 'product',
			),
			'size_guide'                 => array(
				'label'       => __( 'Size Guide', 'aggressive-apparel' ),
				'description' => __( 'Manage reusable size guides and assign them to products or categories.', 'aggressive-apparel' ),
				'section'     => 'product',
			),
			'countdown_timer'            => array(
				'label'       => __( 'Sale Countdown Timer', 'aggressive-apparel' ),
				'description' => __( 'Live countdown for products with scheduled sale end dates.', 'aggressive-apparel' ),
				'section'     => 'product',
			),
			'sticky_add_to_cart'         => array(
				'label'       => __( 'Sticky Add to Cart', 'aggressive-apparel' ),
				'description' => __( 'Fixed bar with product info and add-to-cart when main button scrolls out of view.', 'aggressive-apparel' ),
				'section'     => 'product',
			),
			'stock_status'               => array(
				'label'       => __( 'Stock Status', 'aggressive-apparel' ),
				'description' => __( 'Show stock availability indicator (In Stock, Low Stock, Out of Stock) in Quick View.', 'aggressive-apparel' ),
				'section'     => 'product',
			),
			'quick_view'                 => array(
				'label'       => __( 'Quick View', 'aggressive-apparel' ),
				'description' => __( 'Preview products in a modal overlay from shop pages.', 'aggressive-apparel' ),
				'section'     => 'product',
			),
			'frequently_bought_together' => array(
				'label'       => __( 'Frequently Bought Together', 'aggressive-apparel' ),
				'description' => __( 'Show recommended products with checkboxes and combined add-to-cart on product pages.', 'aggressive-apparel' ),
				'section'     => 'product',
			),

			// ── Cart & Mini Cart ────────────────────────────────.
			'free_shipping_bar'          => array(
				'label'       => __( 'Free Shipping Progress Bar', 'aggressive-apparel' ),
				'description' => __( 'Show progress toward free shipping threshold in the cart.', 'aggressive-apparel' ),
				'section'     => 'cart',
			),
			'mini_cart_styling'          => array(
				'label'       => __( 'Mini Cart Styling', 'aggressive-apparel' ),
				'description' => __( 'Style the native WooCommerce mini-cart to match the theme design.', 'aggressive-apparel' ),
				'section'     => 'cart',
			),

			// ── Customer Engagement ─────────────────────────────.
			'recently_viewed'            => array(
				'label'       => __( 'Recently Viewed Products', 'aggressive-apparel' ),
				'description' => __( 'Show customers their recently viewed products using browser storage.', 'aggressive-apparel' ),
				'section'     => 'engagement',
			),
			'wishlist'                   => array(
				'label'       => __( 'Wishlist', 'aggressive-apparel' ),
				'description' => __( 'Save-for-later with heart icon toggle and dedicated wishlist page.', 'aggressive-apparel' ),
				'section'     => 'engagement',
			),
			'social_proof'               => array(
				'label'       => __( 'Social Proof Notifications', 'aggressive-apparel' ),
				'description' => __( 'Show recent purchase toast notifications to build urgency.', 'aggressive-apparel' ),
				'section'     => 'engagement',
			),
			'back_in_stock'              => array(
				'label'       => __( 'Back in Stock Notifications', 'aggressive-apparel' ),
				'description' => __( 'Let customers subscribe to out-of-stock products and get notified when restocked.', 'aggressive-apparel' ),
				'section'     => 'engagement',
			),
			'exit_intent'                => array(
				'label'       => __( 'Exit Intent Email Capture', 'aggressive-apparel' ),
				'description' => __( 'Show an email signup popup when visitors are about to leave. Configurable text and re-show interval.', 'aggressive-apparel' ),
				'section'     => 'engagement',
			),

			// ── Mobile & UI ─────────────────────────────────────.
			'swatch_tooltips'            => array(
				'label'       => __( 'Swatch Tooltips', 'aggressive-apparel' ),
				'description' => __( 'Show fabric name and composition on color swatch hover.', 'aggressive-apparel' ),
				'section'     => 'ui',
			),
			'mobile_bottom_nav'          => array(
				'label'       => __( 'Mobile Bottom Navigation', 'aggressive-apparel' ),
				'description' => __( 'Fixed bottom bar on mobile with Home, Search, Cart, and Account.', 'aggressive-apparel' ),
				'section'     => 'ui',
			),

			// ── Experimental ────────────────────────────────────.
			'adaptive_colors'            => array(
				'label'       => __( 'Adaptive Colors', 'aggressive-apparel' ),
				'description' => __( 'Per-block light/dark color overrides and auto-generated adaptive palette using CSS light-dark().', 'aggressive-apparel' ),
				'section'     => 'experimental',
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
		$hook = add_theme_page(
			__( 'Store Enhancements', 'aggressive-apparel' ),
			__( 'Store Enhancements', 'aggressive-apparel' ),
			'edit_theme_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' ),
		);

		if ( $hook ) {
			add_action( 'admin_print_styles-' . $hook, array( $this, 'enqueue_admin_styles' ) );
		}
	}

	/**
	 * Enqueue admin styles for the settings page.
	 *
	 * @return void
	 */
	public function enqueue_admin_styles(): void {
		$css_file = AGGRESSIVE_APPAREL_DIR . '/build/styles/admin/store-enhancements-admin.css';
		if ( ! file_exists( $css_file ) ) {
			return;
		}

		wp_enqueue_style(
			'aggressive-apparel-store-enhancements-admin',
			AGGRESSIVE_APPAREL_URI . '/build/styles/admin/store-enhancements-admin.css',
			array(),
			(string) filemtime( $css_file ),
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

		foreach ( self::SECTIONS as $id => $meta ) {
			add_settings_section(
				'aggressive_apparel_features_' . $id,
				$meta['label'],
				'__return_false',
				self::PAGE_SLUG,
			);
		}

		// Register sub-setting options (always, so saved values persist).
		register_setting(
			self::SETTINGS_GROUP,
			self::FILTER_LAYOUT_OPTION,
			array(
				'type'              => 'string',
				'default'           => 'drawer',
				'sanitize_callback' => array( $this, 'sanitize_filter_layout' ),
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::LOAD_MORE_MODE_OPTION,
			array(
				'type'              => 'string',
				'default'           => 'load_more',
				'sanitize_callback' => array( $this, 'sanitize_load_more_mode' ),
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::FILTER_TRIGGER_PLACEMENT_OPTION,
			array(
				'type'              => 'string',
				'default'           => 'auto',
				'sanitize_callback' => array( $this, 'sanitize_filter_trigger_placement' ),
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::WISHLIST_BUTTON_PLACEMENT_OPTION,
			array(
				'type'              => 'string',
				'default'           => 'auto',
				'sanitize_callback' => array( $this, 'sanitize_wishlist_button_placement' ),
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::SOCIAL_PROOF_SOURCES_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_social_proof_sources' ),
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::SOCIAL_PROOF_TRUST_MESSAGES_OPTION,
			array(
				'type'              => 'string',
				'default'           => self::SOCIAL_PROOF_DEFAULT_TRUST_MESSAGES,
				'sanitize_callback' => array( $this, 'sanitize_social_proof_messages' ),
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::SOCIAL_PROOF_ANNOUNCEMENTS_OPTION,
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => array( $this, 'sanitize_social_proof_messages' ),
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::SOCIAL_PROOF_DEMO_OPTION,
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => static fn ( $v ): bool => (bool) $v,
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::SOCIAL_PROOF_MIN_ORDER_AGE_OPTION,
			array(
				'type'              => 'integer',
				'default'           => 5,
				'sanitize_callback' => static fn ( $v ): int => max( 0, min( 1440, (int) $v ) ),
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::SOCIAL_PROOF_DISPLAY_MODE_OPTION,
			array(
				'type'              => 'string',
				'default'           => 'anonymous',
				'sanitize_callback' => array( $this, 'sanitize_social_proof_display_mode' ),
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::SOCIAL_PROOF_LOCATION_GRANULARITY_OPTION,
			array(
				'type'              => 'string',
				'default'           => 'city',
				'sanitize_callback' => array( $this, 'sanitize_social_proof_location_granularity' ),
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::SOCIAL_PROOF_PURCHASE_BADGE_ICON_OPTION,
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => array( $this, 'sanitize_social_proof_purchase_badge_icon' ),
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::SOCIAL_PROOF_ENGAGEMENT_MIN_SALES_OPTION,
			array(
				'type'              => 'integer',
				'default'           => 3,
				'sanitize_callback' => static fn ( $v ): int => max( 1, min( 999999, (int) $v ) ),
			)
		);

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

			// Sub-settings rendered immediately after their parent toggle.
			if ( 'product_filters' === $key && self::is_enabled( 'product_filters' ) ) {
				add_settings_field(
					'filter_layout',
					__( 'Filter Layout', 'aggressive-apparel' ),
					array( $this, 'render_filter_layout_field' ),
					self::PAGE_SLUG,
					'aggressive_apparel_features_catalog',
				);

				add_settings_field(
					'filter_trigger_placement',
					__( 'Filter Trigger Placement', 'aggressive-apparel' ),
					array( $this, 'render_filter_trigger_placement_field' ),
					self::PAGE_SLUG,
					'aggressive_apparel_features_catalog',
				);
			}

			if ( 'load_more' === $key && self::is_enabled( 'load_more' ) ) {
				add_settings_field(
					'load_more_mode',
					__( 'Load More Mode', 'aggressive-apparel' ),
					array( $this, 'render_load_more_mode_field' ),
					self::PAGE_SLUG,
					'aggressive_apparel_features_catalog',
				);
			}

			if ( 'wishlist' === $key && self::is_enabled( 'wishlist' ) ) {
				add_settings_field(
					'wishlist_button_placement',
					__( 'Wishlist Button Placement', 'aggressive-apparel' ),
					array( $this, 'render_wishlist_button_placement_field' ),
					self::PAGE_SLUG,
					'aggressive_apparel_features_engagement',
				);
			}

			if ( 'social_proof' === $key && self::is_enabled( 'social_proof' ) ) {
				add_settings_field(
					'social_proof_sources',
					__( 'Notification Sources', 'aggressive-apparel' ),
					array( $this, 'render_social_proof_sources_field' ),
					self::PAGE_SLUG,
					'aggressive_apparel_features_engagement',
				);

				add_settings_field(
					'social_proof_trust_messages',
					__( 'Trust Messages', 'aggressive-apparel' ),
					array( $this, 'render_social_proof_trust_messages_field' ),
					self::PAGE_SLUG,
					'aggressive_apparel_features_engagement',
				);

				add_settings_field(
					'social_proof_announcements',
					__( 'Custom Announcements', 'aggressive-apparel' ),
					array( $this, 'render_social_proof_announcements_field' ),
					self::PAGE_SLUG,
					'aggressive_apparel_features_engagement',
				);

				add_settings_field(
					'social_proof_purchase_badge_icon',
					__( 'Badge on Purchase Thumbnails', 'aggressive-apparel' ),
					array( $this, 'render_social_proof_purchase_badge_icon_field' ),
					self::PAGE_SLUG,
					'aggressive_apparel_features_engagement',
				);

				add_settings_field(
					'social_proof_icon_help',
					__( 'Icons: reference & customization', 'aggressive-apparel' ),
					array( $this, 'render_social_proof_icon_help_field' ),
					self::PAGE_SLUG,
					'aggressive_apparel_features_engagement',
				);

				add_settings_field(
					'social_proof_engagement_min_sales',
					__( 'Engagement: Minimum Lifetime Sales', 'aggressive-apparel' ),
					array( $this, 'render_social_proof_engagement_min_sales_field' ),
					self::PAGE_SLUG,
					'aggressive_apparel_features_engagement',
				);

				add_settings_field(
					'social_proof_display_mode',
					__( 'Purchase Display Mode', 'aggressive-apparel' ),
					array( $this, 'render_social_proof_display_mode_field' ),
					self::PAGE_SLUG,
					'aggressive_apparel_features_engagement',
				);

				add_settings_field(
					'social_proof_location_granularity',
					__( 'Location Granularity', 'aggressive-apparel' ),
					array( $this, 'render_social_proof_location_granularity_field' ),
					self::PAGE_SLUG,
					'aggressive_apparel_features_engagement',
				);

				add_settings_field(
					'social_proof_min_order_age',
					__( 'Minimum Order Age (Minutes)', 'aggressive-apparel' ),
					array( $this, 'render_social_proof_min_order_age_field' ),
					self::PAGE_SLUG,
					'aggressive_apparel_features_engagement',
				);

				add_settings_field(
					'social_proof_demo',
					__( 'Demo Preview (Admin Only)', 'aggressive-apparel' ),
					array( $this, 'render_social_proof_demo_field' ),
					self::PAGE_SLUG,
					'aggressive_apparel_features_engagement',
				);
			}
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
	 * Render the settings page with tabbed sections.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		$section_counts = $this->get_section_counts();
		$first_key      = array_key_first( self::SECTIONS );

		echo '<div class="wrap aa-features-wrap">';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		settings_errors();
		echo '<p>' . esc_html__( 'Enable or disable individual WooCommerce enhancements. Disabled features have zero performance overhead.', 'aggressive-apparel' ) . '</p>';
		echo '<form method="post" action="options.php">';

		settings_fields( self::SETTINGS_GROUP );

		// Tab navigation.
		echo '<nav class="nav-tab-wrapper aa-features-tabs">';
		foreach ( self::SECTIONS as $id => $meta ) {
			$active = ( $id === $first_key ) ? ' nav-tab-active' : '';
			$counts = $section_counts[ $id ];

			printf(
				'<a href="#" class="nav-tab%s" data-tab="%s"><span class="dashicons %s"></span> %s <span class="aa-features-tab-count">%d/%d</span></a>',
				esc_attr( $active ),
				esc_attr( $id ),
				esc_attr( $meta['icon'] ),
				esc_html( $meta['label'] ),
				absint( $counts['enabled'] ),
				absint( $counts['total'] ),
			);
		}
		echo '</nav>';

		// Tab panels.
		foreach ( self::SECTIONS as $id => $meta ) {
			$hidden     = ( $id !== $first_key ) ? ' hidden' : '';
			$section_id = 'aggressive_apparel_features_' . $id;

			printf( '<div class="aa-features-tab-panel" id="tab-%s"%s>', esc_attr( $id ), esc_attr( $hidden ) );
			echo '<table class="form-table" role="presentation">';
			do_settings_fields( self::PAGE_SLUG, $section_id );
			echo '</table>';
			echo '</div>';
		}

		submit_button( __( 'Save Changes', 'aggressive-apparel' ) );

		echo '</form>';

		// Inline tab-switching script.
		$this->render_tab_script();

		echo '</div>';
	}

	/**
	 * Render inline JavaScript for tab switching.
	 *
	 * @return void
	 */
	private function render_tab_script(): void {
		?>
		<script>
		(function() {
			var KEY = 'aa_features_tab';
			var tabs = document.querySelectorAll('.aa-features-tabs .nav-tab');
			var panels = document.querySelectorAll('.aa-features-tab-panel');

			function activate(id) {
				tabs.forEach(function(t) {
					t.classList.toggle('nav-tab-active', t.dataset.tab === id);
				});
				panels.forEach(function(p) {
					p.hidden = p.id !== 'tab-' + id;
				});
				try { localStorage.setItem(KEY, id); } catch(e) {}
			}

			tabs.forEach(function(t) {
				t.addEventListener('click', function(e) {
					e.preventDefault();
					activate(t.dataset.tab);
				});
			});

			try {
				var saved = localStorage.getItem(KEY);
				if (saved && document.getElementById('tab-' + saved)) {
					activate(saved);
				}
			} catch(e) {}
		})();
		</script>
		<?php
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
	 * Sanitize the filter trigger placement option.
	 *
	 * @param mixed $input Raw input.
	 * @return string Sanitized placement value (`auto` or `block`).
	 */
	public function sanitize_filter_trigger_placement( $input ): string {
		$valid = array( 'auto', 'block' );
		return in_array( $input, $valid, true ) ? $input : 'auto';
	}

	/**
	 * Render the filter trigger placement select field.
	 *
	 * @return void
	 */
	public function render_filter_trigger_placement_field(): void {
		$placement = get_option( self::FILTER_TRIGGER_PLACEMENT_OPTION, 'auto' );
		$options   = array(
			'auto'  => __( 'Automatic (before catalog sorting)', 'aggressive-apparel' ),
			'block' => __( 'Manual placement (use Filter Toggle block)', 'aggressive-apparel' ),
		);

		printf( '<select name="%s">', esc_attr( self::FILTER_TRIGGER_PLACEMENT_OPTION ) );
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $value ),
				selected( $placement, $value, false ),
				esc_html( $label ),
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Automatic mirrors the legacy behavior. Manual lets you place the "Product Filter Toggle" block anywhere in the Site Editor — useful for sidebars, custom toolbars, or above the title.', 'aggressive-apparel' ) . '</p>';
	}

	/**
	 * Sanitize the wishlist button placement option.
	 *
	 * @param mixed $input Raw input.
	 * @return string Sanitized placement value (`auto` or `block`).
	 */
	public function sanitize_wishlist_button_placement( $input ): string {
		$valid = array( 'auto', 'block' );
		return in_array( $input, $valid, true ) ? $input : 'auto';
	}

	/**
	 * Render the wishlist button placement select field.
	 *
	 * @return void
	 */
	public function render_wishlist_button_placement_field(): void {
		$placement = get_option( self::WISHLIST_BUTTON_PLACEMENT_OPTION, 'auto' );
		$options   = array(
			'auto'  => __( 'Automatic (cards + single product page)', 'aggressive-apparel' ),
			'block' => __( 'Manual placement (use Wishlist Button block)', 'aggressive-apparel' ),
		);

		printf( '<select name="%s">', esc_attr( self::WISHLIST_BUTTON_PLACEMENT_OPTION ) );
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $value ),
				selected( $placement, $value, false ),
				esc_html( $label ),
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Automatic injects the heart on product cards and on the single product page. Manual suppresses both auto-injections so you can place the "Wishlist Button" block anywhere — inside a Product Collection, single product template, or even a custom layout.', 'aggressive-apparel' ) . '</p>';
	}

	// -- Social Proof — sub-settings --

	/**
	 * Available social proof sources with their human-readable labels
	 * and descriptions. Demo is intentionally NOT a "source" here — it
	 * lives in its own toggle because of the admin-only visibility gate.
	 *
	 * @return array<string, array{label: string, description: string}>
	 */
	private static function get_social_proof_source_definitions(): array {
		return array(
			'trust'         => array(
				'label'       => __( 'Trust Messages', 'aggressive-apparel' ),
				'description' => __( 'Always-on brand trust signals from your Trust Messages list. Works at zero orders.', 'aggressive-apparel' ),
			),
			'purchases'     => array(
				'label'       => __( 'Real Purchases', 'aggressive-apparel' ),
				'description' => __( 'Anonymized recent orders. Skipped silently when you have no eligible orders.', 'aggressive-apparel' ),
			),
			'announcements' => array(
				'label'       => __( 'Custom Announcements', 'aggressive-apparel' ),
				'description' => __( 'Short-term promos / seasonal copy from your Announcements list.', 'aggressive-apparel' ),
			),
			'engagement'    => array(
				'label'       => __( 'Engagement (sales signal)', 'aggressive-apparel' ),
				'description' => __( 'Shows top-selling catalogue products backed by WooCommerce total sales counts (honest bestseller cues). Quieter than live purchase notices.', 'aggressive-apparel' ),
			),
		);
	}

	/**
	 * Default source mix for new installs.
	 *
	 * Trust messages dominate by default so brand-new stores see useful
	 * content immediately. Purchases are enabled but at lower weight so
	 * they participate in the rotation as soon as orders start coming in.
	 *
	 * @return array<string, int>
	 */
	private static function get_default_social_proof_sources(): array {
		return array(
			'trust'         => 5,
			'purchases'     => 5,
			'announcements' => 0,
			'engagement'    => 3,
		);
	}

	/**
	 * Public accessor: trust messages with defaults applied.
	 *
	 * Falls through cleanly on the frontend where `register_setting()`
	 * has not run and `default_option_*` filters are absent. Returns the
	 * shipped POD-friendly default list when the admin hasn't customised
	 * it, the customised list otherwise.
	 *
	 * @return string Raw textarea contents (newline-separated).
	 */
	public static function get_social_proof_trust_messages(): string {
		$saved = get_option( self::SOCIAL_PROOF_TRUST_MESSAGES_OPTION, null );
		if ( null === $saved || false === $saved ) {
			return self::SOCIAL_PROOF_DEFAULT_TRUST_MESSAGES;
		}
		return (string) $saved;
	}

	/**
	 * Public accessor: custom announcements with defaults applied.
	 *
	 * @return string Raw textarea contents (newline-separated).
	 */
	public static function get_social_proof_announcements(): string {
		$saved = get_option( self::SOCIAL_PROOF_ANNOUNCEMENTS_OPTION, null );
		if ( null === $saved || false === $saved ) {
			return '';
		}
		return (string) $saved;
	}

	/**
	 * Minimum WooCommerce lifetime sales gate for Engagement toasts.
	 *
	 * @return int
	 */
	public static function get_social_proof_engagement_min_sales(): int {
		$option = get_option( self::SOCIAL_PROOF_ENGAGEMENT_MIN_SALES_OPTION, null );

		if ( null === $option || false === $option ) {
			return 3;
		}

		return max( 1, min( 999999, (int) $option ) );
	}

	/**
	 * Resolve the thumbnail badge slug (unset DB row uses the bundled fallback icon).
	 *
	 * Stored empty string hides the thumbnail badge deliberately.
	 *
	 * @return string Sanitized theme icon slug — empty when suppressed.
	 */
	public static function resolve_social_proof_purchase_badge_icon_slug(): string {
		$stored = get_option( self::SOCIAL_PROOF_PURCHASE_BADGE_ICON_OPTION, null );

		if ( null === $stored || false === $stored ) {
			return self::SOCIAL_PROOF_PURCHASE_BADGE_FALLBACK_SLUG;
		}

		return sanitize_key( (string) $stored );
	}

	/**
	 * Public accessor: resolved source mix (defaults applied).
	 *
	 * @return array<string, int>
	 */
	public static function get_social_proof_sources(): array {
		$saved    = get_option( self::SOCIAL_PROOF_SOURCES_OPTION, array() );
		$defaults = self::get_default_social_proof_sources();

		if ( ! is_array( $saved ) || empty( $saved ) ) {
			return $defaults;
		}

		$resolved = array();
		foreach ( $defaults as $key => $weight ) {
			$resolved[ $key ] = isset( $saved[ $key ] ) ? max( 0, min( 10, (int) $saved[ $key ] ) ) : $weight;
		}

		return $resolved;
	}

	/**
	 * Sanitize the source mix array.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, int> Sanitized mix.
	 */
	public function sanitize_social_proof_sources( $input ): array {
		$valid_keys = array_keys( self::get_social_proof_source_definitions() );
		$sanitized  = array();

		foreach ( $valid_keys as $key ) {
			$weight = isset( $input[ $key ] ) ? (int) $input[ $key ] : 0;
			// Clamp to 0–10 so we can't accidentally store absurd weights.
			$sanitized[ $key ] = max( 0, min( 10, $weight ) );
		}

		return $sanitized;
	}

	/**
	 * Sanitize a multiline messages textarea.
	 *
	 * Preserves line breaks; strips tags and per-line whitespace.
	 * Caps total length defensively.
	 *
	 * @param mixed $input Raw input.
	 * @return string
	 */
	public function sanitize_social_proof_messages( $input ): string {
		if ( ! is_string( $input ) ) {
			return '';
		}

		// Cap defensive max length so the option stays small.
		if ( strlen( $input ) > 8192 ) {
			$input = substr( $input, 0, 8192 );
		}

		// Normalise line endings, then sanitise each line.
		$lines = preg_split( '/\r\n|\r|\n/', $input );
		if ( ! is_array( $lines ) ) {
			return '';
		}

		$cleaned = array();
		foreach ( $lines as $line ) {
			$line = sanitize_text_field( $line );
			// Cap per-line length for sane toast widths.
			if ( strlen( $line ) > 200 ) {
				$line = substr( $line, 0, 200 );
			}
			$cleaned[] = $line;
		}

		return implode( "\n", $cleaned );
	}

	/**
	 * Sanitize the purchase display mode option.
	 *
	 * @param mixed $input Raw input.
	 * @return string
	 */
	public function sanitize_social_proof_display_mode( $input ): string {
		$valid = array( 'anonymous', 'initial', 'first_name' );
		return in_array( $input, $valid, true ) ? $input : 'anonymous';
	}

	/**
	 * Sanitize the location granularity option.
	 *
	 * @param mixed $input Raw input.
	 * @return string
	 */
	public function sanitize_social_proof_location_granularity( $input ): string {
		$valid = array( 'city', 'state', 'country', 'hidden' );
		return in_array( $input, $valid, true ) ? $input : 'city';
	}

	/**
	 * Sanitize the optional purchase-thumbnail badge icon slug.
	 *
	 * @param mixed $input Raw input.
	 * @return string Empty string or valid icon slug from the theme library.
	 */
	public function sanitize_social_proof_purchase_badge_icon( $input ): string {
		$key = sanitize_key( (string) $input );

		return ( '' !== $key && Icons::exists( $key ) ) ? $key : '';
	}

	/**
	 * Render the source mix table (checkbox + weight slider per source).
	 *
	 * @return void
	 */
	public function render_social_proof_sources_field(): void {
		$current_sources = self::get_social_proof_sources();
		$definitions     = self::get_social_proof_source_definitions();

		echo '<table class="widefat aa-social-proof-sources" style="max-width: 600px; margin-bottom: 0.5em;">';
		echo '<thead><tr>';
		echo '<th style="width: 40%;">' . esc_html__( 'Source', 'aggressive-apparel' ) . '</th>';
		echo '<th style="width: 20%;">' . esc_html__( 'Weight', 'aggressive-apparel' ) . '</th>';
		echo '<th>' . esc_html__( 'Notes', 'aggressive-apparel' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $definitions as $key => $meta ) {
			$weight = isset( $current_sources[ $key ] ) ? (int) $current_sources[ $key ] : 0;

			echo '<tr>';
			echo '<td><strong>' . esc_html( $meta['label'] ) . '</strong></td>';
			echo '<td>';
			printf(
				'<input type="number" min="0" max="10" step="1" name="%s[%s]" value="%d" style="width: 5em;" />',
				esc_attr( self::SOCIAL_PROOF_SOURCES_OPTION ),
				esc_attr( $key ),
				absint( $weight ),
			);
			echo '</td>';
			echo '<td><span class="description">' . esc_html( $meta['description'] ) . '</span></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'Weight 0 disables a source. Higher weights appear more often in the random rotation. Engagement uses catalog sales totals (requires the minimum sales threshold below). Set Engagement to 0 until you have steady sales. Set Purchases to 0 on day one if you prefer only trust + engagement + announcements.', 'aggressive-apparel' ) . '</p>';
	}

	/**
	 * Render the trust messages textarea.
	 *
	 * @return void
	 */
	public function render_social_proof_trust_messages_field(): void {
		$value = self::get_social_proof_trust_messages();

		printf(
			'<textarea name="%s" rows="8" cols="60" class="large-text code" style="font-family: ui-sans-serif, system-ui, sans-serif;">%s</textarea>',
			esc_attr( self::SOCIAL_PROOF_TRUST_MESSAGES_OPTION ),
			esc_textarea( $value ),
		);
		echo '<p class="description">' . esc_html__( 'One message per line. Optional icon prefix — put PREFIX| before the visible text; PREFIX may be (a) a theme SVG slug such as check, heart, cart, info, warning, … or (b) a secure https URL to a small PNG/SVG/WebP/GIF badge. Prefix none| to force plain text without an icon even when another line uses one. Lines starting with # and blank lines are ignored.', 'aggressive-apparel' ) . '</p>';
	}

	/**
	 * Render the announcements textarea.
	 *
	 * @return void
	 */
	public function render_social_proof_announcements_field(): void {
		$value = self::get_social_proof_announcements();

		printf(
			'<textarea name="%s" rows="5" cols="60" class="large-text code" style="font-family: ui-sans-serif, system-ui, sans-serif;" placeholder="%s">%s</textarea>',
			esc_attr( self::SOCIAL_PROOF_ANNOUNCEMENTS_OPTION ),
			esc_attr__( "gift|Spring drop launches Friday\nhttps://cdn.example.com/sale-dot.png | Free shipping today only", 'aggressive-apparel' ),
			esc_textarea( $value ),
		);
		echo '<p class="description">' . esc_html__( 'Short-term promos and seasonal copy — same PREFIX|MESSAGE icon rules as Trust Messages. Prefix none| to force text-only. Set the Announcements weight above 0 to include them in the rotation.', 'aggressive-apparel' ) . '</p>';
	}

	/**
	 * Render optional purchase-notification thumbnail badge picker.
	 *
	 * @return void
	 */
	public function render_social_proof_purchase_badge_icon_field(): void {
		$value = self::resolve_social_proof_purchase_badge_icon_slug();

		printf(
			'<select name="%s">',
			esc_attr( self::SOCIAL_PROOF_PURCHASE_BADGE_ICON_OPTION ),
		);
		printf(
			'<option value="" %s>%s</option>',
			selected( $value, '', false ),
			esc_html__( 'None', 'aggressive-apparel' ),
		);

		$icons = Icons::list();

		sort( $icons );

		foreach ( $icons as $slug ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $slug ),
				selected( $value, $slug, false ),
				esc_html( $slug ),
			);
		}

		echo '</select>';

		echo '<p class="description">' . esc_html__( 'Tiny SVG pinned to the thumbnail corner on purchase / engagement / demo notifications. Choosing “None” removes it permanently after save. Icons reference & customization sits directly below.', 'aggressive-apparel' ) . '</p>';
	}

	/**
	 * Explain social proof PREFIX lines, thumbnail badges, and icon extension points.
	 *
	 * @return void
	 */
	public function render_social_proof_icon_help_field(): void {
		$icon_slugs = Icons::list();
		sort( $icon_slugs );
		$list = implode( ', ', $icon_slugs );

		echo '<ul class="description" style="list-style:disc;margin-left:1.25em;max-width:42rem;">';
		echo '<li>' . esc_html__( 'Trust Messages and Custom Announcements: each line uses PREFIX|message. PREFIX is either a slug from the expandable list below (same slugs appear in the badge dropdown) or a full https URL to your own PNG/SVG/WebP badge. Prefix none| to force plain text on that row.', 'aggressive-apparel' ) . '</li>';
		echo '<li>' . esc_html__( 'Slides that include a WooCommerce thumbnail only show icons as the thumbnail-corner badge—not the PREFIX column—to avoid crowding.', 'aggressive-apparel' ) . '</li>';
		echo '</ul>';

		echo '<details style="max-width:42rem;margin-top:0.75em;">';
		echo '<summary style="cursor:pointer;">' . esc_html__( 'Built-in icon slugs (copy into PREFIX|)', 'aggressive-apparel' ) . '</summary>';
		echo '<p class="description" style="margin:0.75em 0 0;"><code style="white-space:normal;word-break:break-word;">' . esc_html( $list ) . '</code></p>';
		echo '</details>';

		echo '<p class="description">';
		echo wp_kses_post(
			sprintf(
				/* translators: %s: Inline code snippet with PHP filter name. */
				__( 'Developers — register additional slug → SVG-path pairs via the %s filter (child theme recommended). Paths must describe a single d attribute tuned for viewBox="0 0 24 24".', 'aggressive-apparel' ),
				'<code>aggressive_apparel_icon_definitions</code>'
			),
		);
		echo '</p>';
	}

	/**
	 * Render Engagement minimum lifetime sales threshold.
	 *
	 * @return void
	 */
	public function render_social_proof_engagement_min_sales_field(): void {
		$value = self::get_social_proof_engagement_min_sales();

		printf(
			'<input type="number" name="%s" value="%d" min="1" max="999999" step="1" style="width: 8em;" />',
			esc_attr( self::SOCIAL_PROOF_ENGAGEMENT_MIN_SALES_OPTION ),
			absint( $value ),
		);

		echo '<p class="description">' . esc_html__( 'Only products whose WooCommerce lifetime total sales reach this number are eligible for Engagement toasts together with thumbnail + optional badge. Typical starting values: 2–5 for new shops, higher when you want only strong sellers promoted.', 'aggressive-apparel' ) . '</p>';
	}

	/**
	 * Render the purchase display mode select.
	 *
	 * @return void
	 */
	public function render_social_proof_display_mode_field(): void {
		$mode    = (string) get_option( self::SOCIAL_PROOF_DISPLAY_MODE_OPTION, 'anonymous' );
		$options = array(
			'anonymous'  => __( 'Anonymous — "Someone in [Location] purchased X" (recommended)', 'aggressive-apparel' ),
			'initial'    => __( 'Initial only — "S. in [Location] purchased X"', 'aggressive-apparel' ),
			'first_name' => __( 'First name — "Sarah from [Location] purchased X" (requires checkout consent)', 'aggressive-apparel' ),
		);

		printf( '<select name="%s">', esc_attr( self::SOCIAL_PROOF_DISPLAY_MODE_OPTION ) );
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $value ),
				selected( $mode, $value, false ),
				esc_html( $label ),
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Affects only the Real Purchases source. Anonymous is the safest choice in most jurisdictions; First Name should be paired with explicit checkout consent.', 'aggressive-apparel' ) . '</p>';
	}

	/**
	 * Render the location granularity select.
	 *
	 * @return void
	 */
	public function render_social_proof_location_granularity_field(): void {
		$value   = (string) get_option( self::SOCIAL_PROOF_LOCATION_GRANULARITY_OPTION, 'city' );
		$options = array(
			'city'    => __( 'City — "Portland"', 'aggressive-apparel' ),
			'state'   => __( 'State / Region — "Oregon"', 'aggressive-apparel' ),
			'country' => __( 'Country — "United States"', 'aggressive-apparel' ),
			'hidden'  => __( 'Hide location entirely', 'aggressive-apparel' ),
		);

		printf( '<select name="%s">', esc_attr( self::SOCIAL_PROOF_LOCATION_GRANULARITY_OPTION ) );
		foreach ( $options as $key => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $key ),
				selected( $value, $key, false ),
				esc_html( $label ),
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Lower granularity = more anonymity. State / Region is a good compromise for small markets where city + product can identify a customer.', 'aggressive-apparel' ) . '</p>';
	}

	/**
	 * Render the minimum order age input.
	 *
	 * @return void
	 */
	public function render_social_proof_min_order_age_field(): void {
		$value = (int) get_option( self::SOCIAL_PROOF_MIN_ORDER_AGE_OPTION, 5 );

		printf(
			'<input type="number" name="%s" value="%d" min="0" max="1440" step="1" style="width: 6em;" />',
			esc_attr( self::SOCIAL_PROOF_MIN_ORDER_AGE_OPTION ),
			absint( $value ),
		);
		echo '<p class="description">' . esc_html__( 'Orders younger than this number of minutes are excluded from the rotation. Default 5. Recommended 5–10 to prevent unique product + city + exact-time combinations from identifying individual customers.', 'aggressive-apparel' ) . '</p>';
	}

	/**
	 * Render the demo preview checkbox.
	 *
	 * @return void
	 */
	public function render_social_proof_demo_field(): void {
		$enabled = (bool) get_option( self::SOCIAL_PROOF_DEMO_OPTION, false );

		printf(
			'<label><input type="checkbox" name="%s" value="1" %s /> %s</label>',
			esc_attr( self::SOCIAL_PROOF_DEMO_OPTION ),
			checked( $enabled, true, false ),
			esc_html__( 'Show a sample notification first in the rotation so I can preview the design.', 'aggressive-apparel' ),
		);
		echo '<p class="description">' . esc_html__( 'Visible only to logged-in users with the "Edit Theme Options" capability — customers never see the preview, even when this is on. An indicator appears in your admin bar while it is active.', 'aggressive-apparel' ) . '</p>';
	}

	/**
	 * Sanitize the load more mode option.
	 *
	 * @param mixed $input Raw input.
	 * @return string Sanitized mode value.
	 */
	public function sanitize_load_more_mode( $input ): string {
		$valid = array( 'load_more', 'infinite_scroll' );
		return in_array( $input, $valid, true ) ? $input : 'load_more';
	}

	/**
	 * Render the load more mode select field.
	 *
	 * @return void
	 */
	public function render_load_more_mode_field(): void {
		$mode    = get_option( self::LOAD_MORE_MODE_OPTION, 'load_more' );
		$options = array(
			'load_more'       => __( 'Load More Button', 'aggressive-apparel' ),
			'infinite_scroll' => __( 'Infinite Scroll', 'aggressive-apparel' ),
		);

		printf( '<select name="%s">', esc_attr( self::LOAD_MORE_MODE_OPTION ) );
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $value ),
				selected( $mode, $value, false ),
				esc_html( $label ),
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Load More shows a button; Infinite Scroll loads automatically as users scroll down.', 'aggressive-apparel' ) . '</p>';
	}

	/**
	 * Get enabled/total feature counts per section.
	 *
	 * @return array<string, array{enabled: int, total: int}>
	 */
	private function get_section_counts(): array {
		$counts = array();
		foreach ( self::SECTIONS as $id => $meta ) {
			$counts[ $id ] = array(
				'enabled' => 0,
				'total'   => 0,
			);
		}

		foreach ( self::get_feature_definitions() as $key => $feature ) {
			$section = $feature['section'];
			if ( ! isset( $counts[ $section ] ) ) {
				continue;
			}

			++$counts[ $section ]['total'];
			if ( self::is_enabled( $key ) ) {
				++$counts[ $section ]['enabled'];
			}
		}

		return $counts;
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
