<?php
/**
 * Catalog filters and sorts applied to the archive's real query.
 *
 * @package Aggressive_Apparel\Tests\Integration\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Integration\WooCommerce;

use Aggressive_Apparel\WooCommerce\Catalog_Archive_Filters;
use Aggressive_Apparel\WooCommerce\Catalog_Filter_Set;
use Aggressive_Apparel\WooCommerce\Catalog_Url_Filters;
use Aggressive_Apparel\WooCommerce\Product_Facets_Controller;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * A filtered URL renders the filtered products on the server, for inherited
 * collections (main query) and the archive's explicit collection alike.
 */
class TestCatalogArchiveFilters extends WP_UnitTestCase {

	/** Ensure the Colour attribute and its taxonomy; reset globals. */
	public function setUp(): void {
		parent::setUp();
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is required.' );
		}
		// Other tests create attributes inside rolled-back transactions while
		// WooCommerce caches the attribute list: never trust that cache here.
		self::flush_attribute_cache();
		if ( ! in_array( 'pa_color', wc_get_attribute_taxonomy_names(), true ) ) {
			// A taxonomy left registered by a rolled-back test blocks the slug.
			unregister_taxonomy( 'pa_color' );
			wc_create_attribute(
				array(
					'name' => 'Color',
					'slug' => 'color',
				)
			);
			self::flush_attribute_cache();
		}
		if ( ! taxonomy_exists( 'pa_color' ) ) {
			register_taxonomy( 'pa_color', array( 'product' ), array( 'hierarchical' => false ) );
		}
		$_GET = array();
	}

	/** Reset the query string and the attribute cache this class filled. */
	public function tearDown(): void {
		$_GET = array();
		unset( $GLOBALS['_wp_current_template_id'] );
		parent::tearDown();
		self::flush_attribute_cache();
	}

	/** Drop WooCommerce's cached attribute list. */
	private static function flush_attribute_cache(): void {
		delete_transient( 'wc_attribute_taxonomies' );
		\WC_Cache_Helper::invalidate_cache_group( 'woocommerce-attributes' );
	}

	/** The URL contract parses, validates and bounds every parameter. */
	public function test_url_contract_parses_and_validates(): void {
		$this->assertSame( array(), Catalog_Url_Filters::from_query( array( 'orderby' => 'price' ) ), 'Sorting alone refines nothing.' );

		$this->assertSame(
			array(
				'category'   => 'tees,hoodies',
				'attributes' => array(
					'pa_color'      => 'black,red',
					'pa_size'       => 'm',
					'pa_collection' => 'mens',
				),
				'min_price'  => 20.0,
				'max_price'  => 80.5,
				'stock'      => 'instock',
				'on_sale'    => true,
			),
			Catalog_Url_Filters::from_query(
				array(
					'cat'        => 'tees,hoodies',
					'color'      => 'Black,red,red',
					'size'       => 'm',
					'collection' => 'mens',
					'min_price'  => '20',
					'max_price'  => '80.5',
					'stock'      => 'instock',
					'on_sale'    => '1',
				)
			)
		);

		$this->assertSame(
			array(),
			Catalog_Url_Filters::from_query(
				array(
					'stock'     => 'gone',
					'min_price' => '-5',
					'on_sale'   => 'yes',
					'color'     => array( 'black' ),
				)
			),
			'Invalid values are dropped, never passed through.'
		);
	}

	/** The PHP parameter map matches the filter script's ATTRIBUTE_FILTERS. */
	public function test_attribute_params_match_the_filter_script(): void {
		$types = (string) file_get_contents( get_template_directory() . '/src/interactivity/product-filters/types.ts' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local theme file.
		preg_match_all( "/taxonomy: '([a-z_]+)',\\s*urlParam: '([a-z_]+)'/", $types, $matches, PREG_SET_ORDER );

		$script = array();
		foreach ( $matches as $match ) {
			$script[ $match[2] ] = $match[1];
		}

		$this->assertSame( Catalog_Url_Filters::ATTRIBUTE_PARAMS, $script );
	}

	/** The server's sorts match the ones the filter script puts in URLs. */
	public function test_sorts_match_the_filter_script(): void {
		$url_state = (string) file_get_contents( get_template_directory() . '/src/interactivity/product-filters/url-state.ts' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local theme file.
		$this->assertSame( 1, preg_match( '/SORTS[^=]*= new Set\(\[(.*?)\]\)/s', $url_state, $match ) );
		preg_match_all( "/'([a-z_-]+)'/", $match[1], $sorts );

		$this->assertSame( Catalog_Archive_Filters::SORTS, $sorts[1] );
	}

	/** A filtered category URL lists only matches and stays the category. */
	public function test_main_archive_query_is_filtered(): void {
		$tees  = self::factory()->term->create( array( 'taxonomy' => 'product_cat', 'slug' => 'filter-tees' ) );
		$black = $this->product( $tees, 'black' );
		$this->product( $tees, 'red' );

		$_GET = array( 'color' => 'black' );
		( new Catalog_Archive_Filters() )->init();
		$this->go_to( add_query_arg( 'color', 'black', (string) get_term_link( $tees ) ) );

		$this->assertSame( array( $black ), array_map( static fn( $post ) => (int) $post->ID, $GLOBALS['wp_query']->posts ) );
		$this->assertSame( $tees, get_queried_object_id(), 'The archive stays the category, not the colour.' );
	}

	/** A `?cat=` slug with a digit is a product category, never post category 2. */
	public function test_category_slugs_with_digits_are_not_post_categories(): void {
		$y2k     = self::factory()->term->create( array( 'taxonomy' => 'product_cat', 'slug' => 'y2k-tees' ) );
		$hoodies = self::factory()->term->create( array( 'taxonomy' => 'product_cat', 'slug' => 'filter-hoodies' ) );
		$tee     = $this->product( $y2k, 'black' );
		$hoodie  = $this->product( $hoodies, 'black' );

		$_GET = array( 'cat' => 'y2k-tees,filter-hoodies' );
		( new Catalog_Archive_Filters() )->init();
		$this->go_to( add_query_arg( 'cat', 'y2k-tees,filter-hoodies', (string) get_post_type_archive_link( 'product' ) ) );

		$ids = array_map( static fn( $post ) => (int) $post->ID, $GLOBALS['wp_query']->posts );
		sort( $ids );
		$this->assertSame( array( $tee, $hoodie ), $ids );
		$this->assertFalse( is_category(), 'Core must not read the slugs as post category IDs.' );
		$this->assertSame( array( 'cat' => '12' ), ( new Catalog_Archive_Filters() )->drop_category_slugs( array( 'cat' => '12' ) ), 'Core ID lists pass through.' );
	}

	/**
	 * The archive's explicit collection gets filters and sort; other
	 * collections and inherited ones are left alone.
	 */
	public function test_explicit_archive_collection_query_is_filtered_and_sorted(): void {
		$this->create_block_template(
			'archive-product',
			'<!-- wp:woocommerce/product-collection {"queryId":7,"query":{"perPage":8,"inherit":false,"isProductCollectionBlock":true}} --><div class="wp-block-woocommerce-product-collection"></div><!-- /wp:woocommerce/product-collection -->'
		);
		$this->go_to( (string) get_post_type_archive_link( 'product' ) );
		// Set by WordPress when it resolves the archive's block template.
		$GLOBALS['_wp_current_template_id'] = get_stylesheet() . '//archive-product';

		$filters = new Catalog_Archive_Filters();
		$block   = static fn( int $query_id, bool $inherit ): \WP_Block => new \WP_Block(
			array( 'blockName' => 'core/query-pagination' ),
			array(
				'queryId' => $query_id,
				'query'   => array(
					'isProductCollectionBlock' => true,
					'inherit'                  => $inherit,
				),
			)
		);

		$_GET = array(
			'color'   => 'black',
			'orderby' => 'price-desc',
		);
		$vars = $filters->filter_collection_query( array( 'orderby' => 'date' ), $block( 7, false ) );

		$this->assertSame( 'pa_color', $vars['tax_query'][0]['taxonomy'] ?? null );
		$this->assertSame( 'price', $vars['aa_catalog_lookup_orderby'] ?? null );
		$this->assertSame( 'DESC', $vars['order'] ?? null );
		$this->assertArrayNotHasKey( 'orderby', $vars, 'The block order gives way to the requested sort.' );

		$_GET = array( 'orderby' => 'featured' );
		$this->assertSame( 'featured', $filters->filter_collection_query( array(), $block( 7, false ) )['aa_catalog_custom_sort'] ?? null );

		$_GET = array();
		$this->assertSame(
			array(
				'orderby' => array(
					'menu_order' => 'ASC',
					'ID'         => 'ASC',
				),
				'order'   => 'asc',
			),
			$filters->filter_collection_query(
				array(
					'orderby' => 'menu_order',
					'order'   => 'asc',
				),
				$block( 7, false )
			),
			'The block order ends on the ID, so pages never overlap.'
		);

		$_GET = array( 'color' => 'black' );
		$this->assertSame( array( 'orderby' => 'date' ), $filters->filter_collection_query( array( 'orderby' => 'date' ), $block( 99, false ) ), 'Other collections keep their query.' );
		$this->assertSame( array(), $filters->filter_collection_query( array(), $block( 7, true ) ), 'Inherited collections use the main query.' );
	}

	/**
	 * A filtered archive's main collection always has a No Results block, so
	 * WooCommerce keeps rendering it (and its router region) when empty.
	 */
	public function test_filtered_archive_collection_gets_a_no_results_block(): void {
		$this->go_to( (string) get_post_type_archive_link( 'product' ) );
		$filters    = new Catalog_Archive_Filters();
		$collection = array(
			'blockName'    => 'woocommerce/product-collection',
			'attrs'        => array(
				'queryId' => 3,
				'query'   => array(
					'inherit'                  => true,
					'isProductCollectionBlock' => true,
				),
			),
			'innerBlocks'  => array( array( 'blockName' => 'woocommerce/product-template' ) ),
			'innerHTML'    => '<div class="wp-block-woocommerce-product-collection"></div>',
			'innerContent' => array( '<div class="wp-block-woocommerce-product-collection">', null, '</div>' ),
		);

		$this->assertSame( $collection, $filters->add_no_results_block( $collection ), 'Unfiltered requests are untouched.' );

		$_GET  = array( 'color' => 'black' );
		$added = $filters->add_no_results_block( $collection );

		$this->assertSame( 'woocommerce/product-collection-no-results', $added['innerBlocks'][1]['blockName'] );
		$this->assertStringContainsString( 'aa-catalog-no-results', $added['innerBlocks'][1]['innerHTML'] );
		$this->assertSame( array( '<div class="wp-block-woocommerce-product-collection">', null, null, '</div>' ), $added['innerContent'] );
		$this->assertSame( $added, $filters->add_no_results_block( $added ), 'A template with its own No Results block keeps it.' );

		$related                             = $collection;
		$related['attrs']['query']['inherit'] = false;
		$this->assertSame( $related, $filters->add_no_results_block( $related ), 'Other collections are untouched.' );
	}

	/**
	 * A filtered URL with no matches renders the notice inside the collection.
	 */
	public function test_empty_filtered_collection_renders_the_notice(): void {
		$_GET = array( 'color' => 'no-such-colour' );
		( new Catalog_Archive_Filters() )->init();
		$this->go_to( add_query_arg( 'color', 'no-such-colour', (string) get_post_type_archive_link( 'product' ) ) );

		$html = do_blocks( '<!-- wp:woocommerce/product-collection {"queryId":3,"query":{"inherit":true,"isProductCollectionBlock":true}} --><div class="wp-block-woocommerce-product-collection"><!-- wp:woocommerce/product-template --><!-- wp:post-title /--><!-- /wp:woocommerce/product-template --></div><!-- /wp:woocommerce/product-collection -->' );

		$this->assertStringContainsString( 'aa-catalog-no-results', $html );
		$this->assertStringContainsString( 'data-wp-router-region', $html );
	}

	/** The facets route answers with data only, and product changes invalidate it. */
	public function test_facets_route_and_invalidation(): void {
		update_option( 'woocommerce_coming_soon', 'no' );
		$controller = new Product_Facets_Controller();
		$controller->init();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/aggressive-apparel/v1/catalog/facets' ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'facets', $response->get_data() );

		// Values must be slugs: nested input is rejected before any query, and
		// never reaches the filter parser as an "array to string" warning.
		$nested = new WP_REST_Request( 'GET', '/aggressive-apparel/v1/catalog/facets' );
		$nested->set_query_params( array( 'attributes' => array( 'pa_color' => array( array( 'x' ) ) ) ) );
		$this->assertSame( 400, rest_do_request( $nested )->get_status() );

		$list = new WP_REST_Request( 'GET', '/aggressive-apparel/v1/catalog/facets' );
		$list->set_query_params( array( 'attributes' => array( 'pa_color' => array( 'black', 'red' ) ) ) );
		$this->assertSame( 200, rest_do_request( $list )->get_status() );

		$this->assertSame(
			array( 'pa_color' => array( 'red' ) ),
			( new Catalog_Filter_Set( array( 'attributes' => array( 'pa_color' => array( array( 'x' ), 'red' ) ) ) ) )->attributes(),
			'Other callers get the string values only.'
		);

		$before = (int) get_option( 'aa_pf_facets_version', 1 );
		do_action( 'edited_term', 1, 1, 'product_cat' );
		$this->assertGreaterThan( $before, (int) get_option( 'aa_pf_facets_version', 1 ) );

		$wp_rest_server = null;
	}

	/**
	 * Create a published product in a category with a colour.
	 *
	 * @param int    $category Category ID.
	 * @param string $color    Colour slug.
	 */
	private function product( int $category, string $color ): int {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Filter product ' . $color );
		$product->set_status( 'publish' );
		$product->set_regular_price( '20' );
		$product->set_category_ids( array( $category ) );
		$id = $product->save();
		wp_set_object_terms( $id, array( $color ), 'pa_color' );

		return $id;
	}

	/**
	 * Save a block template for the active theme.
	 *
	 * @param string $slug    Template slug.
	 * @param string $content Template content.
	 */
	private function create_block_template( string $slug, string $content ): void {
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
	}
}
