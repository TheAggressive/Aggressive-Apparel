<?php
/**
 * Test Product Loop Class
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use WP_UnitTestCase;
use Aggressive_Apparel\WooCommerce\Product_Loop;

/**
 * Product Loop Test Case
 */
class TestProductLoop extends WP_UnitTestCase {
	/**
	 * Product loop instance
	 *
	 * @var Product_Loop
	 */
	private $product_loop;

	/**
	 * Set up test
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce not available' );
		}

		$this->product_loop = new Product_Loop( 4, 16 );
		$this->product_loop->init();
	}

	/**
	 * Test loop columns
	 */
	public function test_loop_columns() {
		$columns = $this->product_loop->set_loop_columns( 3 );
		$this->assertEquals( 4, $columns, 'Should return 4 columns' );
	}

	/**
	 * Test products per page
	 */
	public function test_products_per_page() {
		$per_page = $this->product_loop->set_products_per_page( 12 );
		$this->assertEquals( 16, $per_page, 'Should return 16 products per page' );
	}
}
