<?php
/**
 * Test Color Block Swatch Manager Class
 *
 * Fixtures mirror the real WooCommerce 10.x "Add to Cart with Options" variation
 * selector markup: each option is a `wc-block-product-filter-chips__item`
 * <button> (with a static `value` slug + aria-checked), not the old
 * `<label>/<input>` pill. The contract test below fails loudly if WooCommerce
 * renames the block again, so this can't silently rot.
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use WP_UnitTestCase;
use WP_Block_Type_Registry;
use Aggressive_Apparel\WooCommerce\Color_Block_Swatch_Manager;
use Aggressive_Apparel\WooCommerce\Block_Pill_Helper;

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

		// WooCommerce registers pa_color; register it here so the unit test is
		// self-contained when WooCommerce isn't loaded in the test environment.
		if ( ! taxonomy_exists( 'pa_color' ) ) {
			register_taxonomy( 'pa_color', 'product', array( 'hierarchical' => false ) );
		}

		$this->swatch_manager = new Color_Block_Swatch_Manager();
		$this->swatch_manager->init();

		$this->create_test_color_terms();
	}

	/**
	 * Tear down test
	 */
	public function tearDown(): void {
		$this->cleanup_test_color_terms();

		parent::tearDown();
	}

	/**
	 * Build a colour attribute block in the WooCommerce 10.x chip format.
	 *
	 * @param array<string, string> $options slug => label.
	 * @return string Block HTML.
	 */
	private function color_attribute_block( array $options = array( 'blue' => 'Blue' ) ): string {
		$buttons = '';
		foreach ( $options as $slug => $label ) {
			$buttons .= sprintf(
				'<button class="wc-block-product-filter-chips__item" type="button" role="radio" id="attribute_pa_color-%1$s" aria-label="%2$s" value="%1$s" aria-checked="false" data-wp-context="{&quot;item&quot;:{&quot;label&quot;:&quot;%2$s&quot;}}">'
					. '<span class="wc-block-product-filter-chips__text" data-wp-text="context.item.label">%2$s</span>'
					. '</button>',
				$slug,
				$label
			);
		}

		return '<div class="wp-block-woocommerce-add-to-cart-with-options-variation-selector-attribute">'
			. '<div class="wc-block-product-filter-chips"><fieldset class="wc-block-product-filter-chips__fieldset">'
			. '<div class="wc-block-product-filter-chips__items">' . $buttons . '</div>'
			. '</fieldset></div>'
			. '<input type="hidden" name="attribute_pa_color" value="">'
			. '</div>';
	}

	/**
	 * The attribute block array passed to render_block.
	 *
	 * @return array<string, mixed>
	 */
	private function color_block_args(): array {
		return array(
			'blockName' => Block_Pill_Helper::BLOCK_NAME,
			'attrs'     => array(),
		);
	}

	/**
	 * Hooks are registered on init.
	 */
	public function test_swatch_manager_initialization(): void {
		$this->assertInstanceOf( Color_Block_Swatch_Manager::class, $this->swatch_manager );
		$this->assertNotFalse( has_filter( 'render_block', array( $this->swatch_manager, 'inject_color_swatches_in_block' ) ) );
	}

	/**
	 * A colour block with no option buttons is returned untouched.
	 */
	public function test_color_block_without_buttons_not_modified(): void {
		$original = '<div class="wp-block-woocommerce-add-to-cart-with-options-variation-selector-attribute">'
			. '<input type="hidden" name="attribute_pa_color" value=""></div>';

		$result = $this->swatch_manager->inject_color_swatches_in_block( $original, $this->color_block_args() );

		$this->assertEquals( $original, $result );
	}

	/**
	 * Blocks that aren't the variation attribute block are ignored.
	 */
	public function test_different_blocks_not_modified(): void {
		$original = '<div class="some-other-block">Content</div>';

		$result = $this->swatch_manager->inject_color_swatches_in_block(
			$original,
			array(
				'blockName' => 'core/paragraph',
				'attrs'     => array(),
			)
		);

		$this->assertEquals( $original, $result );
	}

	/**
	 * Swatches are injected into the chip buttons for a solid colour attribute.
	 */
	public function test_block_processing_solid_colors(): void {
		$result = $this->swatch_manager->inject_color_swatches_in_block(
			$this->color_attribute_block(
				array(
					'blue' => 'Blue',
					'red'  => 'Red',
				)
			),
			$this->color_block_args()
		);

		// Swatch markup.
		$this->assertStringContainsString( 'aggressive-apparel-color-swatch__circle', $result );
		$this->assertStringContainsString( 'background-color:', $result );

		// The chip button is tagged so CSS can restyle it.
		$this->assertStringContainsString( 'aggressive-apparel-color-chip', $result );

		// The swatch is decorative — the chip <button>'s own aria-label carries
		// the name, so the swatch is aria-hidden (no duplicate announcement) and
		// has no role/title/tabindex of its own.
		$this->assertStringContainsString( 'aria-hidden="true"', $result );
		$this->assertStringContainsString( 'aria-label="Blue"', $result, 'button name preserved' );
		$this->assertStringNotContainsString( 'role="img"', $result );
		$this->assertStringNotContainsString( 'tabindex', $result );

		// Data attributes drive the styling + the hover/focus colour-name tooltip.
		$this->assertStringContainsString( 'data-color="#0000FF"', $result );
		$this->assertStringContainsString( 'data-color-name="Blue"', $result );

		// Both options got a swatch.
		$this->assertSame( 2, substr_count( $result, 'aggressive-apparel-color-swatch__circle' ) );
	}

	/**
	 * The swatch is idempotent — re-running doesn't double-inject.
	 */
	public function test_injection_is_idempotent(): void {
		$once  = $this->swatch_manager->inject_color_swatches_in_block( $this->color_attribute_block(), $this->color_block_args() );
		$twice = $this->swatch_manager->inject_color_swatches_in_block( $once, $this->color_block_args() );

		$this->assertSame(
			substr_count( $once, 'aggressive-apparel-color-swatch__circle' ),
			substr_count( $twice, 'aggressive-apparel-color-swatch__circle' )
		);
	}

	/**
	 * show_label toggles the `--with-label` modifier (the chip text is always
	 * kept in the markup; CSS hides it when labels are off).
	 */
	public function test_show_label_functionality(): void {
		$this->swatch_manager->set_show_label( false );
		$no_label = $this->swatch_manager->inject_color_swatches_in_block( $this->color_attribute_block(), $this->color_block_args() );
		$this->assertStringNotContainsString( 'aggressive-apparel-color-swatch--with-label', $no_label );

		$this->swatch_manager->set_show_label( true );
		$with_label = $this->swatch_manager->inject_color_swatches_in_block( $this->color_attribute_block(), $this->color_block_args() );
		$this->assertStringContainsString( 'aggressive-apparel-color-swatch--with-label', $with_label );
	}

	/**
	 * Contract guard: WooCommerce must still register the block we target.
	 *
	 * This is the check that would have caught the silent break when WooCommerce
	 * renamed the variation selector block. Skips where WooCommerce is inactive.
	 */
	public function test_block_contract_matches_woocommerce(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}

		$this->assertTrue(
			WP_Block_Type_Registry::get_instance()->is_registered( Block_Pill_Helper::BLOCK_NAME ),
			'WooCommerce no longer registers "' . Block_Pill_Helper::BLOCK_NAME . '". The variation swatch/pill/tooltip integration (Block_Pill_Helper, Color_Block_Swatch_Manager, Size_Option_Sorter, Swatch_Tooltips, variation-pills.css) must be updated to the new block.'
		);
	}

	/**
	 * Create test color terms.
	 */
	private function create_test_color_terms(): void {
		$terms = array(
			'blue'  => array(
				'name'  => 'Blue',
				'color' => '#0000FF',
			),
			'red'   => array(
				'name'  => 'Red',
				'color' => '#FF0000',
			),
			'green' => array(
				'name'  => 'Green',
				'color' => '#00FF00',
			),
		);

		foreach ( $terms as $slug => $data ) {
			$term = wp_insert_term( $data['name'], 'pa_color', array( 'slug' => $slug ) );
			if ( ! is_wp_error( $term ) ) {
				update_term_meta( $term['term_id'], 'color_value', $data['color'] );
			}
		}
	}

	/**
	 * Clean up test color terms.
	 */
	private function cleanup_test_color_terms(): void {
		$terms = get_terms(
			array(
				'taxonomy'   => 'pa_color',
				'hide_empty' => false,
			)
		);

		foreach ( $terms as $term ) {
			wp_delete_term( $term->term_id, 'pa_color' );
		}
	}
}
