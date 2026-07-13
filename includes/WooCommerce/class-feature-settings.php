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
	 * String Translation context for Store Copy values.
	 *
	 * @var string
	 */
	public const STORE_COPY_TRANSLATION_CONTEXT = 'Aggressive Apparel Store Copy';

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
	 * Option key for the wishlist button placement.
	 *
	 * `auto`  → Theme injects the heart above the single product title.
	 * `block` → Theme suppresses automatic single-product placement; the user is
	 *           expected to place the `aggressive-apparel/wishlist-button`
	 *           block wherever they want the heart to appear.
	 *
	 * @var string
	 */
	public const WISHLIST_BUTTON_PLACEMENT_OPTION = 'aggressive_apparel_wishlist_button_placement';

	/**
	 * Option key for Quick View media trigger style on product cards.
	 *
	 * @var string
	 */
	public const QUICK_VIEW_TRIGGER_STYLE_OPTION = 'aggressive_apparel_quick_view_trigger_style';

	/**
	 * Option key for Quick View / media action stack corner position.
	 *
	 * @var string
	 */
	public const QUICK_VIEW_TRIGGER_POSITION_OPTION = 'aggressive_apparel_quick_view_trigger_position';

	/**
	 * Option key for whether Wishlist joins the Quick View media action stack.
	 *
	 * @var string
	 */
	public const QUICK_VIEW_MEDIA_WISHLIST_OPTION = 'aggressive_apparel_quick_view_media_wishlist';

	/**
	 * Option key for the variable product button text on product cards.
	 *
	 * @var string
	 */
	public const VARIABLE_PRODUCT_BUTTON_TEXT_OPTION = 'aggressive_apparel_variable_product_button_text';

	/**
	 * Option key for the simple product button text on product cards.
	 *
	 * @var string
	 */
	public const SIMPLE_PRODUCT_BUTTON_TEXT_OPTION = 'aggressive_apparel_simple_product_button_text';

	/**
	 * Option key for the product card button text when a product is out of stock.
	 *
	 * @var string
	 */
	public const OUT_OF_STOCK_BUTTON_TEXT_OPTION = 'aggressive_apparel_out_of_stock_button_text';

	/**
	 * Option key for the product filter trigger button text.
	 *
	 * @var string
	 */
	public const FILTER_TOGGLE_TEXT_OPTION = 'aggressive_apparel_filter_toggle_text';

	/**
	 * Option key for the load more button text.
	 *
	 * @var string
	 */
	public const LOAD_MORE_BUTTON_TEXT_OPTION = 'aggressive_apparel_load_more_button_text';

	/**
	 * Option key for the quick view trigger button text.
	 *
	 * @var string
	 */
	public const QUICK_VIEW_BUTTON_TEXT_OPTION = 'aggressive_apparel_quick_view_button_text';

	/**
	 * Option key for the buy now button text.
	 *
	 * @var string
	 */
	public const BUY_NOW_BUTTON_TEXT_OPTION = 'aggressive_apparel_buy_now_button_text';

	/**
	 * Option key for the view cart button text.
	 *
	 * @var string
	 */
	public const VIEW_CART_BUTTON_TEXT_OPTION = 'aggressive_apparel_view_cart_button_text';

	/**
	 * Option key for the continue shopping button text.
	 *
	 * @var string
	 */
	public const CONTINUE_SHOPPING_BUTTON_TEXT_OPTION = 'aggressive_apparel_continue_shopping_button_text';

	/**
	 * Option key for the view product button text.
	 *
	 * @var string
	 */
	public const VIEW_PRODUCT_BUTTON_TEXT_OPTION = 'aggressive_apparel_view_product_button_text';

	/**
	 * Option key for the back-in-stock button text.
	 *
	 * @var string
	 */
	public const BACK_IN_STOCK_BUTTON_TEXT_OPTION = 'aggressive_apparel_back_in_stock_button_text';

	/**
	 * Option key for the wishlist button text.
	 *
	 * @var string
	 */
	public const WISHLIST_BUTTON_TEXT_OPTION = 'aggressive_apparel_wishlist_button_text';

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
	 * Voice: bold, independent, zero filler — but every line stays true
	 * for any POD apparel brand: no location claims (production may
	 * vary), no specific blank brands, no dollar amounts (shipping
	 * policy is store-specific), no scarcity claims POD can't back up.
	 *
	 * @var string
	 */
	public const SOCIAL_PROOF_DEFAULT_TRUST_MESSAGES = "brand-mark|Not for everyone. That's the point.\nclose|No warehouse. No overstock. No middlemen.\ncheck|Made to order. No exceptions.\ninfo|Real measurements. No vanity sizing.\nheart|Softness is for the fabric. Not the brand.\ncheck|Your order starts the press. Not before.\nbrand-mark|Independent. In-house. Nobody's puppet.";

	/**
	 * Settings page section keys and icons (labels translated in get_sections()).
	 *
	 * @var array<string, array{icon: string}>
	 */
	private const SECTIONS = array(
		'catalog'    => array(
			'icon' => 'dashicons-store',
		),
		'copy'       => array(
			'icon' => 'dashicons-edit',
		),
		'product'    => array(
			'icon' => 'dashicons-products',
		),
		'engagement' => array(
			'icon' => 'dashicons-groups',
		),
		'ui'         => array(
			'icon' => 'dashicons-smartphone',
		),
	);

	/**
	 * Settings page sections with tab metadata.
	 *
	 * @return array<string, array{label: string, icon: string}>
	 */
	public static function get_sections(): array {
		$labels = array(
			'catalog'    => __( 'Catalog & Browsing', 'aggressive-apparel' ),
			'copy'       => __( 'Store Copy', 'aggressive-apparel' ),
			'product'    => __( 'Product Page', 'aggressive-apparel' ),
			'engagement' => __( 'Customer Engagement', 'aggressive-apparel' ),
			'ui'         => __( 'Mobile & UI', 'aggressive-apparel' ),
		);

		$sections = array();
		foreach ( self::SECTIONS as $id => $meta ) {
			$sections[ $id ] = array(
				'label' => $labels[ $id ],
				'icon'  => $meta['icon'],
			);
		}

		return $sections;
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
				'description' => __( 'Preview products in a modal from shop cards. Style and Wishlist pairing options appear when enabled.', 'aggressive-apparel' ),
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
		);
	}

	/**
	 * Storefront microcopy settings with labels, defaults, and admin help.
	 *
	 * @return array<string, array{option: string, label: string, default: string, description: string}>
	 */
	public static function get_store_copy_definitions(): array {
		return array(
			'variable_product_button_text'  => array(
				'option'      => self::VARIABLE_PRODUCT_BUTTON_TEXT_OPTION,
				'label'       => __( 'Variable Product Button Text', 'aggressive-apparel' ),
				'default'     => __( 'Choose', 'aggressive-apparel' ),
				'description' => __( 'Button text for variable products on product cards, Quick View, and Sticky Cart before options are chosen.', 'aggressive-apparel' ),
			),
			'simple_product_button_text'    => array(
				'option'      => self::SIMPLE_PRODUCT_BUTTON_TEXT_OPTION,
				'label'       => __( 'Simple Product Button Text', 'aggressive-apparel' ),
				'default'     => __( 'Add to Cart', 'aggressive-apparel' ),
				'description' => __( 'Product-card button text for simple products that can be added directly.', 'aggressive-apparel' ),
			),
			'out_of_stock_button_text'      => array(
				'option'      => self::OUT_OF_STOCK_BUTTON_TEXT_OPTION,
				'label'       => __( 'Out of Stock Button Text', 'aggressive-apparel' ),
				'default'     => __( 'Out of Stock', 'aggressive-apparel' ),
				'description' => __( 'Button text shown when product-card or sticky-cart products are out of stock.', 'aggressive-apparel' ),
			),
			'filter_toggle_text'            => array(
				'option'      => self::FILTER_TOGGLE_TEXT_OPTION,
				'label'       => __( 'Filter Toggle Text', 'aggressive-apparel' ),
				'default'     => __( 'Filter', 'aggressive-apparel' ),
				'description' => __( 'Visible text for the automatic product-filter trigger and default Filter Toggle block label.', 'aggressive-apparel' ),
			),
			'load_more_button_text'         => array(
				'option'      => self::LOAD_MORE_BUTTON_TEXT_OPTION,
				'label'       => __( 'Load More Button Text', 'aggressive-apparel' ),
				'default'     => __( 'Load More Products', 'aggressive-apparel' ),
				'description' => __( 'Button text for loading the next page of catalog products.', 'aggressive-apparel' ),
			),
			'quick_view_button_text'        => array(
				'option'      => self::QUICK_VIEW_BUTTON_TEXT_OPTION,
				'label'       => __( 'Quick View Button Text', 'aggressive-apparel' ),
				'default'     => __( 'Quick View', 'aggressive-apparel' ),
				'description' => __( 'Label used by Quick View triggers on product cards.', 'aggressive-apparel' ),
			),
			'buy_now_button_text'           => array(
				'option'      => self::BUY_NOW_BUTTON_TEXT_OPTION,
				'label'       => __( 'Buy Now Button Text', 'aggressive-apparel' ),
				'default'     => __( 'Buy Now', 'aggressive-apparel' ),
				'description' => __( 'Button text for checkout-forward purchase actions in Sticky Cart and Quick View.', 'aggressive-apparel' ),
			),
			'view_cart_button_text'         => array(
				'option'      => self::VIEW_CART_BUTTON_TEXT_OPTION,
				'label'       => __( 'View Cart Button Text', 'aggressive-apparel' ),
				'default'     => __( 'View Cart', 'aggressive-apparel' ),
				'description' => __( 'Button text shown after an item is added to the cart.', 'aggressive-apparel' ),
			),
			'continue_shopping_button_text' => array(
				'option'      => self::CONTINUE_SHOPPING_BUTTON_TEXT_OPTION,
				'label'       => __( 'Continue Shopping Button Text', 'aggressive-apparel' ),
				'default'     => __( 'Continue Shopping', 'aggressive-apparel' ),
				'description' => __( 'Button text for closing post-cart panels and returning to browsing.', 'aggressive-apparel' ),
			),
			'view_product_button_text'      => array(
				'option'      => self::VIEW_PRODUCT_BUTTON_TEXT_OPTION,
				'label'       => __( 'View Product Button Text', 'aggressive-apparel' ),
				'default'     => __( 'View Full Product', 'aggressive-apparel' ),
				'description' => __( 'Link text for opening the full product page from custom overlays.', 'aggressive-apparel' ),
			),
			'back_in_stock_button_text'     => array(
				'option'      => self::BACK_IN_STOCK_BUTTON_TEXT_OPTION,
				'label'       => __( 'Back in Stock Button Text', 'aggressive-apparel' ),
				'default'     => __( 'Notify Me', 'aggressive-apparel' ),
				'description' => __( 'Button and badge text for out-of-stock notification signups.', 'aggressive-apparel' ),
			),
			'wishlist_button_text'          => array(
				'option'      => self::WISHLIST_BUTTON_TEXT_OPTION,
				'label'       => __( 'Wishlist Button Text', 'aggressive-apparel' ),
				'default'     => __( 'Add to Wishlist', 'aggressive-apparel' ),
				'description' => __( 'Accessible label and optional visible text for wishlist buttons.', 'aggressive-apparel' ),
			),
		);
	}

	/**
	 * Resolve a Store Copy value by option name, falling back to its default.
	 *
	 * @param string $option_name Store Copy option key.
	 * @return string
	 */
	public static function get_store_copy_text( string $option_name ): string {
		$definition = self::get_store_copy_definition_by_option( $option_name );
		$default    = isset( $definition['default'] ) ? $definition['default'] : '';
		$value      = self::get_store_copy_base_text( $option_name, $default );
		$translated = self::translate_store_copy_text( $value, $option_name );

		/**
		 * Filter the final Store Copy text used by WooCommerce enhancement UI.
		 *
		 * @param string               $translated  Translated Store Copy value.
		 * @param string               $option_name Store Copy option key.
		 * @param string               $default     Registered default value.
		 * @param string               $value       Saved/default base value before multilingual translation.
		 * @param array<string,string> $definition  Store Copy definition metadata.
		 */
		$filtered = apply_filters(
			'aggressive_apparel_store_copy_text',
			$translated,
			$option_name,
			$default,
			$value,
			$definition
		);

		return self::normalize_store_copy_text( $filtered, $translated );
	}

	/**
	 * Register Store Copy values with multilingual string-translation plugins.
	 *
	 * @return void
	 */
	public static function register_store_copy_translation_strings(): void {
		foreach ( self::get_store_copy_definitions() as $definition ) {
			$option_name = $definition['option'];
			$value       = self::get_store_copy_base_text( $option_name, $definition['default'] );

			do_action(
				'wpml_register_single_string',
				self::STORE_COPY_TRANSLATION_CONTEXT,
				$option_name,
				$value
			);

			if ( function_exists( 'pll_register_string' ) ) {
				\pll_register_string(
					$option_name,
					$value,
					self::STORE_COPY_TRANSLATION_CONTEXT,
					false
				);
			}
		}
	}

	/**
	 * Get a Store Copy definition by option name.
	 *
	 * @param string $option_name Store Copy option key.
	 * @return array<string,string>
	 */
	private static function get_store_copy_definition_by_option( string $option_name ): array {
		foreach ( self::get_store_copy_definitions() as $definition ) {
			if ( $option_name === $definition['option'] ) {
				return $definition;
			}
		}

		return array();
	}

	/**
	 * Resolve the saved/default Store Copy value before multilingual filters.
	 *
	 * @param string $option_name Store Copy option key.
	 * @param string $fallback    Registered default value.
	 * @return string
	 */
	private static function get_store_copy_base_text( string $option_name, string $fallback ): string {
		$value = get_option( $option_name, $fallback );

		return self::normalize_store_copy_text( $value, $fallback );
	}

	/**
	 * Normalize a Store Copy text value.
	 *
	 * @param mixed  $value    Raw value.
	 * @param string $fallback Fallback when the raw value is not usable.
	 * @return string
	 */
	private static function normalize_store_copy_text( $value, string $fallback ): string {
		if ( ! is_string( $value ) ) {
			return $fallback;
		}

		$value = trim( wp_strip_all_tags( $value ) );

		return '' === $value ? $fallback : $value;
	}

	/**
	 * Translate a Store Copy value with supported multilingual plugins.
	 *
	 * @param string $value       Store Copy value.
	 * @param string $option_name Store Copy option key.
	 * @return string
	 */
	private static function translate_store_copy_text( string $value, string $option_name ): string {
		$wpml_value = apply_filters(
			'wpml_translate_single_string',
			$value,
			self::STORE_COPY_TRANSLATION_CONTEXT,
			$option_name
		);

		if ( is_string( $wpml_value ) ) {
			$value = $wpml_value;
		}

		if ( function_exists( 'pll__' ) ) {
			$polylang_value = \pll__( $value );

			if ( is_string( $polylang_value ) ) {
				$value = $polylang_value;
			}
		}

		return $value;
	}

	/**
	 * Option keys that should never autoload (large / rarely read on most requests).
	 *
	 * Trust messages and announcements are only needed when Social Proof runs.
	 * Keeping them out of the alloptions payload shrinks every frontend request.
	 *
	 * @var list<string>
	 */
	private const NON_AUTOLOAD_OPTIONS = array(
		self::SOCIAL_PROOF_TRUST_MESSAGES_OPTION,
		self::SOCIAL_PROOF_ANNOUNCEMENTS_OPTION,
	);

	/**
	 * One-shot migration flag for NON_AUTOLOAD_OPTIONS.
	 *
	 * @var string
	 */
	private const AUTOLOAD_MIGRATION_OPTION = 'aggressive_apparel_options_autoload_v1';

	/**
	 * Initialize settings hooks.
	 *
	 * Delegates the admin page lifecycle to Feature_Settings_Page so this
	 * class can stay focused on configuration and the public read API.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', array( self::class, 'register_store_copy_translation_strings' ) );
		add_filter( 'wp_default_autoload_value', array( self::class, 'filter_default_autoload_value' ), 10, 2 );
		add_action( 'init', array( self::class, 'maybe_disable_large_option_autoload' ), 5 );

		( new Feature_Settings_Page() )->init();
	}

	/**
	 * Force autoload off for large social-proof text options on first insert.
	 *
	 * @param bool|null $autoload Current default.
	 * @param string    $option   Option name.
	 * @return bool|null
	 */
	public static function filter_default_autoload_value( $autoload, string $option ) {
		if ( in_array( $option, self::NON_AUTOLOAD_OPTIONS, true ) ) {
			return false;
		}

		return $autoload;
	}

	/**
	 * Flip existing large options to autoload=no once.
	 *
	 * @return void
	 */
	public static function maybe_disable_large_option_autoload(): void {
		if ( get_option( self::AUTOLOAD_MIGRATION_OPTION ) ) {
			return;
		}

		foreach ( self::NON_AUTOLOAD_OPTIONS as $option ) {
			$value = get_option( $option, null );
			if ( null === $value || false === $value ) {
				continue;
			}

			// Re-save with autoload disabled; value unchanged.
			update_option( $option, $value, false );
		}

		update_option( self::AUTOLOAD_MIGRATION_OPTION, 1, false );
	}

	/**
	 * Schema for every standalone sub-setting option (everything except
	 * the feature-flags array and the Store Copy texts, which have their
	 * own data-driven registration loops).
	 *
	 * Single source of truth consumed by BOTH:
	 *   - Feature_Settings_Page::register_sub_settings() (registration), and
	 *   - self::get_setting() (reads with defaults applied).
	 *
	 * Keeping registration defaults and read fallbacks in one place means
	 * they can never drift apart again (the class of bug where the
	 * textarea showed something different from what rendered).
	 *
	 * `empty_means_default` — treat an existing-but-empty row as "not
	 * customised" and fall back to the default. Only set it where an
	 * empty value has no meaning of its own (trust messages: disabling
	 * the source is done via its weight, not by blanking the box).
	 *
	 * `sanitize` — method name on Feature_Settings_Sanitizer.
	 *
	 * @return array<string, array{type: string, default: mixed, sanitize: string, empty_means_default?: bool}>
	 */
	public static function get_option_schema(): array {
		return array(
			self::FILTER_LAYOUT_OPTION                     => array(
				'type'     => 'string',
				'default'  => 'drawer',
				'sanitize' => 'sanitize_filter_layout',
			),
			self::LOAD_MORE_MODE_OPTION                    => array(
				'type'     => 'string',
				'default'  => 'load_more',
				'sanitize' => 'sanitize_load_more_mode',
			),
			self::WISHLIST_BUTTON_PLACEMENT_OPTION         => array(
				'type'     => 'string',
				'default'  => 'auto',
				'sanitize' => 'sanitize_wishlist_button_placement',
			),
			self::QUICK_VIEW_TRIGGER_STYLE_OPTION          => array(
				'type'     => 'string',
				'default'  => 'corner',
				'sanitize' => 'sanitize_quick_view_trigger_style',
			),
			self::QUICK_VIEW_TRIGGER_POSITION_OPTION       => array(
				'type'     => 'string',
				'default'  => 'top-right',
				'sanitize' => 'sanitize_quick_view_trigger_position',
			),
			self::QUICK_VIEW_MEDIA_WISHLIST_OPTION         => array(
				'type'     => 'string',
				'default'  => 'with_wishlist',
				'sanitize' => 'sanitize_quick_view_media_wishlist',
			),
			self::HOVER_IMAGE_ANIMATION_OPTION             => array(
				'type'     => 'string',
				'default'  => 'fade',
				'sanitize' => 'sanitize_hover_image_animation',
			),
			self::HOVER_IMAGE_EXIT_ANIMATION_OPTION        => array(
				'type'     => 'string',
				'default'  => 'fade',
				'sanitize' => 'sanitize_hover_image_exit_animation',
			),
			self::HOVER_IMAGE_EXIT_DURATION_OPTION         => array(
				'type'     => 'integer',
				'default'  => 350,
				'sanitize' => 'sanitize_hover_image_exit_duration',
			),
			self::SOCIAL_PROOF_SOURCES_OPTION              => array(
				'type'     => 'array',
				'default'  => array(),
				'sanitize' => 'sanitize_social_proof_sources',
			),
			self::SOCIAL_PROOF_TRUST_MESSAGES_OPTION       => array(
				'type'                => 'string',
				'default'             => self::SOCIAL_PROOF_DEFAULT_TRUST_MESSAGES,
				'sanitize'            => 'sanitize_social_proof_messages',
				'empty_means_default' => true,
			),
			self::SOCIAL_PROOF_ANNOUNCEMENTS_OPTION        => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_social_proof_messages',
			),
			self::SOCIAL_PROOF_DEMO_OPTION                 => array(
				'type'     => 'boolean',
				'default'  => false,
				'sanitize' => 'sanitize_bool_flag',
			),
			self::SOCIAL_PROOF_MIN_ORDER_AGE_OPTION        => array(
				'type'     => 'integer',
				'default'  => 5,
				'sanitize' => 'sanitize_social_proof_min_order_age',
			),
			self::SOCIAL_PROOF_DISPLAY_MODE_OPTION         => array(
				'type'     => 'string',
				'default'  => 'anonymous',
				'sanitize' => 'sanitize_social_proof_display_mode',
			),
			self::SOCIAL_PROOF_LOCATION_GRANULARITY_OPTION => array(
				'type'     => 'string',
				'default'  => 'city',
				'sanitize' => 'sanitize_social_proof_location_granularity',
			),
			// NOTE: the registration default ('') differs from the read
			// fallback ('check') on purpose — a MISSING row means "never
			// configured, use the bundled icon" while a stored '' means
			// "admin deliberately hid the badge". See the resolver.
			self::SOCIAL_PROOF_PURCHASE_BADGE_ICON_OPTION  => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_social_proof_purchase_badge_icon',
			),
			self::SOCIAL_PROOF_ENGAGEMENT_MIN_SALES_OPTION => array(
				'type'     => 'integer',
				'default'  => 3,
				'sanitize' => 'sanitize_social_proof_engagement_min_sales',
			),
		);
	}

	/**
	 * Read a schema-backed sub-setting with its default applied.
	 *
	 * Works identically on the frontend (where `register_setting()` has
	 * not run and `default_option_*` filters are absent) and in admin:
	 * a missing row — and, when the schema opts in via
	 * `empty_means_default`, an empty row — returns the schema default,
	 * and the value is cast to the schema type.
	 *
	 * @param string $option Option name (must exist in the schema).
	 * @return mixed Typed value or schema default.
	 */
	public static function get_setting( string $option ): mixed {
		$schema = self::get_option_schema()[ $option ] ?? null;

		if ( null === $schema ) {
			return null;
		}

		$saved = get_option( $option, null );

		if ( null === $saved || false === $saved ) {
			return $schema['default'];
		}

		if ( ! empty( $schema['empty_means_default'] ) && is_string( $saved ) && '' === trim( $saved ) ) {
			return $schema['default'];
		}

		return match ( $schema['type'] ) {
			'string'  => (string) $saved,
			'integer' => (int) $saved,
			'boolean' => (bool) $saved,
			default   => $saved,
		};
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
	 * Real purchases dominate by default — they are the strongest social
	 * proof signal, so they lead the rotation as soon as eligible orders
	 * exist. Trust messages stay enabled at lower weight so brand-new
	 * stores with zero orders still see useful content immediately
	 * (purchases are skipped silently when there are none).
	 *
	 * @return array<string, int>
	 */
	private static function get_default_social_proof_sources(): array {
		return array(
			'trust'         => 3,
			'purchases'     => 8,
			'announcements' => 0,
			'engagement'    => 2,
		);
	}

	/**
	 * Public accessor: trust messages with defaults applied.
	 *
	 * An empty saved row (e.g. the page was once saved with a blank
	 * textarea) also falls back to the defaults (via the schema's
	 * `empty_means_default`) so the textarea always shows the copy that
	 * would actually render. Disabling the source is done via the Trust
	 * weight (0), not by emptying the box.
	 *
	 * @return string Raw textarea contents (newline-separated).
	 */
	public static function get_social_proof_trust_messages(): string {
		return (string) self::get_setting( self::SOCIAL_PROOF_TRUST_MESSAGES_OPTION );
	}

	/**
	 * Public accessor: custom announcements with defaults applied.
	 *
	 * @return string Raw textarea contents (newline-separated).
	 */
	public static function get_social_proof_announcements(): string {
		return (string) self::get_setting( self::SOCIAL_PROOF_ANNOUNCEMENTS_OPTION );
	}

	/**
	 * Minimum WooCommerce lifetime sales gate for Engagement toasts.
	 *
	 * Clamped on read as well as on save so legacy rows stored before
	 * the sanitizer existed can never yield a zero/negative gate.
	 *
	 * @return int
	 */
	public static function get_social_proof_engagement_min_sales(): int {
		return max( 1, min( 999999, (int) self::get_setting( self::SOCIAL_PROOF_ENGAGEMENT_MIN_SALES_OPTION ) ) );
	}

	/**
	 * Whether the admin-only demo preview toggle is on.
	 *
	 * Note: visibility additionally requires `edit_theme_options` — see
	 * Social_Proof::should_show_demo().
	 *
	 * @return bool
	 */
	public static function is_social_proof_demo_enabled(): bool {
		return (bool) self::get_setting( self::SOCIAL_PROOF_DEMO_OPTION );
	}

	/**
	 * Minimum order age (minutes) before a purchase may surface.
	 *
	 * @return int
	 */
	public static function get_social_proof_min_order_age(): int {
		return max( 0, (int) self::get_setting( self::SOCIAL_PROOF_MIN_ORDER_AGE_OPTION ) );
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
		return (string) self::get_setting( self::SOCIAL_PROOF_DISPLAY_MODE_OPTION );
	}

	/**
	 * Social proof location granularity (e.g. 'city', 'region', 'country').
	 *
	 * @return string
	 */
	public static function get_social_proof_location_granularity(): string {
		return (string) self::get_setting( self::SOCIAL_PROOF_LOCATION_GRANULARITY_OPTION );
	}

	/**
	 * Wishlist button placement mode (e.g. 'auto', 'block').
	 *
	 * @return string
	 */
	public static function get_wishlist_button_placement(): string {
		return (string) self::get_setting( self::WISHLIST_BUTTON_PLACEMENT_OPTION );
	}

	/**
	 * Quick View media trigger style (`corner`, `bottom-bar`, or `center`).
	 *
	 * @return string
	 */
	public static function get_quick_view_trigger_style(): string {
		return (string) self::get_setting( self::QUICK_VIEW_TRIGGER_STYLE_OPTION );
	}

	/**
	 * Quick View media action stack corner (`top-right`, `top-left`, `bottom-right`, `bottom-left`).
	 *
	 * @return string
	 */
	public static function get_quick_view_trigger_position(): string {
		return (string) self::get_setting( self::QUICK_VIEW_TRIGGER_POSITION_OPTION );
	}

	/**
	 * Whether Wishlist should render inside the Quick View media action stack on listings.
	 *
	 * Requires both Quick View and Wishlist features to be enabled.
	 *
	 * @return bool
	 */
	public static function quick_view_includes_wishlist(): bool {
		if ( ! self::is_enabled( 'quick_view' ) || ! self::is_enabled( 'wishlist' ) ) {
			return false;
		}

		return 'with_wishlist' === (string) self::get_setting( self::QUICK_VIEW_MEDIA_WISHLIST_OPTION );
	}

	/**
	 * Load More display mode (e.g. 'load_more', 'infinite').
	 *
	 * @return string
	 */
	public static function get_load_more_mode(): string {
		return (string) self::get_setting( self::LOAD_MORE_MODE_OPTION );
	}

	/**
	 * Product filters layout (e.g. 'drawer', 'sidebar', 'horizontal').
	 *
	 * @return string
	 */
	public static function get_filter_layout(): string {
		return (string) self::get_setting( self::FILTER_LAYOUT_OPTION );
	}

	/**
	 * Catalog hover image entry animation slug.
	 *
	 * @return string
	 */
	public static function get_hover_image_animation(): string {
		return (string) self::get_setting( self::HOVER_IMAGE_ANIMATION_OPTION );
	}

	/**
	 * Catalog hover image exit animation slug.
	 *
	 * @return string
	 */
	public static function get_hover_image_exit_animation(): string {
		return (string) self::get_setting( self::HOVER_IMAGE_EXIT_ANIMATION_OPTION );
	}

	/**
	 * Catalog hover image exit duration in milliseconds.
	 *
	 * @return int
	 */
	public static function get_hover_image_exit_duration(): int {
		return (int) self::get_setting( self::HOVER_IMAGE_EXIT_DURATION_OPTION );
	}

	/**
	 * Product-card button text for variable products.
	 *
	 * @return string
	 */
	public static function get_variable_product_button_text(): string {
		return self::get_store_copy_text( self::VARIABLE_PRODUCT_BUTTON_TEXT_OPTION );
	}

	/**
	 * Product-card button text for simple products.
	 *
	 * @return string
	 */
	public static function get_simple_product_button_text(): string {
		return self::get_store_copy_text( self::SIMPLE_PRODUCT_BUTTON_TEXT_OPTION );
	}

	/**
	 * Product-card and sticky-cart button text for out-of-stock products.
	 *
	 * @return string
	 */
	public static function get_out_of_stock_button_text(): string {
		return self::get_store_copy_text( self::OUT_OF_STOCK_BUTTON_TEXT_OPTION );
	}

	/**
	 * Sticky add-to-cart text before choosing variable-product options.
	 *
	 * @return string
	 */
	public static function get_sticky_cart_variable_button_text(): string {
		return self::get_variable_product_button_text();
	}

	/**
	 * Product-filter trigger button text.
	 *
	 * @return string
	 */
	public static function get_filter_toggle_text(): string {
		return self::get_store_copy_text( self::FILTER_TOGGLE_TEXT_OPTION );
	}

	/**
	 * Load More button text.
	 *
	 * @return string
	 */
	public static function get_load_more_button_text(): string {
		return self::get_store_copy_text( self::LOAD_MORE_BUTTON_TEXT_OPTION );
	}

	/**
	 * Quick View trigger button text.
	 *
	 * @return string
	 */
	public static function get_quick_view_button_text(): string {
		return self::get_store_copy_text( self::QUICK_VIEW_BUTTON_TEXT_OPTION );
	}

	/**
	 * Buy Now button text.
	 *
	 * @return string
	 */
	public static function get_buy_now_button_text(): string {
		return self::get_store_copy_text( self::BUY_NOW_BUTTON_TEXT_OPTION );
	}

	/**
	 * View Cart button text.
	 *
	 * @return string
	 */
	public static function get_view_cart_button_text(): string {
		return self::get_store_copy_text( self::VIEW_CART_BUTTON_TEXT_OPTION );
	}

	/**
	 * Continue Shopping button text.
	 *
	 * @return string
	 */
	public static function get_continue_shopping_button_text(): string {
		return self::get_store_copy_text( self::CONTINUE_SHOPPING_BUTTON_TEXT_OPTION );
	}

	/**
	 * View Product button text.
	 *
	 * @return string
	 */
	public static function get_view_product_button_text(): string {
		return self::get_store_copy_text( self::VIEW_PRODUCT_BUTTON_TEXT_OPTION );
	}

	/**
	 * Back in Stock button text.
	 *
	 * @return string
	 */
	public static function get_back_in_stock_button_text(): string {
		return self::get_store_copy_text( self::BACK_IN_STOCK_BUTTON_TEXT_OPTION );
	}

	/**
	 * Wishlist button text.
	 *
	 * @return string
	 */
	public static function get_wishlist_button_text(): string {
		return self::get_store_copy_text( self::WISHLIST_BUTTON_TEXT_OPTION );
	}

	/**
	 * Check whether a specific feature is enabled.
	 *
	 * All Store Enhancements features default to OFF.
	 *
	 * @param string $feature Feature key.
	 * @return bool True if enabled.
	 */
	public static function is_enabled( string $feature ): bool {
		$options = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $options ) ) {
			return false;
		}

		return ! empty( $options[ $feature ] );
	}
}
