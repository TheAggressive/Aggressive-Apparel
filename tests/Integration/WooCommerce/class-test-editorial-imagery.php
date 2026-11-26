<?php
/**
 * Editorial imagery and count-aware section layout tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Integration\WooCommerce;

use Aggressive_Apparel\Tests\Support\Builds_Editorial_Fixtures;
use Aggressive_Apparel\WooCommerce\Catalog_Image_Loading;
use Aggressive_Apparel\WooCommerce\Editorial_Campaign_Cover;
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
	 * The cover never picks a product by itself: with stock but nothing
	 * chosen it is the slogan alone, not the newest arrival.
	 */
	public function test_cover_never_picks_a_product_itself(): void {
		$this->create_product( array( 'image' => $this->create_image() ) );
		$this->create_product( array( 'image' => $this->create_cutout() ) );

		$markup = $this->render_pattern( 'editorial-campaign-hero' );
		$blocks = parse_blocks( $markup );

		$this->assertSame( array(), $this->find_all( $blocks, 'core/image' ) );
		$this->assertSame( array(), $this->find_all( $blocks, 'core/template-part' ) );
		$this->assertSame( array(), $this->find_all( $blocks, 'woocommerce/product-collection' ) );
		$this->assertStringContainsString( 'Rebellion has a uniform.', $markup );
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

	/** New Arrivals: one rail of the newest, starting after the cover product when it is the newest; never behind a reveal. */
	public function test_new_arrivals_is_a_rail_after_the_cover(): void {
		for ( $i = 1; $i <= 5; $i++ ) {
			$newest = $this->create_product( array( 'image' => $this->create_cutout() ) );
			wp_update_post(
				array(
					'ID'            => $newest->get_id(),
					'post_date'     => sprintf( '2026-01-%02d 00:00:00', $i ),
					'post_date_gmt' => sprintf( '2026-01-%02d 00:00:00', $i ),
				)
			);
		}

		$blocks = parse_blocks( $this->render_in_page( array( 'editorial-campaign-hero', 'editorial-new-arrivals' ) ) );
		$grids  = $this->find_all( $blocks, 'woocommerce/product-collection' );
		$this->assertCount( 1, $grids );
		$this->assertSame( array( 5, 0 ), array( $grids[0]['attrs']['query']['perPage'], $grids[0]['attrs']['query']['offset'] ), 'Nothing chosen: the rail starts at the newest.' );
		$this->assertStringContainsString( 'aa-grid-rail', $grids[0]['attrs']['className'] );
		$this->assertSame( array(), $this->find_all( $blocks, 'aggressive-blocks/animate-on-scroll' ), 'Products are never behind a reveal.' );

		update_post_meta( $newest->get_id(), Editorial_Controls::COVER_PRODUCT_META, 'yes' );
		$grids = $this->find_all( parse_blocks( $this->render_in_page( array( 'editorial-campaign-hero', 'editorial-new-arrivals' ) ) ), 'woocommerce/product-collection' );
		$this->assertSame( array( 4, 1 ), array( $grids[0]['attrs']['query']['perPage'], $grids[0]['attrs']['query']['offset'] ), 'The cover shows the newest: the rail starts after it.' );

		// A newer product without an image yet still leads the rail, so the
		// cover is no longer the newest and nothing is skipped.
		$imageless = $this->create_product();
		wp_update_post(
			array(
				'ID'            => $imageless->get_id(),
				'post_date'     => '2026-01-06 00:00:00',
				'post_date_gmt' => '2026-01-06 00:00:00',
			)
		);
		$grids = $this->find_all( parse_blocks( $this->render_in_page( array( 'editorial-campaign-hero', 'editorial-new-arrivals' ) ) ), 'woocommerce/product-collection' );
		$this->assertSame( 0, $grids[0]['attrs']['query']['offset'], 'The newest product leads the rail, image or not.' );
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

	/** A short catalog: the rail holds what the cover doesn't show; with nothing left, no section. */
	public function test_new_arrivals_short_catalog_follows_the_cover(): void {
		$only = $this->create_product( array( 'image' => $this->create_cutout() ) );
		$this->assertCount( 1, $this->find_all( parse_blocks( $this->render_in_page( array( 'editorial-campaign-hero', 'editorial-new-arrivals' ) ) ), 'woocommerce/product-collection' ), 'Not on the cover: the one product has a rail.' );

		update_post_meta( $only->get_id(), Editorial_Controls::COVER_PRODUCT_META, 'yes' );
		$this->assertSame( '', trim( $this->render_in_page( array( 'editorial-campaign-hero', 'editorial-new-arrivals' ) ) ), 'The only product is on the cover.' );
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

	/**
	 * Without a campaign photo, the product marked Homepage cover is the
	 * optional fallback: its own image shown whole, named with its price.
	 */
	public function test_cover_product_is_the_optional_fallback(): void {
		$chosen = $this->create_product( array( 'image' => $this->create_cutout() ) );
		$this->create_product( array( 'image' => $this->create_cutout() ) );
		wp_update_post(
			array(
				'ID'            => $chosen->get_id(),
				'post_date'     => '2026-01-01 00:00:00',
				'post_date_gmt' => '2026-01-01 00:00:00',
			)
		);
		update_post_meta( $chosen->get_id(), Editorial_Controls::COVER_PRODUCT_META, 'yes' );

		$blocks = parse_blocks( $this->render_pattern( 'editorial-campaign-hero' ) );
		$image  = $this->find_all( $blocks, 'core/image' );
		$named  = $this->find_all( $blocks, 'woocommerce/product-collection' );
		$this->assertCount( 1, $image );
		$this->assertSame( array( (int) $chosen->get_image_id(), 'contain' ), array( $image[0]['attrs']['id'], $image[0]['attrs']['scale'] ) );
		$this->assertSame( array( $chosen->get_id() ), $named[0]['attrs']['query']['woocommerceHandPickedProducts'] );
		$this->assertStringContainsString( 'aa-grid-caption', $named[0]['attrs']['className'] );
		$this->assertSame( 0, $this->find_all( parse_blocks( $this->render_in_page( array( 'editorial-campaign-hero', 'editorial-new-arrivals' ) ) ), 'woocommerce/product-collection' )[0]['attrs']['query']['offset'], 'The newest still opens New Arrivals.' );
	}

	/**
	 * The campaign photo set in the Site Editor leads the cover: the template
	 * part itself (so it stays editable in place), named for the cover
	 * product, loaded first at the width its crop needs, and never repeated
	 * further down the page.
	 */
	public function test_campaign_photo_leads_the_cover(): void {
		$chosen   = $this->create_product( array( 'image' => $this->create_cutout() ) );
		$campaign = $this->create_image( 2400, 1200 );
		$this->create_product( array( 'image' => $campaign ) );
		update_post_meta( $chosen->get_id(), Editorial_Controls::COVER_PRODUCT_META, 'yes' );
		$this->set_campaign_image( $campaign );

		$markup = $this->render_pattern( 'editorial-campaign-hero' );
		$blocks = parse_blocks( $markup );
		$this->assertSame( array(), $this->find_all( $blocks, 'core/template-part' ) );
		$this->assertSame( array(), $this->find_all( $blocks, 'core/image' ), 'No product image beside the campaign photo.' );
		$covers = $this->find_all( $blocks, 'core/cover' );
		$this->assertCount( 1, $covers, 'The cover is the section.' );
		$this->assertSame( 'full', $covers[0]['attrs']['align'] );
		$this->assertSame( 'aa-campaign-cover aa-campaign-cover--hero aa-cover', $covers[0]['attrs']['className'] );
		$this->assertArrayNotHasKey( 'minHeight', $covers[0]['attrs'], "The layout sets the height, not the part's editor frame." );
		$this->assertStringNotContainsString( 'aa-campaign-image', $covers[0]['innerContent'][0], "The part's own frame class is dropped." );
		$this->assertSame( 1, count( $this->find_all( $covers[0]['innerBlocks'], 'core/heading' ) ), 'The slogan is inside the photo.' );
		$this->assertSame( array( $chosen->get_id() ), $this->find_all( $covers[0]['innerBlocks'], 'woocommerce/product-collection' )[0]['attrs']['query']['woocommerceHandPickedProducts'] );

		$html = new \WP_HTML_Tag_Processor( do_blocks( $markup ) );
		$this->assertTrue( $html->next_tag( array( 'class_name' => 'wp-image-' . $campaign ) ) );
		$this->assertSame( array( 'eager', 'high' ), array( $html->get_attribute( 'loading' ), $html->get_attribute( 'fetchpriority' ) ) );
		$this->assertSame( '--aa-focal:12% 50%;--aa-focal-phone:12% 50%', $html->get_attribute( 'style' ), 'One focal point serves both crops unless the part has a phone photo.' );

		$this->assertNotContains( $campaign, array_column( Editorial_Imagery::leads( 3 ), 'image' ), 'The cover claims its photo.' );
	}

	/** Only a Cover block with a picture counts: the theme's empty part or a video means no campaign photo. */
	public function test_campaign_photo_comes_from_the_site_editor(): void {
		$this->assertNull( Editorial_Imagery::campaign(), "The theme's own part ships empty." );

		$image = $this->create_image( 1600, 2000 );
		$this->set_campaign_image( $image );
		$this->assertSame(
			array(
				'image' => $image,
				'url'   => (string) wp_get_attachment_url( $image ),
			),
			Editorial_Imagery::campaign()
		);
	}

	/**
	 * A drafted or trashed saved part is not what the front end renders, so
	 * it is not a campaign photo: the cover never frames an empty part.
	 */
	public function test_unpublished_campaign_part_is_ignored(): void {
		$part = $this->set_campaign_image( $this->create_image( 1600, 2000 ) );
		$this->assertNotNull( Editorial_Imagery::campaign() );

		wp_update_post(
			array(
				'ID'          => $part,
				'post_status' => 'draft',
			)
		);
		wp_cache_flush();
		$this->assertNull( Editorial_Imagery::campaign(), 'Drafted.' );

		wp_trash_post( $part );
		wp_cache_flush();
		$this->assertNull( Editorial_Imagery::campaign(), 'Trashed.' );
		$this->assertStringNotContainsString( 'wp:template-part', $this->render_pattern( 'editorial-campaign-hero' ) );
	}

	/**
	 * A campaign photo is fetched at the width its crop needs: a 2:1 photo
	 * fills a ~1.8:1 desktop frame (112vw) and a 4:5 phone frame (250vw). A
	 * second Cover in the part gives phones their own photo and focal point,
	 * through a <picture> source.
	 */
	public function test_campaign_photo_sizes_and_phone_crop(): void {
		$campaign = $this->create_sized_image( 2400, 1200 );
		$phone    = $this->create_sized_image( 1600, 2000 );
		$this->set_campaign_image( $campaign, Editorial_Campaign_Cover::HERO, $phone );

		$html = do_blocks( $this->render_pattern( 'editorial-campaign-hero' ) );
		$tags = new \WP_HTML_Tag_Processor( $html );
		$this->assertTrue( $tags->next_tag( 'SOURCE' ) );
		$this->assertSame( '(max-width: 781px)', $tags->get_attribute( 'media' ) );
		$this->assertStringContainsString( 'editorial-sized-1600x2000-1024w', (string) $tags->get_attribute( 'srcset' ) );
		$this->assertSame( '100vw', $tags->get_attribute( 'sizes' ), 'A 4:5 phone photo in a 4:5 frame: its own width.' );
		$this->assertTrue( $tags->next_tag( array( 'class_name' => 'wp-image-' . $campaign ) ) );
		$this->assertSame( '(min-width: 782px) 112vw, 250vw', $tags->get_attribute( 'sizes' ) );
		$this->assertStringContainsString( '--aa-focal-phone:30% 40%', (string) $tags->get_attribute( 'style' ) );

		// Core adds "auto" to a lazy image's sizes; a cropped photo needs more.
		$loading = new Catalog_Image_Loading();
		$this->assertSame( '<img class="aa-campaign-cover__image" sizes="(min-width: 782px) 125vw, 250vw">', $loading->keep_campaign_sizes( '<img class="aa-campaign-cover__image" sizes="auto, (min-width: 782px) 125vw, 250vw">' ) );
		$this->assertSame( '<img class="card" sizes="auto, 100vw">', $loading->keep_campaign_sizes( '<img class="card" sizes="auto, 100vw">' ) );
	}

	/**
	 * A collection campaign: with a photo in its part, the drop is that photo
	 * full width with its copy and one CTA inside, Defiance Division a film
	 * still with its copy beneath; without one, the band.
	 */
	public function test_collection_campaign_covers(): void {
		foreach ( array( 'dead-signal', 'defiance-division' ) as $slug ) {
			$term = self::factory()->term->create_and_get(
				array(
					'taxonomy' => 'product_cat',
					'slug'     => $slug,
					'name'     => ucwords( str_replace( '-', ' ', $slug ) ),
				)
			);
			$this->create_product( array( 'category' => $term->term_id ) );
		}
		$this->assertSame( array(), $this->find_all( parse_blocks( $this->render_pattern( 'editorial-featured-drop' ) ), 'core/cover' ), 'No photo: the band.' );

		$photo = $this->create_image( 2400, 1200, '' );
		$this->set_campaign_image( $photo, Editorial_Campaign_Cover::DROP );
		$drop = $this->find_all( parse_blocks( $this->render_pattern( 'editorial-featured-drop' ) ), 'core/cover' );
		$this->assertCount( 1, $drop );
		$this->assertSame( 'aa-campaign-cover aa-campaign-cover--drop', $drop[0]['attrs']['className'] );
		$this->assertSame( 'Dead Signal', wp_strip_all_tags( $this->find_all( $drop[0]['innerBlocks'], 'core/heading' )[0]['innerHTML'] ) );
		$this->assertCount( 1, $this->find_all( $drop[0]['innerBlocks'], 'core/button' ), 'One clear CTA.' );
		$this->assertSame( array(), $this->find_all( $drop[0]['innerBlocks'], 'woocommerce/product-collection' ) );

		$this->set_campaign_image( $photo, Editorial_Campaign_Cover::DEFIANCE );
		$blocks = parse_blocks( $this->render_pattern( 'editorial-defiance-division' ) );
		$film   = $this->find_all( $blocks, 'core/cover' );
		$this->assertCount( 1, $film );
		$this->assertSame( 'aa-campaign-cover aa-campaign-cover--film', $film[0]['attrs']['className'] );
		$this->assertArrayNotHasKey( 'align', $film[0]['attrs'], "The still sits in the band's wide column." );
		$this->assertSame( array(), $film[0]['innerBlocks'], 'Nothing over the photo.' );
		$this->assertSame( 'Defiance Division', wp_strip_all_tags( $this->find_all( $blocks, 'core/heading' )[0]['innerHTML'] ), 'The title beneath it.' );
	}

	/**
	 * An image attachment with generated sizes, as an upload has, so core
	 * builds its srcset.
	 *
	 * @param int $width  Pixel width.
	 * @param int $height Pixel height.
	 */
	private function create_sized_image( int $width, int $height ): int {
		$file = 'editorial-sized-' . $width . 'x' . $height;
		$id   = self::factory()->attachment->create_object(
			array(
				'file'           => $file . '.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);
		wp_update_attachment_metadata(
			$id,
			array(
				'width'  => $width,
				'height' => $height,
				'file'   => $file . '.jpg',
				'sizes'  => array(
					'large' => array(
						'file'      => $file . '-1024w.jpg',
						'width'     => 1024,
						'height'    => (int) round( 1024 * $height / $width ),
						'mime-type' => 'image/jpeg',
					),
				),
			)
		);

		return $id;
	}

	/**
	 * Uploads are classified once their sizes exist; the Media Library form
	 * only reads that answer and never opens a file.
	 */
	public function test_media_library_reads_only_the_upload_classification(): void {
		$image = $this->create_image( 1200, 1200, '' );
		( new Editorial_Controls() )->attachment_fields( array(), get_post( $image ) );
		$this->assertNull( Editorial_Imagery::cached_kind( $image ), 'The form never classifies.' );

		$cutout = $this->create_cutout();
		$this->assertNull( Editorial_Imagery::cached_kind( $cutout ) );
		$meta = (array) wp_get_attachment_metadata( $cutout );
		$this->assertSame( $meta, Editorial_Imagery::classify_upload( $meta, $cutout ) );
		$this->assertSame( 'cutout', Editorial_Imagery::cached_kind( $cutout ) );
	}

	/** The attachment form saves Homepage use and a clamped priority. */
	public function test_attachment_form_saves_use_and_priority(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$image = $this->create_image( 1200, 1200, '' );

		( new Editorial_Controls() )->save_attachment_fields(
			array( 'ID' => $image ),
			array(
				'aa_editorial_use'      => 'excluded',
				'aa_editorial_priority' => '120',
			)
		);

		$this->assertSame( 'excluded', Editorial_Controls::use_of( $image ) );
		Editorial_Controls::set_use( $image, 'cover' );
		$this->assertSame( 'excluded', Editorial_Controls::use_of( $image ), 'The cover is chosen in the Site Editor, not here.' );
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
