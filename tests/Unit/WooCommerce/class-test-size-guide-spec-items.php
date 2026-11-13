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
use Aggressive_Apparel\WooCommerce\Product_Specs_Values;
use Aggressive_Apparel\WooCommerce\Size_Guide_Rest;
use Aggressive_Apparel\WooCommerce\Size_Guide_Spec_Items;
use Aggressive_Apparel\Tests\Support\Restores_Taxonomies;
use Aggressive_Apparel\Tests\Support\Saves_Spec_Registry;
use WP_UnitTestCase;

/**
 * Covers specs added while editing or importing a size guide.
 */
class Test_Size_Guide_Spec_Items extends WP_UnitTestCase {

	use Restores_Taxonomies;
	use Saves_Spec_Registry;

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
		Product_Specs_Values::save_guide( $hat_guide, array( 'closure' => 'Brass buckle' ) );

		$hat = $this->product( $hat_guide );
		$tee = $this->product( $tee_guide );

		$items = array_column( Product_Specs::for_product( $hat )['items'], 'display', 'key' );
		$this->assertSame( 'Brass buckle', $items['closure'] );
		$this->assertArrayHasKey( 'fit', $items, 'The store’s other specs still apply.' );
		$tee_items = array_column( Product_Specs::for_product( $tee )['items'], 'display', 'key' );
		$this->assertSame( '', $tee_items['closure'], 'Another guide uses it only by giving it a value.' );
	}

	/** Adding never changes a spec already there: a different spec on a taken key gets a fresh one. */
	public function test_add_never_touches_existing_specs(): void {
		$fit        = $this->text_item( 'Fit' );
		$fit['key'] = 'fit';
		$renamed    = array();
		$errors     = Size_Guide_Spec_Items::add( array( $fit ), $renamed );
		$items      = array_column( Product_Specs_Registry::get()['items'], null, 'key' );

		$this->assertSame( array(), $errors );
		$this->assertSame( 'pick', $items['fit']['type'], 'The store’s Fit scale is untouched.' );
		$this->assertSame( 'text', $items['fit_2']['type'] );
		$this->assertSame( array( 'fit' => 'fit_2' ), $renamed );
	}

	/** The same spec sent again (a save retried after a failure) is reused, never duplicated. */
	public function test_retried_save_reuses_the_spec(): void {
		$guide = $this->guide( 'Jersey Tee' );
		$body  = array(
			'title'     => 'Jersey Tee',
			'specItems' => array( array( 'key' => 'neck_tape' ) + $this->text_item( 'Neck tape' ) ),
			'specs'     => array( 'neck_tape' => 'Twill' ),
		);
		$rest  = new Size_Guide_Rest();
		$rest->update( $this->request( $guide, $body ) );
		$saved = $rest->update( $this->request( $guide, $body ) )->get_data();

		$keys = array_column( Product_Specs_Registry::get()['items'], 'key' );
		$this->assertContains( 'neck_tape', $keys );
		$this->assertNotContains( 'neck_tape_2', $keys );
		$this->assertSame( 'Twill', $saved['specs']['neck_tape'] );
	}

	/** A spec saved under another key keeps this guide's switch and wording for it. */
	public function test_renamed_key_keeps_hidden_and_wording(): void {
		$created = ( new Size_Guide_Rest() )->create(
			$this->request(
				0,
				array(
					'title'     => 'Bucket Hat',
					'specItems' => array(
						array(
							'key'  => 'fabric',
							'name' => 'Shell fabric',
							'type' => 'text',
						),
					),
					'hidden'    => array( 'fabric' ),
					'wording'   => array( 'fabric' => array( 'title' => 'Shell' ) ),
				)
			)
		)->get_data();

		$this->assertSame( array( 'fabric_2' ), $created['hidden'] );
		$this->assertSame( array( 'fabric_2' => array( 'title' => 'Shell' ) ), $created['wording'] );
	}

	/** A guide-only spec's default moves to its guides, once, so other products never show it. */
	public function test_guide_only_specs_upgrade_once(): void {
		$hat_guide = $this->guide( 'Dad Hat' );
		$tee_guide = $this->guide( 'Jersey Tee' );
		Size_Guide_Spec_Items::add( array( array( 'default' => 'Snapback' ) + $this->text_item( 'Closure' ) ) );
		$stored = get_option( Product_Specs_Registry::OPTION_KEY );
		foreach ( $stored['items'] as $i => $item ) {
			$stored['items'][ $i ]['guides'] = 'closure' === $item['key'] ? array( $hat_guide ) : array();
		}
		update_option( Product_Specs_Registry::OPTION_KEY, $stored );
		Product_Specs_Registry::flush();

		$items = array_column( Product_Specs_Registry::get()['items'], null, 'key' );
		$this->assertSame( '', $items['closure']['default'], 'The spec keeps no default of its own.' );
		$this->assertSame( 'Snapback', Product_Specs_Values::guide( $hat_guide )['values']['closure'] );
		$this->assertSame( 'Snapback', $this->resolved( $this->product( $hat_guide ) )['closure']['display'] );
		$this->assertSame( '', $this->resolved( $this->product( $tee_guide ) )['closure']['display'] );

		$upgraded = get_option( Product_Specs_Registry::OPTION_KEY );
		$this->assertArrayNotHasKey( 'guides', $upgraded['items'][0], 'Done once: the lists are gone.' );
		$this->assertSame( $stored['revision'] + 1, $upgraded['revision'], 'Open editors see a newer list.' );
	}

	/** Wording alone makes a guide count; entries for specs that don't exist never do. */
	public function test_what_makes_a_guide_count(): void {
		$guide = $this->guide( 'Dad Hat' );
		$this->assertFalse( Product_Specs_Values::guide_has_specs( $guide ) );

		update_post_meta( $guide, Product_Specs_Values::GUIDE_HIDDEN, array( 'gone' ) );
		update_post_meta( $guide, Product_Specs_Values::GUIDE_WORDING, array( 'gone' => array( 'title' => 'Old' ) ) );
		$this->assertFalse( Product_Specs_Values::guide_has_specs( $guide ), 'Left behind by a spec that no longer exists.' );

		Product_Specs_Values::save_wording( $guide, array( 'fit' => array( 'title' => 'Tee fit' ) ) );
		$this->assertTrue( Product_Specs_Values::guide_has_specs( $guide ) );
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
		Product_Specs_Values::save_guide( $guide, array( 'closure' => 'Brass buckle' ) );
		$before = Product_Specs_Registry::get()['items'];

		$copy = ( new Size_Guide_Rest() )->duplicate( $this->request( $guide ) )->get_data();

		$this->assertSame( 'Brass buckle', $copy['specs']['closure'] );
		$this->assertSame( $before, Product_Specs_Registry::get()['items'], 'The specs are left as they were.' );
	}

	/** Wording set on a guide shows on its products only; a product's own wins. */
	public function test_guide_wording_shows_on_its_products(): void {
		$tee_guide = $this->guide( 'Jersey Tee' );
		$hat_guide = $this->guide( 'Dad Hat' );
		$response  = ( new Size_Guide_Rest() )->update(
			$this->request(
				$tee_guide,
				array(
					'specs'   => array( 'fit' => '1' ),
					'wording' => array(
						'fit' => array(
							'title' => 'Tee fit',
							'step1' => 'Trim',
						),
					),
				)
			)
		);
		$this->assertSame(
			array(
				'fit' => array(
					'title' => 'Tee fit',
					'step1' => 'Trim',
				),
			),
			$response->get_data()['wording'] 
		);

		$tee = $this->product( $tee_guide );
		$fit = array_column( Product_Specs::for_product( $tee )['items'], null, 'key' )['fit'];
		$this->assertSame( 'Tee fit', $fit['title'] );
		$this->assertSame( 'Trim', $fit['steps'][1] );

		$hat = array_column( Product_Specs::for_product( $this->product( $hat_guide ) )['items'], null, 'key' )['fit'];
		$this->assertSame( 'Fit', $hat['title'], 'Another guide keeps the spec’s own wording.' );

		update_post_meta( $tee, \Aggressive_Apparel\WooCommerce\Product_Specs_Meta::WORDING, array( 'fit' => array( 'title' => 'This tee’s fit' ) ) );
		Product_Specs::flush();
		$fit = array_column( Product_Specs::for_product( $tee )['items'], null, 'key' )['fit'];
		$this->assertSame( 'This tee’s fit', $fit['title'], 'A product’s own wording wins.' );
		$this->assertSame( 'Trim', $fit['steps'][1], 'Fields it leaves out still come from the guide.' );
	}

	/** Clearing a guide's wording goes back to the spec's; a copy keeps it. */
	public function test_guide_wording_clears_and_copies(): void {
		$guide = $this->guide( 'Jersey Tee' );
		$rest  = new Size_Guide_Rest();
		$rest->update( $this->request( $guide, array( 'wording' => array( 'fit' => array( 'title' => 'Tee fit' ) ) ) ) );

		$copy = $rest->duplicate( $this->request( $guide ) )->get_data();
		$this->assertSame( array( 'fit' => array( 'title' => 'Tee fit' ) ), $copy['wording'] );

		$rest->update( $this->request( $guide, array( 'wording' => array( 'fit' => array( 'title' => '' ) ) ) ) );
		$this->assertSame( '', get_post_meta( $guide, Product_Specs_Values::GUIDE_WORDING, true ) );
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
