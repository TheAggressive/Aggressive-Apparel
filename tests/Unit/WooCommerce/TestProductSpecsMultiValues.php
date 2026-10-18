<?php
/**
 * Text specs with several values, and values linked to archive pages.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Product_Specs;
use Aggressive_Apparel\WooCommerce\Product_Specs_Product_Writer;
use Aggressive_Apparel\WooCommerce\Product_Specs_Registry;
use Aggressive_Apparel\WooCommerce\Product_Specs_Settings;
use Aggressive_Apparel\WooCommerce\Product_Specs_Taxonomy;
use Aggressive_Apparel\WooCommerce\Product_Specs_Values;
use WP_UnitTestCase;

/**
 * Covers multi-value text specs and archive links.
 */
class TestProductSpecsMultiValues extends WP_UnitTestCase {

	/**
	 * Taxonomies registered before each test.
	 *
	 * @var list<string>
	 */
	private array $baseline = array();

	/**
	 * Start from a registry with a multi-value Collection spec on an
	 * existing attribute with archives.
	 */
	public function setUp(): void {
		parent::setUp();

		update_option( Feature_Settings::OPTION_KEY, array( 'size_guide' => true ), false );
		delete_option( Product_Specs_Registry::OPTION_KEY );
		wp_cache_flush();
		delete_transient( 'wc_attribute_taxonomies' );
		Product_Specs_Registry::flush();
		Product_Specs_Taxonomy::flush();
		$this->baseline = array_values( get_taxonomies() );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		wc_create_attribute(
			array(
				'name'         => 'Collection',
				'slug'         => 'collection',
				'has_archives' => true,
			)
		);
		register_taxonomy( 'pa_collection', array( 'product' ), array( 'public' => true ) );
		foreach ( array( 'Mens', 'Womens', 'Unisex' ) as $name ) {
			wp_insert_term( $name, 'pa_collection' );
		}
		Product_Specs_Taxonomy::flush();
	}

	/**
	 * Unregister taxonomies a test created.
	 */
	public function tearDown(): void {
		foreach ( array_diff( array_values( get_taxonomies() ), $this->baseline ) as $taxonomy ) {
			unregister_taxonomy( $taxonomy );
		}
		delete_option( Feature_Settings::OPTION_KEY );
		delete_option( Product_Specs_Registry::OPTION_KEY );
		Product_Specs_Registry::flush();
		Product_Specs_Taxonomy::flush();

		parent::tearDown();
	}

	/** Several values become several terms and display as a linked list. */
	public function test_product_holds_several_values(): void {
		$this->save_collection_spec( false );
		$product = $this->product();

		Product_Specs_Product_Writer::save( wc_get_product( $product ), array( 'collection' => 'Mens | Womens' ) );

		$this->assertEqualsCanonicalizing( array( 'Mens', 'Womens' ), wc_get_product_terms( $product, 'pa_collection', array( 'fields' => 'names' ) ) );
		$item = $this->resolved( $product );
		$this->assertSame( 'Mens, Womens', $item['display'] );
		$this->assertCount( 2, $item['links'] );
		$this->assertNotSame( '', $item['links'][0]['url'], 'Archives on, so each value links.' );
		$this->assertStringContainsString( '<a href=', Product_Specs::value_html( $item ) );
	}

	/** A guide's several default values reach its products. */
	public function test_guide_holds_several_values(): void {
		$this->save_collection_spec( false );
		$guide = self::factory()->post->create(
			array(
				'post_type'    => 'aa_size_guide',
				'post_status'  => 'publish',
				'post_content' => '<p>Guide</p>',
			)
		);
		Product_Specs_Values::save_guide( $guide, array( 'collection' => 'Unisex | Mens' ) );
		$product = $this->product();
		update_post_meta( $product, '_aggressive_apparel_size_guide_id', $guide );

		$this->assertSame( 'Unisex | Mens', Product_Specs_Values::guide( $guide )['values']['collection'] );
		$item = $this->resolved( $product );
		$this->assertSame( Product_Specs::SOURCE_GUIDE, $item['source'] );
		$this->assertSame( 'Unisex, Mens', $item['display'] );
	}

	/** Linking a spec never turns an attribute's archives off; toggling does. */
	public function test_archives_follow_woocommerce_until_toggled(): void {
		$this->save_collection_spec( false );
		$this->assertTrue( Product_Specs_Taxonomy::has_archives( 'pa_collection' ), 'Linking kept archives on.' );
		$this->assertTrue( $this->items()['collection']['archives'] );

		$registry                       = Product_Specs_Registry::get();
		$registry['items'][0]['archives'] = false;
		Product_Specs_Registry::save_connected( $registry );
		Product_Specs_Taxonomy::flush();

		$this->assertFalse( Product_Specs_Taxonomy::has_archives( 'pa_collection' ), 'Toggled off in the editor.' );
		$this->assertSame( array(), array_filter( array_column( $this->resolved( $this->product_with( 'Mens' ) )['links'], 'url' ) ), 'No links without archives.' );
	}

	/** Without term management, known values save and the unknown one fails. */
	public function test_several_values_report_the_ones_that_failed(): void {
		$this->save_collection_spec( false );
		$product = $this->product();

		$result = Product_Specs_Product_Writer::save( wc_get_product( $product ), array( 'collection' => 'Mens | Kids' ), false, false );

		$this->assertSame( array( 'collection' ), $result['failed'] );
		$this->assertSame( array( 'Mens' ), wc_get_product_terms( $product, 'pa_collection', array( 'fields' => 'names' ) ) );
		$this->assertFalse( get_term_by( 'name', 'Kids', 'pa_collection' ) );
	}

	/** The attribute route describes an attribute for linking in the editor. */
	public function test_attribute_route(): void {
		$request = new \WP_REST_Request( 'GET' );
		$request->set_param( 'taxonomy', 'pa_collection' );
		$data = ( new Product_Specs_Settings() )->get_attribute( $request )->get_data();

		$this->assertSame( 'Collection', $data['label'] );
		$this->assertTrue( $data['archives'] );
		$this->assertEqualsCanonicalizing( array( 'Mens', 'Womens', 'Unisex' ), array_column( $data['terms'], 'name' ) );

		$request->set_param( 'taxonomy', 'category' );
		$this->assertWPError( ( new Product_Specs_Settings() )->get_attribute( $request ) );
	}

	/**
	 * Save a registry with one multi-value Collection spec and connect it.
	 *
	 * @param bool $archives Archives the spec asks for.
	 */
	private function save_collection_spec( bool $archives ): void {
		$result = Product_Specs_Registry::validate(
			array(
				'items' => array(
					array(
						'key'      => 'collection',
						'name'     => 'Collection',
						'type'     => 'text',
						'multiple' => true,
						'archives' => $archives,
						'in_table' => true,
					),
				),
			)
		);
		$this->assertSame( array(), Product_Specs_Registry::save_connected( $result ) );
		$this->assertSame( 'pa_collection', $this->items()['collection']['taxonomy'] );
	}

	/**
	 * Registry items by key.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function items(): array {
		return array_column( Product_Specs_Registry::get()['items'], null, 'key' );
	}

	/**
	 * The resolved Collection spec for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>
	 */
	private function resolved( int $product_id ): array {
		return array_column( Product_Specs::for_product( $product_id )['items'], null, 'key' )['collection'];
	}

	/**
	 * A published product.
	 */
	private function product(): int {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Multi value product' );
		$product->set_status( 'publish' );

		return $product->save();
	}

	/**
	 * A product with one Collection value.
	 *
	 * @param string $name Value.
	 */
	private function product_with( string $name ): int {
		$id = $this->product();
		Product_Specs_Product_Writer::save( wc_get_product( $id ), array( 'collection' => $name ) );

		return $id;
	}
}
