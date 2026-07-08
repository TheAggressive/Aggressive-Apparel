<?php
/**
 * Search Results
 *
 * Resolves indexed and fallback IDs into grouped autocomplete payloads.
 *
 * @package Aggressive_Apparel
 */

declare( strict_types=1 );

namespace Aggressive_Apparel\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Builds type-aware search result items.
 */
class Search_Results {

	/**
	 * Results returned per content type.
	 *
	 * @var int
	 */
	public const PER_TYPE = 8;

	/**
	 * Product ID batch size when hydrating visible catalogue matches.
	 *
	 * @var int
	 */
	private const PRODUCT_BATCH_SIZE = 24;

	/**
	 * Maximum product IDs scanned per autocomplete query.
	 *
	 * @var int
	 */
	private const PRODUCT_SCAN_CAP = 200;

	/**
	 * Search index service.
	 *
	 * @var Search_Index
	 */
	private Search_Index $index;

	/**
	 * Construct the result builder.
	 *
	 * @param Search_Index $index Search index service.
	 */
	public function __construct( Search_Index $index ) {
		$this->index = $index;
	}

	/**
	 * Search WooCommerce products.
	 *
	 * @param string $query Search term.
	 * @return array<int, array<string, mixed>>
	 */
	public function products( string $query ): array {
		$items      = array();
		$offset     = 0;
		$item_count = 0;

		while ( $item_count < self::PER_TYPE && $offset < self::PRODUCT_SCAN_CAP ) {
			$ids = $this->query_ids( 'product', $query, self::PRODUCT_BATCH_SIZE, $offset );
			if ( empty( $ids ) ) {
				break;
			}

			foreach ( $ids as $id ) {
				if ( ! Search_Visibility::is_public_product( $id ) ) {
					continue;
				}

				$product = wc_get_product( $id );
				if ( ! $product ) {
					continue;
				}

				$thumb_id = $product->get_image_id();
				$thumb    = $thumb_id
					? wp_get_attachment_image_url( (int) $thumb_id, 'aggressive-apparel-product-thumbnail' )
					: '';

				$items[] = array(
					'id'        => $product->get_id(),
					'title'     => $this->clean_text( $product->get_name() ),
					'url'       => get_permalink( $product->get_id() ),
					'thumbnail' => $thumb ? $thumb : '',
					'price'     => $this->format_product_price( $product ),
					'onSale'    => $product->is_on_sale(),
				);

				++$item_count;
				if ( $item_count >= self::PER_TYPE ) {
					break 2;
				}
			}

			$offset += count( $ids );
			if ( count( $ids ) < self::PRODUCT_BATCH_SIZE ) {
				break;
			}
		}

		return $items;
	}

	/**
	 * Search blog posts.
	 *
	 * @param string $query Search term.
	 * @return array<int, array<string, mixed>>
	 */
	public function posts( string $query ): array {
		$ids = $this->query_ids( 'post', $query );

		$items = array();
		foreach ( $ids as $id ) {
			// Defense in depth: query_ids already filters, but re-check after hydration.
			if ( ! Search_Visibility::is_searchable_post( get_post( $id ) ) ) {
				continue;
			}

			$thumb = get_the_post_thumbnail_url( $id, 'aggressive-apparel-blog-thumbnail' );

			$items[] = array(
				'id'        => $id,
				'title'     => $this->clean_text( get_the_title( $id ) ),
				'url'       => get_permalink( $id ),
				'thumbnail' => $thumb ? $thumb : '',
				'excerpt'   => $this->clean_text( $this->get_excerpt( $id ) ),
				'date'      => get_the_date( '', $id ),
			);
		}

		return $items;
	}

	/**
	 * Search pages.
	 *
	 * @param string $query Search term.
	 * @return array<int, array<string, mixed>>
	 */
	public function pages( string $query ): array {
		$ids = $this->query_ids( 'page', $query );

		$items = array();
		foreach ( $ids as $id ) {
			if ( ! Search_Visibility::is_searchable_post( get_post( $id ) ) ) {
				continue;
			}

			$items[] = array(
				'id'    => $id,
				'title' => $this->clean_text( get_the_title( $id ) ),
				'url'   => get_permalink( $id ),
			);
		}

		return $items;
	}

	/**
	 * Run a search for a single post type and return the IDs.
	 *
	 * @param string $post_type Post type slug.
	 * @param string $query     Search term.
	 * @param int    $limit     Maximum IDs to return.
	 * @param int    $offset    Result offset for paginated hydration.
	 * @return array<int, int>
	 */
	private function query_ids( string $post_type, string $query, int $limit = 0, int $offset = 0 ): array {
		if ( $limit <= 0 ) {
			$limit = self::PER_TYPE;
		}

		$limit  = min( 50, max( 1, $limit ) );
		$offset = max( 0, $offset );

		$indexed_ids = $this->index->search( $post_type, $query, $limit, $offset );
		if ( null !== $indexed_ids ) {
			$indexed_ids = $this->filter_searchable_ids( $indexed_ids );
			if ( ! empty( $indexed_ids ) ) {
				_prime_post_caches( $indexed_ids, false, true );
			}
			return $indexed_ids;
		}

		$q = new \WP_Query(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'has_password'           => false,
				's'                      => $query,
				'posts_per_page'         => $limit,
				'offset'                 => $offset,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'fields'                 => 'ids',
			)
		);

		$ids = array_map(
			static fn ( $post ): int => $post instanceof \WP_Post ? $post->ID : (int) $post,
			$q->posts
		);

		if ( ! empty( $ids ) ) {
			_prime_post_caches( $ids, false, true );
		}

		return $this->filter_searchable_ids( $ids );
	}

	/**
	 * Drop IDs that are not publicly searchable.
	 *
	 * @param array<int, int> $ids Candidate post IDs.
	 * @return array<int, int>
	 */
	private function filter_searchable_ids( array $ids ): array {
		$searchable = array();

		foreach ( $ids as $id ) {
			$post = get_post( (int) $id );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			if ( 'product' === $post->post_type ) {
				if ( Search_Visibility::is_public_product( (int) $id ) ) {
					$searchable[] = (int) $id;
				}
				continue;
			}

			if ( Search_Visibility::is_searchable_post( $post ) ) {
				$searchable[] = (int) $id;
			}
		}

		return $searchable;
	}

	/**
	 * Build a concise, plain-text price for a product.
	 *
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	private function format_product_price( \WC_Product $product ): string {
		if ( $product instanceof \WC_Product_Variable ) {
			$min  = (float) $product->get_variation_price( 'min', true );
			$max  = (float) $product->get_variation_price( 'max', true );
			$html = $min !== $max
				/* translators: %s: lowest price in a variable product's range. */
				? sprintf( __( 'From %s', 'aggressive-apparel' ), wc_price( $min ) )
				: wc_price( $min );
		} else {
			$html = wc_price( (float) wc_get_price_to_display( $product ) );
		}

		return $this->clean_text( $html );
	}

	/**
	 * Strip tags and decode HTML entities to plain text.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private function clean_text( string $text ): string {
		return html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Build a short, plain-text excerpt for a post.
	 *
	 * @param int $id Post ID.
	 * @return string
	 */
	private function get_excerpt( int $id ): string {
		$post = get_post( $id );
		if ( ! $post ) {
			return '';
		}

		$text = has_excerpt( $id )
			? get_the_excerpt( $id )
			: wp_trim_words( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ), 24, '…' );

		return (string) $text;
	}
}
