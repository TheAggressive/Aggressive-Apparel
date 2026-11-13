<?php
/**
 * Feature catalogue
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The store-enhancement toggles: the settings-page sections and every
 * feature's label, description and section. Data only; Feature_Settings
 * reads it (is_enabled(), the settings page).
 */
trait Feature_Catalog {

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
				'label'       => __( 'Size Guides & Specs', 'aggressive-apparel' ),
				'description' => __( 'Size charts with an inch/cm switch, plus fit and fabric specs, managed on Products → Size & Specs and assigned to products or categories.', 'aggressive-apparel' ),
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
				'description' => __( 'Save products for guests and sync signed-in wishlists with WooCommerce.', 'aggressive-apparel' ),
				'section'     => 'engagement',
			),
			'social_proof'               => array(
				'label'       => __( 'Social Proof Notifications', 'aggressive-apparel' ),
				'description' => __( 'Show recent purchase toast notifications to build urgency.', 'aggressive-apparel' ),
				'section'     => 'engagement',
			),
			'back_in_stock'              => array(
				'label'       => __( 'Back in Stock Notifications', 'aggressive-apparel' ),
				'description' => __( 'Show a signup form on out-of-stock products and sizes. The waitlist and emails are WooCommerce\'s own Back in Stock Notifications, which must be turned on.', 'aggressive-apparel' ),
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
}
