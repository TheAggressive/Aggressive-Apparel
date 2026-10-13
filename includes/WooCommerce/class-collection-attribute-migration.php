<?php
/**
 * Collection Attribute Migration
 *
 * Copies audience assignments (Men's / Women's / Unisex) from the legacy
 * `pa_fit` attribute to `pa_collection`, through WooCommerce CRUD so prices,
 * stock, categories, variations and every other attribute are untouched.
 *
 * Additive and idempotent: it only ever adds missing `pa_collection` terms.
 * Removing the migrated terms from `pa_fit` is a separate opt-in, and never
 * happens where `pa_fit` drives variations (that would orphan the variations'
 * `attribute_pa_fit` values). Non-audience `pa_fit` terms are never touched.
 *
 * Run via `wp aggressive-apparel collection-migrate` (see
 * Collection_Migration_Command); it is never run automatically.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Moves audience terms from `pa_fit` to `pa_collection`.
 *
 * @phpstan-type Entry array{id: int, name: string, type: string, status: string, add: list<string>, remove: list<string>, note: string}
 * @phpstan-type Report array{dry_run: bool, attribute: string, terms: list<string>, products: list<Entry>}
 */
final class Collection_Attribute_Migration {

	public const SOURCE = 'pa_fit';
	public const TARGET = 'pa_collection';

	/**
	 * Positively identified audience slugs on `pa_fit` → `pa_collection` slug.
	 * Anything else on `pa_fit` is assumed to be a real fit and left alone.
	 */
	public const AUDIENCE_SLUGS = array(
		'mens'   => 'mens',
		'men'    => 'mens',
		'womens' => 'womens',
		'women'  => 'womens',
		'unisex' => 'unisex',
	);

	public const STATUS_UPDATED      = 'updated';
	public const STATUS_WOULD_UPDATE = 'would-update';
	public const STATUS_UNCHANGED    = 'unchanged';
	public const STATUS_SKIPPED      = 'skipped';

	/** Note on products whose `pa_fit` was kept because it drives variations. */
	public const NOTE_VARIATION = 'pa_fit is used for variations; left in place';

	/** Products handled between runtime-cache flushes. */
	private const BATCH_SIZE = 50;

	/**
	 * Canonical collection terms, in display order.
	 *
	 * @return array<string, string> Slug → name.
	 */
	public static function terms(): array {
		return array(
			'mens'   => __( "Men's", 'aggressive-apparel' ),
			'womens' => __( "Women's", 'aggressive-apparel' ),
			'unisex' => __( 'Unisex', 'aggressive-apparel' ),
		);
	}

	/**
	 * Migrate (or, in a dry run, report) every product carrying `pa_fit`.
	 *
	 * @param bool $dry_run       Report only; write nothing.
	 * @param bool $remove_source Also drop migrated audience terms from `pa_fit`.
	 * @return array Report.
	 * @phpstan-return Report
	 * @throws \RuntimeException When the Collection attribute or a term cannot be created.
	 */
	public function run( bool $dry_run = true, bool $remove_source = false ): array {
		$report = array(
			'dry_run'   => $dry_run,
			'attribute' => $this->ensure_attribute( $dry_run ),
			'terms'     => $this->ensure_terms( $dry_run ),
			'products'  => array(),
		);

		foreach ( $this->product_ids() as $index => $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product instanceof \WC_Product && ! $product->is_type( 'variation' ) ) {
				$report['products'][] = $this->migrate_product( $product, $dry_run, $remove_source );
			}
			// Bound memory on large catalogues. Runtime only: a full flush
			// would empty a shared persistent object cache in production.
			if ( 0 === ( $index + 1 ) % self::BATCH_SIZE ) {
				wp_cache_flush_runtime();
			}
		}

		return $report;
	}

	/**
	 * Migrate one product.
	 *
	 * @param \WC_Product $product       Product (never a variation).
	 * @param bool        $dry_run       Report only.
	 * @param bool        $remove_source Drop migrated terms from `pa_fit`.
	 * @return array Entry.
	 * @phpstan-return Entry
	 */
	public function migrate_product( \WC_Product $product, bool $dry_run, bool $remove_source ): array {
		$entry = array(
			'id'     => $product->get_id(),
			'name'   => $product->get_name(),
			'type'   => $product->get_type(),
			'status' => self::STATUS_UNCHANGED,
			'add'    => array(),
			'remove' => array(),
			'note'   => '',
		);

		$attributes = $product->get_attributes();
		$source     = $attributes[ self::SOURCE ] ?? null;
		if ( ! $source instanceof \WC_Product_Attribute ) {
			$entry['status'] = self::STATUS_SKIPPED;
			$entry['note']   = 'no pa_fit attribute';
			return $entry;
		}

		$audience = array(); // Source slug → target slug.
		foreach ( self::attribute_slugs( $source ) as $slug ) {
			if ( isset( self::AUDIENCE_SLUGS[ $slug ] ) ) {
				$audience[ $slug ] = self::AUDIENCE_SLUGS[ $slug ];
			}
		}
		if ( array() === $audience ) {
			$entry['status'] = self::STATUS_SKIPPED;
			$entry['note']   = 'no audience terms on pa_fit';
			return $entry;
		}

		$target       = $attributes[ self::TARGET ] ?? null;
		$existing     = $target instanceof \WC_Product_Attribute ? self::attribute_slugs( $target ) : array();
		$entry['add'] = array_values( array_diff( array_unique( array_values( $audience ) ), $existing ) );

		if ( $remove_source ) {
			if ( $source->get_variation() ) {
				$entry['note'] = self::NOTE_VARIATION;
			} else {
				$entry['remove'] = array_keys( $audience );
			}
		}

		if ( array() === $entry['add'] && array() === $entry['remove'] ) {
			// A held-back removal still needs a hand fix: never "unchanged".
			if ( self::NOTE_VARIATION === $entry['note'] ) {
				$entry['status'] = self::STATUS_SKIPPED;
			}
			return $entry;
		}

		if ( $dry_run ) {
			$entry['status'] = self::STATUS_WOULD_UPDATE;
			return $entry;
		}

		if ( array() !== $entry['add'] ) {
			$ids = self::term_ids( $entry['add'] );
			if ( count( $ids ) !== count( $entry['add'] ) ) {
				$entry['status'] = self::STATUS_SKIPPED;
				$entry['note']   = 'pa_collection terms missing';
				return $entry;
			}
			if ( $target instanceof \WC_Product_Attribute ) {
				$target->set_options( array_merge( array_map( 'intval', $target->get_options() ), $ids ) );
			} else {
				$target = new \WC_Product_Attribute();
				$target->set_id( wc_attribute_taxonomy_id_by_name( self::TARGET ) );
				$target->set_name( self::TARGET );
				$target->set_options( $ids );
				$target->set_position( count( $attributes ) );
				$target->set_visible( $source->get_visible() );
				$target->set_variation( false );
			}
			$attributes[ self::TARGET ] = $target;
		}

		if ( array() !== $entry['remove'] ) {
			$remaining = array_diff( array_map( 'intval', $source->get_options() ), self::term_ids( $entry['remove'], self::SOURCE ) );
			if ( array() === $remaining ) {
				unset( $attributes[ self::SOURCE ] );
			} else {
				$source->set_options( array_values( $remaining ) );
			}
		}

		$product->set_attributes( $attributes );
		$product->save();
		$entry['status'] = self::STATUS_UPDATED;

		return $entry;
	}

	/**
	 * Create the global Collection attribute when missing.
	 *
	 * @param bool $dry_run Report only.
	 * @return string `exists`, `created` or `would-create`.
	 * @throws \RuntimeException When WooCommerce rejects the attribute.
	 */
	private function ensure_attribute( bool $dry_run ): string {
		if ( wc_attribute_taxonomy_id_by_name( self::TARGET ) > 0 ) {
			return 'exists';
		}
		if ( $dry_run ) {
			return 'would-create';
		}

		$id = wc_create_attribute(
			array(
				'name'         => __( 'Collection', 'aggressive-apparel' ),
				'slug'         => 'collection',
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => false,
			)
		);
		if ( is_wp_error( $id ) ) {
			throw new \RuntimeException( esc_html( $id->get_error_message() ) );
		}

		// WooCommerce registers attribute taxonomies on `init`; this request
		// already passed it, so register the new one for the term inserts.
		if ( ! taxonomy_exists( self::TARGET ) ) {
			register_taxonomy(
				self::TARGET,
				array( 'product' ),
				array(
					'hierarchical' => false,
					'show_ui'      => false,
					'query_var'    => false,
					'rewrite'      => false,
				)
			);
		}

		return 'created';
	}

	/**
	 * Create any missing canonical Collection terms.
	 *
	 * @param bool $dry_run Report only.
	 * @return list<string> Slugs created (or that would be).
	 * @throws \RuntimeException When a term cannot be created.
	 */
	private function ensure_terms( bool $dry_run ): array {
		$created = array();
		$index   = 0;
		foreach ( self::terms() as $slug => $name ) {
			$exists = taxonomy_exists( self::TARGET ) && term_exists( $slug, self::TARGET );
			if ( ! $exists ) {
				if ( ! $dry_run ) {
					$term = wp_insert_term( $name, self::TARGET, array( 'slug' => $slug ) );
					if ( is_wp_error( $term ) ) {
						// Stop before touching products: every one would be skipped.
						throw new \RuntimeException( esc_html( sprintf( 'Could not create Collection term "%s": %s', $slug, $term->get_error_message() ) ) );
					}
					wc_set_term_order( (int) $term['term_id'], $index, self::TARGET );
				}
				$created[] = $slug;
			}
			++$index;
		}
		return $created;
	}

	/**
	 * Every product (any status) with a `pa_fit` term.
	 *
	 * @return list<int>
	 */
	private function product_ids(): array {
		if ( ! taxonomy_exists( self::SOURCE ) ) {
			return array();
		}
		$term_ids = get_terms(
			array(
				'taxonomy'   => self::SOURCE,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		if ( ! is_array( $term_ids ) || array() === $term_ids ) {
			return array();
		}
		$ids = get_objects_in_term( array_map( 'intval', $term_ids ), self::SOURCE );
		return is_array( $ids ) ? array_values( array_unique( array_map( 'intval', $ids ) ) ) : array();
	}

	/**
	 * Term slugs assigned through a taxonomy attribute.
	 *
	 * @param \WC_Product_Attribute $attribute Attribute.
	 * @return list<string>
	 */
	private static function attribute_slugs( \WC_Product_Attribute $attribute ): array {
		$terms = $attribute->get_terms();
		if ( ! is_array( $terms ) ) {
			return array();
		}
		return array_values(
			array_map(
				static fn( \WP_Term $term ): string => $term->slug,
				array_filter( $terms, static fn( $term ): bool => $term instanceof \WP_Term )
			)
		);
	}

	/**
	 * Resolve slugs to term IDs.
	 *
	 * @param string[] $slugs    Term slugs.
	 * @param string   $taxonomy Taxonomy.
	 * @return list<int>
	 */
	private static function term_ids( array $slugs, string $taxonomy = self::TARGET ): array {
		$ids = array();
		foreach ( $slugs as $slug ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( $term instanceof \WP_Term ) {
				$ids[] = (int) $term->term_id;
			}
		}
		return $ids;
	}
}
