<?php
/**
 * Size Guide dynamic block tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\Blocks\Blocks;
use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Size_Guide;
use ReflectionProperty;
use WP_Block;
use WP_Block_Type_Registry;
use WP_UnitTestCase;

/**
 * Covers block rendering, duplicate protection, and template placement.
 */
class TestSizeGuideBlock extends WP_UnitTestCase {

	/**
	 * Enable the feature and reset request-scoped render state.
	 */
	public function setUp(): void {
		parent::setUp();

		update_option(
			Feature_Settings::OPTION_KEY,
			array( 'size_guide' => true ),
			false
		);

		$this->reset_render_state();

		if ( ! Blocks::is_block_registered( 'aggressive-apparel/size-guide' ) ) {
			Blocks::register();
		}
	}

	/**
	 * Restore request-scoped state.
	 */
	public function tearDown(): void {
		delete_option( Feature_Settings::OPTION_KEY );
		$this->reset_render_state();

		parent::tearDown();
	}

	/**
	 * Assigned guide content renders through the product-context block.
	 */
	public function test_block_renders_assigned_guide_with_accessible_dialog_contract(): void {
		$product_id = $this->create_product_with_guide( '<p>Chest 38–40 inches</p>' );

		$html = $this->render_block(
			$product_id,
			array(
				'label'    => '<strong>Find your fit</strong>',
				'showIcon' => false,
			)
		);

		$this->assertStringContainsString( 'wp-block-aggressive-apparel-size-guide', $html );
		$this->assertStringContainsString( 'aggressive-apparel-size-guide-block', $html );
		$this->assertStringContainsString( 'data-wp-interactive="aggressive-apparel/size-guide"', $html );
		$this->assertStringContainsString( 'aria-haspopup="dialog"', $html );
		$this->assertStringContainsString( 'aria-modal="true"', $html );
		$this->assertStringContainsString( 'Find your fit', $html );
		$this->assertStringNotContainsString( '<strong>Find your fit</strong>', $html );
		$this->assertStringNotContainsString( 'aggressive-apparel-size-guide__trigger-icon', $html );
		$this->assertStringContainsString( 'Chest 38–40 inches', $html );
		$this->assertMatchesRegularExpression(
			'/<button(?![^>]*\sstyle=)[^>]*class="[^"]*wp-block-aggressive-apparel-size-guide[^"]*"/',
			$html
		);
	}

	/**
	 * Missing product context or assignment produces no empty UI shell.
	 */
	public function test_block_renders_nothing_without_an_assigned_guide(): void {
		$product_id = self::factory()->post->create( array( 'post_type' => 'product' ) );

		$this->assertSame( '', $this->render_block( $product_id ) );
	}

	/**
	 * Feature setting remains the authoritative kill switch.
	 */
	public function test_block_renders_nothing_when_feature_is_disabled(): void {
		$product_id = $this->create_product_with_guide( '<p>Guide</p>' );
		update_option( Feature_Settings::OPTION_KEY, array(), false );

		$this->assertSame( '', $this->render_block( $product_id ) );
	}

	/**
	 * Malformed templates containing duplicate blocks emit one dialog only.
	 */
	public function test_duplicate_block_render_is_suppressed_per_request(): void {
		$product_id = $this->create_product_with_guide( '<p>Guide</p>' );

		$this->assertNotSame( '', $this->render_block( $product_id ) );
		$this->assertSame( '', $this->render_block( $product_id ) );
	}

	/**
	 * Native design supports style the trigger rather than the dialog shell.
	 */
	public function test_native_design_supports_are_applied_to_the_trigger(): void {
		$product_id = $this->create_product_with_guide( '<p>Guide</p>' );

		$html = $this->render_block(
			$product_id,
			array(
				'align'      => 'center',
				'fontFamily' => 'bebas-neue',
				'style'      => array(
					'border'     => array(
						'color'  => '#ff0033',
						'radius' => '7px',
						'style'  => 'solid',
						'width'  => '2px',
					),
					'color'      => array(
						'background' => '#111111',
						'text'       => '#f5f5f5',
					),
					'spacing'    => array(
						'padding' => array(
							'top'    => '12px',
							'right'  => '18px',
							'bottom' => '12px',
							'left'   => '18px',
						),
					),
					'shadow'     => '0 2px 8px #00000033',
					'typography' => array(
						'fontSize'       => '18px',
						'fontStyle'      => 'italic',
						'fontWeight'     => '600',
						'letterSpacing'  => '0.08em',
						'lineHeight'     => '1.4',
						'textDecoration' => 'underline',
						'textTransform'  => 'none',
					),
				),
			)
		);

		$this->assertMatchesRegularExpression(
			'/<button[^>]*class="[^"]*aggressive-apparel-size-guide__trigger[^"]*wp-block-aggressive-apparel-size-guide[^"]*"/',
			$html
		);
		$this->assertMatchesRegularExpression( '/<button[^>]*class="[^"]*aligncenter[^"]*"/', $html );
		$this->assertMatchesRegularExpression( '/<button[^>]*style="[^"]*background-color:#111111/', $html );
		$this->assertMatchesRegularExpression( '/<button[^>]*style="[^"]*color:#f5f5f5/', $html );
		$this->assertMatchesRegularExpression( '/<button[^>]*style="[^"]*border-color:#ff0033/', $html );
		$this->assertMatchesRegularExpression( '/<button[^>]*style="[^"]*border-radius:7px/', $html );
		$this->assertMatchesRegularExpression( '/<button[^>]*style="[^"]*border-style:solid/', $html );
		$this->assertMatchesRegularExpression( '/<button[^>]*style="[^"]*border-width:2px/', $html );
		$this->assertMatchesRegularExpression( '/<button[^>]*style="[^"]*padding-top:12px/', $html );
		$this->assertMatchesRegularExpression(
			'/<button[^>]*class="[^"]*has-bebas-neue-font-family[^"]*"/',
			$html
		);
		$this->assertMatchesRegularExpression( '/<button[^>]*style="[^"]*font-size:clamp\([^"]*18px\)/', $html );
		$this->assertMatchesRegularExpression( '/<button[^>]*style="[^"]*font-style:italic/', $html );
		$this->assertMatchesRegularExpression( '/<button[^>]*style="[^"]*font-weight:600/', $html );
		$this->assertMatchesRegularExpression( '/<button[^>]*style="[^"]*letter-spacing:0\.08em/', $html );
		$this->assertMatchesRegularExpression( '/<button[^>]*style="[^"]*text-decoration:underline/', $html );
		$this->assertMatchesRegularExpression( '/<button[^>]*style="[^"]*text-transform:none/', $html );
		$this->assertMatchesRegularExpression( '/<button[^>]*style="[^"]*box-shadow:0 2px 8px #00000033/', $html );
		$this->assertStringNotContainsString(
			'<div class="aggressive-apparel-size-guide-block" style=',
			$html
		);
	}

	/**
	 * The registered block exposes the complete native design panel contract.
	 */
	public function test_registered_block_exposes_native_design_supports(): void {
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered(
			'aggressive-apparel/size-guide'
		);

		$this->assertNotNull( $block_type );
		$this->assertTrue( $block_type->supports['spacing']['margin'] );
		$this->assertTrue( $block_type->supports['spacing']['padding'] );
		$this->assertTrue( $block_type->supports['color']['text'] );
		$this->assertTrue( $block_type->supports['color']['background'] );
		$this->assertTrue( $block_type->supports['color']['gradients'] );
		$this->assertTrue( $block_type->supports['__experimentalBorder']['color'] );
		$this->assertTrue( $block_type->supports['__experimentalBorder']['radius'] );
		$this->assertTrue( $block_type->supports['__experimentalBorder']['style'] );
		$this->assertTrue( $block_type->supports['__experimentalBorder']['width'] );
		$this->assertTrue( $block_type->supports['typography']['fontSize'] );
		$this->assertTrue( $block_type->supports['typography']['__experimentalFontFamily'] );
		$this->assertTrue( $block_type->supports['typography']['lineHeight'] );
		$this->assertTrue( $block_type->supports['typography']['__experimentalFontStyle'] );
		$this->assertTrue( $block_type->supports['typography']['__experimentalFontWeight'] );
		$this->assertTrue( $block_type->supports['typography']['__experimentalLetterSpacing'] );
		$this->assertTrue( $block_type->supports['typography']['__experimentalTextDecoration'] );
		$this->assertTrue( $block_type->supports['typography']['__experimentalTextTransform'] );
		$this->assertTrue( $block_type->supports['shadow'] );
	}

	/**
	 * Theme template places the explicit block before add-to-cart.
	 */
	public function test_theme_template_places_block_before_add_to_cart(): void {
		$template = file_get_contents( get_template_directory() . '/templates/single-product.html' );

		$this->assertIsString( $template );
		$this->assertStringContainsString( '<!-- wp:aggressive-apparel/size-guide /-->', $template );
		$this->assertLessThan(
			strpos( $template, '<!-- wp:woocommerce/add-to-cart-with-options /-->' ),
			strpos( $template, '<!-- wp:aggressive-apparel/size-guide /-->' )
		);
	}

	/**
	 * Render the registered block with explicit product context.
	 *
	 * @param int                  $product_id Product post ID.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	private function render_block( int $product_id, array $attributes = array() ): string {
		$parsed = array(
			'blockName'    => 'aggressive-apparel/size-guide',
			'attrs'        => $attributes,
			'innerBlocks'  => array(),
			'innerContent' => array(),
		);

		$block = new WP_Block(
			$parsed,
			array(
				'postId'   => $product_id,
				'postType' => 'product',
			)
		);

		return (string) $block->render();
	}

	/**
	 * Create a product with a published canonical guide assignment.
	 *
	 * @param string $content Size guide post content.
	 * @return int Product post ID.
	 */
	private function create_product_with_guide( string $content ): int {
		$product_id = self::factory()->post->create( array( 'post_type' => 'product' ) );
		$guide_id   = self::factory()->post->create(
			array(
				'post_type'    => 'aa_size_guide',
				'post_status'  => 'publish',
				'post_content' => $content,
			)
		);

		update_post_meta( $product_id, '_aggressive_apparel_size_guide_id', $guide_id );

		return $product_id;
	}

	/**
	 * Reset the service's request-scoped duplicate guard.
	 */
	private function reset_render_state(): void {
		$property = new ReflectionProperty( Size_Guide::class, 'did_render' );
		$property->setValue( null, false );
	}
}
