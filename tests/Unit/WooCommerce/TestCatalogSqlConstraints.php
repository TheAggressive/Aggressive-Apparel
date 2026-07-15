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
		$constraints->add_taxonomy( 'product_cat', array( 'shirts', 'shoes' ) );

		$this->assertSame( 3, substr_count( $constraints->joins(), '%s' ) );
		$this->assertSame( array( 'product_cat', 'shirts', 'shoes' ), $constraints->join_params() );
	}
}
