<?php
/**
 * Store fixtures for the editorial homepage pattern tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Support;

use WC_Product_Simple;

/**
 * Products, images and pattern rendering shared by the editorial tests.
 */
trait Builds_Editorial_Fixtures {

	/**
	 * Create a published simple product.
	 *
	 * @param array{featured?: bool, total_sales?: int, category?: int, image?: int, stock_status?: string} $props Product properties.
	 */
	private function create_product( array $props = array() ): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_name( 'Editorial probe' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '20' );
		$product->set_featured( $props['featured'] ?? false );
		$product->set_total_sales( $props['total_sales'] ?? 0 );
		if ( isset( $props['category'] ) ) {
			$product->set_category_ids( array( $props['category'] ) );
		}
		if ( isset( $props['image'] ) ) {
			$product->set_image_id( $props['image'] );
		}
		if ( isset( $props['stock_status'] ) ) {
			$product->set_stock_status( $props['stock_status'] );
		}
		$product->save();

		return $product;
	}

	/**
	 * Create an image attachment with real dimensions (portrait by default).
	 *
	 * @param int $width  Pixel width.
	 * @param int $height Pixel height.
	 */
	private function create_image( int $width = 504, int $height = 757 ): int {
		$id = self::factory()->attachment->create_object(
			array(
				'file'           => 'editorial-probe.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);
		wp_update_attachment_metadata(
			$id,
			array(
				'width'  => $width,
				'height' => $height,
				'file'   => 'editorial-probe.jpg',
			)
		);

		return $id;
	}

	/**
	 * Depth-first search of parsed blocks.
	 *
	 * @param array<int, array<string, mixed>> $blocks    Parsed blocks.
	 * @param callable                         $predicate Match test.
	 * @return array<string, mixed>|null
	 */
	private function find_block( array $blocks, callable $predicate ): ?array {
		foreach ( $blocks as $block ) {
			if ( null !== $block['blockName'] && $predicate( $block ) ) {
				return $block;
			}
			$inner = $this->find_block( $block['innerBlocks'], $predicate );
			if ( null !== $inner ) {
				return $inner;
			}
		}

		return null;
	}

	/**
	 * Render a theme pattern file as WordPress does (include under output buffering).
	 *
	 * @param string $slug Pattern file slug.
	 */
	private function render_pattern( string $slug ): string {
		wp_cache_flush();
		ob_start();
		include get_theme_file_path( 'patterns/' . $slug . '.php' );

		return (string) ob_get_clean();
	}
}
