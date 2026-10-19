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
		$this->visit_archive();

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

	/** A term named like the label keeps its title; only the label crumb changes. */
	public function test_only_the_label_crumb_changes(): void {
		$this->visit_archive();

		$crumbs = Attribute_Breadcrumb::crumbs(
			array(
				array( 'Home', '/' ),
				array( 'Product Collection', '' ),
				array( 'Product Collection', '/collection/product-collection/' ),
			)
		);

		$this->assertSame( 'Collection', $crumbs[1][0] );
		$this->assertSame( 'Product Collection', $crumbs[2][0] );
	}

	/** With no attribute name to use, WooCommerce's label stays, never the slug. */
	public function test_missing_name_keeps_the_label(): void {
		$this->visit_archive();
		add_filter( 'woocommerce_attribute_label', array( $this, 'slug_label' ), 10, 2 );

		$crumbs = Attribute_Breadcrumb::crumbs( array( array( 'Home', '/' ), array( 'Product Collection', '' ) ) );
		remove_filter( 'woocommerce_attribute_label', array( $this, 'slug_label' ), 10 );

		$this->assertSame( 'Product Collection', $crumbs[1][0] );
	}

	/**
	 * A label filter that returns the bare slug, as when no name is found.
	 *
	 * @param string $label Label.
	 * @param string $name  Taxonomy.
	 */
	public function slug_label( $label, $name ): string {
		unset( $label );

		return wc_attribute_taxonomy_slug( (string) $name );
	}

	/** Anywhere else, the trail is left alone. */
	public function test_other_pages_are_untouched(): void {
		$crumbs = array( array( 'Home', '/' ), array( 'Product Collection', '' ) );

		$this->assertSame( $crumbs, Attribute_Breadcrumb::crumbs( $crumbs ) );
	}

	/**
	 * Create a Collection attribute with a term and visit its archive.
	 */
	private function visit_archive(): void {
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
	}
}
