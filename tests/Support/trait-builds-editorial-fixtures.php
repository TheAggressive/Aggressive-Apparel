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
	 * Create an image attachment with real dimensions (portrait by default),
	 * approved for editorial use unless told otherwise.
	 *
	 * @param int    $width  Pixel width.
	 * @param int    $height Pixel height.
	 * @param string $choice Homepage use: approved, cover, excluded or '' (not reviewed).
	 */
	private function create_image( int $width = 504, int $height = 757, string $choice = 'approved' ): int {
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
		\Aggressive_Apparel\WooCommerce\Editorial_Controls::set_use( $id, $choice );

		return $id;
	}

	/**
	 * Create a product cut-out: a real PNG with a transparent background, as
	 * the store's packshots are, so it never qualifies as an editorial photo.
	 *
	 * @param int $size Square edge in pixels.
	 */
	private function create_cutout( int $size = 800 ): int {
		$canvas = imagecreatetruecolor( $size, $size );
		imagesavealpha( $canvas, true );
		imagefill( $canvas, 0, 0, (int) imagecolorallocatealpha( $canvas, 0, 0, 0, 127 ) );
		imagefilledrectangle( $canvas, (int) ( $size / 4 ), (int) ( $size / 4 ), (int) ( $size * 3 / 4 ), (int) ( $size * 3 / 4 ), (int) imagecolorallocate( $canvas, 20, 20, 20 ) );
		$uploads = wp_upload_dir();
		$file    = trailingslashit( $uploads['path'] ) . wp_unique_filename( $uploads['path'], 'editorial-cutout.png' );
		imagepng( $canvas, $file );

		$id = self::factory()->attachment->create_object(
			array(
				'file'           => $file,
				'post_mime_type' => 'image/png',
			)
		);
		update_attached_file( $id, $file );
		wp_update_attachment_metadata(
			$id,
			array(
				'width'  => $size,
				'height' => $size,
				'file'   => _wp_relative_upload_path( $file ),
			)
		);

		return $id;
	}

	/**
	 * Every block of a type, depth first.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @param string                           $name   Block name.
	 * @return list<array<string, mixed>>
	 */
	private function find_all( array $blocks, string $name ): array {
		$found = array();
		foreach ( $blocks as $block ) {
			if ( $name === $block['blockName'] ) {
				$found[] = $block;
			}
			array_push( $found, ...$this->find_all( $block['innerBlocks'], $name ) );
		}

		return $found;
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

	/**
	 * Render a section after the ones above it in the same request, as the
	 * homepage does (they share the page's claimed photos and cover choice).
	 * Returns the last section's markup.
	 *
	 * @param array<int, string> $slugs Pattern file slugs, top to bottom.
	 */
	private function render_in_page( array $slugs ): string {
		wp_cache_flush();
		$markup = '';
		foreach ( $slugs as $slug ) {
			ob_start();
			include get_theme_file_path( 'patterns/' . $slug . '.php' );
			$markup = (string) ob_get_clean();
		}

		return $markup;
	}
}
