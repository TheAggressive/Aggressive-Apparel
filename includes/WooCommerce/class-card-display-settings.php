<?php
/**
 * Card Display Settings
 *
 * Store Enhancements options for how product-card overlays behave on touch
 * devices and small screens: the Quick View / Wishlist action stack and the
 * product badges. Split out of Feature_Settings for the file-length gate;
 * Feature_Settings merges get_card_display_option_schema() into its schema.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Card_Display_Settings {
	/**
	 * Option key for the card action stack on touch devices.
	 *
	 * `always` → chips stay visible. `toggle` → one chip reveals them.
	 * `hidden` → no card actions on touch devices.
	 *
	 * @var string
	 */
	public const QUICK_VIEW_TOUCH_MODE_OPTION = 'aggressive_apparel_quick_view_touch_mode';

	/**
	 * Option key for how many badges a card shows on small screens (`all` or a count).
	 *
	 * @var string
	 */
	public const BADGE_SMALL_SCREEN_LIMIT_OPTION = 'aggressive_apparel_badge_small_screen_limit';

	/**
	 * Option key for whether the sale badge shows on small screens (`show` / `hide`).
	 *
	 * @var string
	 */
	public const BADGE_SMALL_SCREEN_SALE_OPTION = 'aggressive_apparel_badge_small_screen_sale';

	/**
	 * Option key for badge size on small screens (`standard` / `compact` / `smallest`).
	 *
	 * @var string
	 */
	public const BADGE_SMALL_SCREEN_SIZE_OPTION = 'aggressive_apparel_badge_small_screen_size';

	/**
	 * Allowed values per card display option; the first entry is the default.
	 *
	 * @var array<string, list<string>>
	 */
	public const CARD_DISPLAY_CHOICES = array(
		self::QUICK_VIEW_TOUCH_MODE_OPTION    => array( 'toggle', 'always', 'hidden' ),
		self::BADGE_SMALL_SCREEN_LIMIT_OPTION => array( 'one', 'two', 'three', 'all' ),
		self::BADGE_SMALL_SCREEN_SALE_OPTION  => array( 'hide', 'show' ),
		self::BADGE_SMALL_SCREEN_SIZE_OPTION  => array( 'compact', 'smallest', 'standard' ),
	);

	/**
	 * Schema rows for the card display options.
	 *
	 * @return array<string, array{type: string, default: mixed, sanitize: string}>
	 */
	public static function get_card_display_option_schema(): array {
		$sanitizers = array(
			self::QUICK_VIEW_TOUCH_MODE_OPTION    => 'sanitize_quick_view_touch_mode',
			self::BADGE_SMALL_SCREEN_LIMIT_OPTION => 'sanitize_badge_small_screen_limit',
			self::BADGE_SMALL_SCREEN_SALE_OPTION  => 'sanitize_badge_small_screen_sale',
			self::BADGE_SMALL_SCREEN_SIZE_OPTION  => 'sanitize_badge_small_screen_size',
		);

		$schema = array();
		foreach ( $sanitizers as $option => $sanitize ) {
			$schema[ $option ] = array(
				'type'     => 'string',
				'default'  => self::CARD_DISPLAY_CHOICES[ $option ][0],
				'sanitize' => $sanitize,
			);
		}
		return $schema;
	}

	/**
	 * Admin field per card display option: the feature it sits under, its
	 * title, choice labels (keyed and ordered like CARD_DISPLAY_CHOICES —
	 * TestCardDisplaySettings enforces it) and help text.
	 *
	 * @return array<string, array{feature: string, title: string, choices: array<string, string>, description: string}>
	 */
	public static function get_card_display_field_definitions(): array {
		return array(
			self::QUICK_VIEW_TOUCH_MODE_OPTION    => array(
				'feature'     => 'quick_view',
				'title'       => __( 'Card Buttons on Touch Devices', 'aggressive-apparel' ),
				'choices'     => array(
					'toggle' => __( 'Behind one small chip — tap it to show the actions', 'aggressive-apparel' ),
					'always' => __( 'Always visible', 'aggressive-apparel' ),
					'hidden' => __( 'Hidden (tap the product to open it)', 'aggressive-apparel' ),
				),
				'description' => __( 'Phones and tablets can’t hover, so the Quick View and Wishlist buttons would otherwise sit on every product image. Tapping the image always opens the product. Mouse users still see the buttons on hover.', 'aggressive-apparel' ),
			),
			self::BADGE_SMALL_SCREEN_LIMIT_OPTION => array(
				'feature'     => 'product_badges',
				'title'       => __( 'Badges per Product on Phones', 'aggressive-apparel' ),
				'choices'     => array(
					'one'   => __( 'One badge (highest priority)', 'aggressive-apparel' ),
					'two'   => __( 'Up to two badges', 'aggressive-apparel' ),
					'three' => __( 'Up to three badges', 'aggressive-apparel' ),
					'all'   => __( 'All badges', 'aggressive-apparel' ),
				),
				'description' => __( 'On screens under 600px wide, show only this many badges per product, chosen by each badge’s priority (Products → Badges). Larger screens show every badge.', 'aggressive-apparel' ),
			),
			self::BADGE_SMALL_SCREEN_SALE_OPTION  => array(
				'feature'     => 'product_badges',
				'title'       => __( 'Sale Badge on Phones', 'aggressive-apparel' ),
				'choices'     => array(
					'hide' => __( 'Hide — the price already shows the sale', 'aggressive-apparel' ),
					'show' => __( 'Show', 'aggressive-apparel' ),
				),
				'description' => __( 'Sale prices are shown crossed out next to the new price. To also show the percentage there, turn on Smart Price Display. A hidden sale badge frees its slot for the next badge.', 'aggressive-apparel' ),
			),
			self::BADGE_SMALL_SCREEN_SIZE_OPTION  => array(
				'feature'     => 'product_badges',
				'title'       => __( 'Badge Size on Phones', 'aggressive-apparel' ),
				'choices'     => array(
					'compact'  => __( 'Compact (85%)', 'aggressive-apparel' ),
					'smallest' => __( 'Smallest (75%)', 'aggressive-apparel' ),
					'standard' => __( 'Same as large screens', 'aggressive-apparel' ),
				),
				'description' => __( 'Scales every badge on screens under 600px wide, keeping each badge’s own design.', 'aggressive-apparel' ),
			),
		);
	}

	/**
	 * A card display option's value, constrained to its allowed choices.
	 *
	 * @param string $option Option key from CARD_DISPLAY_CHOICES.
	 * @return string
	 */
	public static function get_card_display_setting( string $option ): string {
		$choices = self::CARD_DISPLAY_CHOICES[ $option ] ?? array( '' );
		$value   = (string) self::get_setting( $option );

		return in_array( $value, $choices, true ) ? $value : $choices[0];
	}

	/**
	 * Card action stack behaviour on touch devices.
	 *
	 * @return string `always`, `toggle`, or `hidden`.
	 */
	public static function get_quick_view_touch_mode(): string {
		return self::get_card_display_setting( self::QUICK_VIEW_TOUCH_MODE_OPTION );
	}

	/**
	 * Maximum badges per card on small screens; 0 means no limit.
	 *
	 * @return int
	 */
	public static function get_badge_small_screen_limit(): int {
		$limits = array(
			'one'   => 1,
			'two'   => 2,
			'three' => 3,
		);

		return $limits[ self::get_card_display_setting( self::BADGE_SMALL_SCREEN_LIMIT_OPTION ) ] ?? 0;
	}

	/**
	 * Whether the sale badge is hidden on small screens.
	 *
	 * @return bool
	 */
	public static function hides_sale_badge_on_small_screens(): bool {
		return 'hide' === self::get_card_display_setting( self::BADGE_SMALL_SCREEN_SALE_OPTION );
	}

	/**
	 * Badge size on small screens.
	 *
	 * @return string `standard`, `compact`, or `smallest`.
	 */
	public static function get_badge_small_screen_size(): string {
		return self::get_card_display_setting( self::BADGE_SMALL_SCREEN_SIZE_OPTION );
	}
}
