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
 * Covers Theme Features enablement and Adaptive Colors gating.
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

	/** Only the canonical persisted value enables a feature. */
	public function test_noncanonical_feature_value_is_disabled(): void {
		update_option( Theme_Features::OPTION_KEY, array( 'adaptive_colors' => true ) );

		$this->assertFalse( Theme_Features::is_enabled( 'adaptive_colors' ) );
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
