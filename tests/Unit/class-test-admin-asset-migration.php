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
class Test_Admin_Asset_Migration extends WP_UnitTestCase {

	/**
	 * Forbidden inline-jQuery shapes, whitespace-tolerant.
	 *
	 * @var array<string, string>
	 */
	private const PATTERNS = array(
		'inline script on wp-color-picker or jquery' => '/wp_add_inline_script\s*\(\s*[\'"](?:wp-color-picker|jquery(?:-core)?)[\'"]/i',
		'jQuery document-ready block'                => '/(?:jQuery|\$)\s*\(\s*document\s*\)\s*\.\s*ready\s*\(/',
	);

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
	 * Admin behaviour ships as compiled TypeScript, never as inline jQuery in
	 * PHP: no inline script is attached to wp-color-picker or jQuery, and no
	 * document-ready block is printed. Whitespace-tolerant, so WPCS spacing
	 * (`jQuery( document ).ready`) is caught too.
	 */
	public function test_no_inline_admin_jquery_in_php(): void {
		$sources = $this->get_includes_php_sources();
		$this->assertNotEmpty( $sources );

		foreach ( $sources as $path => $contents ) {
			foreach ( self::PATTERNS as $label => $pattern ) {
				$this->assertDoesNotMatchRegularExpression( $pattern, $contents, sprintf( '%s found in %s', $label, basename( $path ) ) );
			}
		}
	}

	/**
	 * The patterns above catch the WPCS-formatted spellings they exist for.
	 */
	public function test_patterns_catch_formatted_variants(): void {
		$this->assertMatchesRegularExpression( self::PATTERNS['jQuery document-ready block'], 'jQuery( document ).ready( function () {' );
		$this->assertMatchesRegularExpression( self::PATTERNS['inline script on wp-color-picker or jquery'], 'wp_add_inline_script( \'wp-color-picker\', $js );' );
	}
}
