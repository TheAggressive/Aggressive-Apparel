<?php
/**
 * Product filters wrapper alignment tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Product_Filters;
use ReflectionClass;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * The AJAX grid wrapper replaces the Product Collection as the direct child of
 * the (possibly constrained) template container, so it must carry the wrapped
 * block's alignment or WordPress core caps it at content-size (1200px) and the
 * collection's wide/full alignment never takes effect.
 */
class TestProductFiltersAlignment extends WP_UnitTestCase {

	/**
	 * Invoke the private wrapper against a drawer-layout instance.
	 *
	 * @param string $align_class Alignment class to mirror onto the wrapper.
	 * @return string Wrapper HTML.
	 */
	private function wrap( string $align_class ): string {
		// Bypass the constructor so no WooCommerce request context is required;
		// the drawer path only reads the $layout property.
		$reflection = new ReflectionClass( Product_Filters::class );
		$filters    = $reflection->newInstanceWithoutConstructor();

		$layout = $reflection->getProperty( 'layout' );
		$layout->setAccessible( true );
		$layout->setValue( $filters, 'drawer' );

		$method = new ReflectionMethod( Product_Filters::class, 'wrap_product_collection' );
		$method->setAccessible( true );

		return (string) $method->invoke( $filters, '<div class="wp-block-woocommerce-product-collection">grid</div>', $align_class );
	}

	/**
	 * The wrapper mirrors a wide alignment class onto its container.
	 *
	 * @return void
	 */
	public function test_wide_alignment_is_mirrored_onto_wrapper(): void {
		$html = $this->wrap( 'alignwide' );

		$this->assertStringContainsString( 'aa-product-filters aa-product-filters--drawer alignwide', $html );
	}

	/**
	 * The wrapper mirrors a full-width alignment class onto its container.
	 *
	 * @return void
	 */
	public function test_full_alignment_is_mirrored_onto_wrapper(): void {
		$html = $this->wrap( 'alignfull' );

		$this->assertStringContainsString( 'aa-product-filters--drawer alignfull', $html );
	}

	/**
	 * No alignment class leaves the wrapper at its default (content) width.
	 *
	 * @return void
	 */
	public function test_empty_alignment_adds_no_class(): void {
		$html = $this->wrap( '' );

		$this->assertStringContainsString( 'class="aa-product-filters aa-product-filters--drawer"', $html );
		$this->assertStringNotContainsString( 'align', $html );
	}
}
