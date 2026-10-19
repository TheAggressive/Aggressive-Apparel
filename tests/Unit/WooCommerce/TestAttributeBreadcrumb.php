<?php
/**
 * Attribute archive breadcrumb label.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Attribute_Breadcrumb;
use WP_UnitTestCase;

/**
 * Covers the attribute name replacing WooCommerce's "Product …" label.
 */
class TestAttributeBreadcrumb extends WP_UnitTestCase {

	/**
	 * Unregister the test attribute's taxonomy.
	 */
	public function tearDown(): void {
		unset( $GLOBALS['wc_product_attributes']['pa_bc_collection'] );
		unregister_taxonomy( 'pa_bc_collection' );
		delete_transient( 'wc_attribute_taxonomies' );
		parent::tearDown();
	}

	/** On an attribute archive, the middle crumb is the attribute's name. */
	public function test_attribute_archive_uses_attribute_name(): void {
		$id = wc_create_attribute(
			array(
				'name'         => 'Collection',
				'slug'         => 'bc_collection',
				'has_archives' => true,
			)
		);
		// WooCommerce lists attributes in this global once, at init; a request
		// for a real archive has it, this test adds its own.
		$GLOBALS['wc_product_attributes']['pa_bc_collection'] = wc_get_attribute( $id );
		register_taxonomy(
			'pa_bc_collection',
			array( 'product' ),
			array(
				'public' => true,
				'labels' => array( 'name' => 'Product Collection' ),
			)
		);
		$term = self::factory()->term->create(
			array(
				'taxonomy' => 'pa_bc_collection',
				'name'     => 'Womens',
			)
		);
		$this->go_to( (string) get_term_link( $term, 'pa_bc_collection' ) );

		$crumbs = Attribute_Breadcrumb::crumbs(
			array(
				array( 'Home', '/' ),
				array( 'Product Collection', '' ),
				array( 'Womens', '/collection/womens/' ),
			)
		);

		$this->assertSame( 'Collection', $crumbs[1][0] );
		$this->assertSame( 'Womens', $crumbs[2][0], 'Other crumbs are untouched.' );
	}

	/** Anywhere else, the trail is left alone. */
	public function test_other_pages_are_untouched(): void {
		$crumbs = array( array( 'Home', '/' ), array( 'Product Collection', '' ) );

		$this->assertSame( $crumbs, Attribute_Breadcrumb::crumbs( $crumbs ) );
	}
}
