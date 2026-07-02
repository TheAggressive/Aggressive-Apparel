<?php
/**
 * Site Search REST endpoint integration tests.
 *
 * @package Aggressive_Apparel\Tests\Integration\Core
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Integration\Core;

use Aggressive_Apparel\Core\Search;
use Aggressive_Apparel\Core\Search_Index;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Covers the aggressive-apparel/v1/search handler: scope filtering, the
 * published-only / min-length guards, server-side entity decoding, and the
 * coming-soon product gating.
 */
class TestSearch extends WP_UnitTestCase {

	/**
	 * Search service under test.
	 *
	 * @var Search
	 */
	private Search $search;

	/**
	 * Administrator user id (bypasses coming-soon gating + rate limiting).
	 *
	 * @var int
	 */
	private int $admin_id = 0;

	/**
	 * Set up each test as an administrator.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->search   = new Search();
		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );
	}

	/**
	 * Reset current user after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * Run the search handler and return the decoded response payload.
	 *
	 * @param string $query Search term.
	 * @param string $scope Scope (all|product|post|page).
	 * @return array<string, mixed>
	 */
	private function run_search( string $query, string $scope = 'all' ): array {
		$request = new WP_REST_Request( 'GET', '/aggressive-apparel/v1/search' );
		$request->set_param( 'query', $query );
		$request->set_param( 'scope', $scope );

		/** @var array<string, mixed> $data */
		$data = $this->search->handle( $request )->get_data();

		return $data;
	}

	/**
	 * Extract the items for a given group type from a response.
	 *
	 * @param array<string, mixed> $data Response payload.
	 * @param string               $type Group type.
	 * @return array<int, array<string, mixed>>
	 */
	private function group_items( array $data, string $type ): array {
		foreach ( (array) $data['groups'] as $group ) {
			if ( $type === $group['type'] ) {
				return $group['items'];
			}
		}

		return array();
	}

	/**
	 * Whether a response contains an item with the given title.
	 *
	 * @param array<string, mixed> $data  Response payload.
	 * @param string               $type  Group type.
	 * @param string               $title Title to look for.
	 * @return bool
	 */
	private function has_title( array $data, string $type, string $title ): bool {
		foreach ( $this->group_items( $data, $type ) as $item ) {
			if ( $title === $item['title'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Queries shorter than two characters return nothing.
	 *
	 * @return void
	 */
	public function test_short_query_returns_empty(): void {
		self::factory()->post->create( array( 'post_title' => 'Aaa Searchable Post' ) );

		$data = $this->run_search( 'a', 'all' );

		$this->assertSame( array(), $data['groups'] );
		$this->assertSame( 0, $data['total'] );
	}

	/**
	 * HTML entities in titles are decoded server-side (no "&amp;" on the client).
	 *
	 * @return void
	 */
	public function test_titles_have_entities_decoded(): void {
		self::factory()->post->create(
			array(
				'post_title'  => 'Zentropa Returns & Exchanges',
				'post_status' => 'publish',
			)
		);

		$data  = $this->run_search( 'Zentropa', 'post' );
		$items = $this->group_items( $data, 'post' );

		$this->assertNotEmpty( $items, 'Expected the post to be found.' );
		$this->assertSame( 'Zentropa Returns & Exchanges', $items[0]['title'] );
		$this->assertStringNotContainsString( '&amp;', $items[0]['title'] );
		$this->assertStringNotContainsString( '&#038;', $items[0]['title'] );
	}

	/**
	 * Scope filters results to the requested post type.
	 *
	 * @return void
	 */
	public function test_scope_filters_to_requested_type(): void {
		$keyword = 'Zylophone';
		self::factory()->post->create(
			array(
				'post_title'  => "{$keyword} Article",
				'post_status' => 'publish',
			)
		);
		self::factory()->post->create(
			array(
				'post_title'  => "{$keyword} Page",
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);

		$posts_only = $this->run_search( $keyword, 'post' );
		$this->assertNotEmpty( $this->group_items( $posts_only, 'post' ) );
		$this->assertEmpty( $this->group_items( $posts_only, 'page' ) );

		$pages_only = $this->run_search( $keyword, 'page' );
		$this->assertNotEmpty( $this->group_items( $pages_only, 'page' ) );
		$this->assertEmpty( $this->group_items( $pages_only, 'post' ) );
	}

	/**
	 * The "all" scope returns both posts and pages.
	 *
	 * @return void
	 */
	public function test_all_scope_groups_multiple_types(): void {
		$keyword = 'Zentauri';
		self::factory()->post->create(
			array(
				'post_title'  => "{$keyword} Article",
				'post_status' => 'publish',
			)
		);
		self::factory()->post->create(
			array(
				'post_title'  => "{$keyword} Page",
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);

		$data = $this->run_search( $keyword, 'all' );

		$this->assertNotEmpty( $this->group_items( $data, 'post' ) );
		$this->assertNotEmpty( $this->group_items( $data, 'page' ) );
		$this->assertSame( 2, $data['total'] );
		$this->assertArrayHasKey( 'viewAll', $data );
	}

	/**
	 * Unpublished posts never appear in results.
	 *
	 * @return void
	 */
	public function test_excludes_unpublished_posts(): void {
		self::factory()->post->create(
			array(
				'post_title'  => 'Zedraft Hidden Post',
				'post_status' => 'draft',
			)
		);

		$data = $this->run_search( 'Zedraft', 'post' );

		$this->assertEmpty( $this->group_items( $data, 'post' ) );
		$this->assertSame( 0, $data['total'] );
	}

	/**
	 * In coming-soon mode, products are hidden from the public but visible to a
	 * shop manager / administrator.
	 *
	 * @return void
	 */
	public function test_coming_soon_gates_products_from_public(): void {
		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}

		$product = new \WC_Product_Simple();
		$product->set_name( 'Zentinel Hoodie' );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->save();

		update_option( 'woocommerce_coming_soon', 'yes' );

		// Administrator (set in setUp) can see the catalogue while it's hidden.
		$as_admin = $this->run_search( 'Zentinel', 'product' );
		$this->assertTrue(
			$this->has_title( $as_admin, 'product', 'Zentinel Hoodie' ),
			'Admins should see products during coming-soon.'
		);

		// Anonymous visitors must not get products through the endpoint.
		wp_set_current_user( 0 );
		$as_public = $this->run_search( 'Zentinel', 'product' );
		$this->assertEmpty(
			$this->group_items( $as_public, 'product' ),
			'Products must be hidden from the public during coming-soon.'
		);
	}

	/**
	 * Without coming-soon mode the public can find visible products.
	 *
	 * @return void
	 */
	public function test_public_can_find_products_when_store_is_live(): void {
		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}

		$product = new \WC_Product_Simple();
		$product->set_name( 'Zenith Joggers' );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->save();

		update_option( 'woocommerce_coming_soon', 'no' );
		wp_set_current_user( 0 );

		$data = $this->run_search( 'Zenith', 'product' );

		$this->assertTrue( $this->has_title( $data, 'product', 'Zenith Joggers' ) );
	}

	/**
	 * The large-catalog index supports prefix title and SKU lookups.
	 *
	 * @return void
	 */
	public function test_token_index_finds_product_title_and_sku_prefixes(): void {
		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}

		$index = new Search_Index();
		$index->maybe_install();

		// Complete the test index without clearing shared fixture rows. Production
		// rebuilds begin at cursor zero and process 250 records per action.
		$index->process_rebuild_batch( PHP_INT_MAX );

		$product = new \WC_Product_Simple();
		$product->set_name( 'Nebulonic Trail Jacket' );
		$product->set_sku( 'NEBULA-8421' );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product_id = $product->save();

		$index->sync_ids( array( $product_id ) );

		$this->assertContains( $product_id, $index->search( 'product', 'Nebul', 8 ) );
		$this->assertContains( $product_id, $index->search( 'product', '842', 8 ) );

		$health = $index->add_debug_information( array() );
		$this->assertSame( 'ready', $health['aggressive_apparel_search_index']['fields']['state']['value'] );
	}
}
