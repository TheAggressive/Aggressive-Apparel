<?php
/**
 * Managed Sales product-category integration tests.
 *
 * @package Aggressive_Apparel\Tests\Integration\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Integration\WooCommerce;

use Aggressive_Apparel\WooCommerce\Sale_Category;
use WP_UnitTestCase;

/**
 * Covers automatic membership in the native Sales product category.
 */
class TestSaleCategory extends WP_UnitTestCase {

	/** Service under test. */
	private Sale_Category $service;

	/**
	 * Create the service and clear WooCommerce's sale-product cache.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( '\WC_Product_Simple' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}

		delete_transient( 'wc_products_onsale' );
		$this->service = new Sale_Category();
	}

	/**
	 * Create a published simple product.
	 *
	 * @param bool $on_sale Whether the product should be on sale.
	 * @return \WC_Product_Simple
	 */
	private function create_product( bool $on_sale ): \WC_Product_Simple {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Sale category test ' . wp_generate_password( 6, false ) );
		$product->set_regular_price( '100' );
		if ( $on_sale ) {
			$product->set_sale_price( '75' );
		}
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->save();

		return $product;
	}

	/**
	 * The service creates or adopts the native product category.
	 *
	 * @return void
	 */
	public function test_ensure_category_creates_native_sales_term(): void {
		$term_id = $this->service->ensure_category();
		$term    = get_term( $term_id, 'product_cat' );

		$this->assertGreaterThan( 0, $term_id );
		$this->assertInstanceOf( \WP_Term::class, $term );
		$this->assertSame( Sale_Category::TERM_SLUG, $term->slug );
	}

	/**
	 * An active sale adds the product and ending it removes the product.
	 *
	 * @return void
	 */
	public function test_sync_product_tracks_live_sale_status(): void {
		$product = $this->create_product( true );
		$term_id = $this->service->ensure_category();

		$this->assertTrue( $this->service->sync_product( $product->get_id() ) );
		$this->assertTrue( has_term( $term_id, 'product_cat', $product->get_id() ) );

		$product->set_sale_price( '' );
		$product->save();

		$this->assertTrue( $this->service->sync_product( $product->get_id() ) );
		$this->assertFalse( has_term( $term_id, 'product_cat', $product->get_id() ) );
	}

	/**
	 * A full repair adds missing sale products and removes stale assignments.
	 *
	 * @return void
	 */
	public function test_reconcile_repairs_both_sides_of_membership(): void {
		$sale_product    = $this->create_product( true );
		$regular_product = $this->create_product( false );
		$term_id         = $this->service->ensure_category();

		wp_remove_object_terms( $sale_product->get_id(), array( $term_id ), 'product_cat' );
		$assigned = wp_set_object_terms( $regular_product->get_id(), array( $term_id ), 'product_cat', true );
		$this->assertNotWPError( $assigned );
		clean_object_term_cache( $regular_product->get_id(), 'product' );
		delete_transient( 'wc_products_onsale' );
		$this->assertNotContains( $regular_product->get_id(), wc_get_product_ids_on_sale(), 'The regular product must not be reported as on sale.' );
		$assigned_objects = array_map( 'intval', get_objects_in_term( array( $term_id ), 'product_cat' ) );
		$this->assertContains( $regular_product->get_id(), $assigned_objects, 'The stale test assignment must exist before repair: ' . wp_json_encode( $assigned_objects ) );

		$changed       = $this->service->reconcile();
		$after_objects = array_map( 'intval', get_objects_in_term( array( $term_id ), 'product_cat' ) );

		$this->assertSame( 2, $changed, 'The repair should add one missing and remove one stale assignment.' );
		$this->assertNotContains( $regular_product->get_id(), $after_objects, 'The stale database relationship should be removed.' );
		$this->assertTrue( has_term( $term_id, 'product_cat', $sale_product->get_id() ) );
		$this->assertFalse( has_term( $term_id, 'product_cat', $regular_product->get_id() ) );
	}

	/**
	 * Variation sale changes are applied to the parent catalogue product.
	 *
	 * @return void
	 */
	public function test_variation_sync_targets_parent_product(): void {
		$parent = new \WC_Product_Variable();
		$parent->set_name( 'Variable sale category test' );
		$parent->set_status( 'publish' );
		$parent_id = $parent->save();

		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $parent_id );
		$variation->set_regular_price( '100' );
		$variation->set_sale_price( '50' );
		$variation->set_status( 'publish' );
		$variation_id = $variation->save();

		\WC_Product_Variable::sync( $parent_id );
		wc_delete_product_transients( $parent_id );

		$term_id = $this->service->ensure_category();
		$this->assertTrue( $this->service->sync_product( $variation_id ) );
		$this->assertTrue( has_term( $term_id, 'product_cat', $parent_id ) );
		$this->assertFalse( has_term( $term_id, 'product_cat', $variation_id ) );
	}

	/**
	 * Background worker hooks are registered through the service lifecycle.
	 *
	 * @return void
	 */
	public function test_init_registers_batched_workers(): void {
		$this->service->init();

		$this->assertSame( 10, has_action( 'aggressive_apparel_sync_sale_products', array( $this->service, 'process_product_batch' ) ) );
		$this->assertSame( 10, has_action( 'aggressive_apparel_reconcile_sale_products', array( $this->service, 'process_reconciliation_batch' ) ) );
		$this->assertSame( 20, has_action( 'woocommerce_scheduled_sales', array( $this->service, 'scheduled_reconcile' ) ) );
	}

	/**
	 * A bounded product batch repairs additions and removals together.
	 *
	 * @return void
	 */
	public function test_product_batch_repairs_changed_products(): void {
		$sale_product    = $this->create_product( true );
		$regular_product = $this->create_product( false );
		$term_id         = $this->service->ensure_category();

		wp_set_object_terms( $regular_product->get_id(), array( $term_id ), 'product_cat', true );
		$this->service->process_product_batch( array( $sale_product->get_id(), $regular_product->get_id() ) );

		$this->assertTrue( has_term( $term_id, 'product_cat', $sale_product->get_id() ) );
		$this->assertFalse( has_term( $term_id, 'product_cat', $regular_product->get_id() ) );
	}

	/**
	 * A reconciliation worker advances its cursor and releases the run lock.
	 *
	 * @return void
	 */
	public function test_reconciliation_batch_records_health_and_completes(): void {
		$sale_product    = $this->create_product( true );
		$regular_product = $this->create_product( false );
		$term_id         = $this->service->ensure_category();
		$token           = 'test-reconciliation-token';

		wp_set_object_terms( $regular_product->get_id(), array( $term_id ), 'product_cat', true );
		update_option(
			'aggressive_apparel_sale_category_reconcile_lock',
			array(
				'token'      => $token,
				'expires_at' => time() + HOUR_IN_SECONDS,
			)
		);
		update_option(
			'aggressive_apparel_sale_category_status',
			array(
				'state'   => 'scheduled',
				'token'   => $token,
				'checked' => 0,
				'changed' => 0,
				'failed'  => 0,
			)
		);

		$this->service->process_reconciliation_batch( 0, $token );

		$status = get_option( 'aggressive_apparel_sale_category_status' );
		$this->assertIsArray( $status );
		$this->assertSame( 'complete', $status['state'] );
		$this->assertGreaterThanOrEqual( 2, $status['checked'] );
		$this->assertFalse( get_option( 'aggressive_apparel_sale_category_reconcile_lock' ) );
		$this->assertTrue( has_term( $term_id, 'product_cat', $sale_product->get_id() ) );
		$this->assertFalse( has_term( $term_id, 'product_cat', $regular_product->get_id() ) );
	}
}
