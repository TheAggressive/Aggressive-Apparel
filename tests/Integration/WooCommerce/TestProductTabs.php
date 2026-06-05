<?php
/**
 * Product Tabs integration tests.
 *
 * @package Aggressive_Apparel\Tests\Integration\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Integration\WooCommerce;

use Aggressive_Apparel\WooCommerce\Product_Tabs;
use Aggressive_Apparel\WooCommerce\Product_Tabs_Config;
use Aggressive_Apparel\WooCommerce\Product_Tabs_Sanitizer;
use WP_UnitTestCase;

/**
 * Product Tabs integration test case.
 */
class TestProductTabs extends WP_UnitTestCase {

	/**
	 * Product Tabs instance under test.
	 *
	 * @var Product_Tabs
	 */
	private Product_Tabs $product_tabs;

	/**
	 * Created product IDs for cleanup.
	 *
	 * @var int[]
	 */
	private array $created_product_ids = array();

	/**
	 * Set up each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for Product Tabs tests.' );
		}

		$this->product_tabs = new Product_Tabs();
		add_filter( 'woocommerce_product_tabs', array( $this->product_tabs, 'add_custom_tabs' ), 20 );

		delete_option( Product_Tabs_Config::OPTION_KEY );
	}

	/**
	 * Tear down each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		foreach ( $this->created_product_ids as $product_id ) {
			wp_delete_post( $product_id, true );
		}

		delete_option( Product_Tabs_Config::OPTION_KEY );
		remove_filter( 'woocommerce_product_tabs', array( $this->product_tabs, 'add_custom_tabs' ), 20 );

		parent::tearDown();
	}

	/**
	 * Invalid display styles should fall back to accordion.
	 *
	 * @return void
	 */
	public function test_sanitize_global_tabs_invalid_style_falls_back_to_accordion(): void {
		$sanitizer = new Product_Tabs_Sanitizer();

		$result = $sanitizer->sanitize_global_tabs(
			array(
				'display_style' => 'not-a-real-style',
			)
		);

		$this->assertSame( 'accordion', $result['display_style'] );
	}

	/**
	 * Hide override should remove a global tab from the registry.
	 *
	 * @return void
	 */
	public function test_hide_override_removes_global_shipping_tab(): void {
		update_option(
			Product_Tabs_Config::OPTION_KEY,
			array(
				'shipping_returns' => 'Global shipping policy.',
			)
		);

		$product_id = $this->create_simple_product();
		update_post_meta(
			$product_id,
			Product_Tabs_Config::PRODUCT_TAB_OVERRIDES_META_KEY,
			array(
				'shipping_returns' => array(
					'mode'    => 'hide',
					'content' => '',
				),
			)
		);

		global $post, $product;
		$post    = get_post( $product_id );
		$product = wc_get_product( $product_id );

		$tabs = apply_filters( 'woocommerce_product_tabs', array() );

		$this->assertArrayNotHasKey( 'shipping_returns', $tabs );
	}

	/**
	 * Append override should add product-specific content to a global tab.
	 *
	 * @return void
	 */
	public function test_append_override_adds_product_content(): void {
		update_option(
			Product_Tabs_Config::OPTION_KEY,
			array(
				'shipping_returns' => 'Global shipping policy.',
			)
		);

		$product_id = $this->create_simple_product();
		update_post_meta(
			$product_id,
			Product_Tabs_Config::PRODUCT_TAB_OVERRIDES_META_KEY,
			array(
				'shipping_returns' => array(
					'mode'    => 'append',
					'content' => 'Product-specific shipping note.',
				),
			)
		);

		global $post, $product;
		$post    = get_post( $product_id );
		$product = wc_get_product( $product_id );

		$tabs = apply_filters( 'woocommerce_product_tabs', array() );

		$this->assertArrayHasKey( 'shipping_returns', $tabs );

		ob_start();
		call_user_func( $tabs['shipping_returns']['callback'], 'shipping_returns', $tabs['shipping_returns'] );
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'Global shipping policy.', $output );
		$this->assertStringContainsString( 'Product-specific shipping note.', $output );
	}

	/**
	 * Disabled custom tabs should not be registered.
	 *
	 * @return void
	 */
	public function test_disabled_custom_tab_is_not_registered(): void {
		$product_id = $this->create_simple_product();
		update_post_meta(
			$product_id,
			Product_Tabs_Config::PRODUCT_CUSTOM_TABS_META_KEY,
			array(
				array(
					'enabled'  => false,
					'key'      => 'disabled_tab',
					'title'    => 'Disabled Tab',
					'priority' => 40,
					'source'   => 'manual',
					'layout'   => 'rich_text',
					'content'  => 'Should not render.',
					'items'    => array(),
				),
			)
		);

		global $post, $product;
		$post    = get_post( $product_id );
		$product = wc_get_product( $product_id );

		$tabs = apply_filters( 'woocommerce_product_tabs', array() );

		foreach ( array_keys( $tabs ) as $key ) {
			$this->assertStringNotContainsString( 'disabled_tab', (string) $key );
		}
	}

	/**
	 * Accordion display style should render the expected wrapper markup.
	 *
	 * @return void
	 */
	public function test_render_tabs_by_style_outputs_accordion_markup(): void {
		update_option(
			Product_Tabs_Config::OPTION_KEY,
			array(
				'display_style' => 'accordion',
			)
		);

		$reflection = new \ReflectionClass( Product_Tabs::class );
		$renderer   = $reflection->getProperty( 'renderer' );
		$renderer->setAccessible( true );
		$renderer_instance = $renderer->getValue( $this->product_tabs );

		$html = $renderer_instance->render_tabs_by_style(
			array(
				array(
					'title'   => 'Details',
					'id'      => 'details',
					'content' => '<p>Tab body</p>',
				),
			),
			'<div>fallback</div>'
		);

		$this->assertStringContainsString( 'aa-product-info--accordion', $html );
		$this->assertStringContainsString( 'data-wp-interactive="aggressive-apparel/product-tabs"', $html );
		$this->assertStringContainsString( 'Tab body', $html );
	}

	/**
	 * Create a simple WooCommerce product fixture.
	 *
	 * @return int Product ID.
	 */
	private function create_simple_product(): int {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Product Tabs Test Product' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '19.00' );

		$saved_id = $product->save();
		if ( is_wp_error( $saved_id ) ) {
			$this->fail( 'Failed to create WooCommerce product fixture.' );
		}

		$id = (int) $saved_id;
		$this->created_product_ids[] = $id;

		return $id;
	}
}
