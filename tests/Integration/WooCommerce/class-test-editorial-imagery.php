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
use Aggressive_Apparel\WooCommerce\Editorial_Controls;
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
		$this->create_product( array( 'image' => $this->create_cutout() ) );
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

	/** The name: the statement beside the store's photos, each named in a Shown line. */
	public function test_the_name_sets_the_statement_beside_photos(): void {
		$products = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$products[] = $this->create_product( array( 'image' => $this->create_image( 1200, 1200 ) ) );
		}

		$markup = $this->render_pattern( 'editorial-artwork-spotlight' );
		$images = $this->find_all( parse_blocks( $markup ), 'core/image' );

		$this->assertCount( 3, $images, 'Square photos qualify: slots crop to their own ratio.' );
		$this->assertStringContainsString( 'id="the-label"', $markup );
		foreach ( $products as $product ) {
			$this->assertSame( 2, substr_count( $markup, 'href="' . get_permalink( $product->get_id() ) . '"' ), 'Linked from its photo and the Shown line.' );
		}
	}

	/** Without photos the name stays typographic: never an empty frame or a cut-out. */
	public function test_the_name_without_photos_is_typographic(): void {
		$this->create_product( array( 'image' => $this->create_cutout() ) );

		$markup = $this->render_pattern( 'editorial-artwork-spotlight' );

		$this->assertSame( array(), $this->find_all( parse_blocks( $markup ), 'core/image' ) );
		$this->assertStringContainsString( 'Aggressive is a compliment.', $markup );
	}

	/**
	 * The cover shows the newest arrival whole (a cut-out is never cropped)
	 * and names it with its live price: a one-product collection of it.
	 */
	public function test_cover_shows_the_newest_arrival_whole(): void {
		$older  = $this->create_product( array( 'image' => $this->create_image() ) );
		$newest = $this->create_product( array( 'image' => $this->create_cutout() ) );
		wp_update_post(
			array(
				'ID'            => $older->get_id(),
				'post_date'     => '2026-01-01 00:00:00',
				'post_date_gmt' => '2026-01-01 00:00:00',
			)
		);

		$blocks = parse_blocks( $this->render_pattern( 'editorial-campaign-hero' ) );
		$images = $this->find_all( $blocks, 'core/image' );
		$named  = $this->find_all( $blocks, 'woocommerce/product-collection' );

		$this->assertCount( 1, $images );
		$this->assertSame( (int) $newest->get_image_id(), $images[0]['attrs']['id'] );
		$this->assertSame( 'contain', $images[0]['attrs']['scale'] );
		$this->assertCount( 1, $named );
		$this->assertSame( array( $newest->get_id() ), $named[0]['attrs']['query']['woocommerceHandPickedProducts'] );
		$this->assertStringContainsString( 'aa-grid-caption', $named[0]['attrs']['className'] );
	}

	/** A product's first photo may sit in its gallery behind a cut-out main image. */
	public function test_leads_find_a_gallery_photo_behind_a_cutout(): void {
		$photo   = $this->create_image( 1200, 1200 );
		$product = $this->create_product( array( 'image' => $this->create_cutout() ) );
		$product->set_gallery_image_ids( array( $this->create_cutout(), $photo ) );
		$product->save();

		$this->assertSame(
			array(
				array(
					'product' => $product->get_id(),
					'image'   => $photo,
				),
			),
			Editorial_Imagery::leads( 3 )
		);
	}

	/** Cut-outs and undersized images never fill an editorial slot. */
	public function test_photo_rule_rejects_cutouts_and_small_images(): void {
		$this->assertFalse( Editorial_Imagery::is_photo( $this->create_cutout( 1200 ) ) );
		$this->assertSame( 'cutout', Editorial_Imagery::kind( $this->create_cutout() ) );
		$this->assertFalse( Editorial_Imagery::is_photo( $this->create_image( 400, 600 ) ) );
		$this->assertTrue( Editorial_Imagery::is_photo( $this->create_image( 1200, 900 ) ) );
	}

	/** Each line's row carries a live count of in-stock pieces and up to three of its products. */
	public function test_line_index_rows_count_stock_and_show_three_products(): void {
		$hoodies = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'product_cat',
				'slug'     => 'hoodies',
			)
		);
		for ( $i = 0; $i < 4; $i++ ) {
			$this->create_product(
				array(
					'category' => $hoodies->term_id,
					'image'    => $this->create_cutout(),
				)
			);
		}
		$this->create_product(
			array(
				'category'     => $hoodies->term_id,
				'stock_status' => 'outofstock',
			)
		);

		$markup = $this->render_pattern( 'editorial-category-showcase' );
		$thumbs = $this->find_all( parse_blocks( $markup ), 'core/image' );

		$this->assertStringContainsString( '4 pieces', $markup );
		$this->assertCount( 3, $thumbs );
		$this->assertSame( 'aa-line-index__thumb', $thumbs[0]['attrs']['className'] );
	}


	/** With nothing in stock the cover is the slogan alone: no empty frame. */
	public function test_cover_without_products_is_type_only(): void {
		$markup = $this->render_pattern( 'editorial-campaign-hero' );

		$this->assertSame( array(), $this->find_all( parse_blocks( $markup ), 'core/image' ) );
		$this->assertStringContainsString( 'Rebellion has a uniform.', $markup );
	}

	/** New Arrivals: one rail of the newest after the cover's garment, never behind a reveal. */
	public function test_new_arrivals_is_a_rail_after_the_cover(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->create_product( array( 'image' => $this->create_cutout() ) );
		}

		$blocks = parse_blocks( $this->render_in_page( array( 'editorial-campaign-hero', 'editorial-new-arrivals' ) ) );
		$grids  = $this->find_all( $blocks, 'woocommerce/product-collection' );

		$this->assertCount( 1, $grids );
		$this->assertSame( array( 4, 1 ), array( $grids[0]['attrs']['query']['perPage'], $grids[0]['attrs']['query']['offset'] ), 'The cover shows the newest: the rail starts after it.' );
		$this->assertStringContainsString( 'aa-grid-rail', $grids[0]['attrs']['className'] );
		$this->assertSame( array(), $this->find_all( $blocks, 'aggressive-blocks/animate-on-scroll' ), 'Products are never behind a reveal.' );
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
		$second = $this->create_cutout();
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
	 * Hoodies are a lineup on one studio band: up to four side by side (a
	 * short range centred at a third each, never one stretched card), five or
	 * more a rail.
	 */
	public function test_hoodies_lineup_follows_the_stock(): void {
		$hoodies = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'product_cat',
				'slug'     => 'hoodies',
			)
		);
		$this->create_product( array( 'category' => $hoodies->term_id ) );

		$grid = $this->find_all( parse_blocks( $this->render_pattern( 'editorial-hoodies-feature' ) ), 'woocommerce/product-collection' )[0];
		$this->assertSame( 1, $grid['attrs']['query']['perPage'] );
		$this->assertSame( 3, $grid['attrs']['displayLayout']['columns'] );
		$this->assertStringContainsString( 'aa-grid-lineup', $grid['attrs']['className'] );
		$this->assertStringContainsString( 'aa-grid-centered', $grid['attrs']['className'] );

		for ( $i = 0; $i < 3; $i++ ) {
			$this->create_product( array( 'category' => $hoodies->term_id ) );
		}
		$grid = $this->find_all( parse_blocks( $this->render_pattern( 'editorial-hoodies-feature' ) ), 'woocommerce/product-collection' )[0];
		$this->assertSame( 4, $grid['attrs']['displayLayout']['columns'] );
		$this->assertStringNotContainsString( 'aa-grid-centered', $grid['attrs']['className'] );
		$this->assertStringNotContainsString( 'aa-grid-rail', $grid['attrs']['className'] );

		$this->create_product( array( 'category' => $hoodies->term_id ) );
		$grid = $this->find_all( parse_blocks( $this->render_pattern( 'editorial-hoodies-feature' ) ), 'woocommerce/product-collection' )[0];
		$this->assertSame( 5, $grid['attrs']['query']['perPage'] );
		$this->assertStringContainsString( 'aa-grid-rail', $grid['attrs']['className'] );
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
			$product = $this->create_product( array( 'image' => $this->create_cutout() ) );
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

	/** A short catalog: the rail holds what follows the cover; with nothing after it, no section. */
	public function test_new_arrivals_short_catalog_follows_the_cover(): void {
		$this->create_product( array( 'image' => $this->create_cutout() ) );
		$this->assertSame( '', trim( $this->render_in_page( array( 'editorial-campaign-hero', 'editorial-new-arrivals' ) ) ), 'The only product is on the cover.' );

		wp_cache_flush();
		$this->create_product( array( 'image' => $this->create_cutout() ) );
		$this->create_product( array( 'image' => $this->create_cutout() ) );
		$grids = $this->find_all( parse_blocks( $this->render_in_page( array( 'editorial-campaign-hero', 'editorial-new-arrivals' ) ) ), 'woocommerce/product-collection' );
		$this->assertCount( 1, $grids );
		$this->assertSame( 2, $grids[0]['attrs']['query']['perPage'] );
	}

	/** Nothing publishes itself: unreviewed and excluded photos never fill a slot. */
	public function test_only_approved_photos_fill_editorial_slots(): void {
		$this->create_product( array( 'image' => $this->create_image( 1200, 1200, '' ) ) );
		$this->create_product( array( 'image' => $this->create_image( 1200, 1200, 'excluded' ) ) );
		$this->assertSame( array(), Editorial_Imagery::leads( 3 ) );

		wp_cache_flush();
		$approved = $this->create_image( 1200, 1200 );
		$product  = $this->create_product( array( 'image' => $approved ) );
		$this->assertSame(
			array(
				array(
					'product' => $product->get_id(),
					'image'   => $approved,
				),
			),
			Editorial_Imagery::leads( 3 )
		);
	}

	/** Higher priority first; equal priorities keep the newest product first. */
	public function test_approved_photos_follow_priority(): void {
		$low  = $this->create_image( 1200, 1200 );
		$high = $this->create_image( 1200, 1200 );
		update_post_meta( $high, Editorial_Controls::PRIORITY_META, 5 );
		$this->create_product( array( 'image' => $high ) );
		$this->create_product( array( 'image' => $low ) );

		$this->assertSame( array( $high, $low ), array_column( Editorial_Imagery::leads( 2 ), 'image' ) );
	}

	/** One cover at a time: marking a new campaign photo returns the last to Approved. */
	public function test_cover_choice_is_exclusive(): void {
		$first  = $this->create_image( 1600, 2000, 'cover' );
		$second = $this->create_image( 1600, 2000 );
		Editorial_Controls::set_use( $second, 'cover' );

		$this->assertSame( 'approved', Editorial_Controls::use_of( $first ) );
		$this->assertSame( array( $second ), Editorial_Controls::attachments_with_use( 'cover' ) );
	}

	/**
	 * The merchant's cover leads: the campaign photo filling its frame, named
	 * for the cover product; the cover product's own photo without one; the
	 * newest arrival only when nothing is chosen.
	 */
	public function test_cover_prefers_the_merchants_choice(): void {
		$chosen = $this->create_product( array( 'image' => $this->create_cutout() ) );
		$newest = $this->create_product( array( 'image' => $this->create_cutout() ) );
		wp_update_post(
			array(
				'ID'            => $chosen->get_id(),
				'post_date'     => '2026-01-01 00:00:00',
				'post_date_gmt' => '2026-01-01 00:00:00',
			)
		);
		update_post_meta( $chosen->get_id(), Editorial_Controls::COVER_PRODUCT_META, 'yes' );

		$blocks = parse_blocks( $this->render_pattern( 'editorial-campaign-hero' ) );
		$this->assertSame( (int) $chosen->get_image_id(), $this->find_all( $blocks, 'core/image' )[0]['attrs']['id'], 'The cover product, not the newest.' );
		$this->assertSame( 'contain', $this->find_all( $blocks, 'core/image' )[0]['attrs']['scale'] );
		$this->assertSame( 0, $this->find_all( parse_blocks( $this->render_in_page( array( 'editorial-campaign-hero', 'editorial-new-arrivals' ) ) ), 'woocommerce/product-collection' )[0]['attrs']['query']['offset'], 'The newest still opens New Arrivals.' );

		wp_cache_flush();
		$campaign = $this->create_image( 1600, 2000, 'cover' );
		$blocks   = parse_blocks( $this->render_pattern( 'editorial-campaign-hero' ) );
		$image    = $this->find_all( $blocks, 'core/image' )[0];
		$named    = $this->find_all( $blocks, 'woocommerce/product-collection' )[0];
		$this->assertSame( $campaign, $image['attrs']['id'] );
		$this->assertSame( array( '4/5', 'cover' ), array( $image['attrs']['aspectRatio'], $image['attrs']['scale'] ) );
		$this->assertSame( array( $chosen->get_id() ), $named['attrs']['query']['woocommerceHandPickedProducts'] );
		unset( $newest );
	}

	/** The attachment form saves Homepage use and a clamped priority. */
	public function test_attachment_form_saves_use_and_priority(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$image = $this->create_image( 1200, 1200, '' );

		( new Editorial_Controls() )->save_attachment_fields(
			array( 'ID' => $image ),
			array(
				'aa_editorial_use'      => 'cover',
				'aa_editorial_priority' => '120',
			)
		);

		$this->assertSame( 'cover', Editorial_Controls::use_of( $image ) );
		$this->assertSame( 99, Editorial_Controls::priority_of( $image ) );
	}

	/** Quick edit posts no field marker, so it never clears Show in Shop by line. */
	public function test_category_quick_edit_keeps_the_line_setting(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$term = self::factory()->term->create( array( 'taxonomy' => 'product_cat' ) );
		update_term_meta( $term, Editorial_Controls::LINE_META, 'yes' );
		$controls = new Editorial_Controls();

		$_POST = array();
		$controls->save_line_field( $term );
		$this->assertSame( 'yes', get_term_meta( $term, Editorial_Controls::LINE_META, true ) );

		$_POST = array( 'aa_shop_by_line_field' => '1' );
		$controls->save_line_field( $term );
		$this->assertSame( '', get_term_meta( $term, Editorial_Controls::LINE_META, true ) );
		$_POST = array();
	}
}
