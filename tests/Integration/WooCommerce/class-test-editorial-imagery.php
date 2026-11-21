<?php
/**
 * Editorial imagery and count-aware section layout tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Integration\WooCommerce;

use Aggressive_Apparel\Tests\Support\Builds_Editorial_Fixtures;
use Aggressive_Apparel\WooCommerce\Editorial_Catalog;
use Aggressive_Apparel\WooCommerce\Editorial_Imagery;
use WP_UnitTestCase;

/**
 * Editorial slots show real store photography or change layout; they never
 * render an empty frame, and product sections size to what's in stock.
 */
class Test_Editorial_Imagery extends WP_UnitTestCase {

	use Builds_Editorial_Fixtures;

	/** Start each test with no photos claimed. */
	public function setUp(): void {
		parent::setUp();
		wp_cache_flush();
	}

	/** Square packshots and mockups never fill a portrait editorial slot. */
	public function test_leads_take_only_portrait_photos(): void {
		$this->create_product( array( 'image' => $this->create_image( 800, 800 ) ) );
		$portrait = $this->create_image();
		$product  = $this->create_product( array( 'image' => $portrait ) );

		$leads = Editorial_Imagery::leads( 3 );

		$this->assertSame(
			array(
				array(
					'product' => $product->get_id(),
					'image'   => $portrait,
				),
			),
			$leads
		);
	}

	/** A catalog-wide slot never repeats a photo already shown on the page. */
	public function test_catalog_leads_never_repeat_a_claimed_photo(): void {
		$tile  = $this->create_image();
		$fresh = $this->create_image();
		$this->create_product( array( 'image' => $tile ) );
		$this->create_product( array( 'image' => $fresh ) );
		Editorial_Imagery::claim( $tile );

		$this->assertSame( array( $fresh ), array_column( Editorial_Imagery::leads( 2 ), 'image' ) );
		$this->assertSame( array(), Editorial_Imagery::leads( 1 ), 'Each photo is used once per page.' );
	}

	/** A collection slot shows its own clothing even if a tile used the photo. */
	public function test_collection_leads_may_reuse_a_claimed_photo(): void {
		$term  = self::factory()->term->create_and_get( array( 'taxonomy' => 'product_cat' ) );
		$image = $this->create_image();
		$this->create_product(
			array(
				'category' => $term->term_id,
				'image'    => $image,
			)
		);
		Editorial_Imagery::claim( $image );

		$this->assertSame( array( $image ), array_column( Editorial_Imagery::leads( 1, $term ), 'image' ) );
		$this->assertSame( array(), Editorial_Imagery::leads( 1 ) );
	}

	/** An editorial photo always leads to something the customer can buy. */
	public function test_leads_skip_out_of_stock_products(): void {
		$this->create_product(
			array(
				'image'        => $this->create_image(),
				'stock_status' => 'outofstock',
			)
		);

		$this->assertSame( array(), Editorial_Imagery::leads( 1 ) );
	}

	/** The slot is a plain core/image: full column width, cropped, linked to the product. */
	public function test_image_markup_is_a_linked_full_width_core_image(): void {
		$image   = $this->create_image();
		$product = $this->create_product( array( 'image' => $image ) );

		$blocks = parse_blocks( Editorial_Imagery::image_markup( Editorial_Imagery::leads( 1 )[0], '4/5' ) );

		$this->assertSame( 'core/image', $blocks[0]['blockName'] );
		$this->assertSame( $image, $blocks[0]['attrs']['id'] );
		$this->assertSame( '4/5', $blocks[0]['attrs']['aspectRatio'] );
		$this->assertSame( '100%', $blocks[0]['attrs']['width'] );
		$this->assertSame( 'cover', $blocks[0]['attrs']['scale'] );
		$this->assertStringContainsString( 'href="' . esc_url( (string) get_permalink( $product->get_id() ) ) . '"', $blocks[0]['innerHTML'] );
		$this->assertStringContainsString( 'alt="Editorial probe"', $blocks[0]['innerHTML'] );
	}

	/** Sections count only products a grid can show. */
	public function test_visible_count_excludes_out_of_stock_and_counts_featured(): void {
		$term = self::factory()->term->create_and_get( array( 'taxonomy' => 'product_cat' ) );
		$this->create_product(
			array(
				'category' => $term->term_id,
				'featured' => true,
			)
		);
		$this->create_product( array( 'category' => $term->term_id ) );
		$this->create_product(
			array(
				'category'     => $term->term_id,
				'stock_status' => 'outofstock',
			)
		);

		$this->assertSame( 2, Editorial_Catalog::visible_count( $term ) );
		$this->assertSame( 1, Editorial_Catalog::visible_count( null, true ) );
	}

	/** Without three photos there is no lookbook, never empty frames. */
	public function test_lookbook_renders_nothing_without_three_photos(): void {
		$this->create_product( array( 'image' => $this->create_image() ) );
		$this->create_product( array( 'image' => $this->create_image() ) );

		$this->assertSame( '', trim( $this->render_pattern( 'editorial-featured-looks' ) ) );
	}

	/** Each look is a real product photo, captioned and linked to that product. */
	public function test_lookbook_links_each_look_to_its_product(): void {
		$links = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$product = $this->create_product( array( 'image' => $this->create_image() ) );
			$links[] = esc_url( (string) get_permalink( $product->get_id() ) );
		}

		$markup = $this->render_pattern( 'editorial-featured-looks' );
		$images = $this->find_all( parse_blocks( $markup ), 'core/image' );

		$this->assertCount( 3, $images );
		$this->assertStringNotContainsString( 'wp:cover', $markup, 'No placeholder frames.' );
		foreach ( $links as $link ) {
			// Once on the photo, once on the caption.
			$this->assertSame( 2, substr_count( $markup, 'href="' . $link . '"' ) );
		}
	}

	/** Original Work: a store photo beside the statement, or the statement alone. */
	public function test_original_work_shows_a_photo_or_the_statement_alone(): void {
		$markup = $this->render_pattern( 'editorial-artwork-spotlight' );
		$this->assertSame( array(), $this->find_all( parse_blocks( $markup ), 'core/image' ) );
		$this->assertSame( array(), $this->find_all( parse_blocks( $markup ), 'core/columns' ) );
		$this->assertStringNotContainsString( 'wp:cover', $markup );

		$this->create_product( array( 'image' => $this->create_image() ) );
		$markup = $this->render_pattern( 'editorial-artwork-spotlight' );
		$this->assertCount( 1, $this->find_all( parse_blocks( $markup ), 'core/image' ) );
	}

	/**
	 * Women's: one product is carried by its photo alone; more add a grid of
	 * two or four beside it, never a single lone card.
	 */
	public function test_womens_grid_appears_only_with_two_or_more_products(): void {
		$womens = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'product_cat',
				'slug'     => 'womens',
			)
		);
		$this->create_product(
			array(
				'category' => $womens->term_id,
				'image'    => $this->create_image(),
			)
		);

		$blocks = parse_blocks( $this->render_pattern( 'editorial-womens-collection' ) );
		$this->assertCount( 1, $this->find_all( $blocks, 'core/image' ) );
		$this->assertSame( array(), $this->find_all( $blocks, 'woocommerce/product-collection' ) );

		$this->create_product( array( 'category' => $womens->term_id ) );
		$this->create_product( array( 'category' => $womens->term_id ) );
		$blocks = parse_blocks( $this->render_pattern( 'editorial-womens-collection' ) );
		$grids  = $this->find_all( $blocks, 'woocommerce/product-collection' );
		$this->assertCount( 1, $grids );
		$this->assertSame( 2, $grids[0]['attrs']['query']['perPage'] );
	}

	/** One or two hoodies sit beside the headline (headline first); three make a centred row. */
	public function test_hoodies_layout_follows_the_stock(): void {
		$hoodies = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'product_cat',
				'slug'     => 'hoodies',
			)
		);
		$this->create_product( array( 'category' => $hoodies->term_id ) );

		$markup  = $this->render_pattern( 'editorial-hoodies-feature' );
		$columns = $this->find_all( parse_blocks( $markup ), 'core/columns' );
		$this->assertCount( 1, $columns );
		$this->assertLessThan( strpos( $markup, 'wp:woocommerce/product-collection' ), strpos( $markup, 'is-style-campaign' ), 'Headline precedes the cards.' );
		$this->assertSame( 1, $this->find_all( parse_blocks( $markup ), 'woocommerce/product-collection' )[0]['attrs']['query']['perPage'] );

		$this->create_product( array( 'category' => $hoodies->term_id ) );
		$this->create_product( array( 'category' => $hoodies->term_id ) );
		$markup = $this->render_pattern( 'editorial-hoodies-feature' );
		$grid   = $this->find_all( parse_blocks( $markup ), 'woocommerce/product-collection' )[0];
		$this->assertSame( array(), $this->find_all( parse_blocks( $markup ), 'core/columns' ) );
		$this->assertSame( 3, $grid['attrs']['query']['perPage'] );
		$this->assertStringContainsString( 'aa-grid-centered', $grid['attrs']['className'] );
	}

	/** Staff picks fill their column with the picks that exist. */
	public function test_staff_picks_size_to_the_featured_products(): void {
		$this->create_product( array( 'featured' => true ) );
		$this->create_product( array( 'featured' => true ) );

		$grid = $this->find_all( parse_blocks( $this->render_pattern( 'editorial-best-sellers' ) ), 'woocommerce/product-collection' )[0];

		$this->assertSame( 2, $grid['attrs']['query']['perPage'] );
		$this->assertSame( 2, $grid['attrs']['displayLayout']['columns'] );
	}

	/** Accessories: four across at most. */
	public function test_accessories_row_is_at_most_four_across(): void {
		$accessories = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'product_cat',
				'slug'     => 'accessories',
			)
		);
		for ( $i = 0; $i < 5; $i++ ) {
			$this->create_product( array( 'category' => $accessories->term_id ) );
		}

		$grid = $this->find_all( parse_blocks( $this->render_pattern( 'editorial-accessories' ) ), 'woocommerce/product-collection' )[0];

		$this->assertSame( 4, $grid['attrs']['displayLayout']['columns'] );
		$this->assertSame( 4, $grid['attrs']['query']['perPage'] );
	}

	/**
	 * Every block of a type, depth first.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @param string                           $name   Block name.
	 * @return list<array<string, mixed>>
	 */
	private function find_all( array $blocks, string $name ): array {
		$found = array();
		foreach ( $blocks as $block ) {
			if ( $name === $block['blockName'] ) {
				$found[] = $block;
			}
			array_push( $found, ...$this->find_all( $block['innerBlocks'], $name ) );
		}

		return $found;
	}
}
