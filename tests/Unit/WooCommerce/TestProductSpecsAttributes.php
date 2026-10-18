<?php
/**
 * Specs on WooCommerce attributes: linking, resolution, writes, apply,
 * migration, and the merging registry save.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Product_Specs;
use Aggressive_Apparel\WooCommerce\Product_Specs_Migration;
use Aggressive_Apparel\WooCommerce\Product_Specs_Product_Writer;
use Aggressive_Apparel\WooCommerce\Product_Specs_Registry;
use Aggressive_Apparel\WooCommerce\Product_Specs_Settings;
use Aggressive_Apparel\WooCommerce\Product_Specs_Taxonomy;
use Aggressive_Apparel\WooCommerce\Product_Specs_Values;
use Aggressive_Apparel\WooCommerce\Size_Guide_Apply;
use Aggressive_Apparel\WooCommerce\Size_Guide_Spec_Items;
use WP_UnitTestCase;

/**
 * Covers specs stored as WooCommerce attributes and terms.
 */
class TestProductSpecsAttributes extends WP_UnitTestCase {

	/**
	 * Attribute taxonomies registered before each test.
	 *
	 * @var list<string>
	 */
	private array $baseline = array();

	/**
	 * Enable the feature, start from the default registry, and forget cached
	 * attribute lists (the DB rolls back between tests; caches do not).
	 */
	public function setUp(): void {
		parent::setUp();

		update_option( Feature_Settings::OPTION_KEY, array( 'size_guide' => true ), false );
		delete_option( Product_Specs_Registry::OPTION_KEY );
		delete_option( Product_Specs_Migration::OPTION );
		wp_cache_flush();
		delete_transient( 'wc_attribute_taxonomies' );
		Product_Specs_Registry::flush();
		Product_Specs_Taxonomy::flush();
		$this->baseline = array_values( get_taxonomies() );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Unregister attribute taxonomies a test created.
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

	/** A compatible attribute is reused; a fit scale never takes "Mens, Womens". */
	public function test_linking_reuses_compatible_attributes_only(): void {
		$this->attribute( 'Fit', 'fit', array( 'Mens', 'Womens' ) );
		$this->attribute( 'Fabric', 'fabric', array( 'Linen' ) );

		$this->assertSame( array(), Product_Specs_Registry::ensure_connected() );
		$items = $this->items();

		$this->assertSame( 'pa_fabric', $items['fabric']['taxonomy'] );
		$this->assertNotSame( 'pa_fit', $items['fit']['taxonomy'] );
		$this->assertStringStartsWith( 'pa_fit', $items['fit']['taxonomy'] );
		$this->assertCount( 5, $items['fit']['step_terms'] );
		$this->assertSame( $items['fit']['steps'], Product_Specs_Taxonomy::step_names( $items['fit'] ) );

		// Saving again keeps every link.
		$registry = Product_Specs_Registry::get();
		Product_Specs_Registry::save_connected(
			array(
				'items'           => $registry['items'],
				'hide_dimensions' => false,
			)
		);
		$this->assertSame( array_column( $items, 'taxonomy', 'key' ), array_column( $this->items(), 'taxonomy', 'key' ) );
	}

	/** A product's attribute is its own value; the guide, then the default, fill in. */
	public function test_resolution_reads_attributes_guide_and_default(): void {
		$this->save_defaults( array( 'fabric' => 'Cotton' ) );
		$guide   = $this->guide();
		$product = $this->product( $guide );
		Product_Specs_Values::save_guide( $guide, array( 'fit' => '3' ) );
		Product_Specs_Product_Writer::save( wc_get_product( $product ), array( 'weight' => '240' ) );

		$items = $this->resolved( $product );
		$this->assertSame( 3, $items['fit']['step'] );
		$this->assertSame( Product_Specs::SOURCE_GUIDE, $items['fit']['source'] );
		$this->assertSame( 240.0, $items['weight']['number'] );
		$this->assertSame( Product_Specs::SOURCE_PRODUCT, $items['weight']['source'] );
		$this->assertSame( 'Cotton', $items['fabric']['display'] );
		$this->assertSame( Product_Specs::SOURCE_DEFAULT, $items['fabric']['source'] );

		$weight = $this->items()['weight']['taxonomy'];
		$this->assertSame( array( '240 g/m²' ), wc_get_product_terms( $product, $weight, array( 'fields' => 'names' ) ), 'Stored as a core attribute term.' );

		Product_Specs_Values::save_hidden( $guide, array( 'fabric' ) );
		$this->assertArrayNotHasKey( 'fabric', $this->resolved( $product ), 'Hidden on the guide.' );
	}

	/** Writes keep other attributes and never touch variation attributes. */
	public function test_writer_keeps_other_attributes_and_variations(): void {
		Product_Specs_Registry::ensure_connected();
		$fit     = $this->items()['fit'];
		$product = wc_get_product( $this->product( 0 ) );

		$color = new \WC_Product_Attribute();
		$color->set_name( 'Color' );
		$color->set_options( array( 'Red' ) );
		$sizes = new \WC_Product_Attribute();
		$sizes->set_id( wc_attribute_taxonomy_id_by_name( $fit['taxonomy'] ) );
		$sizes->set_name( $fit['taxonomy'] );
		$sizes->set_options( array( $fit['step_terms'][0] ) );
		$sizes->set_variation( true );
		$product->set_attributes( array( $color, $sizes ) );
		$product->save();

		$result     = Product_Specs_Product_Writer::save( wc_get_product( $product->get_id() ), array( 'fit' => '4', 'fabric' => 'Linen' ) );
		$attributes = wc_get_product( $product->get_id() )->get_attributes();

		$this->assertSame( array( 'fit' ), $result['skipped'] );
		$this->assertSame( array( $fit['step_terms'][0] ), $attributes[ $fit['taxonomy'] ]->get_options(), 'Variation attribute untouched.' );
		$this->assertArrayHasKey( 'color', $attributes );
		$this->assertArrayHasKey( $this->items()['fabric']['taxonomy'], $attributes );
	}

	/** Apply fills only products without their own value, only on that guide. */
	public function test_apply_fills_only_missing_values(): void {
		$guide = $this->guide();
		$other = $this->guide();
		$plain = $this->product( $guide );
		$own   = $this->product( $guide );
		$else  = $this->product( $other );
		Product_Specs_Values::save_guide( $guide, array( 'fabric' => 'Cotton' ) );
		Product_Specs_Product_Writer::save( wc_get_product( $own ), array( 'fabric' => 'Linen' ) );

		$result = Size_Guide_Apply::page( $guide, 1 );
		$fabric = $this->items()['fabric']['taxonomy'];

		$this->assertSame( 1, $result['products'] );
		$this->assertSame( array( 'Cotton' ), wc_get_product_terms( $plain, $fabric, array( 'fields' => 'names' ) ) );
		$this->assertSame( array( 'Linen' ), wc_get_product_terms( $own, $fabric, array( 'fields' => 'names' ) ) );
		$this->assertSame( array(), wc_get_product_terms( $else, $fabric, array( 'fields' => 'names' ) ) );
	}

	/** Old meta moves onto attributes and guide terms, never overwriting. */
	public function test_migration_moves_old_values(): void {
		$this->save_defaults( array() );
		$this->attribute( 'Fabric', 'fabric', array( 'Linen' ) );
		$guide = $this->guide();
		$plain = $this->product( 0 );
		$own   = $this->product( 0 );
		update_post_meta( $plain, '_aa_spec_fabric', 'Silk' );
		update_post_meta( $plain, '_aa_spec_fit', '2' );
		update_post_meta( $own, '_aa_spec_fabric', 'Silk' );
		update_post_meta( $guide, '_aa_spec_fit', '4' );
		$this->set_term( $own, 'pa_fabric', 'Linen' );

		$preview = Product_Specs_Migration::run( true, true );
		$this->assertSame( 3, $preview['posts'] );
		$this->assertSame( 'Silk', get_post_meta( $plain, '_aa_spec_fabric', true ), 'A dry run changes nothing.' );

		$this->assertSame( array(), Product_Specs_Migration::run( false, true )['errors'] );
		$fit = $this->items()['fit'];

		$this->assertSame( array( 'Silk' ), wc_get_product_terms( $plain, 'pa_fabric', array( 'fields' => 'names' ) ) );
		$this->assertSame( array( $fit['step_terms'][2] ), wc_get_product( $plain )->get_attributes()[ $fit['taxonomy'] ]->get_options() );
		$this->assertSame( array( 'Linen' ), wc_get_product_terms( $own, 'pa_fabric', array( 'fields' => 'names' ) ), 'Existing attribute kept.' );
		$this->assertSame( '4', Product_Specs_Values::guide( $guide )['values']['fit'] );
		$this->assertSame( '', get_post_meta( $plain, '_aa_spec_fabric', true ) );
		$this->assertSame( Product_Specs_Migration::VERSION, (int) get_option( Product_Specs_Migration::OPTION ) );
	}

	/** A Specs-tab save made from an older revision keeps changes saved since. */
	public function test_registry_save_merges_changes_made_since(): void {
		$this->save_defaults( array() );
		$loaded = Product_Specs_Registry::get();
		Size_Guide_Spec_Items::save(
			$this->guide(),
			array(
				array(
					'name' => 'Closure',
					'type' => 'text',
				),
			)
		);

		$items            = $loaded['items'];
		$items[2]['name'] = 'Material';
		$request          = new \WP_REST_Request( 'POST' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body(
			(string) wp_json_encode(
				array(
					'items'    => $items,
					'revision' => $loaded['revision'],
					'changed'  => array( 'fabric' ),
				)
			)
		);
		( new Product_Specs_Settings() )->save_registry( $request );
		$saved = $this->items();

		$this->assertSame( 'Material', $saved['fabric']['name'] );
		$this->assertArrayHasKey( 'closure', $saved, 'The guide’s spec survives.' );
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
	 * Resolved items for a product, by key.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, array<string, mixed>>
	 */
	private function resolved( int $product_id ): array {
		return array_column( Product_Specs::for_product( $product_id )['items'], null, 'key' );
	}

	/**
	 * Save the default items with these default values (connected).
	 *
	 * @param array<string, string> $defaults Item key => default.
	 */
	private function save_defaults( array $defaults ): void {
		$items = Product_Specs_Registry::defaults();
		foreach ( $items as $i => $item ) {
			$items[ $i ]['default'] = $defaults[ $item['key'] ] ?? '';
		}
		Product_Specs_Registry::save(
			array(
				'items'           => Product_Specs_Registry::validate( array( 'items' => $items ) )['items'],
				'hide_dimensions' => false,
			)
		);
		if ( array() !== $defaults ) {
			$this->assertSame( array(), Product_Specs_Registry::ensure_connected() );
		}
	}

	/**
	 * A global attribute with terms, registered for this request.
	 *
	 * @param string       $label Label.
	 * @param string       $slug  Slug.
	 * @param list<string> $terms Term names.
	 */
	private function attribute( string $label, string $slug, array $terms ): void {
		wc_create_attribute(
			array(
				'name' => $label,
				'slug' => $slug,
			)
		);
		register_taxonomy( 'pa_' . $slug, array( 'product' ) );
		foreach ( $terms as $term ) {
			wp_insert_term( $term, 'pa_' . $slug );
		}
		Product_Specs_Taxonomy::flush();
	}

	/**
	 * Give a product one term of a global attribute.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $taxonomy   Attribute taxonomy.
	 * @param string $name       Term name.
	 */
	private function set_term( int $product_id, string $taxonomy, string $name ): void {
		$term      = get_term_by( 'name', $name, $taxonomy );
		$attribute = new \WC_Product_Attribute();
		$attribute->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
		$attribute->set_name( $taxonomy );
		$attribute->set_options( array( $term->term_id ) );
		$product = wc_get_product( $product_id );
		$product->set_attributes( array( $attribute ) );
		$product->save();
	}

	/**
	 * A published size guide with content, so products resolve to it.
	 */
	private function guide(): int {
		return self::factory()->post->create(
			array(
				'post_type'    => 'aa_size_guide',
				'post_status'  => 'publish',
				'post_title'   => 'Heavy Tee',
				'post_content' => '<p>Guide</p>',
			)
		);
	}

	/**
	 * A published product, assigned to a guide when one is given.
	 *
	 * @param int $guide Size guide ID, or 0.
	 */
	private function product( int $guide ): int {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Attribute spec product' );
		$product->set_status( 'publish' );
		$id = $product->save();
		if ( $guide > 0 ) {
			update_post_meta( $id, '_aggressive_apparel_size_guide_id', $guide );
		}

		return $id;
	}
}
