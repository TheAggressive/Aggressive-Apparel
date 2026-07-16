<?php
/**
 * Load More / product-filters rendered endpoint integration tests.
 *
 * @package Aggressive_Apparel\Tests\Integration\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Integration\WooCommerce;

use Aggressive_Apparel\WooCommerce\Catalog_Cache_Version;
use Aggressive_Apparel\WooCommerce\Load_More_Controller;
use Aggressive_Apparel\WooCommerce\Product_Collection_Query;
use Aggressive_Apparel\WooCommerce\Product_Fragment_Renderer;
use Aggressive_Apparel\WooCommerce\Rendered_Product_Cache;
use Aggressive_Apparel\WooCommerce\Sale_Category;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_UnitTestCase;
use WP_Query;

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
class TestLoadMoreController extends WP_UnitTestCase {

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
		( new Load_More_Controller() )->init();

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
	 * Facet cache should bump when attribute/category terms change.
	 */
	public function test_term_lifecycle_hooks_invalidate_catalog_caches(): void {
		$controller = new Load_More_Controller();
		$controller->init();

		$this->assertNotFalse(
			has_action( 'created_term', array( $controller, 'invalidate_catalog_cache' ) )
		);
		$this->assertNotFalse(
			has_action( 'edited_term', array( $controller, 'invalidate_catalog_cache' ) )
		);
		$this->assertNotFalse(
			has_action( 'delete_term', array( $controller, 'invalidate_catalog_cache' ) )
		);

		$before = (int) get_option( 'aa_pf_facets_version', 1 );
		$controller->invalidate_catalog_cache();
		$after = (int) get_option( 'aa_pf_facets_version', 1 );

		$this->assertSame( $before + 1, $after );
	}

	/** Lookup-table predicates are prepared directly into WP_Query clauses. */
	public function test_product_lookup_clauses_prepare_filter_values(): void {
		$queries = new Product_Collection_Query();
		$query   = new WP_Query();
		$query->set(
			'aa_catalog_lookup_filters',
			array(
				'min_price' => 10.5,
				'max_price' => 40.25,
				'stock'     => 'instock',
			)
		);
		$query->set( 'aa_catalog_stock_statuses', array( 'instock', 'onbackorder' ) );
		$query->set( 'aa_catalog_excluded_ids', array( 123, 456 ) );
		$query->set( 'aa_catalog_on_sale', true );

		$clauses = $queries->apply_lookup_clauses(
			array(
				'join'    => '',
				'where'   => '',
				'orderby' => '',
			),
			$query
		);

		$this->assertStringContainsString( '`aa_product_lookup`.max_price >= 10.500000', $clauses['where'] );
		$this->assertStringContainsString( '`aa_product_lookup`.min_price <= 40.250000', $clauses['where'] );
		$this->assertStringContainsString( "`aa_product_lookup`.stock_status = 'instock'", $clauses['where'] );
		$this->assertStringContainsString( "`aa_product_lookup`.stock_status = 'instock' OR `aa_product_lookup`.stock_status = 'onbackorder'", $clauses['where'] );
		$this->assertStringContainsString( '`aa_product_lookup`.onsale = 1', $clauses['where'] );
		$this->assertStringContainsString( '`wp_posts`.ID != 123', $clauses['where'] );
		$this->assertStringContainsString( '`wp_posts`.ID != 456', $clauses['where'] );
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
	 * Extract rendered product IDs from card classes.
	 *
	 * @param string $html Rendered card markup.
	 * @return int[]
	 */
	private function product_ids_from_html( string $html ): array {
		preg_match_all( '/\bpost-(\d+)\b/', $html, $matches );
		return array_values( array_unique( array_map( 'intval', $matches[1] ?? array() ) ) );
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
			'<!-- wp:woocommerce/product-collection {"query":{"inherit":true},"displayLayout":{"type":"flex","columns":3}} -->'
			. '<div class="wp-block-woocommerce-product-collection">'
			. '<!-- wp:woocommerce/product-template -->'
			. '<!-- wp:paragraph {"className":"resolved-template-card","style":{"color":{"text":"#123456"}}} -->'
			. '<p class="resolved-template-card has-text-color" style="color:#123456">Resolved template card</p>'
			. '<!-- /wp:paragraph -->'
			. '<!-- /wp:woocommerce/product-template -->'
			. '</div><!-- /wp:woocommerce/product-collection -->'
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

	/** Pathological deep-page requests are rejected before querying MySQL. */
	public function test_page_depth_is_bounded(): void {
		$this->assertSame( 400, $this->dispatch( array( 'page' => 1001 ) )->get_status() );
	}

	/** Anonymous fragment cache keys vary with query, template, and version. */
	public function test_rendered_response_cache_key_is_versioned_and_contextual(): void {
		$cache = new Rendered_Product_Cache( new Catalog_Cache_Version() );
		add_filter( 'aggressive_apparel_rendered_products_cache_enabled', '__return_true' );

		$block = array( 'blockName' => 'woocommerce/product-collection', 'attrs' => array( 'queryId' => 1 ) );
		$key   = $cache->key( array( 'paged' => 1 ), $block, false );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $key );
		$this->assertNotSame( $key, $cache->key( array( 'paged' => 2 ), $block, false ) );
		$this->assertNotSame( $key, $cache->key( array( 'paged' => 1 ), $block, true ) );

		update_option( 'aa_pf_facets_version', (int) get_option( 'aa_pf_facets_version', 1 ) + 1, false );
		$this->assertNotSame( $key, $cache->key( array( 'paged' => 1 ), $block, false ) );
		remove_filter( 'aggressive_apparel_rendered_products_cache_enabled', '__return_true' );
	}

	/** Default cache eligibility follows authentication and object-cache state. */
	public function test_rendered_response_cache_default_eligibility(): void {
		$cache                 = new Rendered_Product_Cache( new Catalog_Cache_Version() );
		$block                 = array( 'blockName' => 'woocommerce/product-collection' );
		$original_user         = get_current_user_id();
		$original_object_cache = wp_using_ext_object_cache();

		try {
			wp_set_current_user( 0 );
			wp_using_ext_object_cache( true );
			$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $cache->key( array( 'paged' => 1 ), $block, false ) );

			wp_set_current_user( $this->admin_id );
			$this->assertSame( '', $cache->key( array( 'paged' => 1 ), $block, false ) );

			wp_set_current_user( 0 );
			wp_using_ext_object_cache( false );
			$this->assertSame( '', $cache->key( array( 'paged' => 1 ), $block, false ) );
		} finally {
			wp_using_ext_object_cache( $original_object_cache );
			wp_set_current_user( $original_user );
		}
	}

	/** Persistent caching remains safe before WooCommerce creates a customer. */
	public function test_rendered_response_cache_handles_missing_customer(): void {
		$woocommerce           = \WC();
		$original_customer     = $woocommerce->customer;
		$woocommerce->customer = null;
		$observed_context      = null;
		$observe_context       = static function ( array $context ) use ( &$observed_context ): array {
			$observed_context = $context;
			return $context;
		};
		add_filter( 'aggressive_apparel_rendered_products_cache_enabled', '__return_true' );
		add_filter( 'aggressive_apparel_rendered_products_cache_context', $observe_context );

		try {
			$response = $this->dispatch( array( 'page' => 1, 'per_page' => 1 ) );
		} finally {
			remove_filter( 'aggressive_apparel_rendered_products_cache_context', $observe_context );
			remove_filter( 'aggressive_apparel_rendered_products_cache_enabled', '__return_true' );
			$woocommerce->customer = $original_customer;
		}

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $observed_context );
		$default_location = wc_get_customer_default_location();
		$this->assertSame( (string) $default_location['country'], $observed_context['country'] );
		$this->assertSame( (string) $default_location['state'], $observed_context['state'] );
		$this->assertArrayHasKey( 'postcode', $observed_context );
		$this->assertArrayHasKey( 'city', $observed_context );
	}

	/** Non-serializable extension context disables caching without key collisions. */
	public function test_rendered_response_cache_rejects_unencodable_context(): void {
		$cache           = new Rendered_Product_Cache( new Catalog_Cache_Version() );
		$invalid_context = static function ( array $context ): array {
			$context['invalid_number'] = NAN;
			return $context;
		};
		add_filter( 'aggressive_apparel_rendered_products_cache_enabled', '__return_true' );
		add_filter( 'aggressive_apparel_rendered_products_cache_context', $invalid_context );

		try {
			$key = $cache->key( array( 'paged' => 1 ), array( 'blockName' => 'woocommerce/product-collection' ), false );
		} finally {
			remove_filter( 'aggressive_apparel_rendered_products_cache_context', $invalid_context );
			remove_filter( 'aggressive_apparel_rendered_products_cache_enabled', '__return_true' );
		}

		$this->assertSame( '', $key );
	}

	/** A failed regeneration can release its cache lock immediately. */
	public function test_rendered_response_cache_lock_can_be_released(): void {
		$cache = new Rendered_Product_Cache( new Catalog_Cache_Version() );
		$key   = 'test-lock-' . wp_generate_uuid4();

		$this->assertTrue( $cache->acquire_lock( $key ) );
		$this->assertFalse( $cache->acquire_lock( $key ) );
		$cache->release_lock( $key, true );
		$this->assertTrue( $cache->acquire_lock( $key ) );
		$cache->release_lock( $key, true );
	}

	/** A contended regeneration lock leaves the stale response available. */
	public function test_rendered_response_cache_serves_stale_while_locked(): void {
		$cache = new Rendered_Product_Cache( new Catalog_Cache_Version() );
		$key   = 'test-stale-' . wp_generate_uuid4();
		$data  = array( 'html' => '<li>Stale product</li>' );

		$cache->store( $key, $data, false );
		wp_cache_delete( $key, 'aggressive-apparel-rendered-products' );

		$this->assertTrue( $cache->acquire_lock( $key ) );
		$this->assertFalse( $cache->acquire_lock( $key ) );
		$this->assertNull( $cache->fresh( $key ) );
		$this->assertSame( $data, $cache->stale( $key ) );

		$cache->release_lock( $key, true );
	}

	/** Fragment extraction distinguishes a missing wrapper from an empty list. */
	public function test_fragment_extraction_reports_missing_product_template(): void {
		$renderer = new Product_Fragment_Renderer();

		$this->assertNull( $renderer->extract_template_items( '<div>No product list</div>' ) );
		$this->assertSame( '', $renderer->extract_template_items( '<ul class="wc-block-product-template"></ul>' ) );
		$this->assertSame(
			'<li><ul><li>Nested item</li></ul></li>',
			$renderer->extract_template_items( '<ul class="wc-block-product-template"><li><ul><li>Nested item</li></ul></li></ul>' )
		);
	}

	/** Malformed template output becomes an observable REST error, not empty data. */
	public function test_missing_product_template_wrapper_returns_observable_error(): void {
		$this->create_product( 31.0 );
		$slug = 'missing-product-list-' . wp_generate_password( 6, false );
		$this->create_block_template(
			$slug,
			'<!-- wp:woocommerce/product-collection {"query":{"inherit":true}} -->'
			. '<div class="wp-block-woocommerce-product-collection">'
			. '<!-- wp:paragraph --><p>Invalid collection structure</p><!-- /wp:paragraph -->'
			. '</div><!-- /wp:woocommerce/product-collection -->'
		);

		$reported    = null;
		$diagnostics = null;
		$listener    = static function ( \Throwable $error, \WP_REST_Request $request, array $query_args, array $context ) use ( &$reported, &$diagnostics ): void {
			$reported    = $error;
			$diagnostics = $context;
		};
		add_action( 'aggressive_apparel_catalog_render_error', $listener, 10, 4 );

		try {
			$response = $this->dispatch( array( 'template' => $slug ) );
		} finally {
			remove_action( 'aggressive_apparel_catalog_render_error', $listener );
		}

		$this->assertSame( 500, $response->get_status() );
		$this->assertSame( 'catalog_render_failed', $response->get_data()['code'] );
		$this->assertInstanceOf( \UnexpectedValueException::class, $reported );
		$this->assertSame( 'disabled', $diagnostics['cache_status'] );
		$this->assertSame( sanitize_title( $slug ), $diagnostics['template'] );
	}

	/** Identical public render requests are served from the fragment cache. */
	public function test_rendered_response_cache_avoids_repeating_product_query(): void {
		$this->create_product( 29.0 );
		add_filter( 'aggressive_apparel_rendered_products_cache_enabled', '__return_true' );
		$query_count = 0;
		$counter     = static function ( array $clauses ) use ( &$query_count ): array {
			++$query_count;
			return $clauses;
		};
		add_filter( 'posts_clauses', $counter, 999 );

		$params = array( 'page' => 1, 'per_page' => 1, 'orderby' => 'date' );
		$first  = $this->dispatch( $params )->get_data();
		$after_first = $query_count;
		$second      = $this->dispatch( $params )->get_data();

		$this->assertGreaterThan( 0, $after_first );
		$this->assertSame( $after_first, $query_count );
		$this->assertSame( $first, $second );

		remove_filter( 'posts_clauses', $counter, 999 );
		remove_filter( 'aggressive_apparel_rendered_products_cache_enabled', '__return_true' );
	}

	/**
	 * WooCommerce's default catalog sort must be accepted and remain stable.
	 *
	 * @return void
	 */
	public function test_menu_order_matches_woocommerce_default_sorting(): void {
		$response = $this->dispatch( array( 'orderby' => 'menu_order' ) );

		$this->assertSame( 200, $response->get_status() );

		$queries = new Product_Collection_Query();
		$args    = $queries->build_args( 2, 12, 'menu_order', '', '', array() );

		$this->assertSame(
			array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
				'ID'         => 'ASC',
			),
			$args['orderby']
		);
		$this->assertSame( 2, $args['paged'] );

		$date_args = $queries->build_args( 1, 12, 'date', '', '', array() );
		$this->assertSame( array( 'date' => 'DESC', 'ID' => 'DESC' ), $date_args['orderby'] );

		$title_args = $queries->build_args( 1, 12, 'title-asc', '', '', array() );
		$this->assertSame( array( 'title' => 'ASC', 'ID' => 'ASC' ), $title_args['orderby'] );
	}

	/**
	 * Saved Product Collection constraints survive dynamic pagination.
	 *
	 * @return void
	 */
	public function test_template_query_constraints_are_validated_and_composed(): void {
		$queries = new Product_Collection_Query();
		$args    = $queries->build_args( 2, 12, 'date', '', '', array() );
		$block = array(
			'attrs' => array(
				'collection' => 'woocommerce/product-collection/featured',
				'query'      => array(
					'offset'                         => 3,
					'pages'                          => 2,
					'search'                         => 'shirt',
					'exclude'                        => array( 90, -91, 'invalid' ),
					'woocommerceHandPickedProducts' => array( 10, 20, 20 ),
					'woocommerceStockStatus'        => array( 'instock', 'invalid' ),
					'woocommerceOnSale'              => true,
					'taxQuery'                       => array(
						array(
							'taxonomy' => 'product_cat',
							'terms'    => array( 7 ),
						),
						array(
							'taxonomy' => 'private_taxonomy',
							'terms'    => array( 8 ),
						),
					),
				),
			),
		);

		$result = $queries->apply_collection_constraints( $args, $block, 2, 12 );

		$this->assertSame( array( 90, 91 ), $result['aa_catalog_excluded_ids'] );
		$this->assertSame( array( 10, 20 ), $result['post__in'] );
		$this->assertSame( 'shirt', $result['s'] );
		$this->assertSame( 15, $result['offset'] );
		$this->assertSame( 3, $result['aa_offset'] );
		$this->assertSame( 2, $result['aa_page_limit'] );
		$this->assertSame( array( 'instock' ), $result['aa_catalog_stock_statuses'] );
		$this->assertTrue( $result['aa_catalog_on_sale'] );
		$this->assertSame( 'AND', $result['tax_query']['relation'] );
		$this->assertSame( 'product_visibility', $result['tax_query'][0]['taxonomy'] );
		$this->assertSame( 'product_cat', $result['tax_query'][1]['taxonomy'] );

		$block['attrs']['query']['woocommerceStockStatus'] = array();
		$empty_stock = $queries->apply_collection_constraints( $args, $block, 1, 12 );
		$this->assertSame( array( 'aa-none' ), $empty_stock['aa_catalog_stock_statuses'] );
	}

	/** Offset collections report totals relative to their visible result set. */
	public function test_template_offset_and_page_limit_adjust_totals(): void {
		$queries            = new Product_Collection_Query();
		$query              = new WP_Query();
		$query->found_posts = 50;

		$this->assertSame(
			array( 'products' => 20, 'pages' => 2 ),
			$queries->totals( $query, array( 'aa_offset' => 5, 'aa_page_limit' => 2 ), 10 )
		);
	}

	/** Explicit hand-picked/excluded/offset collections paginate as one dataset. */
	public function test_explicit_collection_constraints_paginate_without_leaking_products(): void {
		if ( ! class_exists( '\WC_Product_Simple' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}

		$ids = array(
			$this->create_product( 11.0 ),
			$this->create_product( 12.0 ),
			$this->create_product( 13.0 ),
			$this->create_product( 14.0 ),
		);
		$attrs = array(
			'queryId' => 77,
			'query'   => array(
				'perPage'                         => 1,
				'pages'                           => 0,
				'offset'                          => 1,
				'postType'                        => 'product',
				'inherit'                         => false,
				'exclude'                         => array( $ids[1] ),
				'woocommerceHandPickedProducts'  => $ids,
				'woocommerceStockStatus'         => array( 'instock', 'outofstock', 'onbackorder' ),
				'woocommerceOnSale'               => false,
				'woocommerceAttributes'           => array(),
			),
		);
		$content = '<!-- wp:woocommerce/product-collection ' . wp_json_encode( $attrs ) . ' -->'
			. '<div class="wp-block-woocommerce-product-collection"><!-- wp:woocommerce/product-template -->'
			. '<!-- wp:post-title {"isLink":true} /-->'
			. '<!-- /wp:woocommerce/product-template --></div>'
			. '<!-- /wp:woocommerce/product-collection -->';
		$slug = 'explicit-products-' . wp_generate_password( 6, false );
		$this->create_block_template( $slug, $content );

		$page_one = $this->dispatch( array( 'template' => $slug, 'per_page' => 1, 'page' => 1, 'orderby' => 'menu_order' ) )->get_data();
		$page_two = $this->dispatch( array( 'template' => $slug, 'per_page' => 1, 'page' => 2, 'orderby' => 'menu_order' ) )->get_data();
		$page_three = $this->dispatch( array( 'template' => $slug, 'per_page' => 1, 'page' => 3, 'orderby' => 'menu_order' ) )->get_data();

		$this->assertSame( 2, (int) $page_one['total_products'] );
		$this->assertSame( 2, (int) $page_one['total_pages'] );
		$this->assertSame( 2, (int) $page_three['total_products'] );
		$this->assertSame( 2, (int) $page_three['total_pages'] );
		$this->assertSame( '', $page_three['html'] );
		$this->assertNotSame( $page_one['html'], $page_two['html'] );
		$this->assertStringNotContainsString( 'post-' . $ids[1], $page_one['html'] . $page_two['html'] );
		foreach ( $this->product_ids_from_html( $page_one['html'] . $page_two['html'] ) as $rendered_id ) {
			$this->assertContains( $rendered_id, $ids );
		}
	}

	/**
	 * Oversized `include` lists are capped to the request page size (max 100).
	 *
	 * @return void
	 */
	public function test_include_param_is_capped_to_per_page(): void {
		$queries = new Product_Collection_Query();

		$ids  = implode( ',', range( 1, 250 ) );
		$args = $queries->build_args(
			1,
			12,
			'date',
			'',
			'',
			array( 'include' => $ids )
		);

		$this->assertCount( 12, $args['post__in'] );
		$this->assertSame( 12, $args['posts_per_page'] );

		$args_max = $queries->build_args(
			1,
			100,
			'date',
			'',
			'',
			array( 'include' => $ids )
		);

		$this->assertCount( 100, $args_max['post__in'] );
		$this->assertSame( 100, $args_max['posts_per_page'] );
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

	/** Explicit template queries cannot override endpoint paging or sorting. */
	public function test_explicit_collection_query_uses_requested_page_and_order(): void {
		if ( ! class_exists( '\WC_Product_Simple' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}

		$low_id  = $this->create_product( 10.0 );
		$high_id = $this->create_product( 90.0 );

		$this->create_block_template(
			'archive-product',
			'<!-- wp:woocommerce/product-collection {"query":{"perPage":8,"inherit":false,"orderBy":"date","order":"desc"}} -->'
			. '<div class="wp-block-woocommerce-product-collection">'
			. '<!-- wp:woocommerce/product-template -->'
			. '<!-- wp:post-title {"isLink":true} /-->'
			. '<!-- /wp:woocommerce/product-template -->'
			. '</div><!-- /wp:woocommerce/product-collection -->'
		);

		$page_one = $this->dispatch(
			array(
				'template' => 'archive-product',
				'per_page' => 1,
				'page'     => 1,
				'orderby'  => 'price',
			)
		)->get_data();
		$page_two = $this->dispatch(
			array(
				'template' => 'archive-product',
				'per_page' => 1,
				'page'     => 2,
				'orderby'  => 'price',
			)
		)->get_data();

		$this->assertStringContainsString( 'post-' . $low_id, $page_one['html'] );
		$this->assertStringNotContainsString( 'post-' . $high_id, $page_one['html'] );
		$this->assertStringContainsString( 'post-' . $high_id, $page_two['html'] );
		$this->assertStringNotContainsString( 'post-' . $low_id, $page_two['html'] );
	}

	/**
	 * Load-more responses include CSS for their rendered wp-elements classes.
	 *
	 * Regression: appended cards rendered inner blocks only, so Site Editor link
	 * colors on product-collection did not match page-1 SSR cards.
	 *
	 * @return void
	 */
	public function test_rendered_element_styles_are_returned_with_cards(): void {
		if ( ! class_exists( '\WC_Product_Simple' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}

		$this->create_product( 42.0 );

		$this->create_block_template(
			'archive-product',
			'<!-- wp:woocommerce/product-collection {"queryId":1,"query":{"perPage":12,"pages":0,"offset":0,"postType":"product","order":"desc","orderBy":"date","search":"","exclude":[],"inherit":true,"taxQuery":[],"isProductCollectionBlock":true,"featured":false,"woocommerceOnSale":false,"woocommerceStockStatus":["instock","outofstock","onbackorder"],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[]},"displayLayout":{"type":"flex","columns":3}} -->'
			. '<div class="wp-block-woocommerce-product-collection">'
			. '<!-- wp:woocommerce/product-template -->'
			. '<!-- wp:post-title {"level":3,"isLink":true,"style":{"elements":{"link":{"color":{"text":"#ff0000"},":hover":{"color":{"text":"#00ff00"}}}}}} /-->'
			. '<!-- wp:woocommerce/product-price {"isDescendentOfQueryLoop":true} /-->'
			. '<!-- /wp:woocommerce/product-template -->'
			. '</div>'
			. '<!-- /wp:woocommerce/product-collection -->'
		);

		$response = $this->dispatch(
			array(
				'template' => 'archive-product',
				'per_page' => 5,
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertNotEmpty( $data['html'] );
		$this->assertCount( 1, $data['styles'] );

		$this->assertMatchesRegularExpression( '/wp-elements-[a-f0-9]+/', $data['html'] );
		preg_match( '/(wp-elements-[a-f0-9]+)/', $data['html'], $matches );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $data['styles'][0]['id'] );
		$this->assertStringContainsString( '.' . $matches[1], $data['styles'][0]['css'] );
		$this->assertStringContainsString( 'color:#ff0000', $data['styles'][0]['css'] );
		$this->assertStringContainsString( 'color:#00ff00', $data['styles'][0]['css'] );
		$this->assertStringContainsString( 'wp-block-post-title', $data['html'] );
	}
}
