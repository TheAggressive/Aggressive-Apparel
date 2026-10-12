<?php
/**
 * Add-to-cart button accessibility tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Add_To_Cart_Button_A11y;
use WP_UnitTestCase;

/**
 * The button's visual unavailable state reaches assistive tech.
 */
class TestAddToCartButtonA11y extends WP_UnitTestCase {

	/** A class-driven disabled state gains a matching aria-disabled binding. */
	public function test_mirrors_disabled_class_as_aria_disabled(): void {
		$state = 'woocommerce/add-to-cart-with-options::!state.isFormValid';
		$html  = ( new Add_To_Cart_Button_A11y() )->mirror_disabled_state(
			'<div><button data-wp-class--disabled="' . $state . '"><span>Add to cart</span></button></div>'
		);

		$this->assertStringContainsString( 'data-wp-bind--aria-disabled="' . $state . '"', $html );
	}

	/** Buttons without the directive, or already bound, are left alone. */
	public function test_leaves_other_buttons_untouched(): void {
		$a11y = new Add_To_Cart_Button_A11y();

		$plain = '<button data-wp-on--click="actions.addCartItem">Add</button>';
		$this->assertSame( $plain, $a11y->mirror_disabled_state( $plain ) );

		$bound = '<button data-wp-class--disabled="a" data-wp-bind--aria-disabled="b">Add</button>';
		$this->assertSame( $bound, $a11y->mirror_disabled_state( $bound ) );
	}
}
