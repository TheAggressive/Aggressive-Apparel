<?php
/**
 * Card Media Ratio
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
 * Sizes every product card's media slot to the catalog's own photo shape.
 *
 * Product photography is shown whole: cards use `object-fit: contain`, never
 * a crop that could cut a sleeve or a print off an artwork mockup. For the
 * whole photo to fill the slot, the slot must have the photos' shape, and
 * that differs by store (square 2000 x 2000 mockups in production, 2:3
 * model shots in the sample catalog). This measures the newest products'
 * main photos, takes the median height-to-width ratio, snaps it to 1:1,
 * 4:5, 3:4 or 2:3, and prints it once as `--aa-card-media-h` on :root.
 *
 * The value is catalog configuration, the same for every visitor, so it is
 * safe under a full-page cache. It is cached in a transient and recomputed
 * after any product is saved.
 */
final class Card_Media_Ratio {

	/** Transient holding the snapped ratio. */
	private const TRANSIENT = 'aa_card_media_ratio';

	/** Newest products measured. */
	private const SAMPLE = 24;

	/** Ratios (height / width) the slot snaps to: 1:1, 4:5, 3:4, 2:3. */
	private const SNAPS = array( 1.0, 1.25, 1.3333, 1.5 );

	/** Hook the stylesheet output and the cache reset. */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'print_ratio' ), 20 );
		add_action( 'save_post_product', array( self::class, 'forget' ) );
	}

	/** Add the ratio to the main stylesheet as a custom property. */
	public function print_ratio(): void {
		wp_add_inline_style(
			'aggressive-apparel-main',
			sprintf( ':root{--aa-card-media-h:%s}', self::format( self::height_ratio() ) )
		);
	}

	/** The catalog's snapped photo shape, height divided by width (1.0 = square). */
	public static function height_ratio(): float {
		$cached = get_transient( self::TRANSIENT );
		if ( is_numeric( $cached ) ) {
			return (float) $cached;
		}
		$ratio = self::measure();
		set_transient( self::TRANSIENT, (string) $ratio, DAY_IN_SECONDS );

		return $ratio;
	}

	/**
	 * The ratio as a CSS aspect-ratio value (width / height), e.g. "1/1.5".
	 *
	 * For blocks that take an aspect ratio attribute (category tiles).
	 */
	public static function aspect_ratio(): string {
		return '1/' . self::format( self::height_ratio() );
	}

	/** Recompute on the next request (a product was saved). */
	public static function forget(): void {
		delete_transient( self::TRANSIENT );
	}

	/** Median main-photo ratio of the newest products, snapped; square without photos. */
	private static function measure(): float {
		$query  = new \WP_Query(
			array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'posts_per_page'         => self::SAMPLE,
				'fields'                 => 'ids',
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- One indexed lookup, cached for a day.
				'meta_query'             => array(
					array(
						'key'     => '_thumbnail_id',
						'compare' => 'EXISTS',
					),
				),
			)
		);
		$ratios = array();
		foreach ( $query->posts as $post ) {
			$product = $post instanceof \WP_Post ? $post->ID : $post;
			$meta    = wp_get_attachment_metadata( (int) get_post_thumbnail_id( $product ) );
			$width   = is_array( $meta ) ? (int) ( $meta['width'] ?? 0 ) : 0;
			$height  = is_array( $meta ) ? (int) ( $meta['height'] ?? 0 ) : 0;
			if ( $width > 0 && $height > 0 ) {
				$ratios[] = $height / $width;
			}
		}
		if ( array() === $ratios ) {
			return 1.0;
		}
		sort( $ratios );
		$median  = $ratios[ intdiv( count( $ratios ), 2 ) ];
		$nearest = self::SNAPS[0];
		foreach ( self::SNAPS as $snap ) {
			if ( abs( $snap - $median ) < abs( $nearest - $median ) ) {
				$nearest = $snap;
			}
		}

		return $nearest;
	}

	/**
	 * A ratio as a short CSS number.
	 *
	 * @param float $ratio Ratio.
	 */
	private static function format( float $ratio ): string {
		return rtrim( rtrim( number_format( $ratio, 4, '.', '' ), '0' ), '.' );
	}
}
