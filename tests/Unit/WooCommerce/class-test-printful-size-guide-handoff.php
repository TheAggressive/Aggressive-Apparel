<?php
/**
 * On a product page, this theme's guide takes over from the Printful plugin's
 * chart, without touching the plugin's data.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Printful_Size_Guide_Handoff;
use Aggressive_Apparel\WooCommerce\Size_Chart;
use Aggressive_Apparel\WooCommerce\Size_Guide;
use Aggressive_Apparel\WooCommerce\Size_Guide_Post_Type;
use WP_UnitTestCase;

/**
 * Covers handing over from the Printful plugin's size guide.
 */
class Test_Printful_Size_Guide_Handoff extends WP_UnitTestCase {

	/**
	 * Turn guides on and hook the handoff.
	 */
	public function setUp(): void {
		parent::setUp();

		update_option( Feature_Settings::OPTION_KEY, array( 'size_guide' => true ), false );
		( new Printful_Size_Guide_Handoff() )->init();
	}

	/** A product whose guide has a chart shows only that guide. */
	public function test_hidden_when_the_guide_has_a_chart(): void {
		$id = $this->product_with_charts( $this->guide( true ) );
		$this->go_to( (string) get_permalink( $id ) );

		$this->assertTrue( is_product() );
		$this->assertSame( '', get_post_meta( $id, 'pf_size_chart', true ) );
		$this->assertSame( '', get_post_meta( $id, 'pf_advanced_size_chart', true ) );
		$this->assertSame( array(), get_post_meta( $id, 'pf_size_chart', false ) );
	}

	/** Other meta on the same product reads normally. */
	public function test_other_meta_is_untouched(): void {
		$id = $this->product_with_charts( $this->guide( true ) );
		update_post_meta( $id, 'something_else', 'value' );
		$this->go_to( (string) get_permalink( $id ) );

		$this->assertSame( 'value', get_post_meta( $id, 'something_else', true ) );
	}

	/** With no guide here, the plugin's chart is all the shopper has, so it stays. */
	public function test_kept_when_the_product_has_no_guide(): void {
		$id = $this->product_with_charts( 0 );
		$this->go_to( (string) get_permalink( $id ) );

		$this->assertSame( 'Printful chart', get_post_meta( $id, 'pf_size_chart', true ) );
	}

	/** A specs-only guide has no chart to open, so it doesn't replace one. */
	public function test_kept_when_the_guide_has_no_chart(): void {
		$id = $this->product_with_charts( $this->guide( false ) );
		$this->go_to( (string) get_permalink( $id ) );

		$this->assertSame( 'Printful chart', get_post_meta( $id, 'pf_size_chart', true ) );
	}

	/** Away from the product page, the data reads as it is. */
	public function test_data_is_intact_elsewhere(): void {
		$id = $this->product_with_charts( $this->guide( true ) );

		$this->go_to( home_url( '/' ) );
		$this->assertSame( 'Printful chart', get_post_meta( $id, 'pf_size_chart', true ), 'Not a product page.' );

		$this->go_to( (string) get_permalink( $id ) );
		set_current_screen( 'product' );
		$this->assertSame( 'Printful chart', get_post_meta( $id, 'pf_size_chart', true ), 'The product editor.' );
		set_current_screen( 'front' );
	}

	/**
	 * A published product carrying both of the plugin's charts.
	 *
	 * @param int $guide Size guide to assign (0 for none).
	 */
	private function product_with_charts( int $guide ): int {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Tee' );
		$product->set_status( 'publish' );
		$id = $product->save();

		update_post_meta( $id, 'pf_size_chart', 'Printful chart' );
		update_post_meta( $id, 'pf_advanced_size_chart', '{"availableSizes":["S"]}' );
		if ( $guide > 0 ) {
			update_post_meta( $id, Size_Guide::CPT_META_KEY, $guide );
		}

		return $id;
	}

	/**
	 * A published size guide.
	 *
	 * @param bool $chart Whether it has a chart, or only spec values.
	 */
	private function guide( bool $chart ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'    => Size_Guide_Post_Type::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Guide',
				// Old free-form content counts as something to open.
				'post_content' => '',
			)
		);
		if ( $chart ) {
			update_post_meta(
				$id,
				Size_Chart::META,
				array(
					'unit'    => 'in',
					'columns' => array(
						array(
							'label' => 'Chest',
							'type'  => 'measure',
						),
					),
					'rows'    => array(
						array(
							'size'   => 'M',
							'values' => array( '20' ),
						),
					),
				)
			);
		}

		return $id;
	}
}
