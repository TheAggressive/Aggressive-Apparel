<?php
/**
 * Normalized Catalog Filters
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

defined( 'ABSPATH' ) || exit;

/** Immutable, bounded representation of public catalogue filter input. */
final class Catalog_Filter_Set {

	private const MAX_TERMS = 20;

	/**
	 * Selected product-category slugs.
	 *
	 * @var string[]
	 */
	private array $categories;

	/**
	 * Selected attribute slugs keyed by taxonomy.
	 *
	 * @var array<string, string[]>
	 */
	private array $attributes;

	/**
	 * Minimum requested price.
	 *
	 * @var float
	 */
	private float $min_price;

	/**
	 * Maximum requested price.
	 *
	 * @var float
	 */
	private float $max_price;

	/**
	 * Valid WooCommerce stock status.
	 *
	 * @var string
	 */
	private string $stock;

	/**
	 * Whether sale products are required.
	 *
	 * @var bool
	 */
	private bool $on_sale;

	/**
	 * Normalize raw filter input.
	 *
	 * @param array<string, mixed> $filters Raw public filters.
	 */
	public function __construct( array $filters ) {
		$this->categories = self::normalize_slugs( (string) ( $filters['category'] ?? '' ) );
		$this->attributes = array();

		$attributes = is_array( $filters['attributes'] ?? null ) ? $filters['attributes'] : array();
		foreach ( $attributes as $taxonomy => $terms ) {
			$taxonomy = sanitize_key( (string) $taxonomy );
			$slugs    = self::normalize_slugs( (string) $terms );
			if ( '' !== $taxonomy && ! empty( $slugs ) ) {
				$this->attributes[ $taxonomy ] = $slugs;
			}
		}

		$this->min_price = max( 0.0, (float) ( $filters['min_price'] ?? 0 ) );
		$this->max_price = max( 0.0, (float) ( $filters['max_price'] ?? 0 ) );
		$stock           = sanitize_key( (string) ( $filters['stock'] ?? '' ) );
		$this->stock     = in_array( $stock, array( 'instock', 'outofstock', 'onbackorder' ), true ) ? $stock : '';
		$this->on_sale   = ! empty( $filters['on_sale'] );
	}

	/**
	 * Selected product categories.
	 *
	 * @return string[]
	 */
	public function categories(): array {
		return $this->categories;
	}

	/**
	 * Return attributes restricted to known taxonomies.
	 *
	 * @param string[] $allowed Allowed taxonomy names.
	 * @return array<string, string[]>
	 */
	public function attributes( array $allowed = array() ): array {
		if ( empty( $allowed ) ) {
			return $this->attributes;
		}

		return array_intersect_key( $this->attributes, array_flip( $allowed ) );
	}

	/** Minimum requested price. */
	public function min_price(): float {
		return $this->min_price;
	}

	/** Maximum requested price. */
	public function max_price(): float {
		return $this->max_price;
	}

	/** Valid requested stock status, or an empty string. */
	public function stock(): string {
		return $this->stock;
	}

	/** Whether sale products are required. */
	public function on_sale(): bool {
		return $this->on_sale;
	}

	/**
	 * Normalize comma-separated public input to a bounded unique slug list.
	 *
	 * @param string $value Raw public value.
	 * @return string[]
	 */
	public static function normalize_slugs( string $value ): array {
		$slugs = array_map( 'sanitize_title', explode( ',', $value ) );
		return array_slice( array_values( array_unique( array_filter( $slugs ) ) ), 0, self::MAX_TERMS );
	}
}
