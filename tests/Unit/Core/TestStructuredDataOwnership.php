<?php
/**
 * Structured data ownership tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\Core;

use WP_UnitTestCase;

/**
 * The SEO plugin (Rank Math) owns the site's JSON-LD graph. A second
 * Organization or WebSite node reusing its `@id`s gives Google two
 * conflicting entities, so the theme emits none of its own.
 */
class TestStructuredDataOwnership extends WP_UnitTestCase {

	/**
	 * Theme files of a type, recursively.
	 *
	 * @param string $dir       Directory relative to the theme root.
	 * @param string $extension File extension without the dot.
	 * @return list<string>
	 */
	private static function files( string $dir, string $extension ): array {
		$root = get_template_directory() . '/' . $dir;
		if ( ! is_dir( $root ) ) {
			return array();
		}

		$files    = array();
		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) {
			if ( $file instanceof \SplFileInfo && $extension === $file->getExtension() ) {
				$files[] = $file->getPathname();
			}
		}

		return $files;
	}

	/** Every Copyright block in templates, parts and patterns has its schema off. */
	public function test_copyright_blocks_leave_schema_to_the_seo_plugin(): void {
		$files = array_merge( self::files( 'templates', 'html' ), self::files( 'parts', 'html' ), self::files( 'patterns', 'php' ) );
		$this->assertNotEmpty( $files );

		$found = 0;
		foreach ( $files as $file ) {
			$contents = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local theme file.
			preg_match_all( '/<!-- wp:aggressive-blocks\/copyright (\{.*?\}) \/?-->/', $contents, $matches );
			foreach ( $matches[1] as $json ) {
				++$found;
				$attributes = json_decode( $json, true );
				$this->assertIsArray( $attributes, basename( $file ) );
				$this->assertFalse( $attributes['showSchema'] ?? true, basename( $file ) . ': the Copyright block would emit Organization/WebSite JSON-LD.' );
			}
		}

		$this->assertGreaterThan( 0, $found, 'The footer is expected to carry a Copyright block.' );
	}

	/** No theme source prints a JSON-LD script of its own. */
	public function test_theme_emits_no_json_ld(): void {
		$files = array_merge(
			self::files( 'includes', 'php' ),
			self::files( 'src', 'php' ),
			self::files( 'templates', 'html' ),
			self::files( 'parts', 'html' ),
			self::files( 'patterns', 'php' )
		);

		foreach ( $files as $file ) {
			$contents = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local theme file.
			$this->assertStringNotContainsString( 'application/ld+json', $contents, basename( $file ) . ' emits JSON-LD; Rank Math owns structured data.' );
		}
	}
}
