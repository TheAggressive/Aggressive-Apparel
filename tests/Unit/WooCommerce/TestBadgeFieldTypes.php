<?php
/**
 * The TypeScript field-key union must match the PHP registry.
 *
 * `_field-keys.ts` is the one place the badge field list is duplicated across
 * languages — TypeScript cannot read a PHP array, so the union is generated.
 * A generated file with nothing checking it silently rots, which is how the
 * five-way PHP duplication drifted in the first place. This test is the check.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Badge_Field_Registry;
use WP_UnitTestCase;

/** @covers \Aggressive_Apparel\WooCommerce\Badge_Field_Registry */
class TestBadgeFieldTypes extends WP_UnitTestCase {

	private const TS_FILE = '/src/scripts/admin/badge-studio/_field-keys.ts';

	/**
	 * Field keys declared in the generated TypeScript union.
	 *
	 * @return string[]
	 */
	private function ts_keys(): array {
		$path = get_template_directory() . self::TS_FILE;

		$this->assertFileExists( $path, 'The generated field-key union is missing.' );

		$source = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local build artifact, not a remote URL.

		// Read the runtime array rather than the type: a union is erased at
		// compile time, so the array is what the studio can actually iterate.
		$start = strpos( $source, 'BADGE_FIELD_KEYS' );
		$this->assertNotFalse( $start, 'BADGE_FIELD_KEYS not found.' );

		preg_match_all( "/'([a-z0-9_]+)'/", substr( $source, $start ), $matches );

		$keys = array_values( array_unique( $matches[1] ) );
		sort( $keys );

		return $keys;
	}

	/** The generated union covers exactly the registry's fields. */
	public function test_typescript_union_matches_the_registry(): void {
		$php = array_values( Badge_Field_Registry::field_keys() );
		sort( $php );

		$ts = $this->ts_keys();

		$this->assertSame(
			array(),
			array_values( array_diff( $php, $ts ) ),
			'Field(s) exist in Badge_Field_Registry but not in _field-keys.ts. Regenerate it — '
				. 'see the header comment in that file.'
		);

		$this->assertSame(
			array(),
			array_values( array_diff( $ts, $php ) ),
			'Field(s) exist in _field-keys.ts but not in Badge_Field_Registry. The studio would '
				. 'post a field nothing saves.'
		);
	}
}
