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
 * Loads catalog card images at the size and priority the grid needs, and the
 * homepage campaign photo as the page's leading image.
 *
 * Card images arrive lazy-loaded, with `sizes` describing the full upload, so
 * the largest image on a catalog page (a first-row card) waited for the lazy
 * loader, and any browser without `sizes="auto"` fetched near-original
 * photos for a card about 300px wide. On catalog pages this gives every card
 * image the card's real width, and loads the first row eagerly, the first
 * image at high priority.
 *
 * The campaign photo (a Cover block in the "Homepage campaign image" template
 * part) opens the homepage, yet arrives lazy with `sizes` describing the
 * frame's width; cropped to fill a 4:5 frame, a landscape photo needs several
 * times that width in pixels, so the browser picked a file a third of the
 * height it fills. It loads eagerly at high priority, with `sizes` scaled by
 * how much wider the photo is than its frame.
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
		Block_Filter_Hooks::add( 'core/template-part', array( $this, 'optimize_campaign_image' ), 20 );
	}

	/**
	 * Load the homepage campaign photo eagerly, at high priority and at the
	 * width its crop needs.
	 *
	 * @param string               $block_content Rendered block HTML.
	 * @param array<string, mixed> $block         Parsed block.
	 * @return string
	 */
	public function optimize_campaign_image( string $block_content, array $block ): string {
		$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		if ( '' === $block_content || Editorial_Imagery::CAMPAIGN_PART !== ( $attrs['slug'] ?? '' ) ) {
			return $block_content;
		}
		// The frame: 4:5 unless the merchant set a ratio on the Cover block.
		$frame = 0.8;
		$cover = new \WP_HTML_Tag_Processor( $block_content );
		if ( $cover->next_tag( array( 'class_name' => 'wp-block-cover' ) )
			&& preg_match( '#aspect-ratio:\s*([\d.]+)\s*/\s*([\d.]+)#', (string) $cover->get_attribute( 'style' ), $ratio )
			&& (float) $ratio[2] > 0 ) {
			$frame = (float) $ratio[1] / (float) $ratio[2];
		}

		$processor = new \WP_HTML_Tag_Processor( $block_content );
		if ( ! $processor->next_tag( array( 'class_name' => 'wp-block-cover__image-background' ) ) ) {
			return $block_content;
		}
		// Set, not removed: the template's own pass would mark it lazy again.
		$processor->set_attribute( 'loading', 'eager' );
		$processor->set_attribute( 'fetchpriority', 'high' );
		$image = preg_match( '/\bwp-image-(\d+)\b/', (string) $processor->get_attribute( 'class' ), $id ) ? (int) $id[1] : 0;
		$meta  = $image > 0 ? wp_get_attachment_metadata( $image ) : false;
		$width = is_array( $meta ) ? (int) ( $meta['width'] ?? 0 ) : 0;
		$tall  = is_array( $meta ) ? (int) ( $meta['height'] ?? 0 ) : 0;
		if ( null !== $processor->get_attribute( 'srcset' ) && $width > 0 && $tall > 0 ) {
			// The frame is 46% of the wide column (740px at most) on desktop
			// and the full width on phones; a photo wider than its frame
			// fills it by height, needing that much more width.
			$scale = max( 1.0, ( $width / $tall ) / $frame );
			$processor->set_attribute(
				'sizes',
				sprintf( '(min-width: 1600px) %dpx, (min-width: 782px) %dvw, %dvw', (int) ceil( 740 * $scale ), (int) ceil( 46 * $scale ), (int) ceil( 100 * $scale ) )
			);
		}

		return $processor->get_updated_html();
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
