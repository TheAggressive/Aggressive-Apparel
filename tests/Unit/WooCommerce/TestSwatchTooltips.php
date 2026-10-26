<?php
/**
 * Swatch tooltip tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Swatch_Tooltips;
use WP_UnitTestCase;

/**
 * A colour's tooltip is its name plus any fabric details, added to the swatch
 * map every colour chip is decorated from.
 */
class TestSwatchTooltips extends WP_UnitTestCase {

	/**
	 * Register pa_color with a fabric-detailed and a plain colour.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! taxonomy_exists( 'pa_color' ) ) {
			register_taxonomy( 'pa_color', 'product', array( 'hierarchical' => false ) );
		}
		$plaid = wp_insert_term( 'Plaid', 'pa_color', array( 'slug' => 'plaid' ) );
		$this->assertIsArray( $plaid );
		update_term_meta( $plaid['term_id'], 'fabric_name', 'Flannel' );
		update_term_meta( $plaid['term_id'], 'fabric_composition', '100% cotton' );
		wp_insert_term( 'Blue', 'pa_color', array( 'slug' => 'blue' ) );
	}

	/**
	 * Each colour's tooltip goes into the swatch map; unknown slugs get none.
	 *
	 * @return void
	 */
	public function test_adds_tooltips_to_swatch_map(): void {
		$map = ( new Swatch_Tooltips() )->add_tooltips_to_map(
			array(
				'plaid'   => array( 'name' => 'Plaid' ),
				'blue'    => array( 'name' => 'Blue' ),
				'missing' => array( 'name' => 'Missing' ),
			)
		);

		$this->assertSame( 'Plaid — Flannel · 100% cotton', $map['plaid']['tooltip'] );
		$this->assertSame( 'Blue', $map['blue']['tooltip'] );
		$this->assertArrayNotHasKey( 'tooltip', $map['missing'] );
	}
}
