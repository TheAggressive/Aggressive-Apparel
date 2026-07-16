<?php
/**
 * Catalog pagination seed contract tests (public API — no reflection).
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Catalog_Cursor;
use Aggressive_Apparel\WooCommerce\Catalog_Pagination_Seed;
use WP_Query;
use WP_UnitTestCase;

/**
 * Guards orderby / perPage resolution that keeps SSR seed aligned with REST.
 */
class TestCatalogPaginationSeed extends WP_UnitTestCase {

	/**
	 * Explicit Product Collection orderBy must win over the catalog default.
	 *
	 * @return void
	 */
	public function test_explicit_collection_orderby_date_wins_over_catalog_default(): void {
		update_option( 'woocommerce_default_catalog_orderby', 'menu_order' );

		$seed  = new Catalog_Pagination_Seed();
		$block = array(
			'attrs' => array(
				'query' => array(
					'inherit' => false,
					'orderBy' => 'date',
					'order'   => 'desc',
					'perPage' => 8,
				),
			),
		);

		$this->assertSame( 'date', $seed->resolve_orderby( $block ) );
		$this->assertSame( 8, $seed->resolve_per_page( $block ) );
	}

	/**
	 * Inherited collections ignore block orderBy and use the main query sort.
	 *
	 * @return void
	 */
	public function test_inherited_collection_uses_main_query_orderby(): void {
		global $wp_query;

		$previous                        = $wp_query;
		$wp_query                        = new WP_Query();
		$wp_query->query_vars['orderby'] = array(
			'menu_order' => 'ASC',
			'title'      => 'ASC',
			'ID'         => 'ASC',
		);

		$seed  = new Catalog_Pagination_Seed();
		$block = array(
			'attrs' => array(
				'query' => array(
					'inherit' => true,
					'orderBy' => 'date',
					'order'   => 'desc',
				),
			),
		);

		$this->assertSame( 'menu_order', $seed->resolve_orderby( $block ) );

		$wp_query = $previous;
	}

	/**
	 * Explicit request orderby wins over collection and main query.
	 *
	 * @return void
	 */
	public function test_requested_orderby_wins_over_collection(): void {
		$seed  = new Catalog_Pagination_Seed();
		$block = array(
			'attrs' => array(
				'query' => array(
					'inherit' => false,
					'orderBy' => 'date',
					'order'   => 'desc',
				),
			),
		);

		$this->assertSame( 'price', $seed->resolve_orderby( $block, 'price' ) );
	}

	/**
	 * Collection query.orderBy maps title/price directions correctly.
	 *
	 * @return void
	 */
	public function test_collection_query_orderby_maps_title_and_price(): void {
		$seed = new Catalog_Pagination_Seed();

		$this->assertSame(
			'title-desc',
			$seed->orderby_from_collection_query(
				array(
					'orderBy' => 'title',
					'order'   => 'desc',
				)
			)
		);
		$this->assertSame(
			'price',
			$seed->orderby_from_collection_query(
				array(
					'orderBy' => 'price',
					'order'   => 'asc',
				)
			)
		);
	}

	/**
	 * Cursor version must match the continuation orderby.
	 *
	 * @return void
	 */
	public function test_cursor_version_must_match_continuation_orderby(): void {
		$post             = new \WP_Post( (object) array() );
		$post->ID         = 42;
		$post->post_date  = '2026-07-01 12:00:00';
		$post->post_title = 'Alpha';
		$post->menu_order = 3;
		$post->post_type  = 'product';

		$cursors = new Catalog_Cursor();
		$date    = $cursors->from_post( $post, 'date' );
		$menu    = $cursors->from_post( $post, 'menu_order' );

		$this->assertNotNull( $cursors->decode( $date, 'date' ) );
		$this->assertNull( $cursors->decode( $date, 'menu_order' ) );
		$this->assertNull( $cursors->decode( $menu, 'date' ) );
		$this->assertNotNull( $cursors->decode( $menu, 'menu_order' ) );
	}
}
