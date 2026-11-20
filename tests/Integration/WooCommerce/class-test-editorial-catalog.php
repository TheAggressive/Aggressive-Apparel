<?php
/**
 * Editorial catalog and product grid tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Integration\WooCommerce;

use Aggressive_Apparel\WooCommerce\Editorial_Catalog;
use Aggressive_Apparel\WooCommerce\Editorial_Product_Grid;
use WC_Product_Simple;
use WP_UnitTestCase;

/**
 * Editorial patterns resolve live store data and never invent merchandising.
 */
class Test_Editorial_Catalog extends WP_UnitTestCase {

	/** Attribute taxonomy registered for the attribute-filter case. */
	private const ATTRIBUTE = 'pa_editorial_probe';

	/** Register a throwaway attribute taxonomy. */
	public function setUp(): void {
		parent::setUp();
		register_taxonomy( self::ATTRIBUTE, 'product' );
		wp_cache_flush();
	}

	/** Remove the attribute taxonomy. */
	public function tearDown(): void {
		unregister_taxonomy( self::ATTRIBUTE );
		parent::tearDown();
	}

	/** The first existing slug wins, searched taxonomy by taxonomy. */
	public function test_term_resolves_first_existing_candidate(): void {
		$hoodies = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'product_cat',
				'slug'     => 'editorial-hoodies',
			)
		);

		$found = Editorial_Catalog::term( array( 'product_tag', 'product_cat' ), array( 'missing-slug', 'editorial-hoodies' ) );

		$this->assertInstanceOf( \WP_Term::class, $found );
		$this->assertSame( $hoodies->term_id, $found->term_id );
		$this->assertNull( Editorial_Catalog::term( array( 'product_cat', 'not_a_taxonomy' ), array( 'missing-slug' ) ) );
	}

	/** A CTA always lands somewhere real: the archive, or the Shop page. */
	public function test_url_falls_back_to_the_shop_page(): void {
		$term = self::factory()->term->create_and_get( array( 'taxonomy' => 'product_cat' ) );

		$this->assertSame( get_term_link( $term ), Editorial_Catalog::url( $term ) );
		$this->assertSame( wc_get_page_permalink( 'shop' ), Editorial_Catalog::url( null ) );
		$this->assertStringContainsString( 'orderby=date', Editorial_Catalog::shop_url( array( 'orderby' => 'date' ) ) );
	}

	/** Categories filter through taxQuery; global attributes through woocommerceAttributes. */
	public function test_query_filter_matches_how_product_collection_filters(): void {
		$category  = self::factory()->term->create_and_get( array( 'taxonomy' => 'product_cat' ) );
		$attribute = self::factory()->term->create_and_get( array( 'taxonomy' => self::ATTRIBUTE ) );

		$this->assertSame(
			array(
				'taxQuery'              => array( 'product_cat' => array( $category->term_id ) ),
				'woocommerceAttributes' => array(),
			),
			Editorial_Catalog::query_filter( $category )
		);
		$this->assertSame(
			array(
				'taxQuery'              => array(),
				'woocommerceAttributes' => array(
					array(
						'termId'   => $attribute->term_id,
						'taxonomy' => self::ATTRIBUTE,
					),
				),
			),
			Editorial_Catalog::query_filter( $attribute )
		);
	}

	/** Sales data is detected from the product lookup table. */
	public function test_has_sales_data_reflects_recorded_sales(): void {
		$this->create_product();
		$this->assertFalse( Editorial_Catalog::has_sales_data() );

		$this->create_product( array( 'total_sales' => 3 ) );
		wp_cache_flush();

		$this->assertTrue( Editorial_Catalog::has_sales_data() );
	}

	/** With neither sales nor Featured products the section renders nothing. */
	public function test_best_sellers_renders_nothing_without_real_data(): void {
		$this->create_product();

		$this->assertSame( '', trim( $this->render_pattern( 'editorial-best-sellers' ) ) );
	}

	/** Without sales the section is honestly labelled Staff Picks over Featured products. */
	public function test_best_sellers_falls_back_to_featured_staff_picks(): void {
		$this->create_product( array( 'featured' => true ) );

		$markup = $this->render_pattern( 'editorial-best-sellers' );

		$this->assertStringContainsString( 'Staff picks', $markup );
		$this->assertStringNotContainsString( 'Best sellers', $markup );
		$this->assertSame( true, $this->collection_query( $markup )['featured'] );
	}

	/** With real sales the section ranks by popularity. */
	public function test_best_sellers_ranks_by_sales_when_available(): void {
		$this->create_product( array( 'total_sales' => 5 ) );

		$markup = $this->render_pattern( 'editorial-best-sellers' );

		$this->assertStringContainsString( 'Best sellers', $markup );
		$this->assertSame( 'popularity', $this->collection_query( $markup )['orderBy'] );
	}

	/** The grid markup is a canonical Product Collection block with the editorial style. */
	public function test_grid_markup_round_trips_through_the_block_parser(): void {
		$term   = self::factory()->term->create_and_get( array( 'taxonomy' => 'product_cat' ) );
		$markup = Editorial_Product_Grid::markup(
			array(
				'query_id' => 42,
				'columns'  => 3,
				'term'     => $term,
			)
		);
		$blocks = array_values( array_filter( parse_blocks( $markup ), static fn( $block ) => null !== $block['blockName'] ) );

		$this->assertCount( 1, $blocks );
		$this->assertSame( 'woocommerce/product-collection', $blocks[0]['blockName'] );
		$this->assertSame( 'is-style-editorial-grid', $blocks[0]['attrs']['className'] );
		$this->assertSame( 3, $blocks[0]['attrs']['displayLayout']['columns'] );
		$this->assertSame( array( 'product_cat' => array( $term->term_id ) ), $blocks[0]['attrs']['query']['taxQuery'] );
		$this->assertSame( $markup, serialize_blocks( parse_blocks( $markup ) ) );
	}

	/**
	 * Create a published simple product.
	 *
	 * @param array{featured?: bool, total_sales?: int} $props Product properties.
	 */
	private function create_product( array $props = array() ): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_name( 'Editorial probe' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '20' );
		$product->set_featured( $props['featured'] ?? false );
		$product->set_total_sales( $props['total_sales'] ?? 0 );
		$product->save();

		return $product;
	}

	/**
	 * Render a theme pattern file as WordPress does (include under output buffering).
	 *
	 * @param string $slug Pattern file slug.
	 */
	private function render_pattern( string $slug ): string {
		wp_cache_flush();
		ob_start();
		include get_theme_file_path( 'patterns/' . $slug . '.php' );

		return (string) ob_get_clean();
	}

	/**
	 * Query attributes of the first Product Collection in markup.
	 *
	 * @param string $markup Block markup.
	 * @return array<string, mixed>
	 */
	private function collection_query( string $markup ): array {
		$this->assertSame( 1, preg_match( '/<!-- wp:woocommerce\/product-collection (\{.*?\}) -->/', $markup, $match ) );
		$attributes = json_decode( $match[1], true );
		$this->assertIsArray( $attributes );

		return $attributes['query'];
	}
}
