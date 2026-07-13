<?php
/**
 * Social proof cache invalidation tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Social_Proof;
use WP_UnitTestCase;

/**
 * @covers \Aggressive_Apparel\WooCommerce\Social_Proof
 */
class TestSocialProofCache extends WP_UnitTestCase {

	/**
	 * Mirrors Social_Proof::TRANSIENT_KEY.
	 *
	 * @var string
	 */
	private const TRANSIENT_KEY = 'aggressive_apparel_social_proof_v7';

	/**
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		Social_Proof::flush_cache();
	}

	/**
	 * @return void
	 */
	public function tearDown(): void {
		Social_Proof::flush_cache();
		parent::tearDown();
	}

	/**
	 * Order and option hooks should flush the notification pool.
	 */
	public function test_init_registers_cache_invalidation_hooks(): void {
		( new Social_Proof() )->init();

		$this->assertNotFalse(
			has_action( 'woocommerce_new_order', array( Social_Proof::class, 'flush_cache' ) )
		);
		$this->assertNotFalse(
			has_action( 'woocommerce_order_status_changed', array( Social_Proof::class, 'flush_cache' ) )
		);
		$this->assertNotFalse(
			has_action( 'updated_option', array( Social_Proof::class, 'maybe_flush_on_option_change' ) )
		);
	}

	/**
	 * flush_cache should delete the pool transient.
	 */
	public function test_flush_cache_deletes_transient(): void {
		set_transient( self::TRANSIENT_KEY, array( array( 'type' => 'trust' ) ), HOUR_IN_SECONDS );
		$this->assertNotFalse( get_transient( self::TRANSIENT_KEY ) );

		Social_Proof::flush_cache();

		$this->assertFalse( get_transient( self::TRANSIENT_KEY ) );
	}

	/**
	 * Social-proof option changes should flush; unrelated options should not.
	 */
	public function test_maybe_flush_on_option_change(): void {
		set_transient( self::TRANSIENT_KEY, array( array( 'type' => 'trust' ) ), HOUR_IN_SECONDS );

		Social_Proof::maybe_flush_on_option_change( 'blogname' );
		$this->assertNotFalse( get_transient( self::TRANSIENT_KEY ) );

		Social_Proof::maybe_flush_on_option_change( 'aggressive_apparel_social_proof_sources' );
		$this->assertFalse( get_transient( self::TRANSIENT_KEY ) );

		set_transient( self::TRANSIENT_KEY, array( array( 'type' => 'trust' ) ), HOUR_IN_SECONDS );
		Social_Proof::maybe_flush_on_option_change( Feature_Settings::OPTION_KEY );
		$this->assertFalse( get_transient( self::TRANSIENT_KEY ) );
	}
}
