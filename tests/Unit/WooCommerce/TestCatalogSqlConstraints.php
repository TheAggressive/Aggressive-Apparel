<?php
/**
 * Catalog SQL constraint tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Catalog_SQL_Constraints;
use InvalidArgumentException;
use WP_UnitTestCase;

/** Guards the structural SQL generated for indexed catalogue queries. */
class TestCatalogSqlConstraints extends WP_UnitTestCase {

	/** Constructor aliases must be plain SQL identifiers. */
	public function test_rejects_unsafe_aliases(): void {
		$this->expectException( InvalidArgumentException::class );

		new Catalog_SQL_Constraints( 'p; DROP TABLE posts', 'aa_filter' );
	}

	/** Lookup-table names must be validated before clause generation. */
	public function test_rejects_unsafe_lookup_table(): void {
		$constraints = new Catalog_SQL_Constraints();

		$this->expectException( InvalidArgumentException::class );
		$constraints->add_attribute( 'pa_color', array( 'black' ), 'lookup JOIN injected' );
	}

	/** Dynamic values remain placeholders with a matching parameter list. */
	public function test_taxonomy_values_remain_parameterized(): void {
		$constraints = new Catalog_SQL_Constraints();
		$constraints->add_taxonomy( 'product_tag', array( 'shirts', 'shoes' ) );

		$this->assertSame( 3, substr_count( $constraints->joins(), '%s' ) );
		$this->assertSame( array( 'product_tag', 'shirts', 'shoes' ), $constraints->join_params() );
	}

	/**
	 * Hierarchical taxonomies match descendants, like WP_Query's
	 * `include_children`, so facets and custom sorts agree with the archive.
	 */
	public function test_hierarchical_taxonomy_includes_children_as_id_params(): void {
		$parent = self::factory()->term->create( array( 'taxonomy' => 'product_cat', 'slug' => 'sql-parent' ) );
		$child  = self::factory()->term->create( array( 'taxonomy' => 'product_cat', 'slug' => 'sql-child', 'parent' => $parent ) );

		$constraints = new Catalog_SQL_Constraints();
		$constraints->add_taxonomy( 'product_cat', array( 'sql-parent', 'missing' ) );

		$this->assertSame( 1, substr_count( $constraints->joins(), '%s' ) );
		$this->assertSame( 2, substr_count( $constraints->joins(), '%d' ) );
		$this->assertSame( array( 'product_cat', $parent, $child ), $constraints->join_params() );

		// Unknown slugs only: match nothing, never everything.
		$none = new Catalog_SQL_Constraints();
		$none->add_taxonomy( 'product_cat', array( 'missing' ) );
		$this->assertSame( array( 'product_cat', 0 ), $none->join_params() );
	}
}
