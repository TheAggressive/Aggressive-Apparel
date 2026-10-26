<?php
/**
 * A product collection loads its card blocks' assets.
 *
 * @package Aggressive_Apparel\Tests\Unit\Blocks
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\Blocks;

use Aggressive_Apparel\Blocks\Blocks;
use Aggressive_Apparel\Blocks\Card_Block_Assets;
use WP_Block_Type;
use WP_Block_Type_Registry;
use WP_UnitTestCase;

/**
 * Card blocks can render nothing (or never render) on the first page, while
 * cards added later (Load More, filters) still need their assets.
 */
class Card_Block_Assets_Test extends WP_UnitTestCase {

	private const CARD_BLOCKS = array(
		'aggressive-apparel/product-color-swatches',
		'aggressive-apparel/product-rating',
	);

	/**
	 * Register the theme's blocks and start from an empty queue.
	 */
	public function setUp(): void {
		parent::setUp();
		Blocks::init();
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( self::CARD_BLOCKS[0] ) ) {
			Blocks::register();
		}
		$this->dequeue_card_assets();
	}

	/**
	 * Restore the hook a test removed.
	 */
	public function tearDown(): void {
		Card_Block_Assets::init();
		$this->dequeue_card_assets();
		parent::tearDown();
	}

	/**
	 * A collection with no products still loads its card blocks' assets.
	 */
	public function test_assets_load_when_the_card_blocks_never_render(): void {
		$this->render_collection( 'aa-no-such-product' );

		$this->assertCardAssetsEnqueued( true );
	}

	/**
	 * Card blocks that render nothing for a product keep their assets.
	 */
	public function test_assets_load_when_the_card_blocks_render_empty(): void {
		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}
		$product = new \WC_Product_Simple();
		$product->set_name( 'No colours, no reviews' );
		$product->set_status( 'publish' );
		$product->save();

		$html = $this->render_collection( '' );

		$this->assertStringContainsString( 'No colours, no reviews', $html );
		$this->assertStringNotContainsString( 'aa-product-color-swatches', $html );
		$this->assertCardAssetsEnqueued( true );
	}

	/**
	 * Negative control: without the hook, WordPress drops them.
	 */
	public function test_without_the_hook_the_assets_are_dropped(): void {
		remove_all_filters( 'render_block_core/query' );

		$this->render_collection( 'aa-no-such-product' );

		$this->assertCardAssetsEnqueued( false );
	}

	/**
	 * Render a product query whose card holds the card blocks.
	 *
	 * @param string $search Search term limiting the products.
	 * @return string
	 */
	private function render_collection( string $search ): string {
		$query = (string) wp_json_encode(
			array(
				'query' => array(
					'postType' => 'product',
					'perPage'  => 1,
					'search'   => $search,
					'inherit'  => false,
				),
			)
		);

		return do_blocks(
			'<!-- wp:query ' . $query . ' --><div class="wp-block-query">'
			. '<!-- wp:post-template --><!-- wp:post-title /-->'
			. '<!-- wp:aggressive-apparel/product-color-swatches /-->'
			. '<!-- wp:aggressive-apparel/product-rating /-->'
			. '<!-- /wp:post-template --></div><!-- /wp:query -->'
		);
	}

	/**
	 * Assert every card block's style and view module is (or isn't) queued.
	 *
	 * @param bool $expected Whether they should be queued.
	 */
	private function assertCardAssetsEnqueued( bool $expected ): void {
		$checked = 0;
		foreach ( $this->card_types() as $name => $type ) {
			foreach ( $type->style_handles as $handle ) {
				$this->assertSame( $expected, wp_style_is( $handle, 'enqueued' ), "{$name}: {$handle}" );
				++$checked;
			}
			foreach ( $type->view_script_module_ids as $module ) {
				$this->assertSame( $expected, in_array( $module, wp_script_modules()->get_queue(), true ), "{$name}: {$module}" );
				++$checked;
			}
		}
		$this->assertGreaterThan( 0, $checked, 'The card blocks register assets to check.' );
	}

	/**
	 * Remove every card block asset from the queues.
	 */
	private function dequeue_card_assets(): void {
		foreach ( $this->card_types() as $type ) {
			array_map( 'wp_dequeue_style', $type->style_handles );
			array_map( 'wp_dequeue_script_module', $type->view_script_module_ids );
		}
	}

	/**
	 * The registered card block types.
	 *
	 * @return array<string, WP_Block_Type>
	 */
	private function card_types(): array {
		$types = array();
		foreach ( self::CARD_BLOCKS as $name ) {
			$type = WP_Block_Type_Registry::get_instance()->get_registered( $name );
			$this->assertInstanceOf( WP_Block_Type::class, $type, "{$name} is registered." );
			$types[ $name ] = $type;
		}
		return $types;
	}
}
