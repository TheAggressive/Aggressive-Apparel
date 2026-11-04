<?php
/**
 * Typical fabric weights by garment: telling garments apart, fitting the
 * boundaries to a spec, and filling an imported guide's categories.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Fabric_Weight_Bands;
use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Garment_Kinds;
use Aggressive_Apparel\WooCommerce\Product_Specs_Registry;
use Aggressive_Apparel\WooCommerce\Product_Specs_Taxonomy;
use Aggressive_Apparel\Tests\Support\Restores_Taxonomies;
use Aggressive_Apparel\Tests\Support\Saves_Spec_Registry;
use WP_UnitTestCase;

/**
 * Covers the built-in weight boundaries.
 */
class TestFabricWeightBands extends WP_UnitTestCase {

	use Restores_Taxonomies;
	use Saves_Spec_Registry;

	/**
	 * Enable the feature and start from the default registry.
	 */
	public function setUp(): void {
		parent::setUp();

		update_option( Feature_Settings::OPTION_KEY, array( 'size_guide' => true ), false );
		delete_option( Product_Specs_Registry::OPTION_KEY );
		Product_Specs_Registry::flush();
		Product_Specs_Taxonomy::flush();
		$this->snapshot_taxonomies();
	}

	/**
	 * Restore global state.
	 */
	public function tearDown(): void {
		$this->restore_taxonomies();
		delete_option( Feature_Settings::OPTION_KEY );
		delete_option( Product_Specs_Registry::OPTION_KEY );
		Product_Specs_Registry::flush();

		parent::tearDown();
	}

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

	/** Only a number in g/m², or oz/yd², is a fabric weight. */
	public function test_what_counts_as_a_weight(): void {
		$this->assertTrue( Fabric_Weight_Bands::is_weight( $this->weight() ) );
		$this->assertTrue( Fabric_Weight_Bands::is_weight( $this->weight( array( 'unit' => 'GSM' ) ) ) );
		$this->assertTrue( Fabric_Weight_Bands::is_weight( $this->weight( array( 'unit' => 'oz/yd²' ) ) ) );
		$this->assertTrue( Fabric_Weight_Bands::is_weight( $this->weight( array( 'unit' => 'oz' ) ) ), 'Bare oz on a spec named for weight.' );
		$this->assertFalse(
			Fabric_Weight_Bands::is_weight(
				$this->weight(
					array(
						'unit' => 'oz',
						'name' => 'Bottle size',
					)
				)
			)
		);
		$this->assertFalse( Fabric_Weight_Bands::is_weight( $this->weight( array( 'unit' => 'cm' ) ) ) );
	}

	/** Bands are converted to the spec's unit and spread to its step count. */
	public function test_bands_fit_the_spec(): void {
		$hoodie = Fabric_Weight_Bands::BANDS['hoodie'];

		$this->assertSame( array( 240.0, 270.0, 330.0, 360.0 ), Fabric_Weight_Bands::fit( $hoodie, $this->weight() ) );
		$this->assertSame( array( 7.1, 8.0, 9.7, 10.6 ), Fabric_Weight_Bands::fit( $hoodie, $this->weight( array( 'unit' => 'oz/yd²' ) ) ) );
		$this->assertSame(
			array( 240.0, 360.0 ),
			Fabric_Weight_Bands::fit( $hoodie, $this->weight( array( 'steps' => array( 'Light', 'Mid', 'Heavy' ) ) ) ),
			'Three steps: the bands’ ends.'
		);
	}

	/** An imported guide's categories get their garment's boundaries, unless they or a parent have some. */
	public function test_import_fills_its_categories(): void {
		$this->save_defaults( array() );
		$clothing = self::factory()->term->create(
			array(
				'taxonomy' => 'product_cat',
				'name'     => 'Clothing',
			)
		);
		$hoodies  = self::factory()->term->create(
			array(
				'taxonomy' => 'product_cat',
				'name'     => 'Hoodies',
			)
		);
		$sweats   = self::factory()->term->create(
			array(
				'taxonomy' => 'product_cat',
				'name'     => 'Sweatshirts',
				'parent'   => $clothing,
			)
		);
		$this->retype(
			'weight',
			array(
				'category_steps' => array(
					array(
						'category'   => $clothing,
						'thresholds' => array( 200, 250, 300, 350 ),
					),
				),
			)
		);

		Fabric_Weight_Bands::fill( 'hoodie', array( $hoodies, $sweats ) );
		Fabric_Weight_Bands::fill( 'tshirt', array( $hoodies ) );

		$rows = array_column( $this->items()['weight']['category_steps'], 'thresholds', 'category' );
		$this->assertSame( array( 240.0, 270.0, 330.0, 360.0 ), $rows[ $hoodies ] );
		$this->assertArrayNotHasKey( $sweats, $rows, 'Its parent has boundaries already.' );
		$this->assertCount( 2, $rows, 'A tee is the spec’s own scale, so it adds nothing.' );
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

	/**
	 * The built-in Fabric weight spec, with changes.
	 *
	 * @param array<string, mixed> $fields Fields to change.
	 * @return array<string, mixed>
	 */
	private function weight( array $fields = array() ): array {
		$item = array_column( Product_Specs_Registry::defaults(), null, 'key' )['weight'];

		return array_merge( $item, $fields );
	}
}
