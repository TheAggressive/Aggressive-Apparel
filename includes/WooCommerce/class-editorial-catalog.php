<?php
/**
 * Editorial Catalog
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
 * Resolves the live store data editorial patterns link to and filter by.
 *
 * Term IDs and archive URLs differ between environments (local category base
 * `shop/clothing/hoodies/` vs production `shop/hoodies/`), so patterns never
 * hardcode them: they ask for a term by slug at render time and get back the
 * real archive URL, or the Shop page when the store has no such term — a CTA
 * always lands somewhere real, and a product grid is only ever filtered by a
 * term that exists.
 */
final class Editorial_Catalog {

	/**
	 * First existing term among candidate slugs, searched in taxonomy order.
	 *
	 * @param array<int, string> $taxonomies Taxonomies to search, most specific first.
	 * @param array<int, string> $slugs      Candidate slugs, preferred first.
	 */
	public static function term( array $taxonomies, array $slugs ): ?\WP_Term {
		foreach ( $taxonomies as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			foreach ( $slugs as $slug ) {
				$term = get_term_by( 'slug', $slug, $taxonomy );
				if ( $term instanceof \WP_Term ) {
					return $term;
				}
			}
		}

		return null;
	}

	/**
	 * Like term(), but only a term with published products: a tile or grid
	 * must never lead to an empty archive.
	 *
	 * @param array<int, string> $taxonomies Taxonomies to search, most specific first.
	 * @param array<int, string> $slugs      Candidate slugs, preferred first.
	 */
	public static function stocked_term( array $taxonomies, array $slugs ): ?\WP_Term {
		foreach ( $taxonomies as $taxonomy ) {
			foreach ( $slugs as $slug ) {
				$term = self::term( array( $taxonomy ), array( $slug ) );
				if ( null !== $term && $term->count > 0 ) {
					return $term;
				}
			}
		}

		return null;
	}

	/**
	 * Image for a term: its own thumbnail (the image the merchant chose), else
	 * the newest catalog-visible product in it that has one. Never an image
	 * from outside the term, so a tile always shows what it links to.
	 *
	 * @param \WP_Term $term Term.
	 * @return int Attachment ID, or 0 when the term has no image to show.
	 */
	public static function term_image_id( \WP_Term $term ): int {
		$thumbnail = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
		if ( $thumbnail > 0 && wp_attachment_is_image( $thumbnail ) ) {
			return $thumbnail;
		}

		return (int) Request_Cache::remember(
			'editorial-catalog:term-image:' . $term->term_id,
			static function () use ( $term ): int {
				$query = new \WP_Query(
					array(
						'post_type'              => 'product',
						'post_status'            => 'publish',
						'posts_per_page'         => 1,
						'fields'                 => 'ids',
						'orderby'                => 'date',
						'order'                  => 'DESC',
						'no_found_rows'          => true,
						'update_post_term_cache' => false,
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- One indexed lookup per tile, memoised per request.
						'meta_query'             => array(
							array(
								'key'     => '_thumbnail_id',
								'compare' => 'EXISTS',
							),
						),
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- One indexed lookup per tile, memoised per request.
						'tax_query'              => array(
							array(
								'taxonomy' => $term->taxonomy,
								'terms'    => array( $term->term_id ),
							),
							array(
								'taxonomy' => 'product_visibility',
								'field'    => 'name',
								'terms'    => array( 'exclude-from-catalog' ),
								'operator' => 'NOT IN',
							),
						),
					)
				);
				$product = $query->posts[0] ?? 0;

				return $product > 0 ? (int) get_post_thumbnail_id( $product ) : 0;
			}
		);
	}

	/**
	 * Archive URL for a term, or the Shop page when there is none.
	 *
	 * @param \WP_Term|null $term Term.
	 */
	public static function url( ?\WP_Term $term ): string {
		if ( null !== $term ) {
			$link = get_term_link( $term );
			if ( is_string( $link ) ) {
				return $link;
			}
		}

		return self::shop_url();
	}

	/**
	 * Shop page URL, optionally with query arguments (e.g. `orderby=date`).
	 *
	 * @param array<string, string> $args Query arguments.
	 */
	public static function shop_url( array $args = array() ): string {
		$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );

		return array() === $args ? $shop : add_query_arg( $args, $shop );
	}

	/**
	 * Landing URL for a Collection audience (`/shop/womens/`), falling back to
	 * the Shop filtered by that collection when the audience has no landing
	 * page (e.g. a top-level category already owns the slug).
	 *
	 * @param string $audience Collection term slug.
	 */
	public static function audience_url( string $audience ): string {
		if ( isset( Audience_Landing_Pages::audiences()[ $audience ] ) ) {
			return Audience_Landing_Pages::url( $audience );
		}

		return self::shop_url( array( 'collection' => $audience ) );
	}

	/**
	 * Product Collection query fragment that filters by a term.
	 *
	 * Product categories and tags go in `taxQuery`; global attributes
	 * (`pa_*`) go in `woocommerceAttributes`, which is how the block filters
	 * them.
	 *
	 * @param \WP_Term $term Term.
	 * @return array{taxQuery: array<string, array<int, int>>, woocommerceAttributes: array<int, array{termId: int, taxonomy: string}>}
	 */
	public static function query_filter( \WP_Term $term ): array {
		if ( str_starts_with( $term->taxonomy, 'pa_' ) ) {
			return array(
				'taxQuery'              => array(),
				'woocommerceAttributes' => array(
					array(
						'termId'   => $term->term_id,
						'taxonomy' => $term->taxonomy,
					),
				),
			);
		}

		return array(
			'taxQuery'              => array( $term->taxonomy => array( $term->term_id ) ),
			'woocommerceAttributes' => array(),
		);
	}

	/**
	 * Whether the store has real sales to rank by. A "Best Sellers" grid
	 * ordered by popularity with no sales would present an arbitrary order as
	 * popularity, so callers fall back to an honestly labelled alternative.
	 */
	public static function has_sales_data(): bool {
		return (bool) Request_Cache::remember(
			'editorial-catalog:has-sales',
			static function (): bool {
				global $wpdb;

				$table = $wpdb->prefix . 'wc_product_meta_lookup';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One indexed EXISTS probe, memoised per request; no user input.
				return null !== $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM %i WHERE total_sales > 0 LIMIT 1', $table ) );
			}
		);
	}

	/**
	 * Whether any published, catalog-visible product is marked Featured.
	 *
	 * Matches visibility terms by name, so the answer does not depend on
	 * WooCommerce's memoised visibility term IDs or featured-IDs transient.
	 */
	public static function has_featured_products(): bool {
		return (bool) Request_Cache::remember(
			'editorial-catalog:has-featured',
			static function (): bool {
				$query = new \WP_Query(
					array(
						'post_type'              => 'product',
						'post_status'            => 'publish',
						'posts_per_page'         => 1,
						'fields'                 => 'ids',
						'no_found_rows'          => true,
						'update_post_meta_cache' => false,
						'update_post_term_cache' => false,
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- One indexed term lookup, memoised per request.
						'tax_query'              => array(
							array(
								'taxonomy' => 'product_visibility',
								'field'    => 'name',
								'terms'    => array( 'featured' ),
							),
							array(
								'taxonomy' => 'product_visibility',
								'field'    => 'name',
								'terms'    => array( 'exclude-from-catalog' ),
								'operator' => 'NOT IN',
							),
						),
					)
				);

				return array() !== $query->posts;
			}
		);
	}

	/**
	 * Catalog-visible, in-stock products a section can actually show.
	 *
	 * A term's own count includes hidden and out-of-stock products, which an
	 * editorial grid filters out; sections size their grid from this instead,
	 * so three in-stock hoodies get three large cards, not a half-empty row.
	 *
	 * @param \WP_Term|null $term     Collection (category, tag or attribute term) or null for the whole catalog.
	 * @param bool          $featured Count only featured products.
	 */
	public static function visible_count( ?\WP_Term $term = null, bool $featured = false ): int {
		$key = 'editorial-catalog:visible:' . ( null === $term ? 'all' : $term->term_id ) . ( $featured ? ':featured' : '' );

		return (int) Request_Cache::remember(
			$key,
			static function () use ( $term, $featured ): int {
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
				if ( $featured ) {
					$tax_query[] = array(
						'taxonomy' => 'product_visibility',
						'field'    => 'name',
						'terms'    => array( 'featured' ),
					);
				}
				$query = new \WP_Query(
					array(
						'post_type'              => 'product',
						'post_status'            => 'publish',
						'posts_per_page'         => 1,
						'fields'                 => 'ids',
						'update_post_meta_cache' => false,
						'update_post_term_cache' => false,
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- One indexed count per section, memoised per request.
						'tax_query'              => $tax_query,
					)
				);

				return (int) $query->found_posts;
			}
		);
	}
}
