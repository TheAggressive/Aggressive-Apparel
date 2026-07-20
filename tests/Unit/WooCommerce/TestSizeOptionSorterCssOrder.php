<?php
/**
 * Test Size_Option_Sorter CSS `order` emission.
 *
 * The sorter positions option chips with CSS `order` rather than moving DOM
 * nodes (the block's `data-wp-each` binds items positionally). `order` accepts
 * integers only, so two things must hold: the emitted value is always a whole
 * number, and only size chips get ordered at all.
 *
 * Regression: unknown sizes ranked PHP_FLOAT_MAX, which stringified as
 * "1.7976931348623E+308" and made browsers discard the declaration — visible on
 * colour chips, which have no size rank by definition.
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use WP_UnitTestCase;
use Aggressive_Apparel\WooCommerce\Block_Pill_Helper;
use Aggressive_Apparel\WooCommerce\Size_Option_Sorter;

/**
 * Size Option Sorter CSS order test case.
 */
class TestSizeOptionSorterCssOrder extends WP_UnitTestCase {

	/**
	 * Build one option chip button matching real WooCommerce markup.
	 *
	 * @param string $attribute Attribute name, e.g. 'attribute_pa_size'.
	 * @param string $value     Option slug.
	 * @return string Button HTML.
	 */
	private function chip( string $attribute, string $value ): string {
		return sprintf(
			'<button class="%s" type="button" role="radio" id="%s-%s" value="%s">%s</button>',
			Block_Pill_Helper::PILL_CLASS,
			esc_attr( $attribute ),
			esc_attr( $value ),
			esc_attr( $value ),
			esc_html( $value )
		);
	}

	/**
	 * Run block content through the sorter's render filter.
	 *
	 * @param string $inner Chip markup.
	 * @return string Filtered block content.
	 */
	private function render( string $inner ): string {
		$sorter = new Size_Option_Sorter();

		return $sorter->sort_size_options_in_block(
			'<div class="wc-block-product-filter-chips">' . $inner . '</div>',
			array( 'blockName' => Block_Pill_Helper::BLOCK_NAME )
		);
	}

	/**
	 * Extract every `order:` value emitted in the markup.
	 *
	 * @param string $html Rendered markup.
	 * @return string[] Raw order values.
	 */
	private function orders( string $html ): array {
		preg_match_all( '/order:([^;"]+)/', $html, $matches );

		return $matches[1];
	}

	/**
	 * Every emitted order value must be a whole number.
	 *
	 * @return void
	 */
	public function test_emitted_order_values_are_integers(): void {
		$html = $this->render(
			$this->chip( 'attribute_pa_size', 'xs' ) .
			$this->chip( 'attribute_pa_size', 'l' ) .
			$this->chip( 'attribute_pa_size', 'one-size' ) .
			$this->chip( 'attribute_pa_size', '8.5' )
		);

		$orders = $this->orders( $html );
		$this->assertNotEmpty( $orders, 'Size chips should receive an order value.' );

		foreach ( $orders as $order ) {
			$this->assertMatchesRegularExpression(
				'/^-?\d+$/',
				trim( $order ),
				'CSS order must be a whole number, got: ' . $order
			);
		}
	}

	/**
	 * An unranked size must not emit the float sentinel in scientific notation.
	 *
	 * @return void
	 */
	public function test_unknown_size_does_not_emit_scientific_notation(): void {
		$html = $this->render( $this->chip( 'attribute_pa_size', 'one-size' ) );

		$this->assertStringNotContainsStringIgnoringCase( 'e+', $html );
		$this->assertStringNotContainsString( '1.79769', $html );
	}

	/**
	 * Half sizes must not collapse onto their neighbour's order value.
	 *
	 * @return void
	 */
	public function test_fractional_numeric_sizes_keep_their_relative_order(): void {
		$html = $this->render(
			$this->chip( 'attribute_pa_size', '8.5' ) .
			$this->chip( 'attribute_pa_size', '9' )
		);

		$orders = array_map( 'intval', $this->orders( $html ) );

		$this->assertCount( 2, $orders );
		$this->assertLessThan(
			$orders[1],
			$orders[0],
			'Size 8.5 must order before size 9.'
		);
	}

	/**
	 * Colour chips in a block that also mentions a size attribute must be left
	 * alone — they have no size rank, so ordering them is always wrong.
	 *
	 * @return void
	 */
	public function test_non_size_chips_are_not_ordered(): void {
		$html = $this->render(
			$this->chip( 'attribute_pa_size', 'm' ) .
			$this->chip( 'attribute_pa_color', 'black' )
		);

		$this->assertStringNotContainsString(
			'id="attribute_pa_color-black" value="black" style',
			$html,
			'Colour chips must not receive an inline order style.'
		);

		$this->assertCount(
			1,
			$this->orders( $html ),
			'Only the size chip should be ordered.'
		);
	}

	/**
	 * Sizes must still sort in the documented order.
	 *
	 * @return void
	 */
	public function test_known_sizes_sort_ascending(): void {
		$html = $this->render(
			$this->chip( 'attribute_pa_size', 'xs' ) .
			$this->chip( 'attribute_pa_size', 's' ) .
			$this->chip( 'attribute_pa_size', 'm' ) .
			$this->chip( 'attribute_pa_size', 'xl' )
		);

		$orders = array_map( 'intval', $this->orders( $html ) );
		$sorted = $orders;
		sort( $sorted );

		$this->assertSame( $sorted, $orders, 'XS < S < M < XL by CSS order.' );
	}
}
