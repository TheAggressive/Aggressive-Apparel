<?php
/**
 * Variation selector accessibility tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Variation_Selector_A11y;
use WP_UnitTestCase;

/**
 * Attribute rows become the selector list's items.
 */
class TestVariationSelectorA11y extends WP_UnitTestCase {

	/** Every attribute row gets the list item role; other markup is untouched. */
	public function test_marks_each_row_as_a_list_item(): void {
		$row  = 'wp-block-woocommerce-add-to-cart-with-options-variation-selector-attribute';
		$html = ( new Variation_Selector_A11y() )->mark_list_items(
			'<div class="wp-block-woocommerce-add-to-cart-with-options-variation-selector" role="list">'
			. '<div class="' . $row . '"><div role="radiogroup"></div></div>'
			. '<div class="' . $row . '" role="group"></div>'
			. '</div>'
		);

		$this->assertSame(
			'<div class="wp-block-woocommerce-add-to-cart-with-options-variation-selector" role="list">'
			. '<div role="listitem" class="' . $row . '"><div role="radiogroup"></div></div>'
			. '<div class="' . $row . '" role="group"></div>'
			. '</div>',
			$html,
			'A row that already has a role keeps it.'
		);
	}
}
