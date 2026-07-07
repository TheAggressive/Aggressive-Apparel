<?php
/**
 * Product context tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Product_Context;
use WP_UnitTestCase;

/**
 * Verify request context follows WordPress's resolved block template.
 */
class TestProductContext extends WP_UnitTestCase {

	/** Original resolved template ID. */
	private mixed $original_template_id = null;

	/** Whether the global existed before the test. */
	private bool $template_id_was_set = false;

	/**
	 * Preserve WordPress's resolved-template global.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		global $_wp_current_template_id;
		$this->template_id_was_set = isset( $_wp_current_template_id );
		$this->original_template_id = $_wp_current_template_id ?? null;
	}

	/**
	 * Restore WordPress's resolved-template global.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		global $_wp_current_template_id;

		if ( $this->template_id_was_set ) {
			$_wp_current_template_id = $this->original_template_id;
		} else {
			unset( $_wp_current_template_id );
		}

		parent::tearDown();
	}

	/**
	 * Term-specific templates selected by core must be forwarded to AJAX cards.
	 *
	 * @return void
	 */
	public function test_uses_wordpress_resolved_template_slug(): void {
		global $_wp_current_template_id;
		$_wp_current_template_id = get_stylesheet() . '//taxonomy-product_cat-shirts';

		$this->assertSame(
			'taxonomy-product_cat-shirts',
			Product_Context::get_current_template_slug()
		);
	}

	/**
	 * A template ID for another theme must never be forwarded to the endpoint.
	 *
	 * @return void
	 */
	public function test_ignores_resolved_template_from_another_theme(): void {
		global $_wp_current_template_id;
		$_wp_current_template_id = 'another-theme//private-template';

		$this->assertSame( 'archive-product', Product_Context::get_current_template_slug() );
	}

	/**
	 * Malformed resolved IDs fall back instead of becoming template paths.
	 *
	 * @return void
	 */
	public function test_rejects_malformed_resolved_template_slug(): void {
		global $_wp_current_template_id;
		$_wp_current_template_id = get_stylesheet() . '//../taxonomy-product_cat-shirts';

		$this->assertSame( 'archive-product', Product_Context::get_current_template_slug() );
	}
}
