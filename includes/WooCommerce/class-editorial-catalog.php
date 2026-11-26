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
	 * Like term(), but only a term with something to show (visible_count()):
	 * a term whose products are all hidden or out of stock is passed over for
	 * the next candidate, so a section never leads to an empty grid.
	 *
	 * @param array<int, string> $taxonomies Taxonomies to search, most specific first.
	 * @param array<int, string> $slugs      Candidate slugs, preferred first.
	 */
	public static function stocked_term( array $taxonomies, array $slugs ): ?\WP_Term {
		foreach ( $taxonomies as $taxonomy ) {
			foreach ( $slugs as $slug ) {
				$term = self::term( array( $taxonomy ), array( $slug ) );
				if ( null !== $term && $term->count > 0 && self::visible_count( $term ) > 0 ) {
					return $term;
				}
			}
		}

		return null;
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
	 * Where a section's "Shop" link leads: a Collection attribute term to its
	 * audience landing page (attribute archives may not be public, and a
	 * non-public one would ignore the term), any other term to its archive.
	 *
	 * @param \WP_Term $term Term.
	 */
	public static function collection_url( \WP_Term $term ): string {
		return 'pa_collection' === $term->taxonomy ? self::audience_url( $term->slug ) : self::url( $term );
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

	/** Cards in a ranked Best Sellers row (01-03). */
	public const RANKED = 3;

	/**
	 * Whether the store has real sales to rank by: at least RANKED visible,
	 * in-stock products have sold. A sale on a hidden or out-of-stock product
	 * doesn't count, or the grid (which shows only what can be bought) would
	 * fill a "Ranked by real orders" row with products nobody bought; that
	 * would present an arbitrary order as popularity, so callers fall back
	 * to an honestly labelled alternative.
	 */
	public static function has_sales_data(): bool {
		return self::visible_count( null, false, true ) >= self::RANKED;
	}

	/**
	 * Whether any product the Featured grid can show is marked Featured:
	 * published, catalog-visible and in stock, so a section never prints a
	 * heading over an empty grid. Matches visibility terms by name, so the
	 * answer does not depend on WooCommerce's memoised visibility term IDs
	 * or featured-IDs transient.
	 */
	public static function has_featured_products(): bool {
		return self::visible_count( null, true ) > 0;
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
	 * @param bool          $sold     Count only products with recorded sales.
	 */
	public static function visible_count( ?\WP_Term $term = null, bool $featured = false, bool $sold = false ): int {
		$key = 'editorial-catalog:visible:' . ( null === $term ? 'all' : $term->term_id ) . ( $featured ? ':featured' : '' ) . ( $sold ? ':sold' : '' );

		return (int) Request_Cache::remember(
			$key,
			static function () use ( $term, $featured, $sold ): int {
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
				$args = array(
					'post_type'              => 'product',
					'post_status'            => 'publish',
					'posts_per_page'         => 1,
					'fields'                 => 'ids',
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- One indexed count per section, memoised per request.
					'tax_query'              => $tax_query,
				);
				if ( $sold ) {
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- One count, memoised per request.
					$args['meta_query'] = array(
						array(
							'key'     => 'total_sales',
							'value'   => 0,
							'compare' => '>',
							'type'    => 'NUMERIC',
						),
					);
				}
				$query = new \WP_Query( $args );

				return (int) $query->found_posts;
			}
		);
	}
}
