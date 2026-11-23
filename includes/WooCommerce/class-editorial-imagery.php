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
 * editorial slot (the name, the Women's photo, the lookbook, a collection's
 * portraits) shows the store's own product photography instead of an empty
 * frame, and never generated or stock imagery.
 *
 * Only photos the merchant approved qualify (Media Library, "Homepage use",
 * Editorial_Controls): a product's own main or gallery image, so a photo is
 * always shown with, and linked to, the product it belongs to. Approved
 * photos show highest priority first, then newest product first, and must
 * be large enough for a slot; orientation does not matter, slots crop to
 * their own ratio. Whether an image looks like a photograph or a cut-out
 * (corner pixels, cached on the attachment) only guides the merchant's
 * review. A section that can't fill its slot changes layout instead of
 * showing a placeholder.
 *
 * Each photo is used once per page: earlier slots claim theirs first, and
 * later slots take the next unclaimed photo, so the homepage never repeats
 * an image down the scroll.
 */
final class Editorial_Imagery {

	/** Request_Cache key of the attachment IDs used so far on this page. */
	private const CLAIMED = 'editorial-imagery:claimed';

	/** Request_Cache key of the product the cover shows. */
	private const COVER_PRODUCT = 'editorial-imagery:cover-product';

	/** Newest products considered per slot source. */
	private const CANDIDATES = 48;

	/** Shortest edge, in pixels, an image needs to fill an editorial slot. */
	private const MIN_PHOTO_EDGE = 480;

	/** Attachment meta caching what an image is: photo, cutout or packshot. */
	private const KIND_META = '_aa_photo_kind';

	/**
	 * Newest shoppable products with a qualifying photo, claimed for this page.
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
	 * A category's image: the term's own image, else its newest product
	 * image not yet shown on this page (any kind: cut-outs suit a category
	 * row), else its newest product image. Claims what it returns.
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
	 * Main images of a line's newest products, for its index row. Any kind
	 * (cut-outs suit a row of thumbnails) and not claimed: a thumbnail
	 * doesn't spend a photo another section needs.
	 *
	 * @param \WP_Term $term  Category.
	 * @param int      $limit Images wanted.
	 * @return list<int>
	 */
	public static function term_images( \WP_Term $term, int $limit ): array {
		return array_slice( array_values( array_unique( array_column( self::candidates( $term, false ), 'image' ) ) ), 0, max( 0, $limit ) );
	}

	/**
	 * Whether an image may fill an editorial slot: approved by the merchant
	 * and at least MIN_PHOTO_EDGE on its shorter side.
	 *
	 * @param int $image Attachment ID.
	 */
	public static function is_editorial( int $image ): bool {
		if ( 'approved' !== Editorial_Controls::use_of( $image ) ) {
			return false;
		}
		$meta = wp_get_attachment_metadata( $image );

		return is_array( $meta ) && min( (int) ( $meta['width'] ?? 0 ), (int) ( $meta['height'] ?? 0 ) ) >= self::MIN_PHOTO_EDGE;
	}

	/**
	 * The cover: the merchant's campaign photo (Homepage use "Cover") and
	 * cover product ("Homepage cover" on the product), else the cover
	 * product's own photo, else the newest arrival's. Claims the image and
	 * remembers the product, so New Arrivals knows what opened the page.
	 *
	 * @return array{image: int, product: int, campaign: bool}|null
	 */
	public static function cover(): ?array {
		$product = 0;
		foreach ( Editorial_Controls::cover_products() as $candidate ) {
			$item = wc_get_product( $candidate );
			if ( $item instanceof \WC_Product && $item->is_visible() && $item->is_in_stock() ) {
				$product = $candidate;
				break;
			}
		}
		$campaign = Editorial_Controls::attachments_with_use( 'cover' )[0] ?? 0;

		if ( $campaign > 0 && wp_attachment_is_image( $campaign ) ) {
			$cover = array(
				'image'    => $campaign,
				'product'  => $product,
				'campaign' => true,
			);
		} elseif ( $product > 0 && (int) get_post_thumbnail_id( $product ) > 0 ) {
			$cover = array(
				'image'    => (int) get_post_thumbnail_id( $product ),
				'product'  => $product,
				'campaign' => false,
			);
		} else {
			$newest = self::candidates( null, false )[0] ?? null;
			if ( null === $newest ) {
				return null;
			}
			$cover = array(
				'image'    => $newest['image'],
				'product'  => $newest['product'],
				'campaign' => false,
			);
		}
		self::claim( $cover['image'] );
		Request_Cache::set( self::COVER_PRODUCT, $cover['product'] );

		return $cover;
	}

	/**
	 * Whether the cover already shows the newest arrival (New Arrivals then
	 * starts after it).
	 */
	public static function newest_on_cover(): bool {
		$newest = self::candidates( null, false )[0] ?? null;

		return null !== $newest && (int) Request_Cache::get( self::COVER_PRODUCT, 0 ) === $newest['product'];
	}

	/**
	 * Whether an image is a photograph that can fill an editorial slot: not
	 * a cut-out or a studio packshot, and at least MIN_PHOTO_EDGE on its
	 * shorter side.
	 *
	 * @param int $image Attachment ID.
	 */
	public static function is_photo( int $image ): bool {
		$meta = wp_get_attachment_metadata( $image );
		if ( ! is_array( $meta ) ) {
			return false;
		}
		$width  = (int) ( $meta['width'] ?? 0 );
		$height = (int) ( $meta['height'] ?? 0 );

		return min( $width, $height ) >= self::MIN_PHOTO_EDGE && 'photo' === self::kind( $image );
	}

	/**
	 * What an image is: "photo", "cutout" (transparent background) or
	 * "packshot" (flat light background), cached on the attachment.
	 *
	 * @param int $image Attachment ID.
	 */
	public static function kind( int $image ): string {
		$cached = get_post_meta( $image, self::KIND_META, true );
		if ( in_array( $cached, array( 'photo', 'cutout', 'packshot' ), true ) ) {
			return $cached;
		}
		$kind = self::detect_kind( $image );
		update_post_meta( $image, self::KIND_META, $kind );

		return $kind;
	}

	/**
	 * Core Image block markup for a lead: cropped to the slot, linked to its product.
	 *
	 * A plain core/image at the column's full width, so the merchant
	 * replaces it with campaign photography in the editor like any other
	 * image.
	 *
	 * A cut-out is shown whole ($scale "contain"); a photo fills its slot
	 * ("cover").
	 *
	 * @param array{product: int, image: int} $lead  Lead from leads().
	 * @param string                          $ratio Slot aspect ratio, e.g. "4/5".
	 * @param string                          $scale "cover" or "contain".
	 */
	public static function image_markup( array $lead, string $ratio, string $scale = 'cover' ): string {
		$scale = 'contain' === $scale ? 'contain' : 'cover';
		$src   = wp_get_attachment_image_url( $lead['image'], 'large' );
		if ( false === $src ) {
			return '';
		}
		$alt = trim( (string) get_post_meta( $lead['image'], '_wp_attachment_image_alt', true ) );
		if ( '' === $alt && $lead['product'] > 0 ) {
			$alt = get_the_title( $lead['product'] );
		}
		$link       = $lead['product'] > 0 ? (string) get_permalink( $lead['product'] ) : '';
		$attributes = array(
			'id'              => $lead['image'],
			'aspectRatio'     => $ratio,
			'width'           => '100%',
			'scale'           => $scale,
			'sizeSlug'        => 'large',
			'linkDestination' => '' !== $link ? 'custom' : 'none',
		);
		$img        = '<img src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '" class="wp-image-' . $lead['image'] . '" style="aspect-ratio:' . esc_attr( $ratio ) . ';object-fit:' . $scale . ';width:100%"/>';

		return '<!-- wp:image ' . serialize_block_attributes( $attributes ) . ' -->' . "\n"
			. '<figure class="wp-block-image size-large is-resized">' . ( '' !== $link ? '<a href="' . esc_url( $link ) . '">' . $img . '</a>' : $img ) . '</figure>' . "\n"
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
	 * Newest catalog-visible, in-stock products with an image.
	 *
	 * @param \WP_Term|null $term          Collection or null for the whole catalog.
	 * @param bool          $photos_only   Only approved photos from the main or gallery images (editorial slots); false for main images of any kind.
	 * @return list<array{product: int, image: int}>
	 */
	private static function candidates( ?\WP_Term $term, bool $photos_only = true ): array {
		$candidates = Request_Cache::remember(
			'editorial-imagery:candidates:' . ( null === $term ? 'all' : $term->term_id ) . ( $photos_only ? ':photos' : ':any' ),
			/**
			 * Newest products' photos, filtered by orientation.
			 *
			 * @return list<array{product: int, image: int}>
			 */
			static function () use ( $term, $photos_only ): array {
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
					$main    = (int) get_post_thumbnail_id( $product );
					if ( ! $photos_only ) {
						if ( $main > 0 ) {
							$found[] = array(
								'product' => $product,
								'image'   => $main,
							);
						}
						continue;
					}
					// On-figure photos often sit in the gallery behind a
					// cut-out main image: the product's highest-priority
					// approved photo of either qualifies.
					$gallery = array_map( 'intval', array_filter( explode( ',', (string) get_post_meta( $product, '_product_image_gallery', true ) ) ) );
					$best    = null;
					foreach ( array_merge( array( $main ), $gallery ) as $image ) {
						if ( $image > 0 && self::is_editorial( $image ) && ( null === $best || Editorial_Controls::priority_of( $image ) > Editorial_Controls::priority_of( $best ) ) ) {
							$best = $image;
						}
					}
					if ( null !== $best ) {
						$found[] = array(
							'product' => $product,
							'image'   => $best,
						);
					}
				}
				if ( $photos_only ) {
					// Highest priority first; equal priorities keep newest first (stable sort).
					usort( $found, static fn( array $a, array $b ): int => Editorial_Controls::priority_of( $b['image'] ) <=> Editorial_Controls::priority_of( $a['image'] ) );
				}

				return $found;
			}
		);

		return is_array( $candidates ) ? $candidates : array();
	}

	/**
	 * Classify an image from its corner pixels, read from its smallest
	 * uncropped copy. Transparent corners make a cut-out; four near-identical
	 * light corners make a packshot on a studio background. An unreadable
	 * file in a format that can carry transparency is treated as a cut-out,
	 * so it never fills an editorial slot by mistake; a JPEG is a photo.
	 *
	 * @param int $image Attachment ID.
	 */
	private static function detect_kind( int $image ): string {
		$alpha  = in_array( (string) get_post_mime_type( $image ), array( 'image/png', 'image/webp', 'image/avif', 'image/gif' ), true );
		$file   = self::sample_file( $image );
		$pixels = null !== $file ? self::corner_pixels( $file ) : null;
		if ( null === $pixels ) {
			return $alpha ? 'cutout' : 'photo';
		}
		foreach ( $pixels as $pixel ) {
			// GD alpha runs 0 (opaque) to 127 (transparent).
			if ( $pixel['alpha'] > 100 ) {
				return 'cutout';
			}
		}
		$light = array_map( static fn( array $pixel ): float => ( $pixel['red'] + $pixel['green'] + $pixel['blue'] ) / 3, $pixels );
		if ( array() !== $light && min( $light ) > 225 && max( $light ) - min( $light ) < 12 ) {
			return 'packshot';
		}

		return 'photo';
	}

	/**
	 * Path of the smallest proportional copy of an image (medium, else the
	 * original); null when no file exists.
	 *
	 * @param int $image Attachment ID.
	 */
	private static function sample_file( int $image ): ?string {
		$original = get_attached_file( $image );
		if ( ! is_string( $original ) || '' === $original ) {
			return null;
		}
		$meta   = wp_get_attachment_metadata( $image );
		$medium = is_array( $meta ) ? (string) ( $meta['sizes']['medium']['file'] ?? '' ) : '';
		$path   = '' !== $medium ? dirname( $original ) . '/' . $medium : $original;
		if ( ! file_exists( $path ) ) {
			$path = $original;
		}

		return file_exists( $path ) ? $path : null;
	}

	/**
	 * RGBA of the four corner pixels (2px in), or null when GD can't read it.
	 *
	 * @param string $file Image path.
	 * @return list<array{red: int, green: int, blue: int, alpha: int}>|null
	 */
	private static function corner_pixels( string $file ): ?array {
		if ( ! function_exists( 'imagecreatefromstring' ) ) {
			return null;
		}
		$data = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- A local upload, read once and cached as attachment meta.
		if ( false === $data || '' === $data || false === getimagesizefromstring( $data ) ) {
			return null;
		}
		$canvas = imagecreatefromstring( $data );
		if ( false === $canvas ) {
			return null;
		}
		$right  = max( 0, imagesx( $canvas ) - 3 );
		$bottom = max( 0, imagesy( $canvas ) - 3 );
		$pixels = array();
		foreach ( array( array( 2, 2 ), array( $right, 2 ), array( 2, $bottom ), array( $right, $bottom ) ) as $point ) {
			$index = imagecolorat( $canvas, min( $point[0], $right ), min( $point[1], $bottom ) );
			$rgba  = false === $index ? false : imagecolorsforindex( $canvas, $index );
			if ( ! is_array( $rgba ) ) {
				return null;
			}
			$pixels[] = array(
				'red'   => (int) $rgba['red'],
				'green' => (int) $rgba['green'],
				'blue'  => (int) $rgba['blue'],
				'alpha' => (int) $rgba['alpha'],
			);
		}

		return $pixels;
	}
}
