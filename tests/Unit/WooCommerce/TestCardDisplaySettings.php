<?php
/**
 * Card display (touch / small-screen) option tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Custom_Badge_Taxonomy;
use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Feature_Settings_Sanitizer;
use Aggressive_Apparel\WooCommerce\Product_Badges;
use Aggressive_Apparel\WooCommerce\Quick_View_Renderer;
use WC_Product_Simple;
use WP_UnitTestCase;

/**
 * Verifies the Store Enhancements card display options reach the markup.
 */
class TestCardDisplaySettings extends WP_UnitTestCase {

	/**
	 * Reset options after each test.
	 */
	public function tearDown(): void {
		delete_option( Feature_Settings::OPTION_KEY );
		foreach ( array_keys( Feature_Settings::CARD_DISPLAY_CHOICES ) as $option ) {
			delete_option( $option );
		}
		Custom_Badge_Taxonomy::flush_system_badges_cache();

		parent::tearDown();
	}

	/**
	 * Every option registers in the schema with its first choice as default,
	 * and its sanitizer falls back to that default.
	 */
	public function test_options_register_and_sanitize_to_their_choices(): void {
		$schema    = Feature_Settings::get_option_schema();
		$sanitizer = new Feature_Settings_Sanitizer();

		foreach ( Feature_Settings::CARD_DISPLAY_CHOICES as $option => $choices ) {
			$this->assertArrayHasKey( $option, $schema );
			$this->assertSame( $choices[0], $schema[ $option ]['default'] );
			$this->assertSame( $choices[0], Feature_Settings::get_card_display_setting( $option ) );

			$sanitize = array( $sanitizer, $schema[ $option ]['sanitize'] );
			$this->assertIsCallable( $sanitize );
			$this->assertSame( $choices[0], call_user_func( $sanitize, 'nope' ) );
			$this->assertSame( end( $choices ), call_user_func( $sanitize, end( $choices ) ) );
		}
	}

	/**
	 * The toggle chip renders only in the "toggle" touch mode, and the stack
	 * carries the mode as a modifier class.
	 */
	public function test_touch_mode_reaches_the_card_action_stack(): void {
		update_option( Feature_Settings::OPTION_KEY, array( 'quick_view' => '1' ) );
		$product  = $this->create_product( 'Touch Mode Tee' );
		$renderer = new Quick_View_Renderer();

		$toggle = $renderer->build_card_actions_markup( $product );
		$this->assertStringContainsString( 'aggressive-apparel-card-actions--touch-toggle', $toggle );
		$this->assertMatchesRegularExpression( '/popovertarget="(aa-card-actions-' . $product->get_id() . '-\d+)".*id="\1"[^>]*popover="auto"/s', $toggle );
		$this->assertStringContainsString( 'aggressive-apparel-card-actions__popover', $toggle );

		update_option( Feature_Settings::QUICK_VIEW_TOUCH_MODE_OPTION, 'always' );
		$always = $renderer->build_card_actions_markup( $product );
		$this->assertStringContainsString( 'aggressive-apparel-card-actions--touch-always', $always );
		$this->assertStringNotContainsString( 'popover', $always );
	}

	/**
	 * Small-screen badge rules: the sale badge hides by default, the limit
	 * counts the remaining badges by priority, and the size modifier follows
	 * its option.
	 */
	public function test_small_screen_badge_rules(): void {
		if ( ! taxonomy_exists( Custom_Badge_Taxonomy::TAXONOMY ) ) {
			( new Custom_Badge_Taxonomy() )->register_taxonomy();
		}
		delete_option( 'aggressive_apparel_system_badges_version' );
		Custom_Badge_Taxonomy::maybe_seed_system_badges();
		Custom_Badge_Taxonomy::flush_system_badges_cache();

		// On sale and just published: the Sale and New badges both apply.
		$product = $this->create_product( 'Badge Rules Tee', '20' );
		$badges  = new Product_Badges();

		$defaults = $badges->get_badges_html( $product );
		$this->assertSame( 2, substr_count( $defaults, 'class="aggressive-apparel-product-badge ' ) );
		$this->assertSame( 1, substr_count( $defaults, 'aggressive-apparel-product-badge--hide-small' ) );
		$this->assertStringContainsString( 'aggressive-apparel-product-badge__wrapper--small-compact', $defaults );

		update_option( Feature_Settings::BADGE_SMALL_SCREEN_SALE_OPTION, 'show' );
		$limited = $badges->get_badges_html( $product );
		$this->assertSame( 1, substr_count( $limited, 'aggressive-apparel-product-badge--hide-small' ) );

		update_option( Feature_Settings::BADGE_SMALL_SCREEN_LIMIT_OPTION, 'all' );
		update_option( Feature_Settings::BADGE_SMALL_SCREEN_SIZE_OPTION, 'standard' );
		$all = $badges->get_badges_html( $product );
		$this->assertStringNotContainsString( 'aggressive-apparel-product-badge--hide-small', $all );
		$this->assertStringNotContainsString( 'aggressive-apparel-product-badge__wrapper--small-', $all );
	}

	/**
	 * Create a published simple product.
	 *
	 * @param string $name       Product name.
	 * @param string $sale_price Optional sale price (regular price is 30).
	 * @return WC_Product_Simple
	 */
	private function create_product( string $name, string $sale_price = '' ): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_name( $name );
		$product->set_status( 'publish' );
		$product->set_regular_price( '30' );
		if ( '' !== $sale_price ) {
			$product->set_sale_price( $sale_price );
		}
		$product->save();

		return $product;
	}
}
