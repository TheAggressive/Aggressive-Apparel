<?php
/**
 * Specs added from a size guide: site-wide, opt-in by value, never editing others.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Product_Specs;
use Aggressive_Apparel\WooCommerce\Product_Specs_Registry;
use Aggressive_Apparel\WooCommerce\Size_Guide_Rest;
use Aggressive_Apparel\WooCommerce\Size_Guide_Spec_Items;
use Aggressive_Apparel\Tests\Support\Restores_Taxonomies;
use WP_UnitTestCase;

/**
 * Covers specs added while editing or importing a size guide.
 */
class TestSizeGuideSpecItems extends WP_UnitTestCase {

	use Restores_Taxonomies;

	/**
	 * Enable the feature and start from the default registry.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->snapshot_taxonomies();
		update_option( Feature_Settings::OPTION_KEY, array( 'size_guide' => true ), false );
		delete_option( Product_Specs_Registry::OPTION_KEY );
		Product_Specs_Registry::flush();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Restore global state.
	 */
	public function tearDown(): void {
		delete_option( Feature_Settings::OPTION_KEY );
		delete_option( Product_Specs_Registry::OPTION_KEY );
		Product_Specs_Registry::flush();
		$this->restore_taxonomies();

		parent::tearDown();
	}

	/** A spec added from a guide is site-wide, and shows where it has a value. */
	public function test_added_spec_shows_where_it_has_a_value(): void {
		$hat_guide = $this->guide( 'Dad Hat' );
		$tee_guide = $this->guide( 'Jersey Tee' );
		$this->assertSame( array(), Size_Guide_Spec_Items::add( array( $this->text_item( 'Closure' ) ) ) );
		\Aggressive_Apparel\WooCommerce\Product_Specs_Values::save_guide( $hat_guide, array( 'closure' => 'Brass buckle' ) );

		$hat = $this->product( $hat_guide );
		$tee = $this->product( $tee_guide );

		$items = array_column( Product_Specs::for_product( $hat )['items'], 'display', 'key' );
		$this->assertSame( 'Brass buckle', $items['closure'] );
		$this->assertArrayHasKey( 'fit', $items, 'The store’s other specs still apply.' );
		$tee_items = array_column( Product_Specs::for_product( $tee )['items'], 'display', 'key' );
		$this->assertSame( '', $tee_items['closure'], 'Another guide uses it only by giving it a value.' );
		$this->assertArrayNotHasKey( 'guides', Product_Specs_Registry::get()['items'][0] );
	}

	/** Adding never changes a spec already there: a taken key gets a fresh one. */
	public function test_add_never_touches_existing_specs(): void {
		Size_Guide_Spec_Items::add( array( $this->text_item( 'Neck tape' ) ) );

		$fit        = $this->text_item( 'Fit' );
		$fit['key'] = 'fit';
		$renamed    = array();
		$errors     = Size_Guide_Spec_Items::add( array( $fit, $this->text_item( 'Neck tape' ) ), $renamed );
		$items      = array_column( Product_Specs_Registry::get()['items'], null, 'key' );

		$this->assertSame( array(), $errors );
		$this->assertSame( 'pick', $items['fit']['type'], 'The store’s Fit is untouched.' );
		$this->assertSame( 'text', $items['fit_2']['type'] );
		$this->assertArrayHasKey( 'neck_tape_2', $items );
		$this->assertSame( array( 'fit' => 'fit_2' ), $renamed );
	}

	/** Specs that once belonged to particular guides read as site-wide. */
	public function test_guide_only_specs_from_before_are_site_wide(): void {
		$hat_guide = $this->guide( 'Dad Hat' );
		$tee_guide = $this->guide( 'Jersey Tee' );
		Size_Guide_Spec_Items::add( array( $this->text_item( 'Closure' ) ) );
		$stored = get_option( Product_Specs_Registry::OPTION_KEY );
		foreach ( $stored['items'] as &$item ) {
			if ( 'closure' === $item['key'] ) {
				$item['guides'] = array( $hat_guide );
			}
		}
		unset( $item );
		update_option( Product_Specs_Registry::OPTION_KEY, $stored );
		Product_Specs_Registry::flush();
		\Aggressive_Apparel\WooCommerce\Product_Specs_Values::save_guide( $tee_guide, array( 'closure' => 'Snap' ) );

		$items = array_column( Product_Specs::for_product( $this->product( $tee_guide ) )['items'], 'display', 'key' );
		$this->assertSame( 'Snap', $items['closure'] );
	}

	/** An invalid spec saves nothing, and the REST update reports why. */
	public function test_invalid_items_save_nothing(): void {
		$guide = $this->guide( 'Dad Hat' );
		$bad   = array(
			'name'  => 'Stretch',
			'type'  => 'pick',
			'steps' => array( 'Only one' ),
		);

		$this->assertNotSame( array(), Size_Guide_Spec_Items::add( array( $bad ) ) );
		$this->assertNotContains( 'stretch', array_column( Product_Specs_Registry::get()['items'], 'key' ) );

		$response = ( new Size_Guide_Rest() )->update(
			$this->request(
				$guide,
				array(
					'title'     => 'Renamed',
					'specItems' => array( $bad ),
				)
			)
		);
		$this->assertWPError( $response );
		$this->assertSame( 'Dad Hat', get_the_title( $guide ), 'Nothing else is written either.' );
	}

	/** A new guide's specs and values save in one request, under the keys saved. */
	public function test_create_with_spec_items(): void {
		$created = ( new Size_Guide_Rest() )->create(
			$this->request(
				0,
				array(
					'title'     => 'Bucket Hat',
					'specItems' => array(
						$this->text_item( 'Brim' ),
						array(
							'key'  => 'fabric',
							'name' => 'Shell fabric',
							'type' => 'text',
						),
					),
					'specs'     => array(
						'brim'   => '6 cm',
						'fabric' => 'Nylon',
					),
				)
			)
		)->get_data();

		$keys = array_column( $created['registry']['items'], 'name', 'key' );
		$this->assertSame( 'Brim', $keys['brim'] );
		$this->assertSame( 'Shell fabric', $keys['fabric_2'], 'A taken key gets a fresh one.' );
		$this->assertSame( '6 cm', $created['specs']['brim'] );
		$this->assertSame( 'Nylon', $created['specs']['fabric_2'], 'Its value follows it.' );
	}

	/** Duplicating a guide copies its values, with no specs to share. */
	public function test_duplicate_copies_values(): void {
		$guide = $this->guide( 'Dad Hat' );
		Size_Guide_Spec_Items::add( array( $this->text_item( 'Closure' ) ) );
		\Aggressive_Apparel\WooCommerce\Product_Specs_Values::save_guide( $guide, array( 'closure' => 'Brass buckle' ) );
		$before = Product_Specs_Registry::get()['items'];

		$copy = ( new Size_Guide_Rest() )->duplicate( $this->request( $guide ) )->get_data();

		$this->assertSame( 'Brass buckle', $copy['specs']['closure'] );
		$this->assertSame( $before, Product_Specs_Registry::get()['items'], 'The specs are left as they were.' );
	}

	/**
	 * A text spec item.
	 *
	 * @param string $name Name.
	 * @return array<string, mixed>
	 */
	private function text_item( string $name ): array {
		return array(
			'name'     => $name,
			'type'     => 'text',
			'in_table' => true,
		);
	}

	/**
	 * A published size guide with content, so products resolve to it.
	 *
	 * @param string $title Title.
	 */
	private function guide( string $title ): int {
		return self::factory()->post->create(
			array(
				'post_type'    => 'aa_size_guide',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => '<p>Guide</p>',
			)
		);
	}

	/**
	 * A product assigned to a guide.
	 *
	 * @param int $guide Size guide ID.
	 */
	private function product( int $guide ): int {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Spec item product' );
		$product->set_status( 'publish' );
		$id = $product->save();
		update_post_meta( $id, '_aggressive_apparel_size_guide_id', $guide );

		return $id;
	}

	/**
	 * A JSON REST request for a guide.
	 *
	 * @param int                  $id   Guide ID (0 for a collection route).
	 * @param array<string, mixed> $body Body.
	 * @return \WP_REST_Request<array<string, mixed>>
	 */
	private function request( int $id, array $body = array() ): \WP_REST_Request {
		$request = new \WP_REST_Request( 'POST' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $body ) );
		if ( $id > 0 ) {
			$request->set_param( 'id', $id );
		}

		return $request;
	}
}
