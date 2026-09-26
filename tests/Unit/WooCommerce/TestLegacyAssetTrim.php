<?php
/**
 * Legacy asset trim and mini-cart style defer tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Legacy_Asset_Trim;
use Aggressive_Apparel\WooCommerce\Mini_Cart_Style_Defer;
use WP_UnitTestCase;

/**
 * Verify classic WooCommerce CSS only loads where classic markup can render.
 */
class TestLegacyAssetTrim extends WP_UnitTestCase {

	private const STYLE  = 'woocommerce-general';
	private const SCRIPT = 'woocommerce';

	/**
	 * Skip without WooCommerce and queue the classic bundle.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for legacy asset tests.' );
		}

		wp_enqueue_style( self::STYLE, 'https://example.test/woocommerce.css', array(), '1' );
		wp_enqueue_script( self::SCRIPT, 'https://example.test/woocommerce.js', array(), '1', true );
	}

	/**
	 * Reset queues and the resolved template.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		global $_wp_current_template_content;
		$_wp_current_template_content = null;

		wp_dequeue_style( self::STYLE );
		wp_deregister_style( self::STYLE );
		wp_dequeue_script( self::SCRIPT );
		wp_deregister_script( self::SCRIPT );
		remove_all_filters( 'aggressive_apparel_defer_mini_cart_styles' );

		parent::tearDown();
	}

	/**
	 * A block-only shop template drops classic styles but keeps the scripts.
	 *
	 * @return void
	 */
	public function test_block_only_shop_trims_styles_and_keeps_scripts(): void {
		$this->go_to_shop( '<!-- wp:woocommerce/product-collection /-->' );

		( new Legacy_Asset_Trim() )->maybe_trim();

		$this->assertFalse( wp_style_is( self::STYLE, 'enqueued' ) );
		$this->assertTrue( wp_script_is( self::SCRIPT, 'enqueued' ) );
	}

	/**
	 * A shop template with a classic-markup block keeps the classic styles.
	 *
	 * @return void
	 */
	public function test_classic_block_in_template_keeps_styles(): void {
		$this->go_to_shop( '<!-- wp:woocommerce/legacy-template {"template":"archive-product"} /-->' );

		( new Legacy_Asset_Trim() )->maybe_trim();

		$this->assertTrue( wp_style_is( self::STYLE, 'enqueued' ) );
	}

	/**
	 * Single products keep classic styles for the classic reviews template.
	 *
	 * @return void
	 */
	public function test_single_product_keeps_styles(): void {
		global $_wp_current_template_content;

		$product = self::factory()->post->create(
			array(
				'post_type'   => 'product',
				'post_status' => 'publish',
			)
		);
		$this->go_to( (string) get_permalink( $product ) );
		$_wp_current_template_content = '<!-- wp:woocommerce/add-to-cart-with-options /-->';

		( new Legacy_Asset_Trim() )->maybe_trim();

		$this->assertTrue( wp_style_is( self::STYLE, 'enqueued' ) );
	}

	/**
	 * The mini-cart drawer stylesheet is rewritten to a non-blocking tag.
	 *
	 * @return void
	 */
	public function test_mini_cart_styles_are_deferred_with_noscript_fallback(): void {
		$defer = new Mini_Cart_Style_Defer();
		$tag   = "<link rel='stylesheet' id='x-css' href='https://example.test/mini-cart-contents.css' media='all' />\n";

		$deferred = $defer->defer_tag( $tag, Mini_Cart_Style_Defer::HANDLE, 'https://example.test/mini-cart-contents.css', 'all' );

		$this->assertStringContainsString( 'media="print" onload="this.media=\'all\'"', $deferred );
		$this->assertStringContainsString( '<noscript>' . trim( $tag ) . '</noscript>', $deferred );
		$this->assertSame( $tag, $defer->defer_tag( $tag, 'other-handle', '', 'all' ) );

		add_filter( 'aggressive_apparel_defer_mini_cart_styles', '__return_false' );
		$this->assertSame( $tag, $defer->defer_tag( $tag, Mini_Cart_Style_Defer::HANDLE, '', 'all' ) );
	}

	/**
	 * Visit the shop archive with the given resolved block template.
	 *
	 * @param string $template Block template content.
	 * @return void
	 */
	private function go_to_shop( string $template ): void {
		global $_wp_current_template_content;

		$archive = get_post_type_archive_link( 'product' );
		$this->assertIsString( $archive );
		$this->go_to( $archive );
		$_wp_current_template_content = $template;
	}
}
