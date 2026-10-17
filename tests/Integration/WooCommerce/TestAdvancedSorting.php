<?php
/**
 * Advanced sorting: server-side Featured and Savings orders.
 *
 * @package Aggressive_Apparel\Tests\Integration\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Integration\WooCommerce;

use Aggressive_Apparel\WooCommerce\Advanced_Sorting;
use Aggressive_Apparel\WooCommerce\Catalog_Archive_Filters;
use Aggressive_Apparel\WooCommerce\Product_Collection_Query;
use Aggressive_Apparel\WooCommerce\Sale_Category;
use WP_Query;
use WP_UnitTestCase;

/**
 * Custom sorts are applied by SQL to the archive's own query, flagged with
 * `aa_catalog_custom_sort` (explicit collections) or through WooCommerce's
 * ordering args (the main query), and combine with every catalog filter.
 */
class TestAdvancedSorting extends WP_UnitTestCase {

	/** Sorting service under test. */
	private Advanced_Sorting $sorting;

	/** Query argument builder (filters). */
	private Product_Collection_Query $queries;

	/** Register the sorting and lookup clauses. */
	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( '\WC_Product_Simple' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}

		$this->sorting = new Advanced_Sorting();
		$this->sorting->init();
		$this->queries = new Product_Collection_Query();
		add_filter( 'posts_clauses', array( $this->queries, 'apply_lookup_clauses' ), 10, 2 );
		delete_transient( 'wc_products_onsale' );
	}

	/** Remove the clause filters. */
	public function tearDown(): void {
		remove_filter( 'posts_clauses', array( $this->sorting, 'apply_ssr_ordering' ), 20 );
		remove_filter( 'posts_clauses', array( $this->queries, 'apply_lookup_clauses' ), 10 );
		parent::tearDown();
	}

	/**
	 * Create a published product.
	 *
	 * @param float  $price        Regular price.
	 * @param float  $sale_price   Optional active sale price.
	 * @param string $stock_status WooCommerce stock status.
	 * @param bool   $featured     Whether the product is featured.
	 * @return int Product ID.
	 */
	private function create_product( float $price, float $sale_price = 0, string $stock_status = 'instock', bool $featured = true ): int {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Sorted product ' . wp_generate_password( 6, false ) );
		$product->set_regular_price( (string) $price );
		if ( $sale_price > 0 ) {
			$product->set_sale_price( (string) $sale_price );
		}
		$product->set_stock_status( $stock_status );
		$product->set_featured( $featured );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );

		return (int) $product->save();
	}

	/**
	 * Product IDs for a custom sort plus catalog filters, as an explicit
	 * collection's query would run it.
	 *
	 * @param string               $sort    Custom sort.
	 * @param array<string, mixed> $filters Catalog_Filter_Set input.
	 * @param array<string, mixed> $extra   Extra query vars.
	 * @return array{ids: list<int>, total: int}
	 */
	private function sorted( string $sort, array $filters = array(), array $extra = array() ): array {
		$query = new WP_Query(
			array_merge(
				array(
					'post_type'              => 'product',
					'post_status'            => 'publish',
					'fields'                 => 'ids',
					'posts_per_page'         => 50,
					'aa_catalog_custom_sort' => $sort,
				),
				$this->queries->filter_args( $filters ),
				$extra
			)
		);

		return array(
			'ids'   => array_map( 'intval', $query->posts ),
			'total' => (int) $query->found_posts,
		);
	}

	/** Featured products come first. */
	public function test_featured_sort_puts_featured_first(): void {
		$plain    = $this->create_product( 10, 0, 'instock', false );
		$featured = $this->create_product( 20 );

		$this->assertSame( array( $featured, $plain ), $this->sorted( 'featured' )['ids'] );
	}

	/** Savings ranks discount percentage; full-price products come last. */
	public function test_savings_sort_orders_discount_percentage(): void {
		$half_price    = $this->create_product( 100, 50 );
		$quarter_saved = $this->create_product( 100, 75 );
		$regular       = $this->create_product( 100 );

		$this->assertSame( array( $half_price, $quarter_saved, $regular ), $this->sorted( 'savings' )['ids'] );
	}

	/** The On Sale filter combines with a custom sort. */
	public function test_on_sale_filter_applies_to_featured_sort(): void {
		$sale_id = $this->create_product( 100, 75 );
		$this->create_product( 50 );
		( new Sale_Category() )->sync_product( $sale_id );

		$result = $this->sorted( 'featured', array( 'on_sale' => true ) );

		$this->assertSame( array( $sale_id ), $result['ids'] );
		$this->assertSame( 1, $result['total'] );
	}

	/** Price and stock filters combine with a custom sort. */
	public function test_price_and_stock_filters_apply_to_featured_sort(): void {
		$expected_id = $this->create_product( 50 );
		$this->create_product( 200 );
		$this->create_product( 40, 0, 'outofstock' );

		$result = $this->sorted(
			'featured',
			array(
				'max_price' => 75,
				'stock'     => 'instock',
			)
		);

		$this->assertSame( array( $expected_id ), $result['ids'] );
		$this->assertSame( 1, $result['total'] );
	}

	/** Custom sorts paginate in SQL like any query. */
	public function test_featured_sort_paginates_in_sql(): void {
		$this->create_product( 10 );
		$this->create_product( 20 );
		$this->create_product( 30 );

		$result = $this->sorted(
			'featured',
			array(),
			array(
				'posts_per_page' => 2,
				'paged'          => 2,
			)
		);

		$this->assertCount( 1, $result['ids'] );
		$this->assertSame( 3, $result['total'] );
	}

	/** The shop's main query is sorted by the URL's custom sort. */
	public function test_main_archive_query_takes_the_custom_sort_from_the_url(): void {
		// Featured is the older product: date order alone would put it last.
		$featured = $this->create_product( 20 );
		$plain    = $this->create_product( 10, 0, 'instock', false );
		wp_update_post(
			array(
				'ID'            => $featured,
				'post_date'     => '2020-01-01 00:00:00',
				'post_date_gmt' => '2020-01-01 00:00:00',
			)
		);

		$_GET = array( 'orderby' => 'featured' );
		( new Catalog_Archive_Filters() )->init();
		$this->go_to( add_query_arg( 'orderby', 'featured', (string) get_post_type_archive_link( 'product' ) ) );
		$_GET = array();

		$ids = array_map( static fn( $post ): int => (int) $post->ID, $GLOBALS['wp_query']->posts );
		$this->assertSame( array( $featured, $plain ), array_values( array_intersect( $ids, array( $featured, $plain ) ) ) );
	}

	/** Unflagged secondary queries keep their own order. */
	public function test_other_queries_are_left_alone(): void {
		$first  = $this->create_product( 10, 0, 'instock', false );
		$second = $this->create_product( 20 );

		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'posts_per_page' => 50,
			)
		);

		$this->assertSame( array( $first, $second ), array_map( 'intval', $query->posts ) );
	}
}
