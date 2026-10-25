<?php
/**
 * Swatch tooltip tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Block_Pill_Helper;
use Aggressive_Apparel\WooCommerce\Swatch_Tooltips;
use WP_UnitTestCase;

/**
 * A colour's tooltip is its name plus any fabric details, the same whether
 * WooCommerce renders the chip on the server or in the browser.
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
	 * Server-rendered swatches get the tooltip as `data-tooltip`.
	 *
	 * @return void
	 */
	public function test_server_rendered_swatches(): void {
		$html = ( new Swatch_Tooltips() )->add_tooltip_data(
			'<span class="aggressive-apparel-color-swatch" data-color-name="Plaid"></span><span class="aggressive-apparel-color-swatch" data-color-name="Blue"></span>',
			array( 'blockName' => Block_Pill_Helper::BLOCK_NAME )
		);

		$this->assertStringContainsString( 'data-tooltip="Plaid — Flannel · 100% cotton"', $html );
		$this->assertStringContainsString( 'data-tooltip="Blue"', $html );
	}

	/**
	 * Browser-rendered chips get the same text through the swatch map.
	 *
	 * @return void
	 */
	public function test_swatch_map_for_browser_rendered_chips(): void {
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
