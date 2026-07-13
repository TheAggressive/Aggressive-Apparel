<?php
/**
 * Color swatch cross-request cache tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Color_Data_Manager;
use WP_UnitTestCase;

/**
 * @covers \Aggressive_Apparel\WooCommerce\Color_Data_Manager
 */
class TestColorDataManagerCache extends WP_UnitTestCase {

	/**
	 * Mirrors Color_Data_Manager::SWATCH_TRANSIENT_KEY.
	 *
	 * @var string
	 */
	private const TRANSIENT_KEY = 'aggressive_apparel_color_swatch_data_v1';

	/**
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! taxonomy_exists( 'pa_color' ) ) {
			register_taxonomy( 'pa_color', 'product', array( 'hierarchical' => false ) );
		}

		Color_Data_Manager::flush_swatch_data_memo();
	}

	/**
	 * @return void
	 */
	public function tearDown(): void {
		Color_Data_Manager::flush_swatch_data_memo();
		parent::tearDown();
	}

	/**
	 * Safe swatch reads should populate a persistent transient.
	 */
	public function test_get_safe_swatch_data_persists_transient(): void {
		$data = Color_Data_Manager::get_safe_swatch_data();

		$this->assertIsArray( $data );
		$this->assertNotFalse( get_transient( self::TRANSIENT_KEY ) );
		$this->assertSame( $data, get_transient( self::TRANSIENT_KEY ) );
	}

	/**
	 * Flush should clear request memo and transient so the next read rebuilds.
	 */
	public function test_flush_swatch_data_memo_deletes_transient(): void {
		Color_Data_Manager::get_safe_swatch_data();
		$this->assertNotFalse( get_transient( self::TRANSIENT_KEY ) );

		Color_Data_Manager::flush_swatch_data_memo();

		$this->assertFalse( get_transient( self::TRANSIENT_KEY ) );
	}

	/**
	 * Seeded transient should be served without rebuilding when memo is cold.
	 */
	public function test_get_safe_swatch_data_reads_existing_transient(): void {
		$seeded = array(
			'red' => array(
				'value' => '#ff0000',
				'type'  => 'color',
				'name'  => 'Red',
			),
		);
		Color_Data_Manager::flush_swatch_data_memo();
		set_transient( self::TRANSIENT_KEY, $seeded, HOUR_IN_SECONDS );

		$this->assertSame( $seeded, Color_Data_Manager::get_safe_swatch_data() );
	}
}
