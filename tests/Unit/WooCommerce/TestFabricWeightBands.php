<?php
/**
 * Typical fabric weights by garment: telling garments apart from category
 * names, and what the settings screen gets.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Fabric_Weight_Bands;
use Aggressive_Apparel\WooCommerce\Garment_Kinds;
use WP_UnitTestCase;

/**
 * Covers the built-in weight boundaries.
 */
class TestFabricWeightBands extends WP_UnitTestCase {

	/**
	 * Category names name the garment they hold.
	 *
	 * @dataProvider category_names
	 *
	 * @param string $name    Category name.
	 * @param string $garment Garment expected.
	 */
	public function test_categories_name_their_garment( string $name, string $garment ): void {
		$this->assertSame( $garment, Garment_Kinds::of( $name ) );
	}

	/**
	 * Category names and the garment each names.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function category_names(): array {
		return array(
			'tees'                => array( 'Tshirts', 'tshirt' ),
			'hoodies'             => array( 'Hoodies', 'hoodie' ),
			'leggings'            => array( 'Leggings', 'leggings' ),
			'jogger pants'        => array( 'Jogger Pants', 'joggers' ),
			'sweatpants'          => array( 'Sweatpants', 'joggers' ),
			'jeans'               => array( 'Jeans', 'jeans' ),
			'denim'               => array( 'Denim', 'jeans' ),
			'pants'               => array( 'Pants', 'pants' ),
			'chinos'              => array( 'Chinos', 'pants' ),
			'outerwear'           => array( 'Outerwear', 'jacket' ),
			'nothing in a broad'  => array( 'Clothing', '' ),
			'nothing in a promo'  => array( 'New Arrivals', '' ),
		);
	}

	/** The settings screen gets every garment and each category's garment. */
	public function test_presets_list_garments_and_categories(): void {
		$hoodies = self::factory()->term->create(
			array(
				'taxonomy' => 'product_cat',
				'name'     => 'Hoodies',
			)
		);
		$shirts  = self::factory()->term->create(
			array(
				'taxonomy' => 'product_cat',
				'name'     => 'Shirts',
			)
		);

		$presets = Fabric_Weight_Bands::presets();

		$this->assertContains( 'jeans', array_column( $presets['garments'], 'key' ) );
		$this->assertContains(
			array(
				'category' => $hoodies,
				'garment'  => 'hoodie',
			),
			$presets['categories']
		);
		$this->assertNotContains( $shirts, array_column( $presets['categories'], 'category' ), '"Shirts" may hold tees, so it isn’t guessed.' );
	}
}
