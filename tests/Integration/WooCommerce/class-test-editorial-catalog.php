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
use Aggressive_Apparel\Tests\Support\Builds_Editorial_Fixtures;
use WP_UnitTestCase;

/**
 * Editorial patterns resolve live store data and never invent merchandising.
 */
class Test_Editorial_Catalog extends WP_UnitTestCase {

	use Builds_Editorial_Fixtures;

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

	/**
	 * Without sales the section is labelled Featured, exactly what the store
	 * flag means; nothing claims the staff chose them or that they sell.
	 */
	public function test_best_sellers_falls_back_to_featured_products(): void {
		$this->create_product( array( 'featured' => true ) );

		$markup = $this->render_pattern( 'editorial-best-sellers' );

		$this->assertStringContainsString( 'Featured', $markup );
		$this->assertStringNotContainsString( 'Staff', $markup );
		$this->assertStringNotContainsString( 'Best sellers', $markup );
		$this->assertSame( true, $this->collection_query( $markup )['featured'] );
	}

	/**
	 * Featured detection reads visibility terms by name, independent of
	 * WooCommerce's featured-IDs transient.
	 */
	public function test_featured_detection_is_independent_of_the_featured_transient(): void {
		set_transient( 'wc_featured_products', array() );
		$this->create_product( array( 'featured' => true ) );
		set_transient( 'wc_featured_products', array() );
		wp_cache_flush();

		$this->assertTrue( Editorial_Catalog::has_featured_products() );
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

	/** A centred grid carries the class its short-row CSS keys off, and still round-trips. */
	public function test_centered_grid_markup_round_trips(): void {
		$markup = Editorial_Product_Grid::markup(
			array(
				'query_id' => 43,
				'columns'  => 3,
				'centered' => true,
			)
		);
		$blocks = array_values( array_filter( parse_blocks( $markup ), static fn( $block ) => null !== $block['blockName'] ) );

		$this->assertSame( 'is-style-editorial-grid aa-grid-centered', $blocks[0]['attrs']['className'] );
		$this->assertStringContainsString( 'class="wp-block-woocommerce-product-collection alignwide is-style-editorial-grid aa-grid-centered"', $markup );
		$this->assertSame( $markup, serialize_blocks( parse_blocks( $markup ) ) );
	}

	/** An empty category is skipped for the next candidate that has products. */
	public function test_stocked_term_skips_empty_categories(): void {
		self::factory()->term->create(
			array(
				'taxonomy' => 'product_cat',
				'slug'     => 'editorial-empty',
			)
		);
		$stocked = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'product_cat',
				'slug'     => 'editorial-stocked',
			)
		);
		$this->create_product( array( 'category' => $stocked->term_id ) );

		$found = Editorial_Catalog::stocked_term( array( 'product_cat' ), array( 'editorial-empty', 'editorial-stocked' ) );

		$this->assertInstanceOf( \WP_Term::class, $found );
		$this->assertSame( $stocked->term_id, $found->term_id );
		$this->assertNull( Editorial_Catalog::stocked_term( array( 'product_cat' ), array( 'editorial-empty' ) ) );
	}

	/**
	 * Shop by line: one row per stocked line linking to its archive, an
	 * empty category left out. Only the name rises in on scroll; the
	 * thumbnails are never behind the reveal.
	 */
	public function test_line_index_lists_only_stocked_lines(): void {
		$hoodies = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'product_cat',
				'slug'     => 'hoodies',
			)
		);
		$this->create_product( array( 'category' => $hoodies->term_id ) );
		self::factory()->term->create(
			array(
				'taxonomy' => 'product_cat',
				'slug'     => 'accessories',
			)
		);

		$markup = $this->render_pattern( 'editorial-category-showcase' );
		$names  = array_filter( $this->find_all( parse_blocks( $markup ), 'core/heading' ), static fn( $block ) => str_contains( $block['attrs']['className'] ?? '', 'aa-line-index__name' ) );

		$this->assertCount( 1, $names, 'Empty Accessories category gets no row.' );
		$this->assertStringContainsString( 'href="' . get_term_link( $hoodies ) . '"', $markup );

		$reveals = $this->find_all( parse_blocks( $markup ), 'aggressive-blocks/animate-on-scroll' );
		$this->assertCount( 1, $reveals, 'Each row reveals its name.' );
		foreach ( $reveals as $reveal ) {
			$this->assertSame( array( 'core/heading' ), array_column( $reveal['innerBlocks'], 'blockName' ), 'The reveal wraps the name alone.' );
			$this->assertSame( array(), $this->find_all( $reveal['innerBlocks'], 'core/image' ), 'Thumbnails are never behind a reveal.' );
		}
	}

	/**
	 * Until the merchant marks lines, every stocked standard line has a row,
	 * Swimwear and Leggings included, in a fixed order that stock never
	 * reshuffles.
	 */
	public function test_line_index_default_order_is_fixed(): void {
		$stock = array(
			't-shirts'    => 4,
			'hoodies'     => 1,
			'swimwear'    => 3,
			'leggings'    => 1,
			'accessories' => 2,
		);
		$terms = array();
		foreach ( $stock as $slug => $count ) {
			$terms[ $slug ] = self::factory()->term->create(
				array(
					'taxonomy' => 'product_cat',
					'slug'     => $slug,
					'name'     => ucfirst( $slug ),
				)
			);
			for ( $i = 0; $i < $count; $i++ ) {
				$this->create_product( array( 'category' => $terms[ $slug ] ) );
			}
		}

		$names = array_map(
			static fn( $block ) => wp_strip_all_tags( $block['innerHTML'] ),
			array_values( array_filter( $this->find_all( parse_blocks( $this->render_pattern( 'editorial-category-showcase' ) ), 'core/heading' ), static fn( $block ) => str_contains( $block['attrs']['className'] ?? '', 'aa-line-index__name' ) ) )
		);

		$this->assertSame( array( 'T-shirts', 'Hoodies', 'Swimwear', 'Leggings', 'Accessories' ), array_map( 'trim', $names ) );
	}

	/**
	 * Marked lines replace the default, in WooCommerce's category order; an
	 * unmarked or empty category is left out; counts stay live.
	 */
	public function test_line_index_follows_the_merchants_lines_and_order(): void {
		$lines = array();
		foreach ( array(
			'Swimwear' => 2,
			'T-shirts' => 1,
			'Hoodies'  => 0,
			'Sales'    => 3,
		) as $name => $order ) {
			$lines[ $name ] = self::factory()->term->create(
				array(
					'taxonomy' => 'product_cat',
					'name'     => $name,
				)
			);
			update_term_meta( $lines[ $name ], 'order', $order );
			$this->create_product( array( 'category' => $lines[ $name ] ) );
		}
		foreach ( array( 'Swimwear', 'T-shirts', 'Hoodies' ) as $name ) {
			update_term_meta( $lines[ $name ], \Aggressive_Apparel\WooCommerce\Editorial_Controls::LINE_META, 'yes' );
		}
		$empty = self::factory()->term->create(
			array(
				'taxonomy' => 'product_cat',
				'name'     => 'Outerwear',
			)
		);
		update_term_meta( $empty, \Aggressive_Apparel\WooCommerce\Editorial_Controls::LINE_META, 'yes' );

		$markup = $this->render_pattern( 'editorial-category-showcase' );
		$names  = array_map(
			static fn( $block ) => trim( wp_strip_all_tags( $block['innerHTML'] ) ),
			array_values( array_filter( $this->find_all( parse_blocks( $markup ), 'core/heading' ), static fn( $block ) => str_contains( $block['attrs']['className'] ?? '', 'aa-line-index__name' ) ) )
		);

		$this->assertSame( array( 'Hoodies', 'T-shirts', 'Swimwear' ), $names, 'Category order; Sales unmarked, Outerwear empty.' );
		$this->assertStringContainsString( '1 piece', $markup );
	}

	/**
	 * Sections whose CTA names a collection render nothing when the store has
	 * no stocked term to send it to, rather than a CTA to the whole catalog.
	 *
	 * @dataProvider provide_collection_sections
	 *
	 * @param string $slug Pattern file slug.
	 */
	public function test_collection_sections_render_nothing_without_a_stocked_term( string $slug ): void {
		$this->create_product();

		$this->assertSame( '', trim( $this->render_pattern( $slug ) ) );
	}

	/**
	 * Collection-led sections.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function provide_collection_sections(): array {
		return array(
			'featured drop'     => array( 'editorial-featured-drop' ),
			'defiance division' => array( 'editorial-defiance-division' ),
			'womens'            => array( 'editorial-womens-collection' ),
			'hoodies'           => array( 'editorial-hoodies-feature' ),
			'accessories'       => array( 'editorial-accessories' ),
		);
	}

	/** With its term stocked, a collection section links to that term. */
	public function test_collection_section_links_to_its_stocked_term(): void {
		$hoodies = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'product_cat',
				'slug'     => 'hoodies',
			)
		);
		$this->create_product( array( 'category' => $hoodies->term_id ) );

		$markup = $this->render_pattern( 'editorial-hoodies-feature' );

		$this->assertStringContainsString( esc_url( (string) get_term_link( $hoodies ) ), $markup );
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

	/**
	 * The closing row states the label and leads into the catalog; Our story
	 * appears only when a published About page exists, never as a dead link.
	 */
	public function test_closing_row_links_our_story_only_to_a_published_about_page(): void {
		$markup = $this->render_pattern( 'editorial-brand-story' );

		$this->assertStringContainsString( 'Without the edges sanded off.', $markup );
		$this->assertStringContainsString( 'is-style-cta', $markup, 'The closing row carries the page\'s final filled CTA.' );
		$this->assertStringNotContainsString( 'Our story', $markup );

		$about = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'about',
				'post_title'  => 'About',
				'post_status' => 'draft',
			)
		);
		$this->assertStringNotContainsString( 'Our story', $this->render_pattern( 'editorial-brand-story' ), 'A draft About page is not linked.' );

		wp_publish_post( $about );
		$markup = $this->render_pattern( 'editorial-brand-story' );

		$this->assertStringContainsString( 'Our story', $markup );
		$this->assertStringContainsString( esc_url( (string) get_permalink( $about ) ), $markup );
	}
}
