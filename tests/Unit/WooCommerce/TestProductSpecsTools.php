<?php
/**
 * Product Specs attribute suggestions and Products grid tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Product_Specs_Grid;
use Aggressive_Apparel\WooCommerce\Product_Specs_Registry;
use Aggressive_Apparel\WooCommerce\Product_Specs_Suggestions;
use WP_UnitTestCase;

/**
 * Covers turning attributes into specs and editing values in bulk.
 */
class TestProductSpecsTools extends WP_UnitTestCase {

	/**
	 * Enable the feature and start from the default registry.
	 */
	public function setUp(): void {
		parent::setUp();

		update_option( Feature_Settings::OPTION_KEY, array( 'size_guide' => true ), false );
		delete_option( Product_Specs_Registry::OPTION_KEY );
		Product_Specs_Registry::flush();
		// The attribute scan is cached per request and in a transient.
		Product_Specs_Suggestions::flush();
	}

	/**
	 * Restore global state.
	 */
	public function tearDown(): void {
		delete_option( Feature_Settings::OPTION_KEY );
		delete_option( Product_Specs_Registry::OPTION_KEY );
		Product_Specs_Registry::flush();

		parent::tearDown();
	}

	/** Attributes in use become typed suggestions; known ones are skipped. */
	public function test_suggestions(): void {
		foreach ( array( '5 mm', '8 mm', '12 mm' ) as $value ) {
			$this->create_product(
				array(
					'Thickness' => $value,
					'Fabric'    => 'Cotton',
					'Color'     => 'Black',
				)
			);
		}

		$suggestions = array();
		foreach ( Product_Specs_Suggestions::all() as $suggestion ) {
			$suggestions[ $suggestion['name'] ] = $suggestion;
		}

		$this->assertArrayHasKey( 'Thickness', $suggestions );
		$this->assertSame( 'number', $suggestions['Thickness']['type'] );
		$this->assertSame( 'mm', $suggestions['Thickness']['unit'] );
		$this->assertCount( 4, $suggestions['Thickness']['thresholds'] );
		$this->assertArrayNotHasKey( 'Fabric', $suggestions, 'Already read by the default Fabric spec.' );
		$this->assertArrayNotHasKey( 'Color', $suggestions, 'Variation axes are never suggested.' );
	}

	/** The grid lists each spec's own and inherited value per product. */
	public function test_grid_lists_products(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$product_id = $this->create_product( array( 'Fabric' => 'Cotton' ) );
		update_post_meta( $product_id, '_aa_spec_fit', '2' );

		$request = new \WP_REST_Request( 'GET', '/aggressive-apparel/v1/product-specs/products' );
		$request->set_param( 'search', 'Spec grid product' );
		$data = ( new Product_Specs_Grid() )->list_products( $request )->get_data();

		$row = $data['products'][0] ?? array();
		$this->assertSame( $product_id, $row['id'] ?? 0 );
		$this->assertSame( '2', $row['specs']['fit']['own'] ?? '' );
		$this->assertSame( 'Regular', $row['specs']['fit']['shown'] ?? '' );
		$this->assertSame( 'Cotton', $row['specs']['fabric']['inherited'] ?? '' );
	}

	/** The grid saves product values and skips anything that is not a product. */
	public function test_grid_saves_products(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$product_id = $this->create_product();
		$page_id    = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$request = new \WP_REST_Request( 'POST', '/aggressive-apparel/v1/product-specs/products' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body(
			(string) wp_json_encode(
				array(
					'products' => array(
						$product_id => array(
							'fit'    => '3',
							'weight' => 'not a number',
						),
						$page_id    => array( 'fit' => '1' ),
					),
				)
			)
		);
		$result = ( new Product_Specs_Grid() )->save_products( $request )->get_data();

		$this->assertSame( 1, $result['saved'] );
		$this->assertSame( 1, $result['skipped'] );
		$this->assertSame( '3', get_post_meta( $product_id, '_aa_spec_fit', true ) );
		$this->assertSame( '', get_post_meta( $product_id, '_aa_spec_weight', true ) );
		$this->assertSame( '', get_post_meta( $page_id, '_aa_spec_fit', true ) );
	}

	/**
	 * Create a published simple product with custom attributes.
	 *
	 * @param array<string, string> $attributes Attribute name => value.
	 */
	private function create_product( array $attributes = array() ): int {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Spec grid product' );
		$product->set_status( 'publish' );

		$list = array();
		foreach ( $attributes as $name => $value ) {
			$attribute = new \WC_Product_Attribute();
			$attribute->set_id( 0 );
			$attribute->set_name( $name );
			$attribute->set_options( array( $value ) );
			$attribute->set_visible( true );
			$list[] = $attribute;
		}
		$product->set_attributes( $list );

		return $product->save();
	}
}
