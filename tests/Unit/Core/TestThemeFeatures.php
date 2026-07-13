<?php
/**
 * Theme Features unit tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\Core
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\Core;

use Aggressive_Apparel\Core\Adaptive_Colors;
use Aggressive_Apparel\Core\Theme_Features;
use WP_UnitTestCase;

/**
 * Covers Theme Features enablement, Adaptive Colors gating, and legacy migration.
 */
class TestThemeFeatures extends WP_UnitTestCase {

	/**
	 * Reset options before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->reset_options();
	}

	/**
	 * Reset options after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$this->reset_options();
		parent::tearDown();
	}

	/**
	 * Delete options touched by these tests.
	 *
	 * @return void
	 */
	private function reset_options(): void {
		delete_option( Theme_Features::OPTION_KEY );
		delete_option( Theme_Features::MIGRATION_OPTION );
		delete_option( Theme_Features::LEGACY_WC_FEATURES_OPTION );
		delete_option( 'aggressive_apparel_adaptive_colors_enabled' );
		delete_option( 'aggressive_apparel_adaptive_colors_migrated' );
	}

	/**
	 * Adaptive Colors stays on until explicitly saved off.
	 *
	 * @return void
	 */
	public function test_adaptive_colors_defaults_on_until_explicitly_disabled(): void {
		$this->assertTrue( Theme_Features::is_enabled( 'adaptive_colors' ) );

		update_option( Theme_Features::OPTION_KEY, array( 'adaptive_colors' => 'yes' ) );
		$this->assertTrue( Theme_Features::is_enabled( 'adaptive_colors' ) );

		update_option( Theme_Features::OPTION_KEY, array( 'adaptive_colors' => 'no' ) );
		$this->assertFalse( Theme_Features::is_enabled( 'adaptive_colors' ) );
	}

	/**
	 * Unknown feature keys are never enabled.
	 *
	 * @return void
	 */
	public function test_unknown_feature_is_disabled(): void {
		$this->assertFalse( Theme_Features::is_enabled( 'custom_cursor' ) );
		$this->assertFalse( Theme_Features::is_enabled( 'not_a_feature' ) );
	}

	/**
	 * Adaptive Colors runtime hooks respect Theme Features.
	 *
	 * @return void
	 */
	public function test_adaptive_colors_init_respects_theme_features(): void {
		update_option( Theme_Features::OPTION_KEY, array( 'adaptive_colors' => 'no' ) );

		$adaptive = new Adaptive_Colors();
		$adaptive->init();

		$this->assertFalse(
			has_filter( 'wp_theme_json_data_theme', array( $adaptive, 'inject_adaptive_palette' ) ),
			'Disabled Adaptive Colors must not hook theme.json.'
		);
	}

	/**
	 * Dedicated Adaptive Colors option migrates into Theme Features.
	 *
	 * @return void
	 */
	public function test_migrates_from_dedicated_adaptive_colors_option(): void {
		update_option( 'aggressive_apparel_adaptive_colors_enabled', 'no' );

		Theme_Features::maybe_migrate_legacy_options();

		$this->assertFalse( Theme_Features::is_enabled( 'adaptive_colors' ) );
		$this->assertFalse( get_option( 'aggressive_apparel_adaptive_colors_enabled' ) );
		$this->assertSame( '1', get_option( Theme_Features::MIGRATION_OPTION ) );
	}

	/**
	 * Store Enhancements bag migrates when Theme Features has no value.
	 *
	 * @return void
	 */
	public function test_migrates_from_store_enhancements_bag(): void {
		update_option(
			Theme_Features::LEGACY_WC_FEATURES_OPTION,
			array(
				'adaptive_colors' => false,
				'custom_cursor'   => true,
				'wishlist'        => true,
			)
		);

		Theme_Features::maybe_migrate_legacy_options();

		$this->assertFalse( Theme_Features::is_enabled( 'adaptive_colors' ) );

		$legacy = get_option( Theme_Features::LEGACY_WC_FEATURES_OPTION, array() );
		$this->assertArrayNotHasKey( 'adaptive_colors', $legacy );
		$this->assertArrayNotHasKey( 'custom_cursor', $legacy );
		$this->assertTrue( ! empty( $legacy['wishlist'] ) );
	}

	/**
	 * Migration is a no-op the second time.
	 *
	 * @return void
	 */
	public function test_migration_runs_once(): void {
		update_option( Theme_Features::OPTION_KEY, array( 'adaptive_colors' => 'no' ) );
		update_option( Theme_Features::MIGRATION_OPTION, '1' );
		update_option(
			Theme_Features::LEGACY_WC_FEATURES_OPTION,
			array( 'adaptive_colors' => true )
		);

		Theme_Features::maybe_migrate_legacy_options();

		$this->assertFalse( Theme_Features::is_enabled( 'adaptive_colors' ) );
	}

	/**
	 * Sanitize writes explicit yes/no for every known key.
	 *
	 * @return void
	 */
	public function test_sanitize_features_writes_yes_no(): void {
		$features = new Theme_Features();
		$result   = $features->sanitize_features( array() );

		$this->assertSame( 'no', $result['adaptive_colors'] );
	}
}
