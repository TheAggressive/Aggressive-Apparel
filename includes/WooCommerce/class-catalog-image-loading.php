<?php
/**
 * Catalog Image Loading
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads catalog card images at the size and priority the grid needs.
 *
 * Card images arrive lazy-loaded, with `sizes` describing the full upload, so
 * the largest image on a catalog page (a first-row card) waited for the lazy
 * loader, and any browser without `sizes="auto"` fetched near-original
 * photos for a card about 300px wide. On catalog pages this gives every card
 * image the card's real width, and loads the first row eagerly, the first
 * image at high priority.
 */
final class Catalog_Image_Loading {

	/**
	 * Cards at the top of a catalog page (one desktop row, two phone rows).
	 */
	private const EAGER_CARDS = 4;

	/**
	 * The card's rendered width at each breakpoint.
	 */
	private const CARD_SIZES = '(max-width: 600px) 50vw, (max-width: 1024px) 33vw, 320px';

	/**
	 * Card images rendered so far in this request.
	 *
	 * @var int
	 */
	private int $rendered = 0;

	/**
	 * Register block render filters.
	 *
	 * @return void
	 */
	public function init(): void {
		// After Product_Card_Contract (5) has marked the card image.
		Block_Filter_Hooks::add_featured_image( array( $this, 'optimize_card_image' ), 20 );
		Block_Filter_Hooks::add( 'woocommerce/product-image', array( $this, 'optimize_card_image' ), 20 );
	}

	/**
	 * Set a catalog card image's sizes, loading and priority.
	 *
	 * @param string               $block_content Rendered block HTML.
	 * @param array<string, mixed> $block         Parsed block.
	 * @return string
	 */
	public function optimize_card_image( string $block_content, array $block ): string {
		if ( '' === $block_content || ! self::is_catalog_request() || Product_Context::resolve_product_id( $block ) <= 0 ) {
			return $block_content;
		}

		$processor = new \WP_HTML_Tag_Processor( $block_content );
		if ( ! $processor->next_tag( array( 'tag_name' => 'IMG' ) ) ) {
			return $block_content;
		}

		$position = $this->rendered++;
		$lazy     = 'lazy' === $processor->get_attribute( 'loading' );

		if ( $position < self::EAGER_CARDS ) {
			$processor->remove_attribute( 'loading' );
			$lazy = false;
			if ( 0 === $position ) {
				$processor->set_attribute( 'fetchpriority', 'high' );
			}
		}

		if ( null !== $processor->get_attribute( 'srcset' ) ) {
			// `auto` is only valid on lazy images: the browser picks from layout.
			$processor->set_attribute( 'sizes', ( $lazy ? 'auto, ' : '' ) . self::CARD_SIZES );
		}

		return $processor->get_updated_html();
	}

	/**
	 * Whether this request renders a product catalog grid at the top.
	 *
	 * @return bool
	 */
	private static function is_catalog_request(): bool {
		return Product_Context::is_product_archive() || is_search();
	}
}
