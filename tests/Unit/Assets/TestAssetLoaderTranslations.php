<?php
/**
 * Tests for Asset_Loader script translation helpers.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\Assets;

use Aggressive_Apparel\Assets\Asset_Loader;
use WP_UnitTestCase;

/**
 * Asset_Loader i18n helper tests.
 */
class TestAssetLoaderTranslations extends WP_UnitTestCase {

	/**
	 * Text domain matches style.css.
	 */
	public function test_text_domain_constant(): void {
		$this->assertSame( 'aggressive-apparel', Asset_Loader::TEXT_DOMAIN );
	}

	/**
	 * Languages path points at the theme languages directory.
	 */
	public function test_languages_path(): void {
		$expected = get_template_directory() . '/languages';

		$this->assertSame( $expected, Asset_Loader::languages_path() );
		$this->assertDirectoryExists( $expected );
	}

	/**
	 * set_script_translations registers the path for a classic handle.
	 */
	public function test_set_script_translations_registers_path(): void {
		$handle = 'aggressive-apparel-i18n-test-script';

		wp_register_script( $handle, false, array( 'wp-i18n' ), '1.0.0', true );
		Asset_Loader::set_script_translations( $handle );

		$scripts = wp_scripts();
		$obj     = $scripts->registered[ $handle ] ?? null;

		$this->assertNotNull( $obj );
		$this->assertSame( Asset_Loader::TEXT_DOMAIN, $obj->textdomain );
		$this->assertSame( Asset_Loader::languages_path(), $obj->translations_path );
	}
}
