<?php
/**
 * Test Product Loop Class
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use WP_UnitTestCase;
use Aggressive_Apparel\WooCommerce\Feature_Settings;
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

		delete_option( Feature_Settings::VARIABLE_PRODUCT_BUTTON_TEXT_OPTION );
		delete_option( Feature_Settings::SIMPLE_PRODUCT_BUTTON_TEXT_OPTION );
		delete_option( Feature_Settings::OUT_OF_STOCK_BUTTON_TEXT_OPTION );

		$this->product_loop = new Product_Loop( 4, 16 );
		$this->product_loop->init();
	}

	/**
	 * Tear down test
	 */
	public function tearDown(): void {
		delete_option( Feature_Settings::VARIABLE_PRODUCT_BUTTON_TEXT_OPTION );
		delete_option( Feature_Settings::SIMPLE_PRODUCT_BUTTON_TEXT_OPTION );
		delete_option( Feature_Settings::OUT_OF_STOCK_BUTTON_TEXT_OPTION );

		parent::tearDown();
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

	/**
	 * Test variable product add-to-cart button text
	 */
	public function test_variable_product_button_text_is_shortened() {
		$variable_product = new class() {
			/**
			 * Test whether the product is the requested type.
			 *
			 * @param string $type Product type.
			 * @return bool
			 */
			public function is_type( $type ) {
				return 'variable' === $type;
			}
		};

		$text = $this->product_loop->set_product_button_text( 'Select options', $variable_product );
		$this->assertSame( 'Choose', $text, 'Variable product button text should be shortened' );
	}

	/**
	 * Test custom variable product add-to-cart button text
	 */
	public function test_custom_variable_product_button_text_is_used() {
		update_option( Feature_Settings::VARIABLE_PRODUCT_BUTTON_TEXT_OPTION, 'Pick' );

		$variable_product = new class() {
			/**
			 * Test whether the product is the requested type.
			 *
			 * @param string $type Product type.
			 * @return bool
			 */
			public function is_type( $type ) {
				return 'variable' === $type;
			}
		};

		$text = $this->product_loop->set_product_button_text( 'Select options', $variable_product );
		$this->assertSame( 'Pick', $text, 'Custom variable product button text should be used' );
	}

	/**
	 * Test simple product add-to-cart button text
	 */
	public function test_simple_product_button_text_uses_store_copy() {
		$simple_product = new class() {
			/**
			 * Test whether the product is the requested type.
			 *
			 * @param string $type Product type.
			 * @return bool
			 */
			public function is_type( $type ) {
				return 'simple' === $type;
			}
		};

		$text = $this->product_loop->set_product_button_text( 'Add to cart', $simple_product );
		$this->assertSame( 'Add to Cart', $text, 'Simple product button text should use Store Copy' );
	}

	/**
	 * Test out-of-stock product add-to-cart button text
	 */
	public function test_out_of_stock_product_button_text_uses_store_copy() {
		$out_of_stock_product = new class() {
			/**
			 * Test whether the product is in stock.
			 *
			 * @return bool
			 */
			public function is_in_stock() {
				return false;
			}

			/**
			 * Test whether the product is the requested type.
			 *
			 * @param string $type Product type.
			 * @return bool
			 */
			public function is_type( $type ) {
				return 'simple' === $type;
			}
		};

		$text = $this->product_loop->set_product_button_text( 'Read more', $out_of_stock_product );
		$this->assertSame( 'Out of Stock', $text, 'Out-of-stock product button text should use Store Copy' );
	}

	/**
	 * Test non-purchasable product add-to-cart button text
	 */
	public function test_non_purchasable_product_button_text_is_unchanged() {
		$non_purchasable_product = new class() {
			/**
			 * Test whether the product is in stock.
			 *
			 * @return bool
			 */
			public function is_in_stock() {
				return true;
			}

			/**
			 * Test whether the product can be purchased.
			 *
			 * @return bool
			 */
			public function is_purchasable() {
				return false;
			}

			/**
			 * Test whether the product is the requested type.
			 *
			 * @param string $type Product type.
			 * @return bool
			 */
			public function is_type( $type ) {
				return 'simple' === $type;
			}
		};

		$text = $this->product_loop->set_product_button_text( 'Read more', $non_purchasable_product );
		$this->assertSame( 'Read more', $text, 'Non-purchasable product button text should be unchanged' );
	}
}
