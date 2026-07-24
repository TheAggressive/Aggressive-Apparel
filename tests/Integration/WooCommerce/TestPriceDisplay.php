<?php
/**
 * Smart Price Display integration tests.
 *
 * @package Aggressive_Apparel\Tests\Integration\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Integration\WooCommerce;

use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Price_Display;
use WP_UnitTestCase;

/**
 * Covers collapsing the variable-product price range to a single "from" price.
 */
class TestPriceDisplay extends WP_UnitTestCase {

	/** Service under test. */
	private Price_Display $price_display;

	/**
	 * Skip when WooCommerce is unavailable; boot the service otherwise.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( '\WC_Product_Variable' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}

		// The collapse is gated on the Smart Price Display feature flag.
		update_option( Feature_Settings::OPTION_KEY, array( 'price_display' => true ) );

		$this->price_display = new Price_Display();
	}

	/**
	 * Reset options touched by these tests.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( Feature_Settings::PRICE_STARTING_PREFIX_OPTION );
		delete_option( Feature_Settings::OPTION_KEY );

		parent::tearDown();
	}

	/**
	 * Build a variable product with one variation per supplied price.
	 *
	 * @param array<int, string> $prices Regular prices, one variation each.
	 * @return \WC_Product_Variable Reloaded parent product.
	 */
	private function create_variable_product( array $prices ): \WC_Product_Variable {
		$product = new \WC_Product_Variable();
		$product->set_name( 'Aggressive Tee' );

		$attribute = new \WC_Product_Attribute();
		$attribute->set_name( 'size' );
		$attribute->set_options( array_map( static fn( $i ) => 'v' . $i, array_keys( $prices ) ) );
		$attribute->set_visible( true );
		$attribute->set_variation( true );
		$product->set_attributes( array( $attribute ) );

		$product_id = $product->save();

		foreach ( $prices as $index => $price ) {
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $product_id );
			$variation->set_attributes( array( 'size' => 'v' . $index ) );
			$variation->set_regular_price( (string) $price );
			$variation->save();
		}

		// Reload so cached variation prices reflect the saved variations.
		$reloaded = wc_get_product( $product_id );
		$this->assertInstanceOf( \WC_Product_Variable::class, $reloaded );

		return $reloaded;
	}

	/**
	 * Build a variable product from explicit [regular, sale] pairs per variation.
	 *
	 * @param array<int, array{0: string, 1: string}> $variations Regular + sale price per variation ('' sale = none).
	 * @return \WC_Product_Variable Reloaded parent product.
	 */
	private function create_variable_product_with_sales( array $variations ): \WC_Product_Variable {
		$product = new \WC_Product_Variable();
		$product->set_name( 'Aggressive Tee' );

		$attribute = new \WC_Product_Attribute();
		$attribute->set_name( 'size' );
		$attribute->set_options( array_map( static fn( $i ) => 'v' . $i, array_keys( $variations ) ) );
		$attribute->set_visible( true );
		$attribute->set_variation( true );
		$product->set_attributes( array( $attribute ) );

		$product_id = $product->save();

		foreach ( $variations as $index => $pair ) {
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $product_id );
			$variation->set_attributes( array( 'size' => 'v' . $index ) );
			$variation->set_regular_price( $pair[0] );
			if ( '' !== $pair[1] ) {
				$variation->set_sale_price( $pair[1] );
			}
			$variation->save();
		}

		$reloaded = wc_get_product( $product_id );
		$this->assertInstanceOf( \WC_Product_Variable::class, $reloaded );

		return $reloaded;
	}

	/**
	 * Plain-text digits of a wc_price() amount, for locale-agnostic assertions.
	 *
	 * @param float $amount Raw amount.
	 * @return string
	 */
	private function money_text( float $amount ): string {
		return $this->to_text( wc_price( $amount ) );
	}

	/**
	 * Normalize price HTML to visible plain text (tags stripped, entities decoded).
	 *
	 * @param string $html Price markup.
	 * @return string
	 */
	private function to_text( string $html ): string {
		return trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * A spread of variation prices collapses to the prefixed minimum only.
	 *
	 * @return void
	 */
	public function test_range_collapses_to_from_minimum(): void {
		$product = $this->create_variable_product( array( '34.99', '42.99' ) );

		$text = $this->to_text( $this->price_display->format_price_html( $product->get_price_html(), $product ) );

		$this->assertStringContainsString( 'From', $text, 'Default prefix should be applied' );
		$this->assertStringContainsString( $this->money_text( 34.99 ), $text, 'Minimum price should be shown' );
		$this->assertStringNotContainsString( $this->money_text( 42.99 ), $text, 'Maximum price should be hidden' );
	}

	/**
	 * A deliberately blank prefix collapses to the bare amount — no prefix word
	 * and no prefix wrapper markup.
	 *
	 * @return void
	 */
	public function test_blank_prefix_shows_amount_only(): void {
		update_option( Feature_Settings::PRICE_STARTING_PREFIX_OPTION, '' );

		$product = $this->create_variable_product( array( '34.99', '42.99' ) );
		$html    = $this->price_display->format_price_html( $product->get_price_html(), $product );
		$text    = $this->to_text( $html );

		$this->assertStringContainsString( $this->money_text( 34.99 ), $text );
		$this->assertStringNotContainsString( 'From', $text );
		$this->assertStringNotContainsString( 'aggressive-apparel-price-from-prefix', $html, 'No prefix wrapper should be emitted' );
		$this->assertStringNotContainsString( $this->money_text( 42.99 ), $text );
	}

	/**
	 * The prefix is driven by Store Copy and never hardcodes a currency symbol.
	 *
	 * @return void
	 */
	public function test_custom_prefix_is_used(): void {
		update_option( Feature_Settings::PRICE_STARTING_PREFIX_OPTION, 'Starting at' );

		$product = $this->create_variable_product( array( '10.00', '25.00' ) );

		$text = $this->to_text( $this->price_display->format_price_html( $product->get_price_html(), $product ) );

		$this->assertStringContainsString( 'Starting at', $text );
		$this->assertStringNotContainsString( 'From', $text );
		$this->assertStringContainsString( $this->money_text( 10.00 ), $text );
	}

	/**
	 * When every variation shares one price there is no range to collapse,
	 * so the default (single, un-prefixed) price HTML is preserved.
	 *
	 * @return void
	 */
	public function test_equal_prices_are_left_untouched(): void {
		$product = $this->create_variable_product( array( '20.00', '20.00' ) );

		$default = $product->get_price_html();
		$html    = $this->price_display->format_price_html( $default, $product );

		$this->assertStringNotContainsString( 'From', $html, 'No prefix when there is no spread' );
		$this->assertSame( $default, $html, 'Single-price ranges keep the native price HTML' );
	}

	/**
	 * Simple products are never rewritten by the variable-range collapse.
	 *
	 * @return void
	 */
	public function test_simple_products_are_untouched(): void {
		$product = new \WC_Product_Simple();
		$product->set_regular_price( '19.00' );
		$product->save();

		$default = $product->get_price_html();
		$html    = $this->price_display->format_price_html( $default, $product );

		$this->assertSame( $default, $html, 'Simple product price HTML should be unchanged' );
		$this->assertStringNotContainsString( 'From', $html );
	}

	/**
	 * With Smart Price Display off, the native min–max range is preserved.
	 *
	 * @return void
	 */
	public function test_disabled_feature_keeps_native_range(): void {
		update_option( Feature_Settings::OPTION_KEY, array( 'price_display' => false ) );

		$product = $this->create_variable_product( array( '34.99', '42.99' ) );

		$default = $product->get_price_html();
		$html    = $this->price_display->format_price_html( $default, $product );

		$this->assertSame( $default, $html, 'Range should be untouched when the feature is off' );
		$this->assertNull( \Aggressive_Apparel\WooCommerce\Price_Display::from_price_text( $product ) );
	}

	/**
	 * The shared plain-text helper backs the sticky cart and Quick View so
	 * every surface collapses identically. It gates on the feature flag and
	 * exposes the regular (pre-sale) minimum for struck-through pricing.
	 *
	 * @return void
	 */
	public function test_plain_text_helper_matches_and_gates(): void {
		$product = $this->create_variable_product( array( '34.99', '42.99' ) );

		$this->assertSame(
			'From ' . $this->money_text( 34.99 ),
			Price_Display::from_price_text( $product ),
			'Plain-text helper should collapse to the prefixed minimum'
		);

		$this->assertSame(
			'From ' . $this->money_text( 34.99 ),
			Price_Display::from_price_text( $product, true ),
			'Regular variant collapses to the regular minimum when not on sale'
		);
	}

	/**
	 * On sale, the struck-through "was" price is the regular price of the SAME
	 * cheapest variation the "From" figure represents — not the global minimum
	 * regular, which could belong to a different (pricier-when-active) variation.
	 *
	 * @return void
	 */
	public function test_on_sale_regular_reflects_cheapest_variation(): void {
		// v0: regular 45, sale 30 → active 30 (the cheapest, so the "From" price).
		// v1: regular 40, no sale → active 40.
		// Global min regular is 40 (v1), but the cheapest ACTIVE variation is v0,
		// whose regular is 45 — that is the correct strikethrough.
		$product = $this->create_variable_product_with_sales(
			array(
				array( '45.00', '30.00' ),
				array( '40.00', '' ),
			)
		);

		$this->assertSame(
			'From ' . $this->money_text( 30.00 ),
			Price_Display::from_price_text( $product ),
			'Active "From" price is the lowest current price'
		);

		$this->assertSame(
			'From ' . $this->money_text( 45.00 ),
			Price_Display::from_price_text( $product, true ),
			'Regular "was" price is the cheapest active variation\'s regular, not the global min regular'
		);

		// Both collapse together — a caller can never mix a collapsed price with a
		// native range.
		$this->assertNotNull( Price_Display::from_price_text( $product ) );
		$this->assertNotNull( Price_Display::from_price_text( $product, true ) );
	}
}
