<?php
/**
 * Automatic sale badge integration tests.
 *
 * @package Aggressive_Apparel\Tests\Integration\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Integration\WooCommerce;

use Aggressive_Apparel\WooCommerce\Custom_Badge_Taxonomy;
use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Product_Badges;
use WP_UnitTestCase;

/**
 * Covers which products get a sale badge, what it says, and who owns the badge
 * when the rule is switched off.
 */
class TestProductBadgeSale extends WP_UnitTestCase {

	/** Service under test. */
	private Product_Badges $badges;

	/**
	 * Seed the system badge terms and isolate the Sale rule.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( '\WC_Product_Variable' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}

		// Seeding is a no-op until the taxonomy exists, and the badge cache is
		// a static that outlives a single test.
		( new Custom_Badge_Taxonomy() )->register_taxonomy();
		Custom_Badge_Taxonomy::maybe_seed_system_badges();
		Custom_Badge_Taxonomy::flush_system_badges_cache();

		$this->enable_only_the_sale_rule();

		$this->badges = new Product_Badges();
		$this->badges->apply_threshold_filters();
	}

	/**
	 * Reset shared state so badge terms do not leak into later tests.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		Custom_Badge_Taxonomy::flush_system_badges_cache();

		delete_option( Custom_Badge_Taxonomy::RULES_OPTION );
		delete_option( Feature_Settings::SALE_BADGE_TEXT_OPTION );
		delete_option( Feature_Settings::SALE_BADGE_NO_DISCOUNT_TEXT_OPTION );

		parent::tearDown();
	}

	/**
	 * Turn every rule but Sale off, so a rendered badge can only be the sale one.
	 *
	 * @param bool $sale_enabled Whether the Sale rule is on.
	 * @return void
	 */
	private function enable_only_the_sale_rule( bool $sale_enabled = true ): void {
		update_option(
			Custom_Badge_Taxonomy::RULES_OPTION,
			array(
				'sale_enabled'       => $sale_enabled,
				'new_enabled'        => false,
				'low_stock_enabled'  => false,
				'bestseller_enabled' => false,
				'new_days'           => 14,
				'low_stock'          => 5,
				'bestseller_sales'   => 50,
			)
		);
	}

	/**
	 * Visible text of the badges rendered for a product.
	 *
	 * @param \WC_Product $product Product to render.
	 * @return string
	 */
	private function badge_text( \WC_Product $product ): string {
		$html = $this->badges->get_badges_html( $product );

		return trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( $html ) ) );
	}

	/**
	 * Simple product reduced by a fixed percentage.
	 *
	 * @param string $regular Regular price.
	 * @param string $sale    Sale price, or '' for no sale.
	 * @return \WC_Product
	 */
	private function create_simple_product( string $regular, string $sale = '' ): \WC_Product {
		$product = new \WC_Product_Simple();
		$product->set_regular_price( $regular );

		if ( '' !== $sale ) {
			$product->set_sale_price( $sale );
		}

		$product->save();

		return $product;
	}

	/**
	 * Variable product whose cheapest variation is NOT the discounted one, so
	 * no single discount figure describes it.
	 *
	 * @return \WC_Product
	 */
	private function create_mixed_discount_product(): \WC_Product {
		$product = new \WC_Product_Variable();
		$product->set_name( 'Mixed Discount Tee' );

		$attribute = new \WC_Product_Attribute();
		$attribute->set_name( 'size' );
		$attribute->set_options( array( 'v0', 'v1' ) );
		$attribute->set_visible( true );
		$attribute->set_variation( true );
		$product->set_attributes( array( $attribute ) );

		$product_id = $product->save();

		// v0 is the cheapest and carries no discount; v1 is reduced. The
		// minimum-price maths therefore yields no percentage.
		foreach ( array(
			array(
				'size'    => 'v0',
				'regular' => '20.00',
				'sale'    => '',
			),
			array(
				'size'    => 'v1',
				'regular' => '80.00',
				'sale'    => '40.00',
			),
		) as $spec ) {
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $product_id );
			$variation->set_attributes( array( 'size' => $spec['size'] ) );
			$variation->set_regular_price( $spec['regular'] );

			if ( '' !== $spec['sale'] ) {
				$variation->set_sale_price( $spec['sale'] );
			}

			$variation->save();
		}

		$reloaded = wc_get_product( $product_id );
		$this->assertInstanceOf( \WC_Product_Variable::class, $reloaded );

		return $reloaded;
	}

	/**
	 * A discountable product shows the percentage in the merchant's wording.
	 *
	 * @return void
	 */
	public function test_discounted_product_shows_the_percentage(): void {
		update_option( Feature_Settings::SALE_BADGE_TEXT_OPTION, 'Save {percent}%' );

		$product = $this->create_simple_product( '100.00', '80.00' );

		$this->assertSame( 'Save 20%', $this->badge_text( $product ) );
	}

	/**
	 * Regression guard for the rule that made this "Sale" rather than "Sale
	 * percentage": a product on sale with no computable discount still gets a
	 * badge, using the fallback wording rather than nothing at all.
	 *
	 * @return void
	 */
	public function test_sale_without_a_computable_discount_still_gets_a_badge(): void {
		update_option( Feature_Settings::SALE_BADGE_TEXT_OPTION, 'Save {percent}%' );
		update_option( Feature_Settings::SALE_BADGE_NO_DISCOUNT_TEXT_OPTION, 'On Sale' );

		$product = $this->create_mixed_discount_product();

		$this->assertTrue( $product->is_on_sale(), 'Fixture must actually be on sale' );
		$this->assertSame( 'On Sale', $this->badge_text( $product ) );
	}

	/**
	 * Static wording applies to every sale item, discount figure or not.
	 *
	 * @return void
	 */
	public function test_static_wording_applies_to_all_sale_items(): void {
		update_option( Feature_Settings::SALE_BADGE_TEXT_OPTION, 'Now on Sale' );

		$this->assertSame(
			'Now on Sale',
			$this->badge_text( $this->create_simple_product( '100.00', '80.00' ) )
		);

		$this->assertSame(
			'Now on Sale',
			$this->badge_text( $this->create_mixed_discount_product() )
		);
	}

	/**
	 * Full-price products get no sale badge.
	 *
	 * @return void
	 */
	public function test_full_price_product_gets_no_badge(): void {
		$product = $this->create_simple_product( '100.00' );

		$this->assertSame( '', $this->badge_text( $product ) );
	}

	/**
	 * Switching the rule off must hand the job back to WooCommerce rather than
	 * leaving the catalog with no sale signal at all.
	 *
	 * @return void
	 */
	public function test_disabling_the_rule_restores_the_native_sale_badge(): void {
		$this->enable_only_the_sale_rule( false );

		$badges = new Product_Badges();
		$badges->init();

		$this->assertFalse(
			has_filter( 'woocommerce_sale_flash', '__return_empty_string' ),
			'Classic sale flash must survive when this theme draws no sale badge'
		);
		$this->assertFalse(
			has_filter( 'render_block_woocommerce/product-sale-badge' ),
			'Block sale badge must survive when this theme draws no sale badge'
		);
		$this->assertSame( '', $this->badge_text( $this->create_simple_product( '100.00', '80.00' ) ) );
	}

	/**
	 * With the rule on, this theme owns the sale badge and WooCommerce's is
	 * suppressed so the two cannot both render.
	 *
	 * @return void
	 */
	public function test_enabling_the_rule_suppresses_the_native_sale_badge(): void {
		$badges = new Product_Badges();
		$badges->init();

		$this->assertNotFalse( has_filter( 'woocommerce_sale_flash', '__return_empty_string' ) );
		$this->assertNotFalse( has_filter( 'render_block_woocommerce/product-sale-badge' ) );
	}
}
