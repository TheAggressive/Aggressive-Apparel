<?php
/**
 * Store Copy placeholder validation tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Feature_Settings_Sanitizer;
use Aggressive_Apparel\WooCommerce\Sale_Pricing;
use WP_UnitTestCase;

/**
 * A mistyped `{percnt}` would be printed on a product card verbatim, so the
 * save path has to reject it rather than pass it through.
 */
class TestStoreCopyTokens extends WP_UnitTestCase {

	/** Sanitizer under test. */
	private Feature_Settings_Sanitizer $sanitizer;

	/**
	 * Boot the sanitizer and clear any settings errors from earlier tests.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->sanitizer                    = new Feature_Settings_Sanitizer();
		$GLOBALS['wp_settings_errors']      = array();
	}

	/**
	 * Reset globals touched here.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$GLOBALS['wp_settings_errors'] = array();
		delete_option( Feature_Settings::SALE_BADGE_TEXT_OPTION );

		parent::tearDown();
	}

	/**
	 * The sale badge definition, as `register_setting()` binds it.
	 *
	 * @return array<string, mixed>
	 */
	private function sale_badge_definition(): array {
		$definitions = Feature_Settings::get_store_copy_definitions();

		return $definitions['sale_badge_text'];
	}

	/** Unknown placeholders are found; declared ones are not. */
	public function test_finds_only_unrecognised_tokens(): void {
		$allowed = array( Sale_Pricing::PERCENT_TOKEN );

		$this->assertSame( array(), Feature_Settings::find_unknown_copy_tokens( 'Save {percent}%', $allowed ) );
		$this->assertSame( array(), Feature_Settings::find_unknown_copy_tokens( 'Now on Sale', $allowed ) );
		$this->assertSame( array( '{percnt}' ), Feature_Settings::find_unknown_copy_tokens( 'Save {percnt}%', $allowed ) );
		$this->assertSame(
			array( '{pct}', '{off}' ),
			Feature_Settings::find_unknown_copy_tokens( '{pct} and {off} and {percent}', $allowed )
		);
	}

	/** A field declaring no tokens rejects every placeholder. */
	public function test_a_field_without_tokens_rejects_all_of_them(): void {
		$this->assertSame(
			array( '{percent}' ),
			Feature_Settings::find_unknown_copy_tokens( 'Save {percent}%', array() )
		);
	}

	/** Valid copy is saved unchanged. */
	public function test_accepts_a_declared_token(): void {
		$this->assertSame(
			'Save {percent}%',
			$this->sanitizer->sanitize_store_copy_text( 'Save {percent}%', $this->sale_badge_definition() )
		);
		$this->assertSame( array(), get_settings_errors() );
	}

	/** A typo keeps the previous value and says why on screen. */
	public function test_rejects_a_mistyped_token_and_explains(): void {
		update_option( Feature_Settings::SALE_BADGE_TEXT_OPTION, 'Save {percent}%' );

		$result = $this->sanitizer->sanitize_store_copy_text( 'Save {percnt}%', $this->sale_badge_definition() );

		$this->assertSame( 'Save {percent}%', $result, 'The last good value stands' );

		$errors = get_settings_errors( Feature_Settings::SALE_BADGE_TEXT_OPTION );
		$this->assertCount( 1, $errors );
		$this->assertStringContainsString( '{percnt}', $errors[0]['message'] );
		$this->assertStringContainsString( '{percent}', $errors[0]['message'], 'The message names the supported token' );
	}

	/** With nothing saved yet, a rejected value falls back to the default. */
	public function test_rejection_falls_back_to_the_default(): void {
		$this->assertSame(
			'-{percent}%',
			$this->sanitizer->sanitize_store_copy_text( '{nope}', $this->sale_badge_definition() )
		);
	}

	/** Fields that declare no `tokens` key are not placeholder-checked at all. */
	public function test_untokenised_fields_are_left_alone(): void {
		$definitions = Feature_Settings::get_store_copy_definitions();

		$this->assertSame(
			'Pick {size}',
			$this->sanitizer->sanitize_store_copy_text( 'Pick {size}', $definitions['variable_product_button_text'] )
		);
		$this->assertSame( array(), get_settings_errors() );
	}

	/** Length and tag stripping still apply alongside token validation. */
	public function test_still_sanitizes_and_truncates(): void {
		$this->assertSame(
			'',
			$this->sanitizer->sanitize_store_copy_text( '<script>alert(1)</script>', $this->sale_badge_definition() )
		);

		$long = str_repeat( 'a', 80 );
		$this->assertSame( 60, mb_strlen( $this->sanitizer->sanitize_store_copy_text( $long, $this->sale_badge_definition() ) ) );
	}

	/** Every field that ships a `{token}` default must declare that token. */
	public function test_defaults_and_suggestions_only_use_declared_tokens(): void {
		foreach ( Feature_Settings::get_store_copy_definitions() as $key => $definition ) {
			$allowed = isset( $definition['tokens'] ) ? array_keys( $definition['tokens'] ) : array();

			$candidates = array_merge(
				array( $definition['default'] ),
				isset( $definition['suggestions'] ) ? $definition['suggestions'] : array()
			);

			foreach ( $candidates as $candidate ) {
				$this->assertSame(
					array(),
					Feature_Settings::find_unknown_copy_tokens( (string) $candidate, array_map( 'strval', $allowed ) ),
					sprintf( 'Store Copy field "%s" ships copy using a placeholder it does not declare', $key )
				);
			}
		}
	}
}
