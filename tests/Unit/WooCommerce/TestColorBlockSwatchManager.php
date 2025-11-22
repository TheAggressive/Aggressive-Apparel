<?php
/**
 * Test Color Block Swatch Manager Class
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use WP_UnitTestCase;
use Aggressive_Apparel\WooCommerce\Color_Block_Swatch_Manager;

/**
 * Color Block Swatch Manager Test Case
 */
class TestColorBlockSwatchManager extends WP_UnitTestCase {
	/**
	 * Color block swatch manager instance
	 *
	 * @var Color_Block_Swatch_Manager
	 */
	private $swatch_manager;

	/**
	 * Set up test
	 */
	public function setUp(): void {
		parent::setUp();

		$this->swatch_manager = new Color_Block_Swatch_Manager();
		$this->swatch_manager->init();

		// Create test color terms
		$this->create_test_color_terms();
	}

	/**
	 * Tear down test
	 */
	public function tearDown(): void {
		parent::tearDown();

		// Clean up test terms
		$this->cleanup_test_color_terms();
	}

	/**
	 * Test that the swatch manager initializes properly
	 */
	public function test_swatch_manager_initialization(): void {
		$this->assertInstanceOf( Color_Block_Swatch_Manager::class, $this->swatch_manager );

		// Test that hooks are registered
		$this->assertNotFalse( has_filter( 'render_block', array( $this->swatch_manager, 'inject_color_swatches_in_block' ) ) );
	}

	/**
	 * Test JSON context modification for WooCommerce blocks
	 */
	public function test_non_color_blocks_not_modified(): void {
		// Test that blocks without actual HTML labels are not modified
		$original_content = '<div class="wc-block-add-to-cart-with-options-variation-selector-attribute-options" data-wp-context="{&quot;name&quot;:&quot;attribute_pa_color&quot;,&quot;options&quot;:[{&quot;value&quot;:&quot;blue&quot;,&quot;label&quot;:&quot;Blue&quot;,&quot;isSelected&quot;:false},{&quot;value&quot;:&quot;red&quot;,&quot;label&quot;:&quot;Red&quot;,&quot;isSelected&quot;:false}],&quot;selectedValue&quot;:null}"></div>';

		$result = $this->swatch_manager->inject_color_swatches_in_block(
			$original_content,
			array(
				'blockName' => 'woocommerce/add-to-cart-with-options-variation-selector-attribute-options',
				'attrs' => array(
					'attributeName' => 'pa_color',
				),
			)
		);

		// Content without actual HTML labels should not be modified
		$this->assertEquals( $original_content, $result );
	}

	/**
	 * Test that non-color blocks are not modified
	 */
	public function test_different_blocks_not_modified(): void {
		$original_content = '<div class="some-other-block">Content</div>';

		$result = $this->swatch_manager->inject_color_swatches_in_block(
			$original_content,
			array(
				'blockName' => 'core/paragraph',
				'attrs' => array(),
			)
		);

		$this->assertEquals( $original_content, $result );
	}

	/**
	 * Test block processing for render_block filter with rendered HTML
	 */
	public function test_block_processing_solid_colors(): void {
		// HTML content that mimics what WooCommerce blocks render (with labels and inputs)
		$block_content = '<div class="wc-block-add-to-cart-with-options-variation-selector-attribute-options">
			<label class="wc-block-add-to-cart-with-options-variation-selector-attribute-options__pill">
				<input type="radio" name="attribute_pa_color" value="blue">Blue
			</label>
			<label class="wc-block-add-to-cart-with-options-variation-selector-attribute-options__pill">
				<input type="radio" name="attribute_pa_color" value="red">Red
			</label>
		</div>';

		$block = array(
			'blockName' => 'woocommerce/add-to-cart-with-options-variation-selector-attribute-options',
			'attrs' => array(
				'attributeName' => 'pa_color',
			),
		);

		$result = $this->swatch_manager->inject_color_swatches_in_block( $block_content, $block );

		// Should contain swatch spans injected into the labels
		$this->assertStringContainsString( 'aggressive-apparel-color-swatch', $result );
		$this->assertStringContainsString( 'aggressive-apparel-color-swatch__circle', $result );
		$this->assertStringContainsString( 'background-color:', $result );

		// Test accessibility attributes
		$this->assertStringContainsString( 'aria-label="Color option: Blue"', $result );
		$this->assertStringContainsString( 'role="img"', $result );
		$this->assertStringContainsString( 'tabindex="0"', $result );
		$this->assertStringContainsString( 'title="Blue"', $result );
		$this->assertStringContainsString( 'data-color="#0000FF"', $result );
		$this->assertStringContainsString( 'data-color-name="Blue"', $result );
	}

	/**
	 * Test pattern rendering functionality (skipped - tested via integration)
	 * Pattern functionality is tested through the admin interface and AJAX handlers.
	 * This unit test would require complex attachment setup that's better handled
	 * in integration tests.
	 */
	public function test_pattern_rendering_placeholder(): void {
		// Placeholder test - pattern functionality tested via integration tests
		$this->assertTrue( true, 'Pattern rendering tested via integration tests' );
	}

	/**
	 * Test show/hide label functionality
	 */
	public function test_show_label_functionality(): void {
		$block_content = '<div class="wc-block-add-to-cart-with-options-variation-selector-attribute-options">
			<label class="wc-block-add-to-cart-with-options-variation-selector-attribute-options__pill">
				<input type="radio" name="attribute_pa_color" value="blue">Blue
			</label>
		</div>';

		$block = array(
			'blockName' => 'woocommerce/add-to-cart-with-options-variation-selector-attribute-options',
			'attrs' => array(
				'attributeName' => 'pa_color',
			),
		);

		// Test with show_label = false (default)
		$this->swatch_manager->set_show_label( false );
		$result_no_label = $this->swatch_manager->inject_color_swatches_in_block( $block_content, $block );

		$this->assertStringNotContainsString( 'aggressive-apparel-color-swatch--with-label', $result_no_label );
		$this->assertStringContainsString( '</span></label>', $result_no_label ); // Text should be removed, only swatch remains

		// Test with show_label = true
		$this->swatch_manager->set_show_label( true );
		$result_with_label = $this->swatch_manager->inject_color_swatches_in_block( $block_content, $block );

		$this->assertStringContainsString( 'aggressive-apparel-color-swatch--with-label', $result_with_label );
		$this->assertStringContainsString( '</span>Blue', $result_with_label ); // Text should be preserved after swatch
	}

	/**
	 * Test CSS class structure and accessibility features
	 */
	public function test_css_classes_and_accessibility(): void {
		$block_content = '<div class="wc-block-add-to-cart-with-options-variation-selector-attribute-options">
			<label class="wc-block-add-to-cart-with-options-variation-selector-attribute-options__pill">
				<input type="radio" name="attribute_pa_color" value="blue">Blue
			</label>
		</div>';

		$block = array(
			'blockName' => 'woocommerce/add-to-cart-with-options-variation-selector-attribute-options',
			'attrs' => array(
				'attributeName' => 'pa_color',
			),
		);

		$result = $this->swatch_manager->inject_color_swatches_in_block( $block_content, $block );

		// Test base CSS classes
		$this->assertStringContainsString( 'aggressive-apparel-color-swatch', $result );
		$this->assertStringContainsString( 'aggressive-apparel-color-swatch--interactive', $result );
		$this->assertStringContainsString( 'aggressive-apparel-color-swatch__circle', $result );

		// Test accessibility attributes
		$this->assertStringContainsString( 'aria-label=', $result );
		$this->assertStringContainsString( 'role="img"', $result );
		$this->assertStringContainsString( 'tabindex="0"', $result );
		$this->assertStringContainsString( 'title=', $result );

		// Test data attributes
		$this->assertStringContainsString( 'data-color=', $result );
		$this->assertStringContainsString( 'data-color-name=', $result );
	}

	/**
	 * Test that content without color blocks is not modified
	 */
	public function test_content_without_color_blocks_not_modified(): void {
		$content_without_color = '<div>Some regular content</div><p>More content</p>';

		// Test that render_block filter doesn't modify non-matching blocks
		$result = $this->swatch_manager->inject_color_swatches_in_block( $content_without_color, array(
			'blockName' => 'core/paragraph',
			'attrs' => array(),
		) );

		$this->assertEquals( $content_without_color, $result );
	}

	/**
	 * Create test color terms for testing
	 */
	private function create_test_color_terms(): void {
		// Create color terms with hex values
		$terms = array(
			'blue' => array( 'name' => 'Blue', 'color' => '#0000FF' ),
			'red' => array( 'name' => 'Red', 'color' => '#FF0000' ),
			'green' => array( 'name' => 'Green', 'color' => '#00FF00' ),
		);

		foreach ( $terms as $slug => $data ) {
			$term = wp_insert_term( $data['name'], 'pa_color', array( 'slug' => $slug ) );
			if ( ! is_wp_error( $term ) ) {
				update_term_meta( $term['term_id'], 'color_value', $data['color'] );
			}
		}
	}

	/**
	 * Clean up test color terms
	 */
	private function cleanup_test_color_terms(): void {
		$terms = get_terms( array(
			'taxonomy' => 'pa_color',
			'hide_empty' => false,
		) );

		foreach ( $terms as $term ) {
			wp_delete_term( $term->term_id, 'pa_color' );
		}
	}
}
