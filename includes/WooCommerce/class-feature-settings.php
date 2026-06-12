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
 * Single source of truth for WooCommerce enhancement configuration: option
 * keys, feature/section definitions, defaults, and the public read API
 * (`is_enabled()` and the `get_social_proof_*` accessors) consumed across the
 * theme. The admin UI is delegated to focused collaborators:
 *
 *   - Feature_Settings_Page      → menu, assets, Settings API wiring, chrome
 *   - Feature_Settings_Sanitizer → sanitize_callback implementations
 *   - Feature_Settings_Fields    → individual field renderers
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
	public const PAGE_SLUG = 'aggressive-apparel-features';

	/**
	 * Settings group name.
	 *
	 * @var string
	 */
	public const SETTINGS_GROUP = 'aggressive_apparel_features_group';

	/**
	 * Option key for the catalog hover image animation style.
	 *
	 * @var string
	 */
	public const HOVER_IMAGE_ANIMATION_OPTION = 'aggressive_apparel_hover_image_animation';

	/**
	 * Option key for the primary image exit transition duration in milliseconds (50–1500).
	 *
	 * @var string
	 */
	public const HOVER_IMAGE_EXIT_DURATION_OPTION = 'aggressive_apparel_hover_image_exit_duration';

	/**
	 * Option key for the primary image exit animation style.
	 *
	 * @var string
	 */
	public const HOVER_IMAGE_EXIT_ANIMATION_OPTION = 'aggressive_apparel_hover_image_exit_animation';

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
	public const SOCIAL_PROOF_DEFAULT_TRUST_MESSAGES = "check|Made to order with care\ninfo|Print quality guaranteed\nheart|Premium quality materials\ninfo|Honest sizing guide on every product\nhome|Independent brand\ngrid-view|Designed with attention to detail\ncheck|Quality you can feel";

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
	 * Settings page sections with tab metadata.
	 *
	 * @return array<string, array{label: string, icon: string}>
	 */
	public static function get_sections(): array {
		return self::SECTIONS;
	}

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
			'page_transitions'           => array(
				'label'       => __( 'Page Transitions', 'aggressive-apparel' ),
				'description' => __( 'Smooth crossfade between pages with product image morphing (Chrome/Safari).', 'aggressive-apparel' ),
				'section'     => 'catalog',
			),
			'catalog_hover_image'        => array(
				'label'       => __( 'Catalog Hover Image', 'aggressive-apparel' ),
				'description' => __( 'Show the first gallery image on hover for products that have additional gallery photos.', 'aggressive-apparel' ),
				'section'     => 'catalog',
			),

			// ── Product Page ────────────────────────────────────.
			'size_guide'                 => array(
				'label'       => __( 'Size Guide', 'aggressive-apparel' ),
				'description' => __( 'Manage reusable size guides and assign them to products or categories.', 'aggressive-apparel' ),
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

			// ── Customer Engagement ─────────────────────────────.
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
			'custom_cursor'              => array(
				'label'       => __( 'Custom Cursor', 'aggressive-apparel' ),
				'description' => __( 'Branded cursor that morphs on product cards and interactive areas. Desktop only.', 'aggressive-apparel' ),
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
	 * Delegates the admin page lifecycle to Feature_Settings_Page so this
	 * class can stay focused on configuration and the public read API.
	 *
	 * @return void
	 */
	public function init(): void {
		( new Feature_Settings_Page() )->init();
	}

	/**
	 * Available social proof sources with their human-readable labels
	 * and descriptions. Demo is intentionally NOT a "source" here — it
	 * lives in its own toggle because of the admin-only visibility gate.
	 *
	 * @return array<string, array{label: string, description: string}>
	 */
	public static function get_social_proof_source_definitions(): array {
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
	 * Social proof display mode (e.g. 'anonymous', 'named').
	 *
	 * @return string
	 */
	public static function get_social_proof_display_mode(): string {
		return (string) get_option( self::SOCIAL_PROOF_DISPLAY_MODE_OPTION, 'anonymous' );
	}

	/**
	 * Social proof location granularity (e.g. 'city', 'region', 'country').
	 *
	 * @return string
	 */
	public static function get_social_proof_location_granularity(): string {
		return (string) get_option( self::SOCIAL_PROOF_LOCATION_GRANULARITY_OPTION, 'city' );
	}

	/**
	 * Wishlist button placement mode (e.g. 'auto', 'block').
	 *
	 * @return string
	 */
	public static function get_wishlist_button_placement(): string {
		return (string) get_option( self::WISHLIST_BUTTON_PLACEMENT_OPTION, 'auto' );
	}

	/**
	 * Load More display mode (e.g. 'load_more', 'infinite').
	 *
	 * @return string
	 */
	public static function get_load_more_mode(): string {
		return (string) get_option( self::LOAD_MORE_MODE_OPTION, 'load_more' );
	}

	/**
	 * Catalog hover image entry animation slug.
	 *
	 * @return string
	 */
	public static function get_hover_image_animation(): string {
		return (string) get_option( self::HOVER_IMAGE_ANIMATION_OPTION, 'fade' );
	}

	/**
	 * Catalog hover image exit animation slug.
	 *
	 * @return string
	 */
	public static function get_hover_image_exit_animation(): string {
		return (string) get_option( self::HOVER_IMAGE_EXIT_ANIMATION_OPTION, 'fade' );
	}

	/**
	 * Catalog hover image exit duration in milliseconds.
	 *
	 * @return int
	 */
	public static function get_hover_image_exit_duration(): int {
		return (int) get_option( self::HOVER_IMAGE_EXIT_DURATION_OPTION, 350 );
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
