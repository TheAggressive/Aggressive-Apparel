<?php
/**
 * What the product page's Size & specs tab is given to show.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Product_Specs_Admin;
use Aggressive_Apparel\WooCommerce\Product_Specs_Meta;
use Aggressive_Apparel\WooCommerce\Product_Specs_Registry;
use Aggressive_Apparel\WooCommerce\Product_Specs_Taxonomy;
use Aggressive_Apparel\WooCommerce\Product_Specs_Values;
use Aggressive_Apparel\Tests\Support\Restores_Taxonomies;
use Aggressive_Apparel\Tests\Support\Saves_Spec_Registry;
use WP_UnitTestCase;

/**
 * Covers the panel's data.
 */
class Test_Product_Specs_Panel extends WP_UnitTestCase {

	use Restores_Taxonomies;
	use Saves_Spec_Registry;

	/**
	 * Enable the feature and start from the default registry.
	 */
	public function setUp(): void {
		parent::setUp();

		update_option( Feature_Settings::OPTION_KEY, array( 'size_guide' => true ), false );
		delete_option( Product_Specs_Registry::OPTION_KEY );
		Product_Specs_Registry::flush();
		Product_Specs_Taxonomy::flush();
		$this->snapshot_taxonomies();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Restore global state.
	 */
	public function tearDown(): void {
		$this->restore_taxonomies();
		delete_option( Feature_Settings::OPTION_KEY );
		delete_option( Product_Specs_Registry::OPTION_KEY );
		Product_Specs_Registry::flush();

		parent::tearDown();
	}

	/** Each spec comes with where its value falls back to, worded as its guide words it. */
	public function test_panel_data(): void {
		$this->save_defaults( array( 'fabric' => 'Cotton' ) );
		$guide = $this->guide( 'Heavy Tee' );
		Product_Specs_Values::save_guide( $guide, array( 'fabric' => 'Nylon' ) );
		Product_Specs_Values::save_wording( $guide, array( 'fabric' => array( 'title' => 'Shell' ) ) );
		Product_Specs_Values::save_hidden( $guide, array( 'construction' ) );
		$product = $this->product( $guide );
		update_post_meta( $product, Product_Specs_Meta::WORDING, array( 'fabric' => array( 'title' => 'Outer' ) ) );

		$data   = $this->panel( $product );
		$fabric = array_column( $data['items'], null, 'key' )['fabric'];

		$this->assertSame( 'aa_specs', $data['field'] );
		$this->assertSame( 'Heavy Tee', $data['guide'] );
		$this->assertSame( 'Shell', $fabric['name'], 'Worded as the guide words it, before the product’s own.' );
		$this->assertSame( array( 'title' => 'Outer' ), $fabric['wording'] );
		$this->assertSame( '', $fabric['own'] );
		$this->assertSame( 'Nylon', $fabric['default'] );
		$this->assertSame( 'guide', $fabric['source'] );
		$this->assertSame( 'Heavy Tee', $fabric['sourceLabel'] );
		$this->assertNotContains( 'construction', array_column( $data['items'], 'key' ) );
		$this->assertSame( array( 'construction' ), array_column( $data['hiddenByGuide'], 'key' ), 'Hidden by the guide, and said so.' );
		$this->assertTrue( $data['canCreate'] );
	}

	/** Labels come worded as the guide words them, with end labels and advice, as the storefront words them. */
	public function test_labels_match_the_storefront(): void {
		$this->save_defaults( array( 'fit' => '1' ) );
		$guide = $this->guide( 'Heavy Tee' );
		Product_Specs_Values::save_wording(
			$guide,
			array(
				'fit' => array(
					'step0' => 'Close',
					'end'   => 'Roomy',
				),
			)
		);
		$product = $this->product( $guide );

		$fit      = array_column( $this->panel( $product )['items'], null, 'key' )['fit'];
		$resolved = $this->resolved( $product )['fit'];

		$this->assertSame( $resolved['steps'], $fit['steps'] );
		$this->assertSame( 'Close', $fit['steps'][0] );
		$this->assertSame( 'Roomy', $fit['end'] );
		$this->assertSame( '', $fit['start'], 'Left for the panel to fill from the steps, as the storefront does.' );
		$this->assertSame( $resolved['start'], $fit['steps'][0] );
		$this->assertSame( $this->items()['fit']['advice'], $fit['advice'] );

		// The product's own wording is laid over in the panel, not baked in here.
		update_post_meta( $product, Product_Specs_Meta::WORDING, array( 'fit' => array( 'step0' => 'Snug' ) ) );
		\Aggressive_Apparel\WooCommerce\Product_Specs::flush();
		$this->assertSame( 'Close', array_column( $this->panel( $product )['items'], null, 'key' )['fit']['steps'][0] );
		$this->assertSame( 'Snug', $this->resolved( $product )['fit']['steps'][0], 'The storefront has it.' );
	}

	/**
	 * The data the panel is rendered with.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>
	 */
	private function panel( int $product_id ): array {
		ob_start();
		( new Product_Specs_Admin() )->render_product_fields( get_post( $product_id ) );
		$html = (string) ob_get_clean();
		$this->assertSame( 1, preg_match( '/data-aa-product-specs-panel="([^"]*)"/', $html, $match ) );

		return json_decode( html_entity_decode( $match[1], ENT_QUOTES ), true );
	}
}
