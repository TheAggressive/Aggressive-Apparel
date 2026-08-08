<?php
/**
 * Store Copy (button text + editorial copy) settings.
 *
 * Extracted from Feature_Settings to keep each file under the length cap.
 * Composed into Feature_Settings via `use`; all Feature_Settings::method()
 * callers are unchanged.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Store_Copy {
	/**
	 * Per-locale cache of the built definitions list.
	 *
	 * Definitions are static metadata, but building them runs ~50 `__()` calls
	 * and every read (`get_store_copy_text()`) rebuilds the whole list to find
	 * one entry — which product cards do per product. Keying by locale keeps
	 * `switch_to_locale()` correct.
	 *
	 * @var array<string, array<string, array<string, mixed>>>
	 */
	private static array $store_copy_definitions_cache = array();

	/**
	 * Storefront microcopy settings with labels, defaults, and admin help.
	 *
	 * @return array<string, array{option: string, label: string, default: string, description: string, suggestions?: list<string>, placeholder?: string, allow_empty?: bool, tokens?: array<string, string>}>
	 */
	public static function get_store_copy_definitions(): array {
		$locale = determine_locale();

		if ( ! isset( self::$store_copy_definitions_cache[ $locale ] ) ) {
			self::$store_copy_definitions_cache[ $locale ] = self::build_store_copy_definitions();
		}

		/**
		 * Cached value is the built list; the property is loosely typed so the
		 * trait can hold it without repeating the full shape.
		 *
		 * @var array<string, array{option: string, label: string, default: string, description: string, suggestions?: list<string>, placeholder?: string, allow_empty?: bool, tokens?: array<string, string>}>
		 */
		return self::$store_copy_definitions_cache[ $locale ];
	}

	/**
	 * Build the definitions list.
	 *
	 * The optional `suggestions` list surfaces popular phrasings in a datalist
	 * dropdown while still allowing free text. `allow_empty` lets a blank saved
	 * value mean "no copy" instead of falling back to the default, and
	 * `placeholder` overrides the empty-field hint (defaults to the default text).
	 * `tokens` maps each supported placeholder to a sample value: declaring it
	 * turns on save-time validation and the admin preview, so a typo like
	 * `{percnt}` is rejected instead of shipping to the storefront verbatim.
	 *
	 * @return array<string, array{option: string, label: string, default: string, description: string, suggestions?: list<string>, placeholder?: string, allow_empty?: bool, tokens?: array<string, string>}>
	 */
	private static function build_store_copy_definitions(): array {
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
			'price_starting_prefix'         => array(
				'option'      => self::PRICE_STARTING_PREFIX_OPTION,
				'label'       => __( 'Variable Price Prefix', 'aggressive-apparel' ),
				'default'     => __( 'From', 'aggressive-apparel' ),
				'description' => __( 'Requires Smart Price Display. Word shown before the lowest price when a variable product’s range is collapsed to a single figure — e.g. “From $34.99”. Leave blank to show just the price with no prefix. The amount always uses your WooCommerce currency.', 'aggressive-apparel' ),
				'placeholder' => __( 'No prefix', 'aggressive-apparel' ),
				'allow_empty' => true,
				'suggestions' => array(
					__( 'From', 'aggressive-apparel' ),
					__( 'Starting at', 'aggressive-apparel' ),
					__( 'As low as', 'aggressive-apparel' ),
					__( 'Priced from', 'aggressive-apparel' ),
					__( 'Now from', 'aggressive-apparel' ),
				),
			),
			'sale_badge_text'               => array(
				'option'      => self::SALE_BADGE_TEXT_OPTION,
				/* translators: {percent} is a literal token and must not be translated. */
				'default'     => __( '-{percent}%', 'aggressive-apparel' ),
				'label'       => __( 'Sale Badge Text', 'aggressive-apparel' ),
				'description' => __( 'Requires Product Badges. Text on the automatic sale badge. Write {percent} where the discount number should go — “Save {percent}%” shows as “Save 20%”. Leave the token out for wording that never mentions a number, such as “Now on Sale”.', 'aggressive-apparel' ),
				'tokens'      => array( Sale_Pricing::PERCENT_TOKEN => '20' ),
				'suggestions' => array(
					/* translators: {percent} is a literal token and must not be translated. */
					__( '-{percent}%', 'aggressive-apparel' ),
					/* translators: {percent} is a literal token and must not be translated. */
					__( '{percent}% Off', 'aggressive-apparel' ),
					/* translators: {percent} is a literal token and must not be translated. */
					__( 'Save {percent}%', 'aggressive-apparel' ),
					__( 'On Sale', 'aggressive-apparel' ),
					__( 'Now on Sale', 'aggressive-apparel' ),
				),
			),
			'sale_badge_no_discount_text'   => array(
				'option'      => self::SALE_BADGE_NO_DISCOUNT_TEXT_OPTION,
				'default'     => __( 'On Sale', 'aggressive-apparel' ),
				'label'       => __( 'Sale Badge Text (no discount)', 'aggressive-apparel' ),
				'description' => __( 'Fallback wording for products that are on sale but have no single discount figure — most often variable products whose variations are reduced by different amounts. Only used when Sale Badge Text asks for a percentage, so it cannot itself contain one.', 'aggressive-apparel' ),
				'tokens'      => array(),
				'suggestions' => array(
					__( 'On Sale', 'aggressive-apparel' ),
					__( 'Now on Sale', 'aggressive-apparel' ),
					__( 'Sale', 'aggressive-apparel' ),
					__( 'Reduced', 'aggressive-apparel' ),
				),
			),
			'price_savings_text'            => array(
				'option'      => self::PRICE_SAVINGS_TEXT_OPTION,
				/* translators: {percent} is a literal token and must not be translated. */
				'default'     => __( 'Save {percent}%', 'aggressive-apparel' ),
				'label'       => __( 'Price Savings Text', 'aggressive-apparel' ),
				'description' => __( 'Note appended to the price of a reduced product. Uses the same {percent} token as the sale badge, so both can be worded the same way. Leave blank to show the price on its own. Products with no single discount figure never show this note.', 'aggressive-apparel' ),
				'placeholder' => __( 'No savings note', 'aggressive-apparel' ),
				'allow_empty' => true,
				'tokens'      => array( Sale_Pricing::PERCENT_TOKEN => '20' ),
				'suggestions' => array(
					/* translators: {percent} is a literal token and must not be translated. */
					__( 'Save {percent}%', 'aggressive-apparel' ),
					/* translators: {percent} is a literal token and must not be translated. */
					__( '{percent}% off', 'aggressive-apparel' ),
					/* translators: {percent} is a literal token and must not be translated. */
					__( 'You save {percent}%', 'aggressive-apparel' ),
				),
			),
		);
	}

	/**
	 * Placeholders a merchant wrote that the field does not understand.
	 *
	 * Callers only check fields that declare a `tokens` key, so ordinary copy is
	 * free to contain braces. An empty allow-list therefore means "this field
	 * supports no tokens" — which is how the no-discount fallback rejects a
	 * `{percent}` it could never resolve.
	 *
	 * @param string             $text    Candidate copy.
	 * @param array<int, string> $allowed Tokens the field supports, e.g. `{percent}`.
	 * @return list<string> Unrecognised tokens, in order of appearance.
	 */
	public static function find_unknown_copy_tokens( string $text, array $allowed ): array {
		preg_match_all( '/\{[A-Za-z_][A-Za-z0-9_]*\}/', $text, $matches );

		return array_values( array_unique( array_diff( $matches[0], $allowed ) ) );
	}

	/**
	 * Resolve a Store Copy value by option name, falling back to its default.
	 *
	 * @param string $option_name Store Copy option key.
	 * @return string
	 */
	public static function get_store_copy_text( string $option_name ): string {
		$definition  = self::get_store_copy_definition_by_option( $option_name );
		$default     = isset( $definition['default'] ) ? $definition['default'] : '';
		$allow_empty = ! empty( $definition['allow_empty'] );
		$value       = self::get_store_copy_base_text( $option_name, $default, $allow_empty );
		$translated  = self::translate_store_copy_text( $value, $option_name );

		/**
		 * Filter the final Store Copy text used by WooCommerce enhancement UI.
		 *
		 * @param string              $translated  Translated Store Copy value.
		 * @param string              $option_name Store Copy option key.
		 * @param string              $default     Registered default value.
		 * @param string              $value       Saved/default base value before multilingual translation.
		 * @param array<string,mixed> $definition  Store Copy definition metadata.
		 */
		$filtered = apply_filters(
			'aggressive_apparel_store_copy_text',
			$translated,
			$option_name,
			$default,
			$value,
			$definition
		);

		// `allow_empty` fields (e.g. the variable-price prefix) treat a blank
		// value as a deliberate "no prefix" choice, so the default is NOT forced
		// back in. An empty fallback keeps blank blank while still sanitising
		// (and defending against a non-string filter return). Every other field
		// keeps blank→default.
		if ( $allow_empty ) {
			return self::normalize_store_copy_text( $filtered, '' );
		}

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
	 * Keys are optional so the empty "not found" return type-checks alongside a
	 * full definition (which may include suggestions/placeholder/allow_empty).
	 *
	 * @param string $option_name Store Copy option key.
	 * @return array{option?: string, label?: string, default?: string, description?: string, suggestions?: list<string>, placeholder?: string, allow_empty?: bool}
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
	 * When `$allow_empty` is true, a never-saved option resolves to the default
	 * but an option that has been *saved blank* resolves to an empty string —
	 * so the merchant can intentionally opt out of the copy (e.g. no price
	 * prefix). Otherwise a blank value always falls back to the default.
	 *
	 * @param string $option_name Store Copy option key.
	 * @param string $fallback    Registered default value.
	 * @param bool   $allow_empty Preserve a deliberately-blank saved value.
	 * @return string
	 */
	private static function get_store_copy_base_text( string $option_name, string $fallback, bool $allow_empty = false ): string {
		if ( $allow_empty ) {
			// `null` sentinel distinguishes "row absent" (→ default) from a
			// stored empty string (→ intentional no-copy). On the frontend
			// register_setting has not run, so an absent row returns null; in
			// admin its default filter returns the fallback — both yield default.
			$value = get_option( $option_name, null );

			if ( null === $value || false === $value ) {
				return $fallback;
			}

			return is_string( $value ) ? trim( wp_strip_all_tags( $value ) ) : '';
		}

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
	 * "Starting price" prefix for collapsed variable-product prices.
	 *
	 * @return string
	 */
	public static function get_price_starting_prefix(): string {
		return self::get_store_copy_text( self::PRICE_STARTING_PREFIX_OPTION );
	}

	/**
	 * Automatic sale badge text, possibly containing a `{percent}` token.
	 *
	 * @return string
	 */
	public static function get_sale_badge_text(): string {
		return self::get_store_copy_text( self::SALE_BADGE_TEXT_OPTION );
	}

	/**
	 * Sale badge text for products with no computable discount percentage.
	 *
	 * @return string
	 */
	public static function get_sale_badge_no_discount_text(): string {
		return self::get_store_copy_text( self::SALE_BADGE_NO_DISCOUNT_TEXT_OPTION );
	}

	/**
	 * Savings note appended to a reduced price, possibly containing `{percent}`.
	 *
	 * @return string
	 */
	public static function get_price_savings_text(): string {
		return self::get_store_copy_text( self::PRICE_SAVINGS_TEXT_OPTION );
	}
}
