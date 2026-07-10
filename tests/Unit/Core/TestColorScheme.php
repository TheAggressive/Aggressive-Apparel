<?php
/**
 * Tests for Color_Scheme helpers.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\Core;

use Aggressive_Apparel\Core\Color_Scheme;
use WP_UnitTestCase;

/**
 * Color Scheme Test Case
 */
class TestColorScheme extends WP_UnitTestCase {

	/**
	 * Storage keys match the TypeScript color-scheme-storage module.
	 */
	public function test_storage_keys_match_typescript_contract(): void {
		$this->assertSame(
			array(
				'aggressive-apparel-color-scheme',
				'aggressive-apparel-dark-mode',
				'aggressive-apparel-editor-color-scheme',
			),
			Color_Scheme::storage_keys()
		);

		$this->assertSame( Color_Scheme::STORAGE_KEY, Color_Scheme::storage_keys()[0] );
	}

	/**
	 * Shared JS reader includes keys and legacy migration writes.
	 */
	public function test_js_read_function_migrates_legacy_keys(): void {
		$js = Color_Scheme::js_read_stored_scheme_function();

		$this->assertStringContainsString( 'function aaReadStoredColorScheme', $js );
		$this->assertStringContainsString( Color_Scheme::STORAGE_KEY, $js );
		$this->assertStringContainsString( Color_Scheme::LEGACY_FRONTEND_KEY, $js );
		$this->assertStringContainsString( Color_Scheme::LEGACY_EDITOR_KEY, $js );
		$this->assertStringContainsString( 'localStorage.setItem', $js );
		$this->assertStringContainsString( 'localStorage.removeItem', $js );
	}
}
