<?php
/**
 * Icons unit tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit;

use Aggressive_Apparel\Core\Brand_Icons;
use Aggressive_Apparel\Core\Icons;
use WP_UnitTestCase;

/**
 * Icons test case.
 */
class Icons_Test extends WP_UnitTestCase {

	/**
	 * Reset icon cache and filters after each test.
	 */
	public function tearDown(): void {
		remove_all_filters( 'aggressive_apparel_icon_definitions' );
		Brand_Icons::flush_cache_for_tests();
		Icons::flush_cache_for_tests();
		parent::tearDown();
	}

	/**
	 * Core UI icons do not load any generated brand definition.
	 */
	public function test_core_icon_does_not_load_brand_definitions(): void {
		Brand_Icons::init();
		Brand_Icons::flush_cache_for_tests();
		Icons::flush_cache_for_tests();

		$markup = Icons::get( 'cart' );

		$this->assertStringContainsString( '<svg', $markup );
		$this->assertSame( array(), Brand_Icons::loaded_slugs_for_tests() );
	}

	/**
	 * Brand definitions are loaded individually and cached after first use.
	 */
	public function test_brand_icons_are_loaded_one_definition_at_a_time(): void {
		Brand_Icons::init();
		Brand_Icons::flush_cache_for_tests();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'shipping-box' ) );
		$this->assertSame( array( 'shipping-box' ), Brand_Icons::loaded_slugs_for_tests() );

		Icons::get( 'shipping-box' );
		$this->assertSame( array( 'shipping-box' ), Brand_Icons::loaded_slugs_for_tests() );

		Icons::get( 'shield-check' );
		$this->assertSame( array( 'shipping-box', 'shield-check' ), Brand_Icons::loaded_slugs_for_tests() );
	}

	/**
	 * Single-path icons remain backward compatible.
	 */
	public function test_get_renders_legacy_single_path_icon(): void {
		$markup = Icons::get( 'cart' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertStringContainsString( '<path d="', $markup );
		$this->assertStringContainsString( 'fill="currentColor"', $markup );
		$this->assertSame( 1, substr_count( $markup, '<path ' ) );
	}

	/**
	 * Multi-path icons render all paths inside one svg root.
	 */
	public function test_get_renders_multi_path_icon_from_filter(): void {
		add_filter(
			'aggressive_apparel_icon_definitions',
			static function ( array $icons ): array {
				$icons['test-multi'] = array(
					'viewBox' => '0 0 24 24',
					'paths'   => array(
						'M2 2h8v8H2z',
						'M14 14h8v8h-8z',
					),
					'circles' => array(
						array( 'cx' => 12, 'cy' => 12, 'r' => 1 ),
					),
				);

				return $icons;
			}
		);

		Icons::flush_cache_for_tests();

		$markup = Icons::get( 'test-multi' );

		$this->assertTrue( Icons::exists( 'test-multi' ) );
		$this->assertSame( 2, substr_count( $markup, '<path ' ) );
		$this->assertStringContainsString( '<circle cx="12" cy="12" r="1"/>', $markup );
	}

	/**
	 * Custom viewBox values pass through when valid.
	 */
	public function test_get_renders_custom_view_box(): void {
		add_filter(
			'aggressive_apparel_icon_definitions',
			static function ( array $icons ): array {
				$icons['test-viewbox'] = array(
					'viewBox' => '0 0 112.89 115.56',
					'paths'   => array( 'M10 10h4v4h-4z' ),
				);

				return $icons;
			}
		);

		Icons::flush_cache_for_tests();

		$markup = Icons::get( 'test-viewbox' );

		$this->assertStringContainsString( 'viewBox="0 0 112.89 115.56"', $markup );
	}

	/**
	 * Built-in barbed-lock brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_barbed_lock_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'barbed-lock' ) );

		$markup = Icons::get( 'barbed-lock' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 24, substr_count( $markup, '<path ' ) );
		$this->assertSame( 21, substr_count( $markup, '<polygon ' ) );
		$this->assertSame( 4, substr_count( $markup, '<rect ' ) );
		$this->assertSame( 4, substr_count( $markup, ' transform="' ) );
		$this->assertStringContainsString( '<circle cx="14.85" cy="8.77" r="0.15"/>', $markup );
	}

	/**
	 * Sticker patch brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_sticker_patch_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'sticker-patch' ) );

		$markup = Icons::get( 'sticker-patch' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 9, substr_count( $markup, '<path ' ) );
		$this->assertSame( 4, substr_count( $markup, '<polygon ' ) );
	}

	/**
	 * Bell alert brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_bell_alert_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'bell-alert' ) );

		$markup = Icons::get( 'bell-alert' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 5, substr_count( $markup, '<path ' ) );
		$this->assertSame( 3, substr_count( $markup, '<polygon ' ) );
	}

	/**
	 * Best seller medal brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_best_seller_medal_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'best-seller-medal' ) );

		$markup = Icons::get( 'best-seller-medal' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 7, substr_count( $markup, '<path ' ) );
		$this->assertSame( 4, substr_count( $markup, '<polygon ' ) );
		$this->assertSame( 3, substr_count( $markup, '<rect ' ) );
	}

	/**
	 * Broken chain link brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_broken_chain_link_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'broken-chain-link' ) );

		$markup = Icons::get( 'broken-chain-link' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 9, substr_count( $markup, '<path ' ) );
		$this->assertSame( 3, substr_count( $markup, '<polygon ' ) );
	}

	/**
	 * Broken halo brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_broken_halo_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'broken-halo' ) );

		$markup = Icons::get( 'broken-halo' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 3, substr_count( $markup, '<path ' ) );
		$this->assertSame( 1, substr_count( $markup, '<polygon ' ) );
		$this->assertSame( 8, substr_count( $markup, '<rect ' ) );
	}

	/**
	 * Bundle stack brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_bundle_stack_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'bundle-stack' ) );

		$markup = Icons::get( 'bundle-stack' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 9, substr_count( $markup, '<path ' ) );
		$this->assertSame( 9, substr_count( $markup, '<polygon ' ) );
		$this->assertSame( 6, substr_count( $markup, '<rect ' ) );
	}

	/**
	 * Burning star brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_burning_star_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'burning-star' ) );

		$markup = Icons::get( 'burning-star' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 1, substr_count( $markup, '<path ' ) );
	}

	/**
	 * Chat bolt brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_chat_bolt_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'chat-bolt' ) );

		$markup = Icons::get( 'chat-bolt' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 1, substr_count( $markup, '<path ' ) );
		$this->assertSame( 1, substr_count( $markup, '<polygon ' ) );
	}

	/**
	 * Checkered flag brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_checkered_flag_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'checkered-flag' ) );

		$markup = Icons::get( 'checkered-flag' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 13, substr_count( $markup, '<path ' ) );
		$this->assertSame( 12, substr_count( $markup, '<polygon ' ) );
		$this->assertSame( 5, substr_count( $markup, '<rect ' ) );
		$this->assertStringContainsString( '<circle cx="21.17" cy="9.11" r="0.17"/>', $markup );
	}

	/**
	 * Cracked crown brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_cracked_crown_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'cracked-crown' ) );

		$markup = Icons::get( 'cracked-crown' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 7, substr_count( $markup, '<path ' ) );
		$this->assertSame( 6, substr_count( $markup, '<polygon ' ) );
	}

	/**
	 * Cracked smiley face brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_cracked_smiley_face_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'cracked-smiley-face' ) );

		$markup = Icons::get( 'cracked-smiley-face' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 3, substr_count( $markup, '<path ' ) );
	}

	/**
	 * Flame icon brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_flame_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'flame-icon' ) );

		$markup = Icons::get( 'flame-icon' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 1, substr_count( $markup, '<path ' ) );
	}

	/**
	 * Gift box brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_gift_box_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'gift-box' ) );

		$markup = Icons::get( 'gift-box' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 12, substr_count( $markup, '<path ' ) );
		$this->assertSame( 8, substr_count( $markup, '<polygon ' ) );
		$this->assertSame( 9, substr_count( $markup, '<rect ' ) );
		$this->assertStringContainsString( '<circle cx="13.02" cy="10.92" r="0.21"/>', $markup );
	}

	/**
	 * Hang tag brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_hang_tag_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'hang-tag' ) );

		$markup = Icons::get( 'hang-tag' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 4, substr_count( $markup, '<path ' ) );
		$this->assertSame( 1, substr_count( $markup, '<polygon ' ) );
	}

	/**
	 * Hanger brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_hanger_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'hanger' ) );

		$markup = Icons::get( 'hanger' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 2, substr_count( $markup, '<path ' ) );
	}

	/**
	 * Heavy X brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_heavy_x_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'heavy-x' ) );

		$markup = Icons::get( 'heavy-x' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 5, substr_count( $markup, '<path ' ) );
		$this->assertSame( 4, substr_count( $markup, '<polygon ' ) );
		$this->assertSame( 1, substr_count( $markup, '<rect ' ) );
	}

	/**
	 * Hoodie brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_hoodie_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'hoodie' ) );

		$markup = Icons::get( 'hoodie' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 9, substr_count( $markup, '<path ' ) );
		$this->assertSame( 5, substr_count( $markup, '<polygon ' ) );
		$this->assertSame( 5, substr_count( $markup, '<rect ' ) );
	}

	/**
	 * Jagged badge brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_jagged_badge_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'jagged-badge' ) );

		$markup = Icons::get( 'jagged-badge' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 3, substr_count( $markup, '<path ' ) );
		$this->assertSame( 2, substr_count( $markup, '<polygon ' ) );
		$this->assertSame( 2, substr_count( $markup, '<rect ' ) );
	}

	/**
	 * Lightning bolt brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_lightning_bolt_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'lightning-bolt' ) );

		$markup = Icons::get( 'lightning-bolt' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 1, substr_count( $markup, '<path ' ) );
	}

	/**
	 * Lookbook frame brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_lookbook_frame_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'lookbook-frame' ) );

		$markup = Icons::get( 'lookbook-frame' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 9, substr_count( $markup, '<path ' ) );
		$this->assertSame( 1, substr_count( $markup, '<rect ' ) );
	}

	/**
	 * Measuring tape brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_measuring_tape_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'measuring-tape' ) );

		$markup = Icons::get( 'measuring-tape' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 16, substr_count( $markup, '<path ' ) );
		$this->assertSame( 9, substr_count( $markup, '<polygon ' ) );
		$this->assertSame( 4, substr_count( $markup, '<rect ' ) );
	}

	/**
	 * Motocross tread mark brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_motocross_tread_mark_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'motocross-tread-mark' ) );

		$markup = Icons::get( 'motocross-tread-mark' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 10, substr_count( $markup, '<path ' ) );
		$this->assertSame( 20, substr_count( $markup, '<polygon ' ) );
		$this->assertSame( 10, substr_count( $markup, '<rect ' ) );
		$this->assertStringContainsString( '<circle cx="17.75" cy="0.41" r="0.23"/>', $markup );
	}

	/**
	 * Newsletter mail brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_newsletter_mail_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'newsletter-mail' ) );

		$markup = Icons::get( 'newsletter-mail' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 5, substr_count( $markup, '<path ' ) );
		$this->assertSame( 5, substr_count( $markup, '<polygon ' ) );
		$this->assertSame( 2, substr_count( $markup, '<rect ' ) );
	}

	/**
	 * Payment card brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_payment_card_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'payment-card' ) );

		$markup = Icons::get( 'payment-card' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 1, substr_count( $markup, '<path ' ) );
		$this->assertSame( 1, substr_count( $markup, '<polygon ' ) );
	}

	/**
	 * Razor-edge star brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_razor_edge_star_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'razor-edge-star' ) );

		$markup = Icons::get( 'razor-edge-star' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 3, substr_count( $markup, '<path ' ) );
	}

	/**
	 * Returns arrows brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_returns_arrows_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'returns-arrows' ) );

		$markup = Icons::get( 'returns-arrows' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 2, substr_count( $markup, '<path ' ) );
		$this->assertSame( 1, substr_count( $markup, '<polygon ' ) );
		$this->assertSame( 1, substr_count( $markup, '<rect ' ) );
	}

	/**
	 * Safety pin brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_safety_pin_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'safety-pin' ) );

		$markup = Icons::get( 'safety-pin' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 5, substr_count( $markup, '<path ' ) );
		$this->assertSame( 7, substr_count( $markup, '<polygon ' ) );
	}

	/**
	 * Sales ticket brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_sales_ticket_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'sales-ticket' ) );

		$markup = Icons::get( 'sales-ticket' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 2, substr_count( $markup, '<path ' ) );
		$this->assertSame( 1, substr_count( $markup, '<polygon ' ) );
		$this->assertSame( 1, substr_count( $markup, '<rect ' ) );
	}

	/**
	 * Shield check brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_shield_check_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'shield-check' ) );

		$markup = Icons::get( 'shield-check' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 2, substr_count( $markup, '<path ' ) );
	}

	/**
	 * Shipping box brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_shipping_box_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'shipping-box' ) );

		$markup = Icons::get( 'shipping-box' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 8, substr_count( $markup, '<path ' ) );
		$this->assertSame( 8, substr_count( $markup, '<polygon ' ) );
		$this->assertSame( 3, substr_count( $markup, '<rect ' ) );
	}

	/**
	 * Shopping bag brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_shopping_bag_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'shopping-bag' ) );

		$markup = Icons::get( 'shopping-bag' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 8, substr_count( $markup, '<path ' ) );
		$this->assertSame( 7, substr_count( $markup, '<polygon ' ) );
		$this->assertSame( 2, substr_count( $markup, '<rect ' ) );
	}

	/**
	 * Skull brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_skull_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'skull' ) );

		$markup = Icons::get( 'skull' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 5, substr_count( $markup, '<path ' ) );
		$this->assertSame( 4, substr_count( $markup, '<polygon ' ) );
		$this->assertSame( 1, substr_count( $markup, '<rect ' ) );
	}

	/**
	 * Spiked heart brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_spiked_heart_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'spiked-heart' ) );

		$markup = Icons::get( 'spiked-heart' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 3, substr_count( $markup, '<path ' ) );
		$this->assertSame( 4, substr_count( $markup, '<polygon ' ) );
	}

	/**
	 * Spotlight eye brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_spotlight_eye_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'spotlight-eye' ) );

		$markup = Icons::get( 'spotlight-eye' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 8, substr_count( $markup, '<path ' ) );
		$this->assertSame( 13, substr_count( $markup, '<polygon ' ) );
		$this->assertSame( 4, substr_count( $markup, '<rect ' ) );
	}

	/**
	 * Swim trunks brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_swim_trunks_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'swim-trunks' ) );

		$markup = Icons::get( 'swim-trunks' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 16, substr_count( $markup, '<path ' ) );
		$this->assertSame( 10, substr_count( $markup, '<polygon ' ) );
		$this->assertSame( 5, substr_count( $markup, '<rect ' ) );
		$this->assertStringContainsString( '<circle cx="11.31" cy="18.29" r="0.22"/>', $markup );
	}

	/**
	 * Tank top brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_tank_top_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'tank-top' ) );

		$markup = Icons::get( 'tank-top' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 6, substr_count( $markup, '<path ' ) );
	}

	/**
	 * Thorned rose brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_thorned_rose_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'thorned-rose' ) );

		$markup = Icons::get( 'thorned-rose' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 27, substr_count( $markup, '<path ' ) );
		$this->assertSame( 9, substr_count( $markup, '<polygon ' ) );
		$this->assertSame( 1, substr_count( $markup, '<rect ' ) );
	}

	/**
	 * Ticket stub brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_ticket_stub_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 'ticket-stub' ) );

		$markup = Icons::get( 'ticket-stub' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 1, substr_count( $markup, '<path ' ) );
	}

	/**
	 * T-shirt brand icon is registered when Brand_Icons is bootstrapped.
	 */
	public function test_t_shirt_icon_is_available(): void {
		Brand_Icons::init();
		Icons::flush_cache_for_tests();

		$this->assertTrue( Icons::exists( 't-shirt' ) );

		$markup = Icons::get( 't-shirt' );

		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $markup );
		$this->assertSame( 4, substr_count( $markup, '<path ' ) );
	}

	/**
	 * Invalid definitions are rejected by exists() and get().
	 */
	public function test_invalid_definition_is_rejected(): void {
		add_filter(
			'aggressive_apparel_icon_definitions',
			static function ( array $icons ): array {
				$icons['test-invalid'] = array(
					'viewBox' => 'not-a-viewbox',
					'paths'   => array(),
				);

				return $icons;
			}
		);

		Icons::flush_cache_for_tests();

		$this->assertFalse( Icons::exists( 'test-invalid' ) );
		$this->assertSame( '', Icons::get( 'test-invalid' ) );
		$this->assertNotContains( 'test-invalid', Icons::list() );
	}
}
