<?php
/**
 * The shared category read and name matching that Size & Specs relies on.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Match_Name;
use Aggressive_Apparel\WooCommerce\Product_Categories;
use Aggressive_Apparel\WooCommerce\Size_Guide;
use WP_UnitTestCase;

/**
 * Covers Product_Categories and Match_Name.
 */
class TestProductCategories extends WP_UnitTestCase {

	/** Each category is named by its place for people and by its slug path for other sites. */
	public function test_names_and_paths(): void {
		$clothing = $this->category( 'Clothing' );
		$hoodies  = $this->category( 'Hoodies & Fleece', $clothing );

		$this->assertSame( 'Clothing › Hoodies & Fleece', Product_Categories::label( $hoodies ) );
		$this->assertSame( 'clothing/hoodies-fleece', Product_Categories::path( $hoodies ) );
		$this->assertSame( $hoodies, Product_Categories::at_path( 'clothing/hoodies-fleece' ) );
		$this->assertSame( 0, Product_Categories::at_path( 'clothing/none' ) );
		$this->assertSame( 'Clothing', array_values( Product_Categories::labels() )[0], 'Sorted by label.' );
	}

	/** A category given a guide mid-request is seen at once. */
	public function test_guides_refresh_when_categories_change(): void {
		$tees = $this->category( 'Tees' );
		$this->assertSame( array(), Product_Categories::by_guide() );

		update_term_meta( $tees, Size_Guide::CPT_META_KEY, 42 );

		$this->assertSame( array( 42 => array( $tees ) ), Product_Categories::by_guide() );
	}

	/** Names compare without entities, extra spaces or case, in any language. */
	public function test_names_match_loosely(): void {
		$this->assertSame( Match_Name::key( "Men's  Tee" ), Match_Name::key( 'men&#039;s tee' ) );
		$this->assertSame( Match_Name::key( 'ÄRMEL' ), Match_Name::key( 'ärmel' ) );
		$this->assertNotSame( Match_Name::key( 'Regular' ), Match_Name::key( 'Relaxed' ) );
	}

	/**
	 * A product category.
	 *
	 * @param string $name   Name.
	 * @param int    $parent Parent ID.
	 */
	private function category( string $name, int $parent = 0 ): int {
		return self::factory()->term->create(
			array(
				'taxonomy' => 'product_cat',
				'name'     => $name,
				'parent'   => $parent,
			)
		);
	}
}
