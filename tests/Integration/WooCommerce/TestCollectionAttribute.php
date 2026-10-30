<?php
/**
 * Collection attribute tests: filtering, facets and independence from Fit.
 *
 * @package Aggressive_Apparel\Tests\Integration\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Integration\WooCommerce;

use Aggressive_Apparel\WooCommerce\Catalog_Cache_Version;
use Aggressive_Apparel\WooCommerce\Product_Collection_Query;
use Aggressive_Apparel\WooCommerce\Product_Facet_Service;
use Aggressive_Apparel\WooCommerce\Product_Specs_Registry;
use WP_Query;
use WP_UnitTestCase;

/** Collection (audience) filters and facets independently of Fit. */
class TestCollectionAttribute extends WP_UnitTestCase {

	/** Register a clean `pa_fit` with audience and real-fit terms. */
	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( '\WC_Product_Simple' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}

		$this->attribute( 'fit', 'Fit' );
		foreach ( array( 'mens', 'womens', 'slim' ) as $slug ) {
			$this->term( 'pa_fit', $slug );
		}
	}

	/** Unregister the taxonomies the tests registered at runtime. */
	public function tearDown(): void {
		unregister_taxonomy( 'pa_fit' );
		unregister_taxonomy( 'pa_collection' );
		parent::tearDown();
	}

	/** Collection filtering combines with category, and Unisex is its own term. */
	public function test_collection_filter_combines_with_category(): void {
		$this->collection();

		$tanks  = self::factory()->term->create( array( 'taxonomy' => 'product_cat', 'slug' => 'tank-tops' ) );
		$tees   = self::factory()->term->create( array( 'taxonomy' => 'product_cat', 'slug' => 't-shirts' ) );
		$womens = $this->simple( array( 'pa_collection' => array( 'womens' ) ), array( $tanks ) );
		$mens   = $this->simple( array( 'pa_collection' => array( 'mens' ) ), array( $tanks ) );
		$unisex = $this->simple( array( 'pa_collection' => array( 'unisex' ) ), array( $tees ) );

		$this->assertSame( array( $womens ), $this->filtered( array( 'category' => 'tank-tops', 'attributes' => array( 'pa_collection' => 'womens' ) ) ) );
		$this->assertSame( array( $mens ), $this->filtered( array( 'category' => 'tank-tops', 'attributes' => array( 'pa_collection' => 'mens' ) ) ) );
		$this->assertSame( array( $unisex ), $this->filtered( array( 'attributes' => array( 'pa_collection' => 'unisex' ) ) ) );
		$this->assertEqualsCanonicalizing(
			array( $womens, $unisex ),
			$this->filtered( array( 'attributes' => array( 'pa_collection' => 'womens,unisex' ) ) )
		);
	}

	/** Facets report Collection availability and no longer query pa_fit. */
	public function test_facets_measure_collection(): void {
		$this->collection();

		$tanks = self::factory()->term->create( array( 'taxonomy' => 'product_cat', 'slug' => 'tank-tops' ) );
		$this->simple(
			array(
				'pa_collection' => array( 'womens' ),
				'pa_fit'        => array( 'slim' ),
			),
			array( $tanks )
		);

		$facets = ( new Product_Facet_Service( new Product_Collection_Query(), new Catalog_Cache_Version() ) )
			->compute( array( 'category' => 'tank-tops' ), '', '' );

		$this->assertArrayHasKey( 'pa_collection', $facets );
		$this->assertArrayNotHasKey( 'pa_fit', $facets );
		$this->assertSame( array( 'womens' ), $facets['pa_collection'] );

		// A different taxonomy list never reuses the cached result.
		$only_color = static fn(): array => array( 'pa_color' );
		add_filter( 'aggressive_apparel_filter_facet_taxonomies', $only_color );
		$narrowed = ( new Product_Facet_Service( new Product_Collection_Query(), new Catalog_Cache_Version() ) )
			->compute( array( 'category' => 'tank-tops' ), '', '' );
		remove_filter( 'aggressive_apparel_filter_facet_taxonomies', $only_color );
		$this->assertArrayNotHasKey( 'pa_collection', $narrowed );
	}

	/**
	 * Spelling variants of one selection share a cache entry, so a client
	 * cannot write a transient per variant.
	 */
	public function test_facet_cache_is_keyed_on_the_normalized_selection(): void {
		$this->collection();
		foreach ( array( 'tank-tops', 'hoodies' ) as $slug ) {
			self::factory()->term->create(
				array(
					'taxonomy' => 'product_cat',
					'slug'     => $slug,
				)
			);
		}
		$service = new Product_Facet_Service( new Product_Collection_Query(), new Catalog_Cache_Version() );
		$service->compute(
			array(
				'category'   => 'tank-tops,hoodies',
				'attributes' => array( 'pa_collection' => 'womens,mens' ),
			),
			'',
			''
		);

		$writes = 0;
		$count  = static function ( string $transient ) use ( &$writes ): void {
			if ( str_starts_with( $transient, 'aa_pf_facets_' ) ) {
				++$writes;
			}
		};
		add_action( 'set_transient', $count );
		$service->compute(
			array(
				'category'   => 'Hoodies,tank-tops,hoodies',
				'attributes' => array(
					'pa_collection' => 'mens,womens',
					'not_allowed'   => 'x',
				),
				'ignored'    => 'anything',
			),
			'not_a_taxonomy',
			'term'
		);
		remove_action( 'set_transient', $count );

		$this->assertSame( 0, $writes, 'The variant was served from the first entry.' );
	}

	/**
	 * Only selections the filter UI repeats are cached: made-up slugs and
	 * free-form prices compute uncached so callers can't mint a transient each.
	 */
	public function test_facet_cache_skips_unbounded_selections(): void {
		$this->collection();
		self::factory()->term->create(
			array(
				'taxonomy' => 'product_cat',
				'slug'     => 'tank-tops',
			)
		);
		$service = new Product_Facet_Service( new Product_Collection_Query(), new Catalog_Cache_Version() );
		$writes  = 0;
		$count   = static function ( string $transient ) use ( &$writes ): void {
			if ( str_starts_with( $transient, 'aa_pf_facets_' ) ) {
				++$writes;
			}
		};
		add_action( 'set_transient', $count );

		$service->compute( array( 'attributes' => array( 'pa_collection' => 'not-a-real-term' ) ), '', '' );
		$service->compute( array( 'min_price' => 12.34 ), '', '' );
		$this->assertSame( 0, $writes );

		$service->compute( array( 'category' => 'tank-tops' ), '', '' );
		remove_action( 'set_transient', $count );
		$this->assertSame( 1, $writes );
	}

	/** The Fit spec scale stays a silhouette scale with no attribute source. */
	public function test_fit_spec_is_independent_of_collection(): void {
		$fit = null;
		foreach ( Product_Specs_Registry::defaults() as $item ) {
			if ( 'fit' === $item['key'] ) {
				$fit = $item;
			}
		}

		$this->assertNotNull( $fit );
		$this->assertSame( array(), $fit['attributes'], 'Fit must not read an audience attribute.' );
		$this->assertNotContains( "Men's", $fit['steps'] );
		$this->assertNotContains( "Women's", $fit['steps'] );
	}

	/**
	 * Create a global attribute and register its taxonomy for this request.
	 *
	 * @param string $slug  Attribute slug.
	 * @param string $label Attribute label.
	 */
	private function attribute( string $slug, string $label ): void {
		// Other tests create attributes inside rolled-back transactions while
		// WooCommerce caches the attribute list: check the real list.
		delete_transient( 'wc_attribute_taxonomies' );
		\WC_Cache_Helper::invalidate_cache_group( 'woocommerce-attributes' );
		if ( ! in_array( wc_attribute_taxonomy_name( $slug ), wc_get_attribute_taxonomy_names(), true ) ) {
			// A taxonomy left registered by a rolled-back test blocks the slug.
			unregister_taxonomy( wc_attribute_taxonomy_name( $slug ) );
			wc_create_attribute(
				array(
					'name' => $label,
					'slug' => $slug,
				)
			);
			delete_transient( 'wc_attribute_taxonomies' );
			\WC_Cache_Helper::invalidate_cache_group( 'woocommerce-attributes' );
		}
		$taxonomy = wc_attribute_taxonomy_name( $slug );
		if ( ! taxonomy_exists( $taxonomy ) ) {
			register_taxonomy( $taxonomy, array( 'product' ), array( 'hierarchical' => false ) );
		}
	}

	/** Create the Collection attribute with its audience terms, in order. */
	private function collection(): void {
		$this->attribute( 'collection', 'Collection' );
		foreach ( array( 'mens', 'womens', 'unisex' ) as $order => $slug ) {
			wc_set_term_order( $this->term( 'pa_collection', $slug ), $order, 'pa_collection' );
		}
	}

	/**
	 * Get or create a term.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $slug     Slug.
	 */
	private function term( string $taxonomy, string $slug ): int {
		$term = get_term_by( 'slug', $slug, $taxonomy );
		if ( $term instanceof \WP_Term ) {
			return $term->term_id;
		}
		$created = wp_insert_term( ucfirst( $slug ), $taxonomy, array( 'slug' => $slug ) );
		return is_array( $created ) ? (int) $created['term_id'] : 0;
	}

	/**
	 * Build a taxonomy product attribute.
	 *
	 * @param string    $taxonomy  Taxonomy.
	 * @param list<int> $term_ids  Term IDs.
	 * @param bool      $variation Used for variations.
	 * @param int       $position  Position.
	 */
	private function product_attribute( string $taxonomy, array $term_ids, bool $variation, int $position ): \WC_Product_Attribute {
		$attribute = new \WC_Product_Attribute();
		$attribute->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
		$attribute->set_name( $taxonomy );
		$attribute->set_options( $term_ids );
		$attribute->set_visible( true );
		$attribute->set_variation( $variation );
		$attribute->set_position( $position );
		return $attribute;
	}

	/**
	 * Create a published simple product.
	 *
	 * @param array<string, list<string>> $attributes Taxonomy → term slugs.
	 * @param list<int>                   $categories Category IDs.
	 */
	private function simple( array $attributes, array $categories = array() ): int {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Product ' . wp_generate_password( 6, false ) );
		$product->set_status( 'publish' );
		$product->set_regular_price( '25' );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( 7 );
		$product->set_category_ids( $categories );

		$built    = array();
		$position = 0;
		foreach ( $attributes as $taxonomy => $slugs ) {
			$ids     = array_map( fn( string $slug ): int => $this->term( $taxonomy, $slug ), $slugs );
			$built[] = $this->product_attribute( $taxonomy, $ids, false, $position++ );
		}
		$product->set_attributes( $built );

		$id = $product->save();
		$this->regenerate_lookup( $id );
		return $id;
	}

	/**
	 * Populate WooCommerce's attribute lookup table synchronously.
	 *
	 * @param int $product_id Product ID.
	 */
	private function regenerate_lookup( int $product_id ): void {
		$store = '\Automattic\WooCommerce\Internal\ProductAttributesLookup\LookupDataStore';
		if ( function_exists( 'wc_get_container' ) && class_exists( $store ) ) {
			wc_get_container()->get( $store )->create_data_for_product( wc_get_product( $product_id ) );
		}
	}

	/**
	 * Product IDs matched by the catalogue filter query.
	 *
	 * @param array<string, mixed> $filters Public filters.
	 * @return list<int>
	 */
	private function filtered( array $filters ): array {
		$args  = ( new Product_Collection_Query() )->filter_args( $filters );
		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'tax_query'      => $args['tax_query'] ?? array(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			)
		);
		return array_map( 'intval', $query->posts );
	}
}
