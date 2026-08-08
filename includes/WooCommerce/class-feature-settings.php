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
	use Store_Copy;
	use Social_Proof_Settings;


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
	 * Option key for the "starting price" prefix used by Smart Price Display.
	 *
	 * Prepended to the lowest variation price when a variable product's price
	 * range is collapsed to a single "from" figure (e.g. "From", "Starting at").
	 *
	 * @var string
	 */
	public const PRICE_STARTING_PREFIX_OPTION = 'aggressive_apparel_price_starting_prefix';

	/**
	 * Option key for the automatic sale badge text.
	 *
	 * A `{percent}` token is replaced with the whole-number discount, so
	 * "Save {percent}%" renders as "Save 20%". Text without the token is used
	 * verbatim, which is how a merchant opts out of showing the number.
	 *
	 * @var string
	 */
	public const SALE_BADGE_TEXT_OPTION = 'aggressive_apparel_sale_badge_text';

	/**
	 * Option key for the sale badge text used when no discount can be computed.
	 *
	 * Only consulted when the main text asks for a `{percent}` the product
	 * cannot supply — e.g. a variable product whose variation prices do not
	 * resolve to a positive saving.
	 *
	 * @var string
	 */
	public const SALE_BADGE_NO_DISCOUNT_TEXT_OPTION = 'aggressive_apparel_sale_badge_no_discount_text';

	/**
	 * Option key for the savings line appended to sale prices.
	 *
	 * Shares the `{percent}` token with the sale badge so the two surfaces can
	 * be worded consistently. Blank removes the savings line entirely.
	 *
	 * @var string
	 */
	public const PRICE_SAVINGS_TEXT_OPTION = 'aggressive_apparel_price_savings_text';

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
				'description' => __( 'Show "Sale", "New", "Low Stock", and "Bestseller" badges on product cards. Sale badge wording is set under Store Copy.', 'aggressive-apparel' ),
				'section'     => 'catalog',
			),
			'price_display'              => array(
				'label'       => __( 'Smart Price Display', 'aggressive-apparel' ),
				'description' => __( 'Collapse variable price ranges to a single starting price (e.g. “From $34.99”) on shop cards and product pages, and add “Save X%” on sale items. Set the prefix under Store Copy → Variable Price Prefix.', 'aggressive-apparel' ),
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
	 * Wishlist button placement mode (e.g. 'auto', 'block').
	 *
	 * @return string
	 */
	public static function get_wishlist_button_placement(): string {
		return (string) self::get_setting( self::WISHLIST_BUTTON_PLACEMENT_OPTION );
	}

	/**
	 * Quick View media trigger style (`corner` or `bottom-bar`).
	 *
	 * @return string
	 */
	public static function get_quick_view_trigger_style(): string {
		$style = (string) self::get_setting( self::QUICK_VIEW_TRIGGER_STYLE_OPTION );

		return in_array( $style, array( 'corner', 'bottom-bar' ), true ) ? $style : 'corner';
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
