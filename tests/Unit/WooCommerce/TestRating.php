<?php
/**
 * Rating renderer unit tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\Core\Icons;
use Aggressive_Apparel\WooCommerce\Rating;
use WP_UnitTestCase;

/**
 * Rating renderer test case.
 */
class TestRating extends WP_UnitTestCase {

	/**
	 * Reset the icon cache/filters between tests.
	 */
	public function tearDown(): void {
		remove_all_filters( 'aggressive_apparel_icon_definitions' );
		Icons::flush_cache_for_tests();
		parent::tearDown();
	}

	/**
	 * Pull the fill width percentage out of the rendered markup.
	 *
	 * @param string $html Rendered rating markup.
	 * @return float
	 */
	private function fill_width( string $html ): float {
		$this->assertSame( 1, preg_match( '/width:([0-9.]+)%/', $html, $matches ) );

		return (float) $matches[1];
	}

	/**
	 * The markup carries WooCommerce's accessibility contract.
	 */
	public function test_renders_accessible_role_and_label(): void {
		$html = Rating::stars( 3.5 );

		$this->assertStringContainsString( 'role="img"', $html );
		$this->assertStringContainsString( 'aria-label="Rated 3.5 out of 5"', $html );
	}

	/**
	 * The fill width is proportional to the rating (rating / 5 * 100).
	 *
	 * @dataProvider provide_fill_widths
	 *
	 * @param float $rating   Rating value.
	 * @param float $expected Expected fill width percentage.
	 */
	public function test_fill_width_is_proportional( float $rating, float $expected ): void {
		$this->assertSame( $expected, $this->fill_width( Rating::stars( $rating ) ) );
	}

	/**
	 * Data provider for fill-width math, including out-of-range clamping.
	 *
	 * @return array<string, array{float, float}>
	 */
	public function provide_fill_widths(): array {
		return array(
			'zero'          => array( 0.0, 0.0 ),
			'three'         => array( 3.0, 60.0 ),
			'three-and-half' => array( 3.5, 70.0 ),
			'five'          => array( 5.0, 100.0 ),
			'over-clamps'   => array( 6.0, 100.0 ),
			'negative-clamps' => array( -2.0, 0.0 ),
		);
	}

	/**
	 * Whole numbers drop the decimal in the label; fractions keep it.
	 */
	public function test_label_formatting(): void {
		$this->assertStringContainsString( 'aria-label="Rated 4 out of 5"', Rating::stars( 4.0 ) );
		$this->assertStringContainsString( 'aria-label="Rated 3.5 out of 5"', Rating::stars( 3.5 ) );
	}

	/**
	 * Renders a five-mark track and a five-mark fill (ten marks total).
	 */
	public function test_renders_track_and_fill_marks(): void {
		$html = Rating::stars( 3.0 );

		$this->assertStringContainsString( 'aa-rating__track', $html );
		$this->assertStringContainsString( 'aa-rating__fill', $html );
		$this->assertSame( 10, substr_count( $html, 'aa-rating__mark' ) );
	}

	/**
	 * Marks are empty spans (the glyph is drawn via CSS mask), so the markup
	 * survives the reviews-tab wp_kses pass that strips inline SVG.
	 */
	public function test_marks_contain_no_inline_svg(): void {
		$this->assertStringNotContainsString( '<svg', Rating::stars( 4.0 ) );
	}
}
