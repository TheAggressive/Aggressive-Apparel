<?php
/**
 * Catalog filter service tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Catalog_Filter_Set;
use Aggressive_Apparel\WooCommerce\Product_Filter_Renderer;
use WP_UnitTestCase;

/** Covers normalized filter input and the extracted control renderer. */
class TestCatalogFilterServices extends WP_UnitTestCase {

	/** Public filter input is bounded, normalized, and allow-listed. */
	public function test_filter_set_normalizes_public_input(): void {
		$filters = new Catalog_Filter_Set(
			array(
				'category'   => ' Shirts,shirts,Hats ',
				'attributes' => array(
					'pa_color' => 'Dark Blue,dark-blue',
					'bad tax'  => 'ignored',
				),
				'min_price'  => -5,
				'max_price'  => 100,
				'stock'      => 'invalid',
				'on_sale'    => true,
			)
		);

		$this->assertSame( array( 'shirts', 'hats' ), $filters->categories() );
		$this->assertSame( array( 'pa_color' => array( 'dark-blue' ) ), $filters->attributes( array( 'pa_color' ) ) );
		$this->assertSame( 0.0, $filters->min_price() );
		$this->assertSame( 100.0, $filters->max_price() );
		$this->assertSame( '', $filters->stock() );
		$this->assertTrue( $filters->on_sale() );
	}

	/** Extracted renderer preserves the availability controls contract. */
	public function test_renderer_outputs_availability_controls(): void {
		$renderer = new Product_Filter_Renderer();
		ob_start();
		$renderer->render_sections(
			array(
				'categories' => array(),
				'fitTerms'   => array(),
				'colorTerms' => array(),
				'sizeTerms'  => array(),
				'priceRange' => array( 'min' => 0, 'max' => 0 ),
			)
		);
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'actions.toggleInStockOnly', $html );
		$this->assertStringContainsString( 'actions.toggleOnSaleOnly', $html );
	}
}
