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
	 * Inline <script>/<style> blocks must be dropped whole — tag and contents —
	 * so wp_kses() cannot leave their source rendering as visible text (e.g.
	 * WooCommerce's comment-form unfiltered-html script in the reviews tab).
	 *
	 * @return void
	 */
	public function test_kses_tab_content_strips_script_and_style_blocks(): void {
		$content = new \Aggressive_Apparel\WooCommerce\Product_Tabs_Content();

		$html = '<p>Reviews</p>'
			. '<script>(function(){window.__pwn=1;})();</script>'
			. '<style>.x{color:red}</style>'
			. '<p>After</p>';

		$result = $content->kses_tab_content( $html );

		$this->assertStringNotContainsString( '__pwn', $result );
		$this->assertStringNotContainsString( 'color:red', $result );
		$this->assertStringNotContainsString( '<script', $result );
		$this->assertStringNotContainsString( '<style', $result );
		$this->assertStringContainsString( 'Reviews', $result );
		$this->assertStringContainsString( 'After', $result );
	}

	/**
	 * The tab-title heading strip must remove a redundant title heading whether
	 * it is top-level or nested in leading wrappers (WooCommerce nests the
	 * reviews title in #reviews > #comments), but never a non-matching heading
	 * or one that appears after real content.
	 *
	 * @return void
	 */
	public function test_strip_leading_tab_title_heading_handles_nesting(): void {
		$renderer = new \Aggressive_Apparel\WooCommerce\Product_Tabs_Renderer(
			new \Aggressive_Apparel\WooCommerce\Product_Tabs_Content(),
			$this->product_tabs
		);

		// Top-level heading matching the title is removed.
		$this->assertStringNotContainsString(
			'<h2>Description</h2>',
			$renderer->strip_leading_tab_title_heading( '<h2>Description</h2><p>Body</p>', 'Description' )
		);

		// Heading nested in leading wrappers (reviews) is removed; wrappers stay.
		$nested = '<div id="reviews"><div id="comments"><h2 class="woocommerce-Reviews-title">Reviews</h2><ol></ol></div></div>';
		$out    = $renderer->strip_leading_tab_title_heading( $nested, 'Reviews (0)' );
		$this->assertStringNotContainsString( 'woocommerce-Reviews-title', $out );
		$this->assertStringContainsString( 'id="reviews"', $out );
		$this->assertStringContainsString( 'id="comments"', $out );

		// A non-matching heading (populated reviews) is kept.
		$populated = '<div id="reviews"><h2 class="woocommerce-Reviews-title">3 reviews for Hat</h2></div>';
		$this->assertStringContainsString(
			'3 reviews for Hat',
			$renderer->strip_leading_tab_title_heading( $populated, 'Reviews (3)' )
		);

		// A heading that follows real content is never stripped.
		$this->assertStringContainsString(
			'<h2>Description</h2>',
			$renderer->strip_leading_tab_title_heading( '<p>Intro</p><h2>Description</h2>', 'Description' )
		);
	}

	/** The block owns frontend styles; no parallel legacy enqueue remains. */
	public function test_product_tabs_uses_block_style_pipeline_only(): void {
		$this->assertFalse( method_exists( $this->product_tabs, 'enqueue_assets' ) );
		$this->assertFileExists( AGGRESSIVE_APPAREL_DIR . '/src/blocks-interactivity/product-tabs/style.css' );
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
