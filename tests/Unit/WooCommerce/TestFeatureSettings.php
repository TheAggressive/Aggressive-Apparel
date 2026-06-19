<?php
/**
 * Test Feature Settings Class
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use WP_UnitTestCase;
use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Feature_Settings_Sanitizer;

/**
 * Feature Settings Test Case
 */
class TestFeatureSettings extends WP_UnitTestCase {
	/**
	 * Tear down test
	 */
	public function tearDown(): void {
		foreach ( Feature_Settings::get_store_copy_definitions() as $definition ) {
			delete_option( $definition['option'] );
		}

		remove_all_filters( 'aggressive_apparel_store_copy_text' );
		remove_all_filters( 'wpml_register_single_string' );
		remove_all_filters( 'wpml_translate_single_string' );

		parent::tearDown();
	}

	/**
	 * Test Store Copy section registration
	 */
	public function test_store_copy_section_is_registered() {
		$sections = Feature_Settings::get_sections();

		$this->assertArrayHasKey( 'copy', $sections, 'Store Copy section should be registered' );
		$this->assertSame( 'Store Copy', $sections['copy']['label'], 'Store Copy section label should match' );
	}

	/**
	 * Test Store Copy defaults
	 */
	public function test_store_copy_defaults_are_used() {
		$this->assertSame( 'Choose', Feature_Settings::get_variable_product_button_text(), 'Variable product copy should default to Choose' );
		$this->assertSame( 'Choose', Feature_Settings::get_sticky_cart_variable_button_text(), 'Sticky Cart variable copy should follow the variable product default' );
		$this->assertSame( 'Filter', Feature_Settings::get_filter_toggle_text(), 'Filter toggle copy should default to Filter' );
		$this->assertSame( 'Load More Products', Feature_Settings::get_load_more_button_text(), 'Load More copy should use the existing default' );
		$this->assertSame( 'Quick View', Feature_Settings::get_quick_view_button_text(), 'Quick View copy should use the existing default' );
		$this->assertSame( 'Buy Now', Feature_Settings::get_buy_now_button_text(), 'Buy Now copy should use the existing default' );
		$this->assertSame( 'View Cart', Feature_Settings::get_view_cart_button_text(), 'View Cart copy should use the existing default' );
		$this->assertSame( 'Continue Shopping', Feature_Settings::get_continue_shopping_button_text(), 'Continue Shopping copy should use the existing default' );
		$this->assertSame( 'View Full Product', Feature_Settings::get_view_product_button_text(), 'View Product copy should use the existing default' );
		$this->assertSame( 'Notify Me', Feature_Settings::get_back_in_stock_button_text(), 'Back in Stock copy should use the existing default' );
		$this->assertSame( 'Add to Wishlist', Feature_Settings::get_wishlist_button_text(), 'Wishlist copy should use the existing default' );
	}

	/**
	 * Test custom Store Copy values are used
	 */
	public function test_custom_store_copy_values_are_used() {
		update_option( Feature_Settings::VARIABLE_PRODUCT_BUTTON_TEXT_OPTION, 'Pick' );
		update_option( Feature_Settings::BUY_NOW_BUTTON_TEXT_OPTION, 'Checkout' );
		update_option( Feature_Settings::BACK_IN_STOCK_BUTTON_TEXT_OPTION, 'Email Me' );
		update_option( Feature_Settings::WISHLIST_BUTTON_TEXT_OPTION, 'Save' );

		$this->assertSame( 'Pick', Feature_Settings::get_variable_product_button_text(), 'Custom Variable Product copy should be used' );
		$this->assertSame( 'Pick', Feature_Settings::get_sticky_cart_variable_button_text(), 'Sticky Cart variable copy should follow Variable Product copy' );
		$this->assertSame( 'Checkout', Feature_Settings::get_buy_now_button_text(), 'Custom Buy Now copy should be used' );
		$this->assertSame( 'Email Me', Feature_Settings::get_back_in_stock_button_text(), 'Custom Back in Stock copy should be used' );
		$this->assertSame( 'Save', Feature_Settings::get_wishlist_button_text(), 'Custom Wishlist copy should be used' );
	}

	/**
	 * Test blank Store Copy values fall back to defaults
	 */
	public function test_blank_store_copy_values_fall_back_to_defaults() {
		update_option( Feature_Settings::VARIABLE_PRODUCT_BUTTON_TEXT_OPTION, '   ' );

		$this->assertSame( 'Choose', Feature_Settings::get_variable_product_button_text(), 'Blank copy should fall back to default' );
	}

	/**
	 * Test Store Copy values are filterable
	 */
	public function test_store_copy_values_are_filterable() {
		update_option( Feature_Settings::VARIABLE_PRODUCT_BUTTON_TEXT_OPTION, 'Pick' );

		add_filter(
			'aggressive_apparel_store_copy_text',
			static function ( $text, $option_name, $default, $base_value, $definition ) {
				if ( Feature_Settings::VARIABLE_PRODUCT_BUTTON_TEXT_OPTION !== $option_name ) {
					return $text;
				}

				if ( 'Choose' !== $default || 'Pick' !== $base_value || 'Variable Product Button Text' !== $definition['label'] ) {
					return $text;
				}

				return 'Select';
			},
			10,
			5
		);

		$this->assertSame( 'Select', Feature_Settings::get_variable_product_button_text(), 'Store Copy text should be filterable after translation' );
	}

	/**
	 * Test Store Copy values can be translated by WPML String Translation
	 */
	public function test_store_copy_values_use_wpml_string_translation_filter() {
		update_option( Feature_Settings::BUY_NOW_BUTTON_TEXT_OPTION, 'Checkout' );

		add_filter(
			'wpml_translate_single_string',
			static function ( $text, $context, $name ) {
				if (
					Feature_Settings::STORE_COPY_TRANSLATION_CONTEXT === $context &&
					Feature_Settings::BUY_NOW_BUTTON_TEXT_OPTION === $name
				) {
					return 'Comprar';
				}

				return $text;
			},
			10,
			3
		);

		$this->assertSame( 'Comprar', Feature_Settings::get_buy_now_button_text(), 'Store Copy text should pass through WPML String Translation' );
	}

	/**
	 * Test Store Copy values are registered for multilingual plugins
	 */
	public function test_store_copy_strings_are_registered_for_multilingual_plugins() {
		$registered = array();

		update_option( Feature_Settings::BACK_IN_STOCK_BUTTON_TEXT_OPTION, 'Email Me' );

		add_action(
			'wpml_register_single_string',
			static function ( $context, $name, $value ) use ( &$registered ): void {
				$registered[ $name ] = array(
					'context' => $context,
					'value'   => $value,
				);
			},
			10,
			3
		);

		Feature_Settings::register_store_copy_translation_strings();

		$this->assertArrayHasKey( Feature_Settings::BACK_IN_STOCK_BUTTON_TEXT_OPTION, $registered, 'Back in Stock copy should be registered for translation' );
		$this->assertSame( Feature_Settings::STORE_COPY_TRANSLATION_CONTEXT, $registered[ Feature_Settings::BACK_IN_STOCK_BUTTON_TEXT_OPTION ]['context'], 'Store Copy strings should share a translation context' );
		$this->assertSame( 'Email Me', $registered[ Feature_Settings::BACK_IN_STOCK_BUTTON_TEXT_OPTION ]['value'], 'Saved Store Copy text should be registered as the base string' );
	}

	/**
	 * Test Store Copy text sanitization
	 */
	public function test_store_copy_text_sanitization() {
		$sanitizer = new Feature_Settings_Sanitizer();
		$result    = $sanitizer->sanitize_store_copy_text( '<strong>' . str_repeat( 'A', 80 ) . '</strong>' );

		$this->assertLessThanOrEqual( 60, strlen( $result ), 'Store Copy text should be capped at 60 characters' );
		$this->assertStringNotContainsString( '<strong>', $result, 'Store Copy text should strip markup' );
	}
}
