<?php
/**
 * Guide-owned spec items: ownership, merging, sharing, and resolution.
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
use WP_UnitTestCase;

/**
 * Covers specs created inside a size guide.
 */
class TestSizeGuideSpecItems extends WP_UnitTestCase {

	/**
	 * Attribute taxonomies registered before each test.
	 *
	 * @var list<string>
	 */
	private array $baseline = array();

	/**
	 * Enable the feature and start from the default registry.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->baseline = array_values( get_taxonomies() );
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
		// Spec attribute taxonomies (e.g. `pa_fit`) outlive the rollback and
		// block later tests from creating that attribute.
		foreach ( array_diff( array_values( get_taxonomies() ), $this->baseline ) as $taxonomy ) {
			unregister_taxonomy( $taxonomy );
		}

		parent::tearDown();
	}

	/** A guide's own spec shows only on products using that guide. */
	public function test_owned_spec_shows_only_with_its_guide(): void {
		$guide = $this->guide( 'Dad Hat' );
		$other = $this->guide( 'Jersey Tee' );
		$this->assertSame( array(), Size_Guide_Spec_Items::save( $guide, array( $this->text_item( 'Closure' ) ) ) );
		update_post_meta( $guide, '_aa_spec_closure', 'Brass buckle' );

		$hat = $this->product( $guide );
		$tee = $this->product( $other );

		$keys = array_column( Product_Specs::for_product( $hat )['items'], 'display', 'key' );
		$this->assertSame( 'Brass buckle', $keys['closure'] );
		$this->assertArrayHasKey( 'fit', $keys, 'Store-wide specs still apply.' );
		$this->assertNotContains( 'closure', array_column( Product_Specs::for_product( $tee )['items'], 'key' ) );
	}

	/** Saving a guide never changes store-wide items or another guide's. */
	public function test_save_only_touches_owned_items(): void {
		$guide = $this->guide( 'Dad Hat' );
		$other = $this->guide( 'Jersey Tee' );
		Size_Guide_Spec_Items::save( $other, array( $this->text_item( 'Neck tape' ) ) );

		// Same keys as a store-wide item and another guide's item.
		$fit             = $this->text_item( 'Fit' );
		$fit['key']      = 'fit';
		$tape            = $this->text_item( 'Neck tape' );
		$tape['guides']  = array();
		$errors          = Size_Guide_Spec_Items::save( $guide, array( $fit, $tape ) );
		$items           = array_column( Product_Specs_Registry::get()['items'], null, 'key' );

		$this->assertSame( array(), $errors );
		$this->assertSame( 'pick', $items['fit']['type'], 'The store-wide Fit is untouched.' );
		$this->assertSame( array(), $items['fit']['guides'] );
		$this->assertSame( array( $other ), $items['neck_tape']['guides'] );
		$this->assertSame( array( $guide ), $items['fit_2']['guides'], 'A taken key gets a fresh one.' );
		$this->assertSame( array( $guide ), $items['neck_tape_2']['guides'], 'Submitted guides are ignored.' );
	}

	/** Left-out items leave the guide; shared ones survive on the other guide. */
	public function test_removing_and_sharing(): void {
		$guide = $this->guide( 'Dad Hat' );
		Size_Guide_Spec_Items::save( $guide, array( $this->text_item( 'Closure' ), $this->text_item( 'Crown' ) ) );

		$copy = ( new Size_Guide_Rest() )->duplicate( $this->request( $guide ) )->get_data();
		$copy = (int) $copy['id'];
		$keys = array_column( Product_Specs_Registry::get()['items'], 'guides', 'key' );
		$this->assertSame( array( $guide, $copy ), $keys['closure'], 'A duplicate shares the guide’s specs.' );

		Size_Guide_Spec_Items::save( $guide, array( $this->text_item( 'Crown' ) ) );
		$keys = array_column( Product_Specs_Registry::get()['items'], 'guides', 'key' );
		$this->assertSame( array( $copy ), $keys['closure'], 'Still on the copy.' );

		Size_Guide_Spec_Items::save( $copy, array( $this->text_item( 'Crown' ) ) );
		$keys = array_column( Product_Specs_Registry::get()['items'], 'guides', 'key' );
		$this->assertArrayNotHasKey( 'closure', $keys, 'Deleted once no guide has it.' );
	}

	/** An invalid spec saves nothing, and the REST update reports why. */
	public function test_invalid_items_save_nothing(): void {
		$guide = $this->guide( 'Dad Hat' );
		$bad   = array(
			'name'  => 'Stretch',
			'type'  => 'pick',
			'steps' => array( 'Only one' ),
		);

		$this->assertNotSame( array(), Size_Guide_Spec_Items::save( $guide, array( $bad ) ) );
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

	/** A new guide's specs and values save in one request. */
	public function test_create_with_spec_items(): void {
		$created = ( new Size_Guide_Rest() )->create(
			$this->request(
				0,
				array(
					'title'     => 'Bucket Hat',
					'specItems' => array( $this->text_item( 'Brim' ) ),
					'specs'     => array( 'brim' => '6 cm' ),
				)
			)
		)->get_data();

		$keys = array_column( $created['registry']['items'], 'guides', 'key' );
		$this->assertSame( array( (int) $created['id'] ), $keys['brim'] );
		$this->assertSame( '6 cm', $created['specs']['brim'] );
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
