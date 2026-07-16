<?php
/**
 * Catalog cursor and keyset clause unit tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Catalog_Cursor;
use Aggressive_Apparel\WooCommerce\Catalog_Keyset_Clause;
use WP_UnitTestCase;

/** Covers opaque catalog cursors and keyset SQL predicates. */
class TestCatalogCursorPagination extends WP_UnitTestCase {

	/** Round-trip encoding preserves validated payload fields. */
	public function test_cursor_round_trip_for_date_sort(): void {
		$cursors = new Catalog_Cursor();
		$token   = $cursors->encode(
			array(
				'v'  => 'date',
				'id' => 99,
				'd'  => '2026-07-15 12:00:00',
			)
		);

		$this->assertNotSame( '', $token );
		$decoded = $cursors->decode( $token, 'date' );
		$this->assertIsArray( $decoded );
		$this->assertSame( 'date', $decoded['v'] );
		$this->assertSame( 99, $decoded['id'] );
		$this->assertSame( '2026-07-15 12:00:00', $decoded['d'] );
	}

	/** Cursor orderby must match the active request sort. */
	public function test_cursor_rejects_orderby_mismatch(): void {
		$cursors = new Catalog_Cursor();
		$token   = $cursors->encode(
			array(
				'v'  => 'price',
				'id' => 5,
				'p'  => 12.5,
			)
		);

		$this->assertNull( $cursors->decode( $token, 'date' ) );
	}

	/** Malformed tokens are rejected. */
	public function test_cursor_rejects_garbage(): void {
		$cursors = new Catalog_Cursor();
		$this->assertNull( $cursors->decode( '%%%', 'date' ) );
		$this->assertNull( $cursors->decode( '', 'date' ) );
	}

	/** Date keyset clause seeks strictly after the cursor in DESC order. */
	public function test_keyset_clause_for_date_desc(): void {
		global $wpdb;

		$keyset  = new Catalog_Keyset_Clause();
		$clauses = $keyset->apply(
			array(
				'join'    => '',
				'where'   => '',
				'orderby' => '',
			),
			array(
				'v'  => 'date',
				'id' => 10,
				'd'  => '2026-01-01 00:00:00',
			),
			'date'
		);

		$this->assertStringContainsString( "{$wpdb->posts}.post_date <", $clauses['where'] );
		$this->assertStringContainsString( "{$wpdb->posts}.ID < 10", $clauses['where'] );
	}

	/** Price ASC keyset joins the lookup table and seeks forward. */
	public function test_keyset_clause_for_price_asc(): void {
		$keyset  = new Catalog_Keyset_Clause();
		$clauses = $keyset->apply(
			array(
				'join'    => '',
				'where'   => '',
				'orderby' => '',
			),
			array(
				'v'  => 'price',
				'id' => 7,
				'p'  => 19.99,
			),
			'price'
		);

		$this->assertStringContainsString( 'aa_product_lookup', $clauses['join'] );
		$this->assertStringContainsString( 'min_price >', $clauses['where'] );
		$this->assertStringContainsString( 'ID > 7', $clauses['where'] );
	}

	/** Menu-order keyset uses the three-part stable sort tuple. */
	public function test_keyset_clause_for_menu_order(): void {
		global $wpdb;

		$keyset  = new Catalog_Keyset_Clause();
		$clauses = $keyset->apply(
			array(
				'join'    => '',
				'where'   => '',
				'orderby' => '',
			),
			array(
				'v'  => 'menu_order',
				'id' => 3,
				'm'  => 2,
				't'  => 'Alpha',
			),
			'menu_order'
		);

		$this->assertStringContainsString( "{$wpdb->posts}.menu_order > 2", $clauses['where'] );
		$this->assertStringContainsString( "{$wpdb->posts}.post_title > 'Alpha'", $clauses['where'] );
		$this->assertStringContainsString( "{$wpdb->posts}.ID > 3", $clauses['where'] );
	}
}
