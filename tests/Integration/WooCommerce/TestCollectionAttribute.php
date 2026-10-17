<?php
/**
 * Collection attribute tests: pa_fit → pa_collection migration, Collection
 * filtering and facets.
 *
 * @package Aggressive_Apparel\Tests\Integration\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Integration\WooCommerce;

use Aggressive_Apparel\WooCommerce\Catalog_Cache_Version;
use Aggressive_Apparel\WooCommerce\Collection_Attribute_Migration as Migration;
use Aggressive_Apparel\WooCommerce\Product_Collection_Query;
use Aggressive_Apparel\WooCommerce\Product_Facet_Service;
use Aggressive_Apparel\WooCommerce\Product_Specs_Registry;
use WP_Query;
use WP_UnitTestCase;

/** Collection (audience) is independent of Fit and migrates safely. */
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

	/** A dry run reports the plan and writes nothing. */
	public function test_dry_run_writes_nothing(): void {
		$product = $this->simple( array( 'pa_fit' => array( 'womens' ) ) );

		$report = ( new Migration() )->run( true );

		$this->assertSame( 'would-create', $report['attribute'] );
		$this->assertSame( array( 'mens', 'womens', 'unisex' ), $report['terms'] );
		$this->assertSame( Migration::STATUS_WOULD_UPDATE, $this->entry( $report, $product )['status'] );
		$this->assertSame( array( 'womens' ), $this->entry( $report, $product )['add'] );
		$this->assertSame( 0, wc_attribute_taxonomy_id_by_name( 'pa_collection' ) );
		$this->assertArrayNotHasKey( 'pa_collection', wc_get_product( $product )->get_attributes() );
	}

	/** Apply copies only audience terms and preserves all other product data. */
	public function test_apply_copies_audience_and_preserves_product(): void {
		$cat     = self::factory()->term->create( array( 'taxonomy' => 'product_cat' ) );
		$product = $this->simple( array( 'pa_fit' => array( 'womens', 'slim' ) ), array( $cat ) );

		$report = ( new Migration() )->run( false );
		$entry  = $this->entry( $report, $product );

		$this->assertSame( 'created', $report['attribute'] );
		$this->assertSame( Migration::STATUS_UPDATED, $entry['status'] );
		$this->assertSame( array(), $entry['remove'] );

		$saved = wc_get_product( $product );
		$this->assertSame( array( 'womens' ), $this->slugs( $saved, 'pa_collection' ) );
		$this->assertFalse( $saved->get_attributes()['pa_collection']->get_variation() );
		$this->assertEqualsCanonicalizing( array( 'womens', 'slim' ), $this->slugs( $saved, 'pa_fit' ), 'pa_fit is untouched without --remove-source.' );
		$this->assertSame( '25', $saved->get_regular_price() );
		$this->assertSame( 7, $saved->get_stock_quantity() );
		$this->assertSame( array( $cat ), $saved->get_category_ids() );
		$this->assertTrue( has_term( 'womens', 'pa_collection', $product ) );
	}

	/** Re-running is a no-op and never duplicates terms or assignments. */
	public function test_apply_is_idempotent(): void {
		$product = $this->simple( array( 'pa_fit' => array( 'mens' ) ) );

		( new Migration() )->run( false );
		$second = ( new Migration() )->run( false );

		$this->assertSame( 'exists', $second['attribute'] );
		$this->assertSame( array(), $second['terms'] );
		$this->assertSame( Migration::STATUS_UNCHANGED, $this->entry( $second, $product )['status'] );
		$this->assertSame( array( 'mens' ), $this->slugs( wc_get_product( $product ), 'pa_collection' ) );
		$this->assertCount( 3, get_terms( array( 'taxonomy' => 'pa_collection', 'hide_empty' => false ) ) );
	}

	/** Non-audience pa_fit values are never treated as collections. */
	public function test_non_audience_fit_is_skipped(): void {
		$product = $this->simple( array( 'pa_fit' => array( 'slim' ) ) );

		$entry = $this->entry( ( new Migration() )->run( false, true ), $product );

		$this->assertSame( Migration::STATUS_SKIPPED, $entry['status'] );
		$this->assertSame( array( 'slim' ), $this->slugs( wc_get_product( $product ), 'pa_fit' ) );
	}

	/** --remove-source drops only the migrated audience terms. */
	public function test_remove_source_keeps_real_fit_terms(): void {
		$mixed = $this->simple( array( 'pa_fit' => array( 'mens', 'slim' ) ) );
		$only  = $this->simple( array( 'pa_fit' => array( 'womens' ) ) );

		( new Migration() )->run( false, true );

		$this->assertSame( array( 'slim' ), $this->slugs( wc_get_product( $mixed ), 'pa_fit' ) );
		$this->assertSame( array( 'mens' ), $this->slugs( wc_get_product( $mixed ), 'pa_collection' ) );
		$this->assertArrayNotHasKey( 'pa_fit', wc_get_product( $only )->get_attributes() );
		$this->assertSame( array( 'womens' ), $this->slugs( wc_get_product( $only ), 'pa_collection' ) );
	}

	/** Variable products keep their variations; a variation pa_fit stays put. */
	public function test_variable_product_variations_are_preserved(): void {
		$this->attribute( 'color', 'Color' );
		$black = $this->term( 'pa_color', 'black' );
		$red   = $this->term( 'pa_color', 'red' );

		$product = new \WC_Product_Variable();
		$product->set_name( 'Variable Tee' );
		$product->set_status( 'publish' );
		$product->set_attributes(
			array(
				$this->product_attribute( 'pa_color', array( $black, $red ), true, 0 ),
				$this->product_attribute( 'pa_fit', array( $this->term( 'pa_fit', 'mens' ) ), true, 1 ),
			)
		);
		$parent_id  = $product->save();
		$variations = array();
		foreach ( array( 'black', 'red' ) as $color ) {
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $parent_id );
			$variation->set_attributes(
				array(
					'pa_color' => $color,
					'pa_fit'   => 'mens',
				)
			);
			$variation->set_regular_price( '30' );
			$variations[ $variation->save() ] = $color;
		}

		$entry = $this->entry( ( new Migration() )->run( false, true ), $parent_id );

		$this->assertSame( Migration::STATUS_UPDATED, $entry['status'] );
		$this->assertSame( array(), $entry['remove'] );
		$this->assertStringContainsString( 'variations', $entry['note'] );

		$saved = wc_get_product( $parent_id );
		$this->assertTrue( $saved->get_attributes()['pa_fit']->get_variation(), 'Variation attribute is preserved.' );
		$this->assertFalse( $saved->get_attributes()['pa_collection']->get_variation() );
		$this->assertSame( array( 'mens' ), $this->slugs( $saved, 'pa_collection' ) );
		$this->assertEqualsCanonicalizing( array_keys( $variations ), $saved->get_children() );
		foreach ( $variations as $variation_id => $color ) {
			$variation = wc_get_product( $variation_id );
			$this->assertSame(
				array(
					'pa_color' => $color,
					'pa_fit'   => 'mens',
				),
				$variation->get_attributes()
			);
			$this->assertSame( '30', $variation->get_regular_price() );
		}
	}

	/** A held-back variation removal is reported as skipped, never unchanged. */
	public function test_held_variation_source_is_skipped_once_collection_exists(): void {
		$product = new \WC_Product_Variable();
		$product->set_name( 'Variable Hat' );
		$product->set_status( 'publish' );
		$product->set_attributes( array( $this->product_attribute( 'pa_fit', array( $this->term( 'pa_fit', 'mens' ) ), true, 0 ) ) );
		$id = $product->save();

		( new Migration() )->run( false );
		$entry = $this->entry( ( new Migration() )->run( false, true ), $id );

		$this->assertSame( Migration::STATUS_SKIPPED, $entry['status'] );
		$this->assertSame( Migration::NOTE_VARIATION, $entry['note'] );
	}

	/** A term that cannot be created aborts before any product is touched. */
	public function test_term_creation_failure_aborts(): void {
		$product = $this->simple( array( 'pa_fit' => array( 'mens' ) ) );
		$fail    = static fn( $term, string $taxonomy ) => 'pa_collection' === $taxonomy ? new \WP_Error( 'nope', 'Rejected' ) : $term;
		add_filter( 'pre_insert_term', $fail, 10, 2 );

		try {
			( new Migration() )->run( false );
			$this->fail( 'Expected the migration to abort.' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'Rejected', $e->getMessage() );
		} finally {
			remove_filter( 'pre_insert_term', $fail, 10 );
		}

		$this->assertArrayNotHasKey( 'pa_collection', wc_get_product( $product )->get_attributes() );
	}

	/** Collection filtering combines with category, and Unisex is its own term. */
	public function test_collection_filter_combines_with_category(): void {
		( new Migration() )->run( false ); // Creates pa_collection + terms.

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
		( new Migration() )->run( false );

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
		if ( ! wc_attribute_taxonomy_id_by_name( $slug ) ) {
			wc_create_attribute(
				array(
					'name' => $label,
					'slug' => $slug,
				)
			);
		}
		$taxonomy = wc_attribute_taxonomy_name( $slug );
		if ( ! taxonomy_exists( $taxonomy ) ) {
			register_taxonomy( $taxonomy, array( 'product' ), array( 'hierarchical' => false ) );
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

	/**
	 * Term slugs on a product attribute.
	 *
	 * @param \WC_Product $product  Product.
	 * @param string      $taxonomy Taxonomy.
	 * @return list<string>
	 */
	private function slugs( \WC_Product $product, string $taxonomy ): array {
		$attribute = $product->get_attributes()[ $taxonomy ] ?? null;
		if ( ! $attribute instanceof \WC_Product_Attribute ) {
			return array();
		}
		return array_values( wp_list_pluck( (array) $attribute->get_terms(), 'slug' ) );
	}

	/**
	 * The report entry for one product.
	 *
	 * @param array<string, mixed> $report     Migration report.
	 * @param int                  $product_id Product ID.
	 * @return array<string, mixed>
	 */
	private function entry( array $report, int $product_id ): array {
		foreach ( $report['products'] as $entry ) {
			if ( $entry['id'] === $product_id ) {
				return $entry;
			}
		}
		$this->fail( "No report entry for product {$product_id}." );
	}
}
