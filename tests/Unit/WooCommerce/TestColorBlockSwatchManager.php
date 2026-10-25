<?php
/**
 * Test Color Block Swatch Manager Class
 *
 * Fixtures mirror WooCommerce's server render of a "Color / image" attribute
 * in the "Add to Cart with Options" variation selector: each option is a
 * `wc-block-product-filter-chips__item` <button> (static `value` slug,
 * `title`) holding a `wc-block-product-filter-chips__swatch` span painted from
 * the term's swatch meta. The contract tests fail loudly if WooCommerce renames
 * the block or the classes this targets.
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use WP_UnitTestCase;
use WP_Block_Type_Registry;
use Aggressive_Apparel\WooCommerce\Color_Block_Swatch_Manager;
use Aggressive_Apparel\WooCommerce\Color_Data_Manager;
use Aggressive_Apparel\WooCommerce\Block_Pill_Helper;

/**
 * Color Block Swatch Manager Test Case
 */
class TestColorBlockSwatchManager extends WP_UnitTestCase {

	/**
	 * Swatch manager under test.
	 *
	 * @var Color_Block_Swatch_Manager
	 */
	private Color_Block_Swatch_Manager $swatch_manager;

	/**
	 * Pattern image URL.
	 *
	 * @var string
	 */
	private string $image_url = '';

	/**
	 * Register pa_color and its terms.
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! taxonomy_exists( 'pa_color' ) ) {
			register_taxonomy( 'pa_color', 'product', array( 'hierarchical' => false ) );
		}

		$image           = (int) self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$this->image_url = (string) wp_get_attachment_url( $image );
		foreach ( array(
			'blue'  => array( 'Blue', 'color', '#0000ff' ),
			'plaid' => array( 'Plaid', 'image', $image ),
		) as $slug => $term ) {
			$created = wp_insert_term( $term[0], 'pa_color', array( 'slug' => $slug ) );
			if ( is_array( $created ) ) {
				update_term_meta( $created['term_id'], $term[1], $term[2] );
			}
		}
		Color_Data_Manager::flush_swatch_data_memo();

		$this->swatch_manager = new Color_Block_Swatch_Manager();
		$this->swatch_manager->init();
	}

	/**
	 * Drop the swatch cache.
	 */
	public function tearDown(): void {
		Color_Data_Manager::flush_swatch_data_memo();
		wp_dequeue_script( 'aggressive-apparel-color-swatch-chips' );
		remove_all_filters( 'aggressive_apparel_color_swatch_map' );
		parent::tearDown();
	}

	/**
	 * A colour attribute block as WooCommerce renders it.
	 *
	 * @param array<string, string> $options   slug => swatch style.
	 * @param string                $attribute Attribute taxonomy.
	 * @param array<int, string>    $overflow  Option slugs WooCommerce renders
	 *                                         only in the browser.
	 * @return string Block HTML.
	 */
	private function color_attribute_block( array $options, string $attribute = 'pa_color', array $overflow = array() ): string {
		$items = array_map(
			static fn( $slug ) => array(
				'value' => $slug,
				'label' => ucfirst( $slug ),
			),
			array_merge( array_keys( $options ), $overflow )
		);

		$buttons = '';
		foreach ( $options as $slug => $style ) {
			$buttons .= sprintf(
				'<button class="wc-block-product-filter-chips__item" type="button" role="radio" id="attribute_pa_color-%1$s" aria-label="%1$s" title="%1$s" value="%1$s" aria-checked="false">'
					. '<span class="wc-block-product-filter-chips__label">'
					. '<span class="wc-block-product-filter-chips__swatch" style="%2$s" aria-hidden="true"></span>'
					. '<span class="wc-block-product-filter-chips__text">%1$s</span>'
					. '</span></button>',
				$slug,
				$style
			);
		}

		return '<div class="wp-block-woocommerce-add-to-cart-with-options-variation-selector-attribute" data-wp-context=\'' . esc_attr( (string) wp_json_encode( array( 'items' => $items ) ) ) . '\'>'
			. '<div class="wp-block-woocommerce-product-filter-chips wc-block-product-filter-chips is-style-swatch">'
			. '<div class="wc-block-product-filter-chips__items">' . $buttons . '</div></div>'
			. '<input type="hidden" name="attribute_' . $attribute . '" value="">'
			. '</div>';
	}

	/**
	 * Render a block through the manager's filter.
	 *
	 * @param string $html Block HTML.
	 * @return string
	 */
	private function decorate( string $html ): string {
		return $this->swatch_manager->decorate_color_swatches( $html, array( 'blockName' => Block_Pill_Helper::BLOCK_NAME ) );
	}

	/**
	 * Get the first tag matching a query.
	 *
	 * @param string               $html  HTML.
	 * @param array<string, mixed> $query Tag query.
	 * @return \WP_HTML_Tag_Processor
	 */
	private function tag( string $html, array $query ): \WP_HTML_Tag_Processor {
		$processor = new \WP_HTML_Tag_Processor( $html );
		$this->assertTrue( $processor->next_tag( $query ), 'Tag not found: ' . wp_json_encode( $query ) );

		return $processor;
	}

	/**
	 * Colour chips gain the theme's classes, tooltip name and ring colour, and
	 * keep WooCommerce's own swatch paint.
	 */
	public function test_decorates_color_chips(): void {
		$html = $this->decorate( $this->color_attribute_block( array( 'blue' => 'background-color:#0000ff' ) ) );

		$chip = $this->tag( $html, array( 'tag_name' => 'BUTTON' ) );
		$this->assertTrue( $chip->has_class( 'aggressive-apparel-color-chip' ) );
		$this->assertNull( $chip->get_attribute( 'title' ), 'The styled tooltip replaces the browser one.' );
		$this->assertSame( '--swatch-color: #0000ff;', $chip->get_attribute( 'style' ) );

		$swatch = $this->tag( $html, array( 'class_name' => 'wc-block-product-filter-chips__swatch' ) );
		$this->assertTrue( $swatch->has_class( 'aggressive-apparel-color-swatch__circle' ) );
		$this->assertSame( 'Blue', $swatch->get_attribute( 'data-color-name' ) );
		$this->assertSame( '#0000ff', $swatch->get_attribute( 'data-color' ) );
		$this->assertSame( 'background-color:#0000ff', $swatch->get_attribute( 'style' ), "WooCommerce's paint is left alone." );
		$this->assertTrue( wp_script_is( 'aggressive-apparel-color-swatch-chips', 'enqueued' ) );
	}

	/**
	 * An image swatch is styled as a pattern with the neutral ring.
	 */
	public function test_decorates_pattern_chips(): void {
		$html = $this->decorate( $this->color_attribute_block( array( 'plaid' => "background-image:url('" . $this->image_url . "')" ) ) );

		$this->assertSame( '--swatch-color: rgb(0 0 0 / 40%);', $this->tag( $html, array( 'tag_name' => 'BUTTON' ) )->get_attribute( 'style' ) );
		$swatch = $this->tag( $html, array( 'class_name' => 'wc-block-product-filter-chips__swatch' ) );
		$this->assertTrue( $swatch->has_class( 'aggressive-apparel-color-swatch--pattern' ) );
		$this->assertNull( $swatch->get_attribute( 'data-color' ) );
	}

	/**
	 * A chip whose term has no swatch data is left as WooCommerce drew it.
	 */
	public function test_unknown_terms_are_untouched(): void {
		$html = $this->decorate( $this->color_attribute_block( array( 'nonexistent' => '' ) ) );

		$chip = $this->tag( $html, array( 'tag_name' => 'BUTTON' ) );
		$this->assertFalse( $chip->has_class( 'aggressive-apparel-color-chip' ) );
		$this->assertSame( 'nonexistent', $chip->get_attribute( 'title' ) );
	}

	/**
	 * Another "Color / image" attribute is not pa_color, even where a slug
	 * matches a pa_color term.
	 */
	public function test_other_visual_attributes_are_untouched(): void {
		$block = $this->color_attribute_block( array( 'blue' => 'background-color:#123456' ), 'pa_trim-color' );

		$this->assertSame( $block, $this->decorate( $block ) );
		$this->assertFalse( wp_script_is( 'aggressive-apparel-color-swatch-chips', 'enqueued' ) );
	}

	/**
	 * The chip list carries this attribute's swatch map — every option,
	 * including those WooCommerce renders only in the browser — for the
	 * script to decorate those chips; filters can extend each entry.
	 */
	public function test_list_carries_swatch_map_for_browser_rendered_chips(): void {
		add_filter(
			'aggressive_apparel_color_swatch_map',
			static function ( array $map ): array {
				$map['plaid']['tooltip'] = 'Plaid — Flannel';
				return $map;
			}
		);

		$html = $this->decorate( $this->color_attribute_block( array( 'blue' => 'background-color:#0000ff' ), 'pa_color', array( 'plaid' ) ) );

		$map = json_decode( (string) $this->tag( $html, array( 'class_name' => 'wc-block-product-filter-chips' ) )->get_attribute( 'data-aa-color-swatches' ), true );
		$this->assertSame(
			array(
				'name'    => 'Blue',
				'ring'    => '#0000ff',
				'pattern' => false,
				'color'   => '#0000ff',
			),
			$map['blue']
		);
		$this->assertTrue( $map['plaid']['pattern'] );
		$this->assertSame( 'rgb(0 0 0 / 40%)', $map['plaid']['ring'] );
		$this->assertSame( 'Plaid — Flannel', $map['plaid']['tooltip'] );
	}

	/**
	 * Attribute blocks without swatches (sizes) and other blocks are untouched.
	 */
	public function test_non_swatch_blocks_are_untouched(): void {
		$pills = '<div class="wp-block-woocommerce-add-to-cart-with-options-variation-selector-attribute"><button class="wc-block-product-filter-chips__item" value="m"><span class="wc-block-product-filter-chips__text">M</span></button><input type="hidden" name="attribute_pa_color" value=""></div>';
		$this->assertSame( $pills, $this->decorate( $pills ) );

		$block = $this->color_attribute_block( array( 'blue' => 'background-color:#0000ff' ) );
		$this->assertSame( $block, $this->swatch_manager->decorate_color_swatches( $block, array( 'blockName' => 'core/paragraph' ) ) );
	}

	/**
	 * Decorating is attribute-only and safe to repeat.
	 */
	public function test_decoration_adds_no_elements(): void {
		$block = $this->color_attribute_block( array( 'blue' => 'background-color:#0000ff' ) );
		$html  = $this->decorate( $block );

		$this->assertSame( substr_count( $block, '<' ), substr_count( $html, '<' ), 'No elements added.' );
		$this->assertSame( '--swatch-color: #0000ff;', $this->tag( $this->decorate( $html ), array( 'tag_name' => 'BUTTON' ) )->get_attribute( 'style' ) );
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
	 * Contract guard: WooCommerce's chips still render the swatch markup this
	 * decorates.
	 */
	public function test_swatch_markup_contract_matches_woocommerce(): void {
		if ( ! class_exists( '\Automattic\WooCommerce\Blocks\BlockTypes\ProductFilterChips' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}

		$source = (string) file_get_contents( (string) ( new \ReflectionClass( '\Automattic\WooCommerce\Blocks\BlockTypes\ProductFilterChips' ) )->getFileName() );
		foreach ( array( 'wc-block-product-filter-chips__item', 'wc-block-product-filter-chips__swatch', 'wc-block-product-filter-chips__label', 'wc-block-product-filter-chips__text' ) as $class ) {
			$this->assertStringContainsString( $class, $source, "WooCommerce's chips no longer render {$class}; update Color_Block_Swatch_Manager and color-swatches.css." );
		}
	}
}
