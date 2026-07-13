<?php
/**
 * Quick View card action stack unit tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Block_Render_Helper;
use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Feature_Settings_Sanitizer;
use Aggressive_Apparel\WooCommerce\Quick_View;
use ReflectionMethod;
use WC_Product_Simple;
use WP_UnitTestCase;

/**
 * Verifies Store Enhancements options and injected card markup.
 */
class TestQuickViewCardActions extends WP_UnitTestCase {

	/**
	 * Reset options after each test.
	 */
	public function tearDown(): void {
		delete_option( Feature_Settings::OPTION_KEY );
		delete_option( Feature_Settings::QUICK_VIEW_TRIGGER_STYLE_OPTION );
		delete_option( Feature_Settings::QUICK_VIEW_TRIGGER_POSITION_OPTION );
		delete_option( Feature_Settings::QUICK_VIEW_MEDIA_WISHLIST_OPTION );

		parent::tearDown();
	}

	/**
	 * Invalid style / position values fall back safely.
	 */
	public function test_quick_view_option_sanitizers(): void {
		$sanitizer = new Feature_Settings_Sanitizer();

		$this->assertSame( 'corner', $sanitizer->sanitize_quick_view_trigger_style( 'nope' ) );
		$this->assertSame( 'bottom-bar', $sanitizer->sanitize_quick_view_trigger_style( 'bottom-bar' ) );
		$this->assertSame( 'top-right', $sanitizer->sanitize_quick_view_trigger_position( 'side' ) );
		$this->assertSame( 'bottom-left', $sanitizer->sanitize_quick_view_trigger_position( 'bottom-left' ) );
		$this->assertSame( 'with_wishlist', $sanitizer->sanitize_quick_view_media_wishlist( 'maybe' ) );
		$this->assertSame( 'quick_view_only', $sanitizer->sanitize_quick_view_media_wishlist( 'quick_view_only' ) );
	}

	/**
	 * Card stack markup includes role, style modifiers, and no IA click binding.
	 */
	public function test_card_actions_markup_structure(): void {
		if ( ! class_exists( WC_Product_Simple::class ) ) {
			$this->markTestSkipped( 'WooCommerce is required.' );
		}

		update_option(
			Feature_Settings::OPTION_KEY,
			array(
				'quick_view' => '1',
				'wishlist'   => '1',
			)
		);
		update_option( Feature_Settings::QUICK_VIEW_TRIGGER_STYLE_OPTION, 'corner' );
		update_option( Feature_Settings::QUICK_VIEW_TRIGGER_POSITION_OPTION, 'top-left' );
		update_option( Feature_Settings::QUICK_VIEW_MEDIA_WISHLIST_OPTION, 'with_wishlist' );

		$product = $this->create_test_product( 'Card Stack Tee' );
		$quick   = new Quick_View();
		$method  = new ReflectionMethod( Quick_View::class, 'build_card_actions_markup' );
		$method->setAccessible( true );

		$html = (string) $method->invoke( $quick, $product );

		$this->assertStringContainsString( 'aggressive-apparel-card-actions--corner', $html );
		$this->assertStringContainsString( 'aggressive-apparel-card-actions--top-left', $html );
		$this->assertStringContainsString( 'role="group"', $html );
		$this->assertStringContainsString( 'aggressive-apparel-quick-view__trigger', $html );
		$this->assertStringContainsString( 'aggressive-apparel-wishlist__toggle--card-media', $html );
		$this->assertStringContainsString( 'data-aa-product-id="' . $product->get_id() . '"', $html );
		$this->assertStringNotContainsString( 'data-wp-on--click', $html );
		$this->assertStringContainsString( 'productId', $html );
		$this->assertStringContainsString( (string) $product->get_id(), $html );

		wp_delete_post( $product->get_id(), true );
	}

	/**
	 * Quick View-only mode omits the wishlist heart from the stack.
	 */
	public function test_card_actions_can_exclude_wishlist(): void {
		if ( ! class_exists( WC_Product_Simple::class ) ) {
			$this->markTestSkipped( 'WooCommerce is required.' );
		}

		update_option( Feature_Settings::OPTION_KEY, array( 'quick_view' => '1', 'wishlist' => '1' ) );
		update_option( Feature_Settings::QUICK_VIEW_MEDIA_WISHLIST_OPTION, 'quick_view_only' );

		$product = $this->create_test_product( 'QV Only Hoodie' );
		$quick   = new Quick_View();
		$method  = new ReflectionMethod( Quick_View::class, 'build_card_actions_markup' );
		$method->setAccessible( true );

		$html = (string) $method->invoke( $quick, $product );

		$this->assertStringContainsString( 'aggressive-apparel-quick-view__trigger', $html );
		$this->assertStringNotContainsString( 'aggressive-apparel-wishlist__toggle', $html );

		wp_delete_post( $product->get_id(), true );
	}

	/**
	 * Inject appends the stack before the image wrapper close.
	 */
	public function test_inject_appends_before_wrapper_close(): void {
		if ( ! class_exists( WC_Product_Simple::class ) ) {
			$this->markTestSkipped( 'WooCommerce is required.' );
		}

		update_option( Feature_Settings::OPTION_KEY, array( 'quick_view' => '1' ) );
		update_option( Feature_Settings::QUICK_VIEW_MEDIA_WISHLIST_OPTION, 'quick_view_only' );

		$product = $this->create_test_product( 'Append Stack' );
		$quick   = new Quick_View();
		$method  = new ReflectionMethod( Quick_View::class, 'build_card_actions_markup' );
		$method->setAccessible( true );

		$stack   = (string) $method->invoke( $quick, $product );
		$content = '<div class="wp-block-woocommerce-product-image"><img src="x.jpg" alt="" /></div>';
		$result  = Block_Render_Helper::append_before_wrapper_close( $content, $stack );

		$this->assertStringContainsString( 'aggressive-apparel-card-actions', $result );
		$this->assertMatchesRegularExpression( '/aggressive-apparel-card-actions[\s\S]*<\/div>\s*$/', $result );

		wp_delete_post( $product->get_id(), true );
	}

	/**
	 * Create and persist a simple product for markup tests.
	 *
	 * @param string $name Product name.
	 * @return WC_Product_Simple
	 */
	private function create_test_product( string $name ): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_name( $name );
		$product->set_regular_price( '29.00' );
		$product->set_status( 'publish' );
		$product->save();

		return $product;
	}
}
