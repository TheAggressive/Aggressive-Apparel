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
	 * Shared JS reader reads only the canonical storage key.
	 */
	public function test_js_read_function_uses_canonical_key(): void {
		$js = Color_Scheme::js_read_stored_scheme_function();

		$this->assertStringContainsString( 'function aaReadStoredColorScheme', $js );
		$this->assertStringContainsString( Color_Scheme::STORAGE_KEY, $js );
		$this->assertStringNotContainsString( 'localStorage.setItem', $js );
		$this->assertStringNotContainsString( 'localStorage.removeItem', $js );
	}
}
