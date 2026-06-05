<?php
/**
 * Product Tabs shared configuration constants.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared constants for the Product Tabs feature.
 *
 * @since 1.17.0
 */
final class Product_Tabs_Config {

	/**
	 * Settings group for the global Product Tabs management page.
	 *
	 * @var string
	 */
	public const SETTINGS_GROUP = 'aggressive_apparel_product_tabs_group';

	/**
	 * Admin page slug for the global Product Tabs management page.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'aggressive-apparel-product-tabs';

	/**
	 * Option key for global tab content and display settings.
	 *
	 * @var string
	 */
	public const OPTION_KEY = 'aggressive_apparel_product_tabs';

	/**
	 * Product meta key for per-product custom tabs.
	 *
	 * @var string
	 */
	public const PRODUCT_CUSTOM_TABS_META_KEY = '_aggressive_apparel_custom_tabs';

	/**
	 * Product meta key for per-product global tab overrides.
	 *
	 * @var string
	 */
	public const PRODUCT_TAB_OVERRIDES_META_KEY = '_aggressive_apparel_tab_overrides';

	/**
	 * Valid display style values.
	 *
	 * @var string[]
	 */
	public const VALID_STYLES = array( 'accordion', 'inline', 'modern-tabs', 'scrollspy' );

	/**
	 * Valid tab content source values.
	 *
	 * @var string[]
	 */
	public const VALID_TAB_SOURCES = array( 'manual', 'product_meta', 'product_attribute' );

	/**
	 * Valid no-code custom tab layout values.
	 *
	 * @var string[]
	 */
	public const VALID_TAB_LAYOUTS = array(
		'rich_text',
		'feature_cards',
		'care_grid',
		'specs_table',
		'faq',
		'shipping_timeline',
		'icon_list',
		'custom_html',
	);

	/**
	 * Valid per-product override modes for global tabs.
	 *
	 * @var string[]
	 */
	public const VALID_OVERRIDE_MODES = array( 'inherit', 'override', 'append', 'hide' );

	/**
	 * Get custom tab source labels.
	 *
	 * @return array<string, string> Source key => label.
	 */
	public static function get_tab_source_labels(): array {
		return array(
			'manual'            => __( 'Manual / fallback content', 'aggressive-apparel' ),
			'product_meta'      => __( 'Product meta field', 'aggressive-apparel' ),
			'product_attribute' => __( 'Product attribute', 'aggressive-apparel' ),
		);
	}

	/**
	 * Get custom tab layout labels.
	 *
	 * @return array<string, string> Layout key => label.
	 */
	public static function get_tab_layout_labels(): array {
		return array(
			'rich_text'         => __( 'Rich Text', 'aggressive-apparel' ),
			'feature_cards'     => __( 'Feature Cards', 'aggressive-apparel' ),
			'care_grid'         => __( 'Care Grid', 'aggressive-apparel' ),
			'specs_table'       => __( 'Specs Table', 'aggressive-apparel' ),
			'faq'               => __( 'FAQ / Accordion', 'aggressive-apparel' ),
			'shipping_timeline' => __( 'Shipping Timeline', 'aggressive-apparel' ),
			'icon_list'         => __( 'Icon List', 'aggressive-apparel' ),
			'custom_html'       => __( 'Custom HTML', 'aggressive-apparel' ),
		);
	}

	/**
	 * Get custom tab layout help text.
	 *
	 * @return array<string, string> Layout key => help text.
	 */
	public static function get_tab_layout_help(): array {
		return array(
			'rich_text'         => __( 'Best for normal formatted content. Layout item rows are hidden.', 'aggressive-apparel' ),
			'feature_cards'     => __( 'Creates responsive cards from the no-code item rows.', 'aggressive-apparel' ),
			'care_grid'         => __( 'Creates compact care or instruction cards from item rows.', 'aggressive-apparel' ),
			'specs_table'       => __( 'Uses Label as the spec name and Text as the value.', 'aggressive-apparel' ),
			'faq'               => __( 'Uses Label as the question and Text as the answer.', 'aggressive-apparel' ),
			'shipping_timeline' => __( 'Uses Detail as timing, Label as the step, and Text as the explanation.', 'aggressive-apparel' ),
			'icon_list'         => __( 'Creates a simple icon-supported list from item rows.', 'aggressive-apparel' ),
			'custom_html'       => __( 'For advanced markup. Layout item rows are hidden.', 'aggressive-apparel' ),
		);
	}

	/**
	 * Get quick-start templates for custom tab creation.
	 *
	 * @return array<string, array<string, mixed>> Template key => tab template.
	 */
	public static function get_tab_templates(): array {
		return array(
			'blank'            => array(
				'title'   => '',
				'layout'  => 'rich_text',
				'content' => '',
				'items'   => array(),
			),
			'size_fit'         => array(
				'title'   => __( 'Size & Fit', 'aggressive-apparel' ),
				'layout'  => 'feature_cards',
				'content' => __( 'Add fit guidance, model notes, and sizing expectations for this product.', 'aggressive-apparel' ),
				'items'   => array(
					array(
						'icon'  => __( 'Fit', 'aggressive-apparel' ),
						'title' => __( 'Fit', 'aggressive-apparel' ),
						'meta'  => __( 'Silhouette', 'aggressive-apparel' ),
						'text'  => __( 'Describe whether this product runs fitted, relaxed, cropped, oversized, or true to size.', 'aggressive-apparel' ),
					),
					array(
						'icon'  => __( 'Size', 'aggressive-apparel' ),
						'title' => __( 'Sizing', 'aggressive-apparel' ),
						'meta'  => __( 'Recommendation', 'aggressive-apparel' ),
						'text'  => __( 'Add size selection guidance or measurement notes.', 'aggressive-apparel' ),
					),
				),
			),
			'shipping_returns' => array(
				'title'   => __( 'Shipping & Returns', 'aggressive-apparel' ),
				'layout'  => 'shipping_timeline',
				'content' => __( 'Use this tab for store-wide fulfillment and return expectations.', 'aggressive-apparel' ),
				'items'   => array(
					array(
						'icon'  => '1',
						'title' => __( 'Processing', 'aggressive-apparel' ),
						'meta'  => __( '1-3 business days', 'aggressive-apparel' ),
						'text'  => __( 'Orders are packed and prepared for carrier pickup.', 'aggressive-apparel' ),
					),
					array(
						'icon'  => '2',
						'title' => __( 'Shipping', 'aggressive-apparel' ),
						'meta'  => __( 'Carrier transit', 'aggressive-apparel' ),
						'text'  => __( 'Tracking details are sent as soon as the order ships.', 'aggressive-apparel' ),
					),
					array(
						'icon'  => '3',
						'title' => __( 'Returns', 'aggressive-apparel' ),
						'meta'  => __( 'Policy window', 'aggressive-apparel' ),
						'text'  => __( 'Add return eligibility, condition requirements, and exchange details.', 'aggressive-apparel' ),
					),
				),
			),
			'faq'              => array(
				'title'   => __( 'FAQ', 'aggressive-apparel' ),
				'layout'  => 'faq',
				'content' => '',
				'items'   => array(
					array(
						'icon'  => '',
						'title' => __( 'How does this fit?', 'aggressive-apparel' ),
						'meta'  => '',
						'text'  => __( 'Add a clear answer for shoppers.', 'aggressive-apparel' ),
					),
					array(
						'icon'  => '',
						'title' => __( 'How should I care for it?', 'aggressive-apparel' ),
						'meta'  => '',
						'text'  => __( 'Add washing, drying, or storage guidance.', 'aggressive-apparel' ),
					),
				),
			),
			'specs'            => array(
				'title'   => __( 'Product Specs', 'aggressive-apparel' ),
				'layout'  => 'specs_table',
				'content' => '',
				'items'   => array(
					array(
						'icon'  => '',
						'title' => __( 'Material', 'aggressive-apparel' ),
						'meta'  => '',
						'text'  => __( 'Add material or fabric details.', 'aggressive-apparel' ),
					),
					array(
						'icon'  => '',
						'title' => __( 'Weight', 'aggressive-apparel' ),
						'meta'  => '',
						'text'  => __( 'Add product weight or fabric weight.', 'aggressive-apparel' ),
					),
					array(
						'icon'  => '',
						'title' => __( 'Origin', 'aggressive-apparel' ),
						'meta'  => '',
						'text'  => __( 'Add manufacturing or sourcing details.', 'aggressive-apparel' ),
					),
				),
			),
			'care'             => array(
				'title'   => __( 'Care Instructions', 'aggressive-apparel' ),
				'layout'  => 'care_grid',
				'content' => __( 'Help customers keep the product looking right over time.', 'aggressive-apparel' ),
				'items'   => array(
					array(
						'icon'  => __( 'Cold', 'aggressive-apparel' ),
						'title' => __( 'Wash Cold', 'aggressive-apparel' ),
						'meta'  => __( 'Cleaning', 'aggressive-apparel' ),
						'text'  => __( 'Use cold water and similar colors.', 'aggressive-apparel' ),
					),
					array(
						'icon'  => __( 'Low', 'aggressive-apparel' ),
						'title' => __( 'Tumble Low', 'aggressive-apparel' ),
						'meta'  => __( 'Drying', 'aggressive-apparel' ),
						'text'  => __( 'Dry on low heat or hang dry when possible.', 'aggressive-apparel' ),
					),
					array(
						'icon'  => __( 'No', 'aggressive-apparel' ),
						'title' => __( 'Avoid Bleach', 'aggressive-apparel' ),
						'meta'  => __( 'Do not use', 'aggressive-apparel' ),
						'text'  => __( 'Avoid bleach and harsh detergents.', 'aggressive-apparel' ),
					),
				),
			),
		);
	}
}
