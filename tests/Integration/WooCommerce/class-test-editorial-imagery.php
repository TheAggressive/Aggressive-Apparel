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

	/** The name: a typographic statement, never a placeholder frame or a photo. */
	public function test_the_name_section_is_typographic(): void {
		$this->create_product( array( 'image' => $this->create_image() ) );

		$markup = $this->render_pattern( 'editorial-artwork-spotlight' );

		$this->assertSame( array(), $this->find_all( parse_blocks( $markup ), 'core/image' ) );
		$this->assertStringNotContainsString( 'wp:cover', $markup );
		$this->assertStringContainsString( 'id="the-label"', $markup );
	}

	/**
	 * The hero shows a real product photo, linked to the product, but never
	 * the newest product's: New Arrivals opens with that one directly below.
	 */
	public function test_hero_photo_skips_the_newest_arrival(): void {
		$older  = $this->create_product( array( 'image' => $this->create_image() ) );
		$newest = $this->create_product( array( 'image' => $this->create_image() ) );
		wp_update_post(
			array(
				'ID'            => $older->get_id(),
				'post_date'     => '2026-01-01 00:00:00',
				'post_date_gmt' => '2026-01-01 00:00:00',
			)
		);

		$markup = $this->render_pattern( 'editorial-campaign-hero' );
		$images = $this->find_all( parse_blocks( $markup ), 'core/image' );

		$this->assertCount( 1, $images );
		$this->assertSame( (int) $older->get_image_id(), $images[0]['attrs']['id'] );
		$this->assertStringNotContainsString( (string) get_permalink( $newest->get_id() ), $markup );
	}

	/** With two portrait photos the hero is a diptych, each photo named in the Shown line. */
	public function test_hero_diptych_names_both_products(): void {
		$this->create_product( array( 'image' => $this->create_image() ) );
		$this->create_product( array( 'image' => $this->create_image() ) );
		$this->create_product( array( 'image' => $this->create_image() ) );

		$markup = $this->render_pattern( 'editorial-campaign-hero' );
		$images = $this->find_all( parse_blocks( $markup ), 'core/image' );

		$this->assertCount( 2, $images );
		$this->assertSame( array( '2/3', '2/3' ), array_column( array_column( $images, 'attrs' ), 'aspectRatio' ) );
		foreach ( $images as $image ) {
			preg_match( '/href="([^"]+)"/', $image['innerHTML'], $link );
			$this->assertSame( 2, substr_count( $markup, 'href="' . $link[1] . '"' ), 'Linked from the photo and the Shown line.' );
		}
	}

	/** Each category tile carries a live count of in-stock pieces. */
	public function test_category_tiles_show_in_stock_counts(): void {
		$hoodies = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'product_cat',
				'slug'     => 'hoodies',
			)
		);
		$this->create_product( array( 'category' => $hoodies->term_id ) );
		$this->create_product( array( 'category' => $hoodies->term_id ) );
		$this->create_product(
			array(
				'category'     => $hoodies->term_id,
				'stock_status' => 'outofstock',
			)
		);

		$markup = $this->render_pattern( 'editorial-category-showcase' );

		$this->assertStringContainsString( '2 pieces', $markup );
		$this->assertStringContainsString( '"aspectRatio":"4/5"', $markup, 'A square photo over a name band.' );
	}

	/** The New Arrivals lead grid is marked as the lead (larger name, 3:4 image). */
	public function test_new_arrivals_lead_grid_is_marked_lead(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->create_product();
		}

		$grids = $this->find_all( parse_blocks( $this->render_pattern( 'editorial-new-arrivals' ) ), 'woocommerce/product-collection' );

		$this->assertStringContainsString( 'aa-grid-lead', $grids[0]['attrs']['className'] );
		$this->assertStringNotContainsString( 'aa-grid-lead', $grids[1]['attrs']['className'] );
		$this->assertStringContainsString( '"fontSize":"x-large"', serialize_blocks( array( $grids[0] ) ) );
	}

	/** Without a portrait photo the hero is the slogan alone: no empty frame. */
	public function test_hero_without_photography_is_type_only(): void {
		$this->create_product( array( 'image' => $this->create_image( 800, 800 ) ) );

		$markup = $this->render_pattern( 'editorial-campaign-hero' );

		$this->assertSame( array(), $this->find_all( parse_blocks( $markup ), 'core/image' ) );
		$this->assertStringContainsString( 'Rebellion has a uniform.', $markup );
	}

	/** New Arrivals: the newest product as a lead card, the next four beside it. */
	public function test_new_arrivals_leads_with_the_newest_then_the_next_four(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->create_product();
		}

		$grids = $this->find_all( parse_blocks( $this->render_pattern( 'editorial-new-arrivals' ) ), 'woocommerce/product-collection' );

		$this->assertCount( 2, $grids );
		$this->assertSame( array( 1, 0 ), array( $grids[0]['attrs']['query']['perPage'], $grids[0]['attrs']['query']['offset'] ) );
		$this->assertSame( array( 4, 1 ), array( $grids[1]['attrs']['query']['perPage'], $grids[1]['attrs']['query']['offset'] ) );
		$this->assertSame( array(), $this->find_all( parse_blocks( $this->render_pattern( 'editorial-new-arrivals' ) ), 'aggressive-blocks/animate-on-scroll' ), 'Products are never behind a reveal.' );
	}

	/**
	 * A tile shows the category's own image, else its newest product photo not
	 * already on the page, else its newest; never another category's photo.
	 */
	public function test_term_image_prefers_thumbnail_then_unclaimed_product_photo(): void {
		$term  = self::factory()->term->create_and_get( array( 'taxonomy' => 'product_cat' ) );
		$other = self::factory()->term->create_and_get( array( 'taxonomy' => 'product_cat' ) );
		$this->create_product(
			array(
				'category' => $other->term_id,
				'image'    => $this->create_image(),
			)
		);
		$this->assertSame( 0, Editorial_Imagery::term_image( $term ) );

		$first  = $this->create_image();
		$second = $this->create_image( 800, 800 );
		$this->create_product(
			array(
				'category' => $term->term_id,
				'image'    => $first,
			)
		);
		$this->create_product(
			array(
				'category' => $term->term_id,
				'image'    => $second,
			)
		);
		wp_cache_flush();
		Editorial_Imagery::claim( $second );
		$this->assertSame( $first, Editorial_Imagery::term_image( $term ), 'Skips the photo already shown; any orientation qualifies.' );

		wp_cache_flush();
		$thumbnail = $this->create_image();
		update_term_meta( $term->term_id, 'thumbnail_id', $thumbnail );
		$this->assertSame( $thumbnail, Editorial_Imagery::term_image( $term ) );
	}

	/** Lookbook: tops up from photos shown higher rather than disappearing. */
	public function test_lookbook_reuses_shown_photos_before_giving_up(): void {
		$shown = $this->create_image();
		$this->create_product( array( 'image' => $shown ) );
		$this->create_product( array( 'image' => $this->create_image() ) );
		$this->create_product( array( 'image' => $this->create_image() ) );
		Editorial_Imagery::claim( $shown );

		$this->assertCount( 2, Editorial_Imagery::leads( 3 ) );
		wp_cache_flush();
		Editorial_Imagery::claim( $shown );
		$this->assertCount( 3, Editorial_Imagery::leads( 3, null, true ) );
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

	/**
	 * One hoodie is a half-and-half spread with a lead card, two sit beside the
	 * headline (headline first), three or four make a centred row, five or
	 * more a rail.
	 */
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
		$this->assertSame( '50%', $columns[0]['innerBlocks'][1]['attrs']['width'], 'One card gets half the row, not a third beside a void.' );
		$this->assertStringContainsString( 'aa-grid-lead', $this->find_all( parse_blocks( $markup ), 'woocommerce/product-collection' )[0]['attrs']['className'] );

		$this->create_product( array( 'category' => $hoodies->term_id ) );
		$this->create_product( array( 'category' => $hoodies->term_id ) );
		$markup = $this->render_pattern( 'editorial-hoodies-feature' );
		$grid   = $this->find_all( parse_blocks( $markup ), 'woocommerce/product-collection' )[0];
		$this->assertSame( array(), $this->find_all( parse_blocks( $markup ), 'core/columns' ) );
		$this->assertSame( 3, $grid['attrs']['query']['perPage'] );
		$this->assertStringContainsString( 'aa-grid-centered', $grid['attrs']['className'] );

		$this->create_product( array( 'category' => $hoodies->term_id ) );
		$this->create_product( array( 'category' => $hoodies->term_id ) );
		$grid = $this->find_all( parse_blocks( $this->render_pattern( 'editorial-hoodies-feature' ) ), 'woocommerce/product-collection' )[0];
		$this->assertSame( 5, $grid['attrs']['query']['perPage'] );
		$this->assertStringContainsString( 'aa-grid-rail', $grid['attrs']['className'] );
		$this->assertStringNotContainsString( 'aa-grid-centered', $grid['attrs']['className'] );
	}

	/** Staff picks fill their column with the picks that exist. */
	public function test_staff_picks_size_to_the_featured_products(): void {
		$this->create_product( array( 'featured' => true ) );
		$this->create_product( array( 'featured' => true ) );

		$grid = $this->find_all( parse_blocks( $this->render_pattern( 'editorial-best-sellers' ) ), 'woocommerce/product-collection' )[0];

		$this->assertSame( 2, $grid['attrs']['query']['perPage'] );
		$this->assertSame( 2, $grid['attrs']['displayLayout']['columns'] );
	}

	/** Accessories: four across at most; more than four scroll as a rail. */
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
		$this->assertSame( 5, $grid['attrs']['query']['perPage'], 'More than four become a rail of all of them, up to eight.' );
		$this->assertStringContainsString( 'aa-grid-rail', $grid['attrs']['className'] );
	}

	/**
	 * A collection band with a portrait photo is a spread: the photo in a
	 * full-height column beside one row of two products. No placeholder.
	 */
	public function test_collection_band_with_a_photo_is_a_spread(): void {
		$defiance = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'product_tag',
				'slug'     => 'defiance-division',
			)
		);
		for ( $i = 0; $i < 5; $i++ ) {
			$product = $this->create_product( array( 'image' => $this->create_image() ) );
			wp_set_object_terms( $product->get_id(), array( $defiance->term_id ), 'product_tag' );
		}

		$markup = $this->render_pattern( 'editorial-defiance-division' );
		$blocks = parse_blocks( $markup );

		$this->assertCount( 1, $this->find_all( $blocks, 'core/image' ) );
		$this->assertStringContainsString( 'aa-bleed-photo', $markup );
		$this->assertStringNotContainsString( 'wp:cover', $markup );
		$this->assertSame( 2, $this->find_all( $blocks, 'woocommerce/product-collection' )[0]['attrs']['query']['perPage'] );
	}

	/** Without a portrait photo the band is a header row over a stock-sized grid. */
	public function test_collection_band_without_a_photo_sizes_its_grid(): void {
		$defiance = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'product_tag',
				'slug'     => 'defiance-division',
			)
		);
		for ( $i = 0; $i < 3; $i++ ) {
			$product = $this->create_product( array( 'image' => $this->create_image( 800, 800 ) ) );
			wp_set_object_terms( $product->get_id(), array( $defiance->term_id ), 'product_tag' );
		}

		$markup = $this->render_pattern( 'editorial-defiance-division' );
		$blocks = parse_blocks( $markup );
		$grid   = $this->find_all( $blocks, 'woocommerce/product-collection' )[0];

		$this->assertSame( array(), $this->find_all( $blocks, 'core/image' ) );
		$this->assertStringNotContainsString( 'wp:cover', $markup );
		$this->assertSame( 3, $grid['attrs']['displayLayout']['columns'] );
		$this->assertSame( 3, $grid['attrs']['query']['perPage'] );
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
	/**
	 * With nothing in stock, the shopping sections that list products are
	 * omitted rather than printing a heading over an empty row.
	 */
	public function test_empty_catalog_omits_category_and_new_arrival_sections(): void {
		self::factory()->term->create(
			array(
				'taxonomy' => 'product_cat',
				'slug'     => 'hoodies',
			)
		);

		$this->assertSame( '', trim( $this->render_pattern( 'editorial-category-showcase' ) ) );
		$this->assertSame( '', trim( $this->render_pattern( 'editorial-new-arrivals' ) ) );
	}

	/** Fewer than five new arrivals make one row sized to the count, never a lead with holes beside it. */
	public function test_new_arrivals_short_catalog_is_one_row(): void {
		for ( $i = 0; $i < 3; $i++ ) {
			$this->create_product();
		}

		$grids = $this->find_all( parse_blocks( $this->render_pattern( 'editorial-new-arrivals' ) ), 'woocommerce/product-collection' );
		$this->assertCount( 1, $grids );
		$this->assertSame( 3, $grids[0]['attrs']['query']['perPage'] );
		$this->assertSame( 3, $grids[0]['attrs']['displayLayout']['columns'] );
		$this->assertStringNotContainsString( 'aa-grid-lead', $grids[0]['attrs']['className'] );

		$this->create_product();
		$this->create_product();
		$grids = $this->find_all( parse_blocks( $this->render_pattern( 'editorial-new-arrivals' ) ), 'woocommerce/product-collection' );
		$this->assertCount( 2, $grids, 'Five or more: the lead card beside a 2 x 2.' );
		$this->assertStringContainsString( 'aa-grid-lead', $grids[0]['attrs']['className'] );
	}
}
