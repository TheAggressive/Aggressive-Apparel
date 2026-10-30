<?php
/**
 * Audience Landing Pages Integration Tests
 *
 * @package Aggressive_Apparel\Tests\Integration\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Integration\WooCommerce;

use Aggressive_Apparel\WooCommerce\Audience_Landing_Pages;
use Aggressive_Apparel\WooCommerce\Catalog_Archive_Filters;
use WP_UnitTestCase;

/**
 * `/{category base}/{audience}/` and `/{category base}/{audience}/{category}/`.
 */
class TestAudienceLandingPages extends WP_UnitTestCase {

	/**
	 * The class under test, hooked.
	 *
	 * @var Audience_Landing_Pages
	 */
	private Audience_Landing_Pages $pages;

	/** Collection attribute with Men's / Women's, routes registered. */
	public function setUp(): void {
		parent::setUp();
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is required.' );
		}

		$this->set_permalink_structure( '/%postname%/' );
		self::flush_attribute_cache();
		if ( ! in_array( Audience_Landing_Pages::TAXONOMY, wc_get_attribute_taxonomy_names(), true ) ) {
			// A taxonomy left registered by a rolled-back test blocks the slug.
			unregister_taxonomy( Audience_Landing_Pages::TAXONOMY );
			wc_create_attribute(
				array(
					'name' => 'Collection',
					'slug' => 'collection',
				)
			);
			self::flush_attribute_cache();
		}
		if ( ! taxonomy_exists( Audience_Landing_Pages::TAXONOMY ) ) {
			register_taxonomy( Audience_Landing_Pages::TAXONOMY, array( 'product' ), array( 'hierarchical' => false ) );
		}
		foreach ( array(
			'mens'   => "Men's",
			'womens' => "Women's",
		) as $slug => $name ) {
			if ( ! term_exists( $slug, Audience_Landing_Pages::TAXONOMY ) ) {
				wp_insert_term( $name, Audience_Landing_Pages::TAXONOMY, array( 'slug' => $slug ) );
			}
		}

		$_GET        = array();
		$this->pages = new Audience_Landing_Pages();
		$this->pages->init();
		( new Catalog_Archive_Filters() )->init();
	}

	/** Reset the request and the attribute cache. */
	public function tearDown(): void {
		$_GET = array();
		parent::tearDown();
		self::flush_attribute_cache();
	}

	/** A category landing page lists that audience's products and stays the category. */
	public function test_category_landing_page_filters_and_stays_the_category(): void {
		$tees   = $this->category( 'aud-tees', 'Tees' );
		$mens   = $this->product( $tees, array( 'mens' ) );
		$both   = $this->product( $tees, array( 'mens', 'womens' ) );
		$womens = $this->product( $tees, array( 'womens' ) );

		$this->visit( Audience_Landing_Pages::url( 'mens', get_term( $tees ) ) );

		$this->assertSame( 'mens', Audience_Landing_Pages::current() );
		$this->assertSame( $tees, get_queried_object_id() );
		$ids = $this->listed();
		$this->assertContains( $mens, $ids );
		$this->assertContains( $both, $ids );
		$this->assertNotContains( $womens, $ids );
	}

	/** The audience page lists every product of that audience. */
	public function test_shop_landing_page_lists_the_audience(): void {
		$tees   = $this->category( 'aud-tees', 'Tees' );
		$mens   = $this->product( $tees, array( 'mens' ) );
		$womens = $this->product( $tees, array( 'womens' ) );

		$this->visit( Audience_Landing_Pages::url( 'womens' ) );

		$this->assertTrue( is_post_type_archive( 'product' ) );
		$this->assertContains( $womens, $this->listed() );
		$this->assertNotContains( $mens, $this->listed() );

		$this->visit( Audience_Landing_Pages::url( 'womens', get_term( $tees ), 2 ) );
		// Routed to page 2 (a 404 here: one product fits on page 1).
		$this->assertSame( 'womens', $GLOBALS['wp']->query_vars[ Audience_Landing_Pages::QUERY_VAR ] ?? '' );
		$this->assertSame( '2', $GLOBALS['wp']->query_vars['paged'] ?? '', 'Later pages route too.' );
	}

	/** Heading, titles, canonical and robots describe the landing page. */
	public function test_seo_output(): void {
		$tees = $this->category( 'aud-tees', 'Tees' );
		$this->product( $tees, array( 'mens' ) );
		$url = Audience_Landing_Pages::url( 'mens', get_term( $tees ) );

		$this->visit( $url );

		$this->assertSame( "Men's Tees", $this->pages->archive_title( 'Tees' ) );
		$this->assertSame( "Men's Tees", $this->pages->document_title( array( 'title' => 'Tees' ) )['title'] );
		$this->assertSame( "Men's Graphic Tees | Site", $this->pages->seo_title( 'Graphic Tees | Site' ) );
		$this->assertSame( "Men's Tees — Shop tees.", $this->pages->seo_description( 'Shop tees.' ) );
		$this->assertSame( $url, $this->pages->canonical( (string) get_term_link( $tees ) ) );
		$this->assertSame( 'index', $this->pages->robots( array( 'index' => 'index' ) )['index'] );
		$this->assertSame( array( 'max-image-preview' => 'large' ), $this->pages->core_robots( array( 'max-image-preview' => 'large' ) ) );
	}

	/** A combination without products is noindex. */
	public function test_empty_combination_is_noindex(): void {
		$tees = $this->category( 'aud-tees', 'Tees' );
		$this->product( $tees, array( 'womens' ) );
		$this->product( $this->category( 'aud-hoods', 'Hoods' ), array( 'mens' ) );

		$this->visit( Audience_Landing_Pages::url( 'mens', get_term( $tees ) ) );

		$this->assertSame( 'noindex', $this->pages->robots( array( 'index' => 'index' ) )['index'] );
		$core_robots = apply_filters( 'wp_robots', array( 'index' => true, 'nofollow' => true ) );
		$this->assertTrue( $core_robots['noindex'] );
		$this->assertArrayNotHasKey( 'index', $core_robots );
		$this->assertTrue( $core_robots['nofollow'], 'Existing robots directives survive.' );
	}

	/** Elsewhere nothing changes. */
	public function test_other_pages_are_untouched(): void {
		$tees = $this->category( 'aud-tees', 'Tees' );
		$this->product( $tees, array( 'mens' ) );

		$this->visit( (string) get_term_link( $tees ) );

		$this->assertSame( '', Audience_Landing_Pages::current() );
		$this->assertSame( 'Tees', $this->pages->archive_title( 'Tees' ) );
		$this->assertSame( 'x', $this->pages->canonical( 'x' ) );
		$this->assertSame( 'index', $this->pages->robots( array( 'index' => 'index' ) )['index'] );
		$this->assertSame( array( 'noindex' => true ), $this->pages->core_robots( array( 'noindex' => true ) ), 'Preserve a site-wide noindex setting.' );
	}

	/** An unknown category under an audience is a 404, not the audience page. */
	public function test_unknown_category_is_not_found(): void {
		$this->product( $this->category( 'aud-tees', 'Tees' ), array( 'mens' ) );

		$this->visit( home_url( '/product-category/mens/no-such-category/' ) );

		$this->assertTrue( is_404() );
	}

	/** Breadcrumbs: Home › Shop › Men's › Tees, and Home › Shop › Men's. */
	public function test_breadcrumbs(): void {
		$tees = $this->category( 'aud-tees', 'Tees' );
		$this->product( $tees, array( 'mens' ) );
		$home = home_url( '/' );
		$shop = (string) get_post_type_archive_link( 'product' );

		$this->visit( Audience_Landing_Pages::url( 'mens', get_term( $tees ) ) );
		$this->assertSame(
			array( array( 'Home', $home ), array( 'Shop', $shop ), array( "Men's", Audience_Landing_Pages::url( 'mens' ) ), array( 'Tees', '' ) ),
			$this->pages->breadcrumbs( array( array( 'Home', $home ), array( 'Shop', $shop ), array( 'Tees', '' ) ) )
		);

		$this->visit( Audience_Landing_Pages::url( 'mens' ) );
		$this->assertSame(
			array( array( 'Home', $home ), array( 'Shop', $shop ), array( "Men's", Audience_Landing_Pages::url( 'mens' ) ) ),
			$this->pages->breadcrumbs( array( array( 'Home', $home ), array( 'Shop', '' ) ) )
		);
	}

	/** Every address of a single-audience selection leads to its landing page. */
	public function test_redirects(): void {
		$tees = $this->category( 'aud-tees', 'Tees' );
		$this->product( $tees, array( 'mens', 'womens' ) );
		$term    = get_term( $tees );
		$landing = Audience_Landing_Pages::url( 'mens', $term );

		$this->visit( add_query_arg( 'collection', 'mens', (string) get_term_link( $tees ) ), array( 'collection' => 'mens' ) );
		$this->assertSame( $landing, Audience_Landing_Pages::redirect_target(), 'A single-audience filter moves to the landing page.' );

		$this->visit( add_query_arg( array( 'collection' => 'mens', 'size' => 'm' ), (string) get_term_link( $tees ) ), array( 'collection' => 'mens', 'size' => 'm' ) );
		$this->assertSame( add_query_arg( 'size', 'm', $landing ), Audience_Landing_Pages::redirect_target(), 'Other filters come along.' );

		$this->visit( add_query_arg( 'collection', 'mens,womens', (string) get_term_link( $tees ) ), array( 'collection' => 'mens,womens' ) );
		$this->assertSame( '', Audience_Landing_Pages::redirect_target(), 'Both audiences stay a filter.' );

		$this->visit( add_query_arg( 'collection', 'womens', $landing ), array( 'collection' => 'womens' ) );
		$this->assertSame( add_query_arg( 'collection', rawurlencode( 'mens,womens' ), (string) get_term_link( $tees ) ), Audience_Landing_Pages::redirect_target(), 'Adding an audience widens to the category.' );

		$this->visit( add_query_arg( 'collection', 'mens', $landing ), array( 'collection' => 'mens' ) );
		$this->assertSame( $landing, Audience_Landing_Pages::redirect_target(), 'Repeating the audience drops the parameter.' );

		$this->visit( $landing );
		$this->assertSame( '', Audience_Landing_Pages::redirect_target(), 'The landing page itself stays.' );
	}

	/** Only audiences with products get routes; a same-slug category keeps its archive. */
	public function test_routes_only_for_audiences_with_products(): void {
		$tees = $this->category( 'aud-tees', 'Tees' );
		$this->product( $tees, array( 'mens' ) );

		$this->assertSame( array( 'mens' ), array_keys( Audience_Landing_Pages::audiences() ) );

		$this->category( 'mens', 'Mens category' );
		$this->assertSame( array(), Audience_Landing_Pages::audiences() );
	}

	/** The sitemap lists each landing page that has products. */
	public function test_sitemap_lists_landing_pages_with_products(): void {
		$tees  = $this->category( 'aud-tees', 'Tees' );
		$hoods = $this->category( 'aud-hoods', 'Hoods' );
		$this->product( $tees, array( 'mens' ) );
		$this->product( $hoods, array( 'womens' ) );

		$locs = array_column( Audience_Landing_Pages::sitemap_links(), 'loc' );

		$this->assertContains( Audience_Landing_Pages::url( 'mens' ), $locs );
		$this->assertContains( Audience_Landing_Pages::url( 'mens', get_term( $tees ) ), $locs );
		$this->assertContains( Audience_Landing_Pages::url( 'womens', get_term( $hoods ) ), $locs );
		$this->assertNotContains( Audience_Landing_Pages::url( 'mens', get_term( $hoods ) ), $locs );
	}

	/**
	 * Request a URL with the routes for the current audiences.
	 *
	 * @param string               $url   URL.
	 * @param array<string, mixed> $query Query string, as `$_GET` would hold it.
	 */
	private function visit( string $url, array $query = array() ): void {
		$this->pages->register_rules();
		flush_rewrite_rules( false );
		$_GET = $query;
		$this->go_to( $url );
	}

	/**
	 * IDs the main query lists.
	 *
	 * @return list<int>
	 */
	private function listed(): array {
		return array_map( static fn( $post ): int => (int) $post->ID, $GLOBALS['wp_query']->posts );
	}

	/**
	 * Create a product category.
	 *
	 * @param string $slug Slug.
	 * @param string $name Name.
	 */
	private function category( string $slug, string $name ): int {
		return (int) self::factory()->term->create(
			array(
				'taxonomy' => 'product_cat',
				'slug'     => $slug,
				'name'     => $name,
			)
		);
	}

	/**
	 * Create a published product in a category for audiences.
	 *
	 * @param int          $category  Category ID.
	 * @param list<string> $audiences Collection slugs.
	 */
	private function product( int $category, array $audiences ): int {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Audience product ' . implode( '+', $audiences ) );
		$product->set_status( 'publish' );
		$product->set_regular_price( '20' );
		$product->set_category_ids( array( $category ) );
		$id = $product->save();
		wp_set_object_terms( $id, $audiences, Audience_Landing_Pages::TAXONOMY );

		return $id;
	}

	/** Drop WooCommerce's cached attribute list. */
	private static function flush_attribute_cache(): void {
		delete_transient( 'wc_attribute_taxonomies' );
		\WC_Cache_Helper::invalidate_cache_group( 'woocommerce-attributes' );
	}
}
