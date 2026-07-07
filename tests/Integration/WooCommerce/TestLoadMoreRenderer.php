<?php
/**
 * Load More / product-filters rendered endpoint integration tests.
 *
 * @package Aggressive_Apparel\Tests\Integration\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Integration\WooCommerce;

use Aggressive_Apparel\WooCommerce\Load_More_Renderer;
use Aggressive_Apparel\WooCommerce\Sale_Category;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * Covers the aggressive-apparel/v1/products/rendered endpoint that powers Load
 * More and the product-filters UI.
 *
 * The primary purpose is a regression guard: the filter params are sanitized
 * through the REST pipeline ( sanitize_callback is invoked as
 * `( $value, $request, $key )` ), and using PHP-internal one-arg functions
 * (`floatval`) or a function whose 2nd parameter is meaningful
 * (`sanitize_title`'s `$fallback_title`) there caused a fatal on every request.
 * These params are therefore exercised through `rest_do_request()` — which runs
 * the sanitize layer — not the handler directly.
 */
class TestLoadMoreRenderer extends WP_UnitTestCase {

	/** REST route under test. */
	private const ROUTE = '/aggressive-apparel/v1/products/rendered';

	/**
	 * Administrator id (bypasses coming-soon gating + rate limiting).
	 *
	 * @var int
	 */
	private int $admin_id = 0;

	/**
	 * Register the route on a fresh REST server and authenticate as admin.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );

		// Make the catalogue publicly queryable so the handler doesn't early-return.
		update_option( 'woocommerce_coming_soon', 'no' );

		// Boot the endpoint and (re)build the REST server so the route is live.
		( new Load_More_Renderer() )->init();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down the REST server and reset the current user.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * Dispatch the rendered-products route through the full REST pipeline.
	 *
	 * @param array<string, mixed> $params Query params.
	 * @return WP_REST_Response
	 */
	private function dispatch( array $params ): WP_REST_Response {
		$request = new WP_REST_Request( 'GET', self::ROUTE );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_do_request( $request );
	}

	/**
	 * Create a published, visible simple product.
	 *
	 * @param float            $price     Regular price.
	 * @param array<int,string> $cat_slugs Category slugs to assign.
	 * @return int Product id (0 when WooCommerce is unavailable).
	 */
	private function create_product( float $price, array $cat_slugs = array() ): int {
		if ( ! class_exists( '\WC_Product_Simple' ) ) {
			return 0;
		}

		$product = new \WC_Product_Simple();
		$product->set_name( 'Test Product ' . wp_generate_password( 6, false ) );
		$product->set_regular_price( (string) $price );
		$product->set_price( (string) $price );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product_id = $product->save();

		if ( ! empty( $cat_slugs ) ) {
			$term_ids = array();
			foreach ( $cat_slugs as $slug ) {
				$existing = term_exists( $slug, 'product_cat' );
				$term     = $existing ? $existing : wp_insert_term( ucfirst( $slug ), 'product_cat', array( 'slug' => $slug ) );
				if ( ! is_wp_error( $term ) ) {
					$term_ids[] = (int) $term['term_id'];
				}
			}
			wp_set_object_terms( $product_id, $term_ids, 'product_cat' );
		}

		return (int) $product_id;
	}

	/**
	 * Create an active-theme block template in the Site Editor data store.
	 *
	 * @param string $slug    Template post-name.
	 * @param string $content Serialized block content.
	 * @return int Template post ID.
	 */
	private function create_block_template( string $slug, string $content ): int {
		$template_id = self::factory()->post->create(
			array(
				'post_type'    => 'wp_template',
				'post_status'  => 'publish',
				'post_name'    => $slug,
				'post_title'   => $slug,
				'post_content' => $content,
			)
		);

		wp_set_object_terms( $template_id, get_stylesheet(), 'wp_theme' );
		wp_cache_flush();

		return $template_id;
	}

	/**
	 * A term-specific resolved template supplies the appended card structure.
	 *
	 * @return void
	 */
	public function test_term_specific_template_renders_its_product_card_blocks(): void {
		if ( ! class_exists( '\WC_Product_Simple' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}

		$this->create_product( 25.0, array( 'shirts' ) );
		$this->create_block_template(
			'taxonomy-product_cat-shirts',
			'<!-- wp:woocommerce/product-template -->'
			. '<!-- wp:paragraph {"className":"resolved-template-card","style":{"color":{"text":"#123456"}}} -->'
			. '<p class="resolved-template-card has-text-color" style="color:#123456">Resolved template card</p>'
			. '<!-- /wp:paragraph -->'
			. '<!-- /wp:woocommerce/product-template -->'
		);

		$response = $this->dispatch(
			array(
				'template' => 'taxonomy-product_cat-shirts',
				'category' => 'shirts',
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertStringContainsString( 'resolved-template-card', $data['html'] );
		$this->assertStringContainsString( 'color:#123456', $data['html'] );
	}

	/**
	 * Missing or irrelevant resolved templates retain the product archive card.
	 *
	 * @return void
	 */
	public function test_missing_template_falls_back_to_product_archive(): void {
		if ( ! class_exists( '\WC_Product_Simple' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}

		$this->create_product( 30.0 );

		$response = $this->dispatch( array( 'template' => 'missing-product-template' ) );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertStringContainsString( 'wp-block-post-title', $data['html'] );
		$this->assertStringNotContainsString( 'Template not found', $data['html'] );
	}

	/**
	 * The full set of filter params must sanitize and dispatch without a fatal.
	 *
	 * Regression: `floatval` / `sanitize_title` sanitize callbacks threw
	 * ArgumentCountError / a string-cast TypeError, 500-ing every request.
	 *
	 * @return void
	 */
	public function test_filter_params_do_not_fatal(): void {
		$response = $this->dispatch(
			array(
				'per_page'   => 3,
				'min_price'  => 10.5,
				'max_price'  => 99.99,
				'attributes' => array( 'pa_color' => 'black,blue' ),
				'category'   => 'clothing',
				'stock'      => 'instock',
				'on_sale'    => true,
				'orderby'    => 'price',
			)
		);

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertLessThan( 500, $response->get_status(), 'Filter params must not 500.' );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'html', $data );
		$this->assertArrayHasKey( 'total_products', $data );
		$this->assertArrayHasKey( 'total_pages', $data );
	}

	/**
	 * Float price params must sanitize without an ArgumentCountError.
	 *
	 * @return void
	 */
	public function test_price_params_accept_floats(): void {
		$response = $this->dispatch(
			array(
				'min_price' => 0.01,
				'max_price' => 1234.56,
			)
		);

		$this->assertLessThan( 500, $response->get_status() );
	}

	/**
	 * An empty `term` (the filter UI never sends one) must not return the
	 * request object from sanitize_title's $fallback_title.
	 *
	 * @return void
	 */
	public function test_empty_term_does_not_fatal(): void {
		$response = $this->dispatch(
			array(
				'taxonomy' => 'product_cat',
				'term'     => '',
			)
		);

		$this->assertLessThan( 500, $response->get_status() );
	}

	/**
	 * An ordered `include` list must sanitize/dispatch cleanly (custom sort path).
	 *
	 * @return void
	 */
	public function test_include_param_does_not_fatal(): void {
		$response = $this->dispatch( array( 'include' => '1,2,3' ) );

		$this->assertLessThan( 500, $response->get_status() );
	}

	/**
	 * The price filter narrows results to products within the band.
	 *
	 * @return void
	 */
	public function test_price_filter_narrows_results(): void {
		if ( ! class_exists( '\WC_Product_Simple' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}

		$this->create_product( 5.0 );
		$this->create_product( 50.0 );
		$this->create_product( 500.0 );

		$all   = $this->dispatch( array( 'per_page' => 10 ) )->get_data();
		$band  = $this->dispatch(
			array(
				'per_page'  => 10,
				'min_price' => 10.0,
				'max_price' => 100.0,
			)
		)->get_data();

		$this->assertSame( 3, (int) $all['total_products'] );
		$this->assertSame( 1, (int) $band['total_products'], 'Only the $50 product falls in the $10–$100 band.' );
	}

	/**
	 * The on-sale filter returns only products WooCommerce currently considers on sale.
	 *
	 * @return void
	 */
	public function test_on_sale_filter_narrows_results(): void {
		if ( ! class_exists( '\WC_Product_Simple' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}

		$sale_id       = $this->create_product( 100.0, array( 'clothing' ) );
		$other_sale_id = $this->create_product( 80.0, array( 'accessories' ) );
		$this->create_product( 50.0, array( 'clothing' ) );

		$sale_product = wc_get_product( $sale_id );
		$this->assertInstanceOf( \WC_Product::class, $sale_product );
		$sale_product->set_sale_price( '75' );
		$sale_product->save();
		$other_sale_product = wc_get_product( $other_sale_id );
		$this->assertInstanceOf( \WC_Product::class, $other_sale_product );
		$other_sale_product->set_sale_price( '60' );
		$other_sale_product->save();

		$sale_category = new Sale_Category();
		$sale_category->sync_product( $sale_id );
		$sale_category->sync_product( $other_sale_id );

		$data = $this->dispatch(
			array(
				'per_page' => 10,
				'on_sale'  => true,
				'category' => 'clothing',
			)
		)->get_data();

		$this->assertSame( 1, (int) $data['total_products'], 'Sales and shopper categories must combine with AND.' );
		$this->assertTrue( has_term( Sale_Category::TERM_SLUG, 'product_cat', $sale_id ) );
	}

	/**
	 * A facets-only request returns availability keyed by attribute taxonomy and
	 * never renders card HTML.
	 *
	 * @return void
	 */
	public function test_facets_only_returns_facet_shape(): void {
		$response = $this->dispatch( array( 'facets_only' => true ) );

		$this->assertLessThan( 500, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'facets', $data );
		$this->assertIsArray( $data['facets'] );
		$this->assertArrayNotHasKey( 'html', $data, 'facets_only must skip card rendering.' );

		// Each computed facet (present only for registered attribute taxonomies)
		// must map to an array of slugs.
		foreach ( $data['facets'] as $slugs ) {
			$this->assertIsArray( $slugs );
		}
	}

	/**
	 * `with_facets` attaches availability to a normal results response.
	 *
	 * @return void
	 */
	public function test_with_facets_attaches_facets_to_results(): void {
		$response = $this->dispatch(
			array(
				'per_page'    => 3,
				'with_facets' => true,
			)
		);

		$this->assertLessThan( 500, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'facets', $data );
		$this->assertArrayHasKey( 'html', $data );
	}

	/**
	 * The category filter narrows results, and combines with price (AND).
	 *
	 * @return void
	 */
	public function test_category_filter_narrows_and_combines(): void {
		if ( ! class_exists( '\WC_Product_Simple' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}

		$this->create_product( 20.0, array( 'shirts' ) );
		$this->create_product( 25.0, array( 'shirts' ) );
		$this->create_product( 30.0, array( 'hats' ) );

		$shirts = $this->dispatch(
			array(
				'per_page' => 10,
				'category' => 'shirts',
			)
		)->get_data();
		$this->assertSame( 2, (int) $shirts['total_products'] );

		// Category + price together (AND): only the $25 shirt qualifies.
		$combo = $this->dispatch(
			array(
				'per_page'  => 10,
				'category'  => 'shirts',
				'min_price' => 24.0,
				'max_price' => 26.0,
			)
		)->get_data();
		$this->assertSame( 1, (int) $combo['total_products'] );
	}
}
