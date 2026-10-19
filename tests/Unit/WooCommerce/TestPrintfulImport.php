<?php
/**
 * Printful catalog search, draft conversion, and import bookkeeping, with
 * recorded API responses (tests/fixtures/printful) instead of the network.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Printful_Catalog;
use Aggressive_Apparel\WooCommerce\Printful_Import;
use Aggressive_Apparel\WooCommerce\Printful_Rest;
use Aggressive_Apparel\WooCommerce\Product_Specs_Registry;
use WP_UnitTestCase;

/**
 * Covers importing a size guide from Printful.
 */
class TestPrintfulImport extends WP_UnitTestCase {

	/**
	 * URLs requested during a test.
	 *
	 * @var list<string>
	 */
	private array $requests = array();

	/**
	 * Serve recorded responses and start clean.
	 */
	public function setUp(): void {
		parent::setUp();

		update_option( Feature_Settings::OPTION_KEY, array( 'size_guide' => true ), false );
		delete_option( Product_Specs_Registry::OPTION_KEY );
		delete_option( Printful_Import::RULES );
		foreach ( array( 'aa_printful_products', 'aa_printful_product_71', 'aa_printful_sizes_71' ) as $key ) {
			delete_transient( $key );
		}
		Product_Specs_Registry::flush();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		add_filter( 'pre_http_request', array( $this, 'respond' ), 10, 3 );
	}

	/**
	 * Restore global state.
	 */
	public function tearDown(): void {
		remove_filter( 'pre_http_request', array( $this, 'respond' ), 10 );
		delete_option( Feature_Settings::OPTION_KEY );
		delete_option( Printful_Import::RULES );
		Product_Specs_Registry::flush();

		parent::tearDown();
	}

	/**
	 * A recorded response for a Printful URL, or a 404.
	 *
	 * @param false|array<mixed> $response Response so far.
	 * @param array<mixed>       $args     Request args.
	 * @param string             $url      URL.
	 * @return array<mixed>
	 */
	public function respond( $response, $args, $url ): array {
		unset( $response, $args );
		$this->requests[] = $url;
		$files            = array(
			'/categories'       => 'categories.json',
			'/products'         => 'products.json',
			'/products/71'      => 'product-71.json',
			'/products/71/sizes' => 'sizes-71.json',
		);
		$path             = (string) wp_parse_url( $url, PHP_URL_PATH );
		$file             = 'api.printful.com' === wp_parse_url( $url, PHP_URL_HOST ) && isset( $files[ $path ] )
			? dirname( __DIR__, 2 ) . '/fixtures/printful/' . $files[ $path ]
			: '';

		return array(
			'headers'  => array(),
			'body'     => '' !== $file ? (string) file_get_contents( $file ) : '{"code":404,"result":"Not Found"}', // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture.
			'response' => array(
				'code'    => '' !== $file ? 200 : 404,
				'message' => '',
			),
			'cookies'  => array(),
		);
	}

	/** Search lists apparel only (no accessories, nothing discontinued), cached. */
	public function test_search_lists_current_apparel_and_caches(): void {
		$all = Printful_Catalog::search( '' );

		$this->assertEqualsCanonicalizing( array( 71, 679 ), array_column( $all, 'id' ) );
		$this->assertSame( array( 71 ), array_column( Printful_Catalog::search( '3001' ), 'id' ) );
		$this->assertSame( "Men's clothing", $all[0]['group'] );
		$this->assertCount( 1, array_filter( $this->requests, static fn( string $url ): bool => str_ends_with( $url, '/products' ) ), 'The catalog is fetched once.' );
	}

	/** Both size tables become charts, ranges and order kept; notes are plain text. */
	public function test_draft_converts_charts_and_notes(): void {
		$draft = Printful_Import::draft( 71 );

		$this->assertSame( 'Bella + Canvas 3001', $draft['title'] );
		$this->assertSame( array( 'Length', 'Width' ), array_column( $draft['charts']['garment']['columns'], 'label' ) );
		$this->assertSame( 'XS', $draft['charts']['garment']['rows'][0]['size'] );
		$this->assertSame( 'in', $draft['charts']['garment']['unit'] );
		$this->assertSame( '31–34', $draft['charts']['body']['rows'][0]['values'][1], 'A range keeps both ends.' );
		$this->assertStringContainsString( 'Place the end of the tape', $draft['notes']['garment'] );
		$this->assertStringNotContainsString( '<', $draft['notes']['garment'] );
		$this->assertSame( 'files.cdn.printful.com', wp_parse_url( $draft['diagrams']['garment'], PHP_URL_HOST ) );
	}

	/** Description lines map to specs where clear, with values ready. */
	public function test_facts_map_to_specs(): void {
		$facts = array_column( Printful_Import::draft( 71 )['facts'], null, 'text' );

		$this->assertSame( array( 'fabric', 'sure', '100% combed and ring-spun cotton' ), $this->fact( $facts, '100% combed' ) );
		$this->assertSame( array( 'weight', 'sure', '142' ), $this->fact( $facts, 'Fabric weight' ) );
		$this->assertSame( array( 'construction', 'sure', 'Side-seamed' ), $this->fact( $facts, 'Side-seamed' ) );
		$this->assertSame( array( '', 'unsure', '' ), $this->fact( $facts, 'Pre-shrunk' ) );
		$this->assertSame( 'skipped', $this->fact( $facts, 'Blank product' )[1] );
	}

	/** Creating from an import records its source and remembers the choices. */
	public function test_created_records_source_and_remembers_choices(): void {
		$guide = self::factory()->post->create( array( 'post_type' => 'aa_size_guide' ) );
		Printful_Import::created(
			$guide,
			array(
				'importSource' => array(
					'provider' => 'printful',
					'id'       => 71,
				),
				'importRules'  => array(
					array(
						'key'    => 'Pre-shrunk fabric',
						'target' => 'skip',
					),
				),
			)
		);

		$this->assertSame( 71, get_post_meta( $guide, Printful_Import::SOURCE, true )['id'] );
		$this->assertSame( array( 71 => $guide ), Printful_Import::imported( 'printful' ) );
		$facts = array_column( Printful_Import::draft( 71 )['facts'], null, 'text' );
		$this->assertSame( 'skipped', $this->fact( $facts, 'Pre-shrunk' )[1], 'The rule applies next time.' );
		$this->assertSame( $guide, Printful_Import::draft( 71 )['guide'], 'The draft knows it was imported.' );
	}

	/** A diagram is only ever fetched from Printful's file CDN. */
	public function test_diagram_only_from_printful(): void {
		$guide = self::factory()->post->create( array( 'post_type' => 'aa_size_guide' ) );
		Printful_Import::created(
			$guide,
			array(
				'importSource' => array(
					'provider' => 'printful',
					'id'       => 71,
				),
				'diagramUrl'   => 'https://evil.example/diagram.png',
			)
		);

		$this->assertSame( '', get_post_meta( $guide, '_aa_size_diagram', true ) );
		$this->assertSame( array(), array_filter( $this->requests, static fn( string $url ): bool => str_contains( $url, 'evil.example' ) ) );
	}

	/** A product with no size tables still imports its specs. */
	public function test_product_without_sizes_still_drafts(): void {
		set_transient( 'aa_printful_product_72', array( 'product' => array( 'title' => 'Bag', 'description' => "• 100% cotton canvas" ) ), HOUR_IN_SECONDS );
		$draft = Printful_Import::draft( 72 );

		$this->assertNull( $draft['charts']['garment'] );
		$this->assertSame( 'fabric', $draft['facts'][0]['target'] );
	}

	/** The search route marks products already imported. */
	public function test_search_route_marks_imported(): void {
		$guide = self::factory()->post->create( array( 'post_type' => 'aa_size_guide' ) );
		update_post_meta(
			$guide,
			Printful_Import::SOURCE,
			array(
				'provider' => 'printful',
				'id'       => 71,
			)
		);
		$request = new \WP_REST_Request( 'GET' );
		$request->set_param( 'search', '3001' );

		$data = ( new Printful_Rest() )->search( $request )->get_data();

		$this->assertSame( $guide, $data['products'][0]['guide'] );
	}

	/**
	 * A fact's target, status, and value, by the start of its text.
	 *
	 * @param array<string, array<string, string>> $facts  Facts by text.
	 * @param string                               $prefix Text prefix.
	 * @return array{0: string, 1: string, 2: string}
	 */
	private function fact( array $facts, string $prefix ): array {
		foreach ( $facts as $text => $fact ) {
			if ( str_starts_with( $text, $prefix ) ) {
				return array( $fact['target'], $fact['status'], $fact['value'] );
			}
		}
		$this->fail( 'No fact starting with ' . $prefix );
	}
}
