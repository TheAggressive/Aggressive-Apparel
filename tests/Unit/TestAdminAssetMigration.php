<?php
/**
 * Admin Asset Migration Tests
 *
 * Regression guard ensuring the colour-picker admin behaviour stays in
 * compiled TypeScript and is never reintroduced as inline jQuery in PHP.
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Unit;

use WP_UnitTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Admin Asset Migration Test Case
 */
class TestAdminAssetMigration extends WP_UnitTestCase {

	/**
	 * Collect the contents of every PHP file under includes/.
	 *
	 * @return array<string, string> Map of file path => contents.
	 */
	private function get_includes_php_sources(): array {
		$sources = array();
		$base    = get_template_directory() . '/includes';

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $base, RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( 'php' !== strtolower( $file->getExtension() ) ) {
				continue;
			}
			$sources[ $file->getPathname() ] = (string) file_get_contents( $file->getPathname() );
		}

		return $sources;
	}

	/**
	 * No PHP file should inject inline jQuery into the colour picker handle.
	 */
	public function test_no_inline_jquery_color_picker_scripts() {
		foreach ( $this->get_includes_php_sources() as $path => $contents ) {
			$this->assertStringNotContainsString(
				"wp_add_inline_script( 'wp-color-picker'",
				$contents,
				sprintf( 'Inline wp-color-picker script found in %s', basename( $path ) )
			);
		}
	}

	/**
	 * No PHP file should ship jQuery DOM-ready handlers as strings.
	 */
	public function test_no_jquery_ready_blocks_in_php() {
		foreach ( $this->get_includes_php_sources() as $path => $contents ) {
			$this->assertStringNotContainsString(
				'jQuery(document).ready',
				$contents,
				sprintf( 'Inline jQuery(document).ready found in %s', basename( $path ) )
			);
		}
	}

	/**
	 * The migrated admin scripts must exist as TypeScript sources.
	 */
	public function test_migrated_typescript_sources_exist() {
		$dir = get_template_directory() . '/src/scripts/admin/woocommerce';

		$this->assertFileExists( $dir . '/color-swatch-admin.ts' );
		$this->assertFileExists( $dir . '/badge-preview-admin.ts' );
	}
}
