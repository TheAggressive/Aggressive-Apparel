<?php
/**
 * Product badge enhanced-style tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Custom_Badge_Taxonomy;
use WP_UnitTestCase;

/** Test alpha-safe badge color storage. */
class TestBadgeAdvancedStyles extends WP_UnitTestCase {
	/** Studio mount bootstraps theme palette swatches for color fields. */
	public function test_badge_editor_bootstraps_theme_palette(): void {
		$taxonomy = new Custom_Badge_Taxonomy();

		ob_start();
		$taxonomy->render_add_fields();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'id="aa-badge-studio-root"', $html );
		$this->assertStringContainsString( 'data-aa-badge-studio=', $html );
		$this->assertStringContainsString( '&quot;palette&quot;:', $html );
		$this->assertStringContainsString( 'var(--wp--preset--color--accent)', $html );
		$this->assertStringNotContainsString( 'vivid-purple', $html );
		$this->assertStringNotContainsString( 'cyan-bluish-gray', $html );
		$this->assertStringContainsString( 'name="badge_border_style"', $html );
		$this->assertStringContainsString( 'name="badge_font_weight"', $html );
	}

	/** Automatic rules expose stable merchant-facing defaults. */
	public function test_badge_rule_defaults(): void {
		$rules = Custom_Badge_Taxonomy::get_badge_rules();

		$this->assertTrue( $rules['sale_enabled'] );
		$this->assertTrue( $rules['new_enabled'] );
		$this->assertSame( 14, $rules['new_days'] );
		$this->assertSame( 5, $rules['low_stock'] );
		$this->assertSame( 50, $rules['bestseller_sales'] );
	}

	/** Rule submission must not reuse the taxonomy screen's core submit ID. */
	public function test_badge_rules_render_an_independent_submit_button(): void {
		$taxonomy = new Custom_Badge_Taxonomy();

		ob_start();
		$taxonomy->render_rules_panel();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="action" value="aggressive_apparel_save_badge_rules"', $html );
		$this->assertStringContainsString( 'id="aa_badge_rules_submit"', $html );
		$this->assertStringNotContainsString( 'id="submit"', $html );
	}

	/**
	 * Supported colors are canonicalized without discarding alpha.
	 *
	 * @dataProvider supported_colors
	 */
	public function test_sanitizes_supported_badge_colors( string $input, string $expected ): void {
		$this->assertSame( $expected, Custom_Badge_Taxonomy::sanitize_badge_color( $input ) );
	}

	/** @return array<string, array{string, string}> */
	public function supported_colors(): array {
		return array(
			'short hex'       => array( '#AbC', '#aabbcc' ),
			'short hex alpha' => array( '#AbC8', '#aabbcc88' ),
			'long hex'        => array( '#112233', '#112233' ),
			'long hex alpha'  => array( '#11223380', '#11223380' ),
			'keyword'         => array( ' transparent ', 'transparent' ),
		);
	}

	/** Unsafe and unsupported CSS syntax must not reach inline styles. */
	public function test_rejects_unsupported_badge_colors(): void {
		foreach ( array( 'red', 'rgba(0,0,0,.5)', 'url(https://example.test)', '#12', '#abcdex' ) as $input ) {
			$this->assertSame( '', Custom_Badge_Taxonomy::sanitize_badge_color( $input ) );
		}
	}
}
