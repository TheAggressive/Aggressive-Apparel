<?php
/**
 * Editorial Imagery
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Core\Request_Cache;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Store photography for the homepage's editorial image slots.
 *
 * Campaign photography is the merchant's to supply. Until it exists, an
 * editorial slot (the Original Work portrait, the Women's portrait, the
 * lookbook) shows the store's own product photography instead of an empty
 * frame, and never generated or stock imagery. Only portrait-format photos
 * qualify: the slots are portrait, and square images are packshots or flat
 * mockups that read as catalogue, not editorial. A section that can't fill
 * its slot changes layout instead of showing a placeholder.
 *
 * Each photo is used once per page: the category tiles claim theirs first,
 * and later slots take the next unclaimed photo, so the homepage never
 * repeats an image down the scroll.
 */
final class Editorial_Imagery {

	/** Request_Cache key of the attachment IDs used so far on this page. */
	private const CLAIMED = 'editorial-imagery:claimed';

	/** Newest products considered per slot source. */
	private const CANDIDATES = 48;

	/** Height must exceed width by this factor to count as portrait. */
	private const PORTRAIT_RATIO = 1.15;

	/**
	 * Newest shoppable products with a portrait photo, claimed for this page.
	 *
	 * Catalog-wide slots (no term) only take unclaimed photos. A slot about
	 * one collection prefers unclaimed photos but may reuse one already shown:
	 * the Women's portrait must show women's clothing, even if a category tile
	 * used the same photo.
	 *
	 * A catalog-wide slot that must show $limit photos or nothing (the
	 * lookbook) passes $fill: when the unclaimed photos run short, it tops up
	 * with ones shown higher on the page rather than disappearing.
	 *
	 * @param int           $limit Photos wanted.
	 * @param \WP_Term|null $term  Collection (category, tag or attribute term) or null for the whole catalog.
	 * @param bool          $fill  Catalog-wide only: reuse claimed photos when unclaimed ones run short.
	 * @return list<array{product: int, image: int}>
	 */
	public static function leads( int $limit, ?\WP_Term $term = null, bool $fill = false ): array {
		$claimed = self::claimed();
		$fresh   = array();
		$reused  = array();
		$seen    = array();
		foreach ( self::candidates( $term ) as $candidate ) {
			if ( isset( $seen[ $candidate['image'] ] ) ) {
				continue;
			}
			$seen[ $candidate['image'] ] = true;
			if ( in_array( $candidate['image'], $claimed, true ) ) {
				$reused[] = $candidate;
			} else {
				$fresh[] = $candidate;
			}
		}

		$leads = array_slice( null === $term && ! $fill ? $fresh : array_merge( $fresh, $reused ), 0, max( 0, $limit ) );
		foreach ( $leads as $lead ) {
			self::claim( $lead['image'] );
		}

		return $leads;
	}

	/**
	 * Mark a photo as shown on this page.
	 *
	 * @param int $image Attachment ID.
	 */
	public static function claim( int $image ): void {
		$claimed = self::claimed();
		if ( $image > 0 && ! in_array( $image, $claimed, true ) ) {
			$claimed[] = $image;
			Request_Cache::set( self::CLAIMED, $claimed );
		}
	}

	/**
	 * Keep the newest product's photo out of editorial slots.
	 *
	 * New Arrivals opens with the newest product as a large card; the hero
	 * calls this first so it never shows the same photo directly above it.
	 */
	public static function reserve_newest_arrival(): void {
		$newest = self::candidates( null, false )[0] ?? null;
		if ( null !== $newest ) {
			self::claim( $newest['image'] );
		}
	}

	/**
	 * A category tile's photo: the term's own image, else its newest product
	 * photo not yet shown on this page (any orientation; tiles crop to 3:4),
	 * else its newest product photo. Claims what it returns.
	 *
	 * @param \WP_Term $term Category.
	 */
	public static function term_image( \WP_Term $term ): int {
		$thumbnail = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
		if ( $thumbnail > 0 && wp_attachment_is_image( $thumbnail ) ) {
			self::claim( $thumbnail );

			return $thumbnail;
		}
		$claimed = self::claimed();
		$images  = array_column( self::candidates( $term, false ), 'image' );
		$fresh   = array_values( array_diff( $images, $claimed ) );
		$image   = $fresh[0] ?? ( $images[0] ?? 0 );
		self::claim( $image );

		return $image;
	}

	/**
	 * Whether an image is portrait format (taller than wide by a clear margin).
	 *
	 * @param int $image Attachment ID.
	 */
	public static function is_portrait( int $image ): bool {
		$meta = wp_get_attachment_metadata( $image );
		if ( ! is_array( $meta ) ) {
			return false;
		}
		$width  = (int) ( $meta['width'] ?? 0 );
		$height = (int) ( $meta['height'] ?? 0 );

		return $width > 0 && $height >= $width * self::PORTRAIT_RATIO;
	}

	/**
	 * Core Image block markup for a lead: cropped to the slot, linked to its product.
	 *
	 * A plain core/image at the column's full width, so the merchant
	 * replaces it with campaign photography in the editor like any other
	 * image.
	 *
	 * @param array{product: int, image: int} $lead  Lead from leads().
	 * @param string                          $ratio Slot aspect ratio, e.g. "4/5".
	 */
	public static function image_markup( array $lead, string $ratio ): string {
		$src = wp_get_attachment_image_url( $lead['image'], 'large' );
		if ( false === $src ) {
			return '';
		}
		$alt = trim( (string) get_post_meta( $lead['image'], '_wp_attachment_image_alt', true ) );
		if ( '' === $alt ) {
			$alt = get_the_title( $lead['product'] );
		}
		$attributes = array(
			'id'              => $lead['image'],
			'aspectRatio'     => $ratio,
			'width'           => '100%',
			'scale'           => 'cover',
			'sizeSlug'        => 'large',
			'linkDestination' => 'custom',
		);

		return '<!-- wp:image ' . serialize_block_attributes( $attributes ) . ' -->' . "\n"
			. '<figure class="wp-block-image size-large is-resized"><a href="' . esc_url( (string) get_permalink( $lead['product'] ) ) . '">'
			. '<img src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '" class="wp-image-' . $lead['image'] . '" style="aspect-ratio:' . esc_attr( $ratio ) . ';object-fit:cover;width:100%"/>'
			. '</a></figure>' . "\n"
			. '<!-- /wp:image -->';
	}

	/**
	 * Photos used so far on this page.
	 *
	 * @return list<int>
	 */
	private static function claimed(): array {
		$claimed = Request_Cache::get( self::CLAIMED, array() );

		return is_array( $claimed ) ? array_values( array_map( 'intval', $claimed ) ) : array();
	}

	/**
	 * Newest catalog-visible, in-stock products with a main photo.
	 *
	 * @param \WP_Term|null $term          Collection or null for the whole catalog.
	 * @param bool          $portrait_only Only portrait photos (editorial slots); false for any orientation (tiles).
	 * @return list<array{product: int, image: int}>
	 */
	private static function candidates( ?\WP_Term $term, bool $portrait_only = true ): array {
		$candidates = Request_Cache::remember(
			'editorial-imagery:candidates:' . ( null === $term ? 'all' : $term->term_id ) . ( $portrait_only ? ':portrait' : ':any' ),
			/**
			 * Newest products' photos, filtered by orientation.
			 *
			 * @return list<array{product: int, image: int}>
			 */
			static function () use ( $term, $portrait_only ): array {
				$tax_query = array(
					array(
						'taxonomy' => 'product_visibility',
						'field'    => 'name',
						'terms'    => array( 'exclude-from-catalog', 'outofstock' ),
						'operator' => 'NOT IN',
					),
				);
				if ( null !== $term ) {
					$tax_query[] = array(
						'taxonomy' => $term->taxonomy,
						'terms'    => array( $term->term_id ),
					);
				}
				$query = new \WP_Query(
					array(
						'post_type'              => 'product',
						'post_status'            => 'publish',
						'posts_per_page'         => self::CANDIDATES,
						'fields'                 => 'ids',
						'orderby'                => 'date',
						'order'                  => 'DESC',
						'no_found_rows'          => true,
						'update_post_term_cache' => false,
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- One indexed lookup per slot source, memoised per request.
						'meta_query'             => array(
							array(
								'key'     => '_thumbnail_id',
								'compare' => 'EXISTS',
							),
						),
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- One indexed lookup per slot source, memoised per request.
						'tax_query'              => $tax_query,
					)
				);

				$found = array();
				foreach ( $query->posts as $post ) {
					$product = $post instanceof \WP_Post ? $post->ID : $post;
					$image   = (int) get_post_thumbnail_id( $product );
					if ( $image > 0 && ( ! $portrait_only || self::is_portrait( $image ) ) ) {
						$found[] = array(
							'product' => $product,
							'image'   => $image,
						);
					}
				}

				return $found;
			}
		);

		return is_array( $candidates ) ? $candidates : array();
	}
}
