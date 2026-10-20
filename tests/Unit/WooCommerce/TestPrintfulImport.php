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
	 * Taxonomies registered before each test.
	 *
	 * @var list<string>
	 */
	private array $baseline = array();

	/**
	 * HTTP status Printful answers the size table request with.
	 *
	 * @var int
	 */
	private int $sizes_status = 200;

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
		$this->baseline = array_values( get_taxonomies() );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		add_filter( 'pre_http_request', array( $this, 'respond' ), 10, 3 );
	}

	/**
	 * Restore global state.
	 */
	public function tearDown(): void {
		// Attribute taxonomies outlive the DB rollback; drop the ones made here.
		foreach ( array_diff( array_values( get_taxonomies() ), $this->baseline ) as $taxonomy ) {
			unregister_taxonomy( $taxonomy );
		}
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

		if ( '/products/71/sizes' === $path && 200 !== $this->sizes_status ) {
			return array(
				'headers'  => array(),
				'body'     => '{"code":500,"result":"Server error"}',
				'response' => array(
					'code'    => $this->sizes_status,
					'message' => '',
				),
				'cookies'  => array(),
			);
		}

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
		update_post_meta( $guide, Printful_Import::SOURCE_KEY, 'printful:71' );
		$request = new \WP_REST_Request( 'GET' );
		$request->set_param( 'search', '3001' );

		$data = ( new Printful_Rest() )->search( $request )->get_data();

		$this->assertSame( $guide, $data['products'][0]['guide'] );
	}

	/** Before a search, popular blanks come first and are marked. */
	public function test_popular_blanks_lead_an_empty_search(): void {
		$all = Printful_Catalog::search( '' );

		$this->assertSame( 71, $all[0]['id'], 'Bella + Canvas 3001 is a popular blank.' );
		$this->assertTrue( $all[0]['popular'] );
		$this->assertFalse( Printful_Catalog::search( '3001' )[0]['popular'], 'Not flagged once searching.' );
	}

	/** Only a real composition maps to Fabric, not a line that mentions a fiber. */
	public function test_fabric_needs_a_composition(): void {
		$facts = array_column( Printful_Import::facts( "• 1 × 1 rib-knit cuffs with spandex\n• 50% cotton, 50% polyester\n• Fabric: French terry" ), 'target', 'text' );

		$this->assertSame( '', $facts['1 × 1 rib-knit cuffs with spandex'] );
		$this->assertSame( 'fabric', $facts['50% cotton, 50% polyester'] );
		$this->assertSame( 'fabric', $facts['Fabric: French terry'] );
	}

	/** Creating a guide can add a store-wide spec and join another guide's spec. */
	public function test_create_adds_store_specs_and_joins_guide_specs(): void {
		// The feature registers this on boot; the test registers it itself.
		if ( false === has_action( 'aggressive_apparel_size_guide_created', array( Printful_Import::class, 'created' ) ) ) {
			( new Printful_Import() )->init();
		}
		$rest  = new \Aggressive_Apparel\WooCommerce\Size_Guide_Rest();
		$first = $rest->create(
			$this->json(
				array(
					'title'          => 'Bella + Canvas 3001',
					'categories'     => array(),
					'specItems'      => array(
						array(
							'key'  => 'pre_shrunk',
							'name' => 'Pre-shrunk',
							'type' => 'text',
						),
					),
					'storeSpecItems' => array(
						array(
							'key'      => 'closure',
							'name'     => 'Closure',
							'type'     => 'text',
							'multiple' => true,
						),
					),
					'specs'          => array(
						'pre_shrunk' => 'Yes',
						'closure'    => 'Zip | Snap',
					),
					'importSource'   => array(
						'provider' => 'printful',
						'id'       => 71,
						'title'    => 'Unisex Staple T-Shirt',
					),
				)
			)
		)->get_data();
		$second = $rest->create(
			$this->json(
				array(
					'title'     => 'Gildan 18500',
					'specItems' => array(
						array(
							'key'  => 'pre_shrunk',
							'name' => 'Pre-shrunk',
							'join' => true,
						),
					),
				)
			)
		)->get_data();

		$items = array_column( Product_Specs_Registry::get()['items'], null, 'key' );
		$this->assertSame( array(), $items['closure']['guides'], 'Store-wide.' );
		$this->assertTrue( $items['closure']['multiple'] );
		$this->assertSame( array( $first['id'], $second['id'] ), $items['pre_shrunk']['guides'], 'One spec, shared.' );
		$this->assertArrayNotHasKey( 'pre_shrunk_2', $items, 'No duplicate.' );
		$this->assertSame( 'Zip | Snap', $first['specs']['closure'] );
		$this->assertSame( 'Unisex Staple T-Shirt', $first['source']['title'] );
	}

	/** Printful being down is an error, not a guide without its chart. */
	public function test_draft_reports_a_size_table_outage(): void {
		$this->sizes_status = 500;

		$draft = Printful_Import::draft( 71 );

		$this->assertWPError( $draft );
		$this->assertFalse( get_transient( 'aa_printful_sizes_71' ), 'A failure is not cached.' );
	}

	/** Only garment and "measure yourself" tables become charts. */
	public function test_draft_ignores_other_size_tables(): void {
		$table = static fn( string $type, string $label ): array => array(
			'type'         => $type,
			'unit'         => 'inches',
			'measurements' => array(
				array(
					'type_label' => $label,
					'values'     => array(
						array(
							'size'  => 'S',
							'value' => '30',
						),
					),
				),
			),
		);
		set_transient( 'aa_printful_product_73', array( 'product' => array( 'title' => 'Tee' ) ), HOUR_IN_SECONDS );
		set_transient(
			'aa_printful_sizes_73',
			array( 'size_tables' => array( $table( 'international', 'EU' ), $table( 'measure_yourself', 'Chest' ) ) ),
			HOUR_IN_SECONDS
		);

		$draft = Printful_Import::draft( 73 );

		$this->assertNull( $draft['charts']['garment'], 'A size conversion is not a garment chart.' );
		$this->assertSame( 'Chest', $draft['charts']['body']['columns'][0]['label'] );
	}

	/** Rules and matches only target store-wide specs that are on. */
	public function test_facts_only_target_store_wide_specs(): void {
		$items = Product_Specs_Registry::get()['items'];
		foreach ( $items as &$item ) {
			if ( 'construction' === $item['key'] ) {
				$item['guides'] = array( self::factory()->post->create( array( 'post_type' => 'aa_size_guide' ) ) );
			}
			if ( 'fabric' === $item['key'] ) {
				$item['enabled'] = false;
			}
		}
		unset( $item );
		Product_Specs_Registry::save(
			array(
				'items'           => $items,
				'hide_dimensions' => false,
			)
		);
		update_option( Printful_Import::RULES, array( 'printful' => array( 'pre-shrunk fabric' => 'construction' ) ), false );

		$facts = array_column( Printful_Import::facts( "• Side-seamed\n• Pre-shrunk fabric\n• 100% cotton" ), 'target', 'text' );

		$this->assertSame( '', $facts['Side-seamed'], 'Not a spec of one guide.' );
		$this->assertSame( '', $facts['Pre-shrunk fabric'], 'A rule for a guide spec is ignored.' );
		$this->assertSame( '', $facts['100% cotton'], 'Not a disabled spec.' );
	}

	/** A line's key never ends in half a character. */
	public function test_fact_key_is_character_safe(): void {
		$text = str_repeat( 'é', 130 );
		$key  = Printful_Import::facts( '• ' . $text )[0]['key'];

		$this->assertSame( 120, mb_strlen( $key ) );
		$this->assertTrue( mb_check_encoding( $key, 'UTF-8' ) );
	}

	/** The diagram downloads in the background, not during the save. */
	public function test_diagram_downloads_in_the_background(): void {
		$guide = self::factory()->post->create( array( 'post_type' => 'aa_size_guide' ) );
		$url   = 'https://files.cdn.printful.com/upload/product-measure/71.png';
		Printful_Import::created(
			$guide,
			array(
				'importSource' => array(
					'provider' => 'printful',
					'id'       => 71,
				),
				'diagramUrl'   => $url,
			)
		);

		$this->assertSame( array(), $this->requests, 'Nothing fetched during the save.' );
		$scheduled = function_exists( 'as_has_scheduled_action' )
			? as_has_scheduled_action( Printful_Import::DIAGRAM_ACTION, array( $guide, $url ), 'aggressive-apparel' )
			: false !== wp_next_scheduled( Printful_Import::DIAGRAM_ACTION, array( $guide, $url ) );
		$this->assertTrue( $scheduled );
	}

	/** A spec re-keyed on save keeps its value and its import rule. */
	public function test_renamed_key_keeps_value_and_rule(): void {
		if ( false === has_action( 'aggressive_apparel_size_guide_created', array( Printful_Import::class, 'created' ) ) ) {
			( new Printful_Import() )->init();
		}
		$guide = ( new \Aggressive_Apparel\WooCommerce\Size_Guide_Rest() )->create(
			$this->json(
				array(
					'title'          => 'Tee',
					// "fabric" is already a store-wide spec, so this one is re-keyed.
					'storeSpecItems' => array(
						array(
							'key'  => 'fabric',
							'name' => 'Shell fabric',
							'type' => 'text',
						),
					),
					'specs'          => array( 'fabric' => 'Nylon' ),
					'importSource'   => array(
						'provider' => 'printful',
						'id'       => 71,
					),
					'importRules'    => array(
						array(
							'key'    => 'shell: nylon',
							'target' => 'fabric',
						),
					),
				)
			)
		)->get_data();

		$this->assertSame( 'Nylon', $guide['specs']['fabric_2'] ?? null );
		$this->assertArrayNotHasKey( 'fabric', array_filter( $guide['specs'] ) );
		$this->assertSame( 'fabric_2', get_option( Printful_Import::RULES )['printful']['shell: nylon'] );
	}

	/** Nothing that names the supplier reaches a product page. */
	public function test_supplier_never_reaches_the_storefront(): void {
		update_option( Printful_Import::RULES, array( 'printful' => array( 'made especially for you on demand' => 'construction' ) ), false );
		$facts = array_column( Printful_Import::facts( "• Made especially for you on demand\n• Printful quality guarantee\n• Side-seamed" ), null, 'text' );

		$this->assertSame( 'skipped', $facts['Made especially for you on demand']['status'], 'Even a saved rule never maps it.' );
		$this->assertSame( 'skipped', $facts['Printful quality guarantee']['status'] );
		$this->assertSame( 'construction', $facts['Side-seamed']['target'] );

		set_transient( 'aa_printful_product_74', array( 'product' => array( 'title' => 'Tee' ) ), HOUR_IN_SECONDS );
		set_transient(
			'aa_printful_sizes_74',
			array(
				'size_tables' => array(
					array(
						'type'         => 'product_measure',
						'unit'         => 'inches',
						'description'  => '<p>Lay the shirt flat. Printful measures every blank by hand. Measure across the chest.</p>',
						'measurements' => array(
							array(
								'type_label' => 'Width',
								'values'     => array(
									array(
										'size'  => 'S',
										'value' => '18',
									),
								),
							),
						),
					),
				),
			),
			HOUR_IN_SECONDS
		);

		$notes = Printful_Import::draft( 74 )['notes']['garment'];

		$this->assertStringNotContainsStringIgnoringCase( 'printful', $notes );
		$this->assertStringContainsString( 'Lay the shirt flat. Measure across the chest.', $notes );
	}

	/** Visitors can't list guides through the REST API; staff still can. */
	public function test_guides_are_not_in_the_public_rest_api(): void {
		global $wp_rest_server;
		$type       = new \Aggressive_Apparel\WooCommerce\Size_Guide_Post_Type();
		$registered = post_type_exists( 'aa_size_guide' );
		if ( ! $registered ) {
			$type->register();
		}
		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );
		$filter = array( $type, 'staff_only_rest' );
		add_filter( 'rest_pre_dispatch', $filter, 10, 3 );
		$guide = self::factory()->post->create(
			array(
				'post_type'  => 'aa_size_guide',
				'post_title' => 'Bella + Canvas 3001',
			)
		);

		$staff = rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/aa_size_guide' ) );
		wp_set_current_user( 0 );
		$list = rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/aa_size_guide' ) );
		$one  = rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/aa_size_guide/' . $guide ) );
		remove_filter( 'rest_pre_dispatch', $filter, 10 );
		$wp_rest_server = null;
		if ( ! $registered ) {
			unregister_post_type( 'aa_size_guide' );
		}

		$this->assertSame( 200, $staff->get_status() );
		$this->assertSame( 401, $list->get_status() );
		$this->assertSame( 401, $one->get_status() );
	}

	/**
	 * A JSON REST request.
	 *
	 * @param array<string, mixed> $body Body.
	 * @return \WP_REST_Request<array<string, mixed>>
	 */
	private function json( array $body ): \WP_REST_Request {
		$request = new \WP_REST_Request( 'POST' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $body ) );

		return $request;
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
