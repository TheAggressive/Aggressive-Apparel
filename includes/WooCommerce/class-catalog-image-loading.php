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
		Block_Filter_Hooks::add( 'core/cover', array( $this, 'optimize_campaign_image' ), 20 );
		add_filter( 'wp_content_img_tag', array( $this, 'keep_campaign_sizes' ) );
	}

	/**
	 * Undo core's `sizes="auto, …"` on a lazy campaign photo: auto sizes to
	 * the element's box, but a photo cropped to its frame (object-fit: cover)
	 * needs more width than that.
	 *
	 * @param string $image The img tag, after core's content pass.
	 */
	public function keep_campaign_sizes( string $image ): string {
		$processor = new \WP_HTML_Tag_Processor( $image );
		if ( ! $processor->next_tag( array( 'class_name' => 'aa-campaign-cover__image' ) ) ) {
			return $image;
		}
		$sizes = (string) $processor->get_attribute( 'sizes' );
		$processor->set_attribute( 'sizes', (string) preg_replace( '/^\s*auto\s*,\s*/i', '', $sizes ) );

		return $processor->get_updated_html();
	}

	/**
	 * A campaign cover's photo (Editorial_Campaign_Cover): fetched at the
	 * width its crop needs, the opening one eagerly and first; on phones,
	 * where the cover stacks into a 4:5 frame, the part's phone photo or the
	 * same photo with its own focal point.
	 *
	 * @param string               $block_content Rendered block HTML.
	 * @param array<string, mixed> $block         Parsed block.
	 * @return string
	 */
	public function optimize_campaign_image( string $block_content, array $block ): string {
		$class = (string) ( $block['attrs']['className'] ?? '' );
		if ( '' === $block_content || ! preg_match( '/\baa-campaign-cover--(hero|drop|film)\b/', $class, $variant ) ) {
			return $block_content;
		}
		$part = (string) array_search( $variant[1], Editorial_Campaign_Cover::VARIANTS, true );
		// Desktop frame width / height: the hero and drop fill most of a
		// landscape screen; the film still is 21:9. Phones: 4:5.
		$frame = array(
			'hero' => 1.8,
			'drop' => 1.6,
			'film' => 21 / 9,
		)[ $variant[1] ];

		$processor = new \WP_HTML_Tag_Processor( $block_content );
		if ( ! $processor->next_tag( array( 'class_name' => 'wp-block-cover__image-background' ) ) || 'IMG' !== $processor->get_tag() ) {
			return $block_content;
		}
		if ( 'hero' === $variant[1] ) {
			// Set, not removed: the template's own pass would mark it lazy again.
			$processor->set_attribute( 'loading', 'eager' );
			$processor->set_attribute( 'fetchpriority', 'high' );
		} else {
			// Further down the page: fetched as it nears the viewport.
			$processor->set_attribute( 'loading', 'lazy' );
			$processor->set_attribute( 'decoding', 'async' );
		}
		$processor->add_class( 'aa-campaign-cover__image' );
		$image = preg_match( '/\bwp-image-(\d+)\b/', (string) $processor->get_attribute( 'class' ), $id ) ? (int) $id[1] : 0;
		$sizes = self::cover_sizes( $image, $frame );
		if ( '' !== $sizes['srcset'] ) {
			$processor->set_attribute( 'srcset', $sizes['srcset'] );
			$processor->set_attribute( 'sizes', $sizes['desktop'] );
		}

		// Focal points as custom properties, so the phone crop can differ.
		$phone       = Editorial_Campaign_Cover::phone( $part );
		$phone_attrs = is_array( $phone['attrs'] ?? null ) ? $phone['attrs'] : array();
		$focal       = preg_match( '/object-position:\s*([^;"]+)/', (string) $processor->get_attribute( 'style' ), $position ) ? trim( $position[1] ) : '50% 50%';
		$phone_focal = isset( $phone_attrs['focalPoint'] ) ? self::focal( $phone_attrs['focalPoint'] ) : $focal;
		$processor->set_attribute( 'style', '--aa-focal:' . $focal . ';--aa-focal-phone:' . $phone_focal );
		$html = $processor->get_updated_html();

		// Phones: the phone photo if the part has one, else the same photo
		// at the width a 4:5 crop of it needs.
		$phone_image = (int) ( $phone_attrs['id'] ?? 0 );
		$phone_url   = is_string( $phone_attrs['url'] ?? null ) ? $phone_attrs['url'] : '';
		$phone_sizes = null !== $phone ? self::cover_sizes( $phone_image, 0.8 ) : self::cover_sizes( $image, 0.8 );
		$srcset      = '' !== $phone_sizes['srcset'] ? $phone_sizes['srcset'] : ( null !== $phone ? $phone_url : '' );
		if ( '' === $srcset || ! preg_match( '#<img\b[^>]*\bwp-block-cover__image-background\b[^>]*>#', $html, $img ) ) {
			return $html;
		}
		$source = sprintf(
			'<source media="(max-width: 781px)" srcset="%s" sizes="%s">',
			esc_attr( $srcset ),
			esc_attr( $phone_sizes['phone'] )
		);

		return str_replace( $img[0], '<picture class="aa-campaign-cover__picture">' . $source . $img[0] . '</picture>', $html );
	}

	/**
	 * An attachment's srcset and the sizes a cropped frame needs: a photo
	 * wider than its frame fills it by height, needing that much more width.
	 *
	 * @param int   $image Attachment ID (0: none).
	 * @param float $frame Desktop frame width / height.
	 * @return array{srcset: string, desktop: string, phone: string}
	 */
	private static function cover_sizes( int $image, float $frame ): array {
		$meta   = $image > 0 ? wp_get_attachment_metadata( $image ) : false;
		$width  = is_array( $meta ) ? (int) ( $meta['width'] ?? 0 ) : 0;
		$height = is_array( $meta ) ? (int) ( $meta['height'] ?? 0 ) : 0;
		$srcset = $image > 0 ? wp_get_attachment_image_srcset( $image, 'full' ) : false;
		$ratio  = $width > 0 && $height > 0 ? $width / $height : 1.0;
		$wide   = (int) ceil( 100 * max( 1.0, $ratio / $frame ) );
		$phone  = (int) ceil( 100 * max( 1.0, $ratio / 0.8 ) );

		return array(
			'srcset'  => is_string( $srcset ) ? $srcset : '',
			'desktop' => sprintf( '(min-width: 782px) %1$dvw, %2$dvw', $wide, $phone ),
			'phone'   => sprintf( '%dvw', $phone ),
		);
	}

	/**
	 * A Cover focal point as CSS object-position.
	 *
	 * @param mixed $point Focal point attribute ({x, y} in 0..1).
	 */
	private static function focal( mixed $point ): string {
		$x = is_array( $point ) && is_numeric( $point['x'] ?? null ) ? (float) $point['x'] : 0.5;
		$y = is_array( $point ) && is_numeric( $point['y'] ?? null ) ? (float) $point['y'] : 0.5;

		return sprintf( '%s%% %s%%', round( $x * 100, 2 ), round( $y * 100, 2 ) );
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
