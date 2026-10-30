<?php
/**
 * Product Specs Values
 *
 * A size guide's spec values: the terms products using the guide show when
 * they have no value of their own (stored as term IDs, since every value is a
 * term of the spec's attribute), and the store-wide specs it hides. Products'
 * own values are product attributes (Product_Specs_Product_Writer).
 *
 * Values arrive and leave as the editor uses them: a step index, a number,
 * or text.
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
 * Size guide spec values and value normalization.
 *
 * @phpstan-import-type Item from Product_Specs_Registry
 * @phpstan-type GuideSpecs array{values: array<string, string>, hidden: list<string>}
 */
final class Product_Specs_Values {

	/**
	 * Guide meta: item key => term IDs (one, or several for a multi-value
	 * spec; a single ID from before still reads).
	 *
	 * @var string
	 */
	public const GUIDE_TERMS = '_aa_guide_specs';

	/**
	 * Guide meta: store-wide item keys hidden on the guide's products.
	 *
	 * @var string
	 */
	public const GUIDE_HIDDEN = '_aa_guide_hidden';

	/**
	 * Store a guide's submitted values. Items missing from $values are left
	 * untouched; an empty value removes the guide's value.
	 *
	 * @param int                  $guide_id Size guide ID.
	 * @param array<string, mixed> $values   Item key => value.
	 */
	public static function save_guide( int $guide_id, array $values ): void {
		Product_Specs_Registry::ensure_connected();
		$terms = self::guide_terms( $guide_id );
		foreach ( Product_Specs_Registry::get()['items'] as $item ) {
			if ( ! array_key_exists( $item['key'], $values ) || ! is_scalar( $values[ $item['key'] ] ) ) {
				continue;
			}

			$raw = (string) $values[ $item['key'] ];
			$ids = Product_Specs_Taxonomy::terms_for( $item, $raw );
			// A value that isn't storable (no attribute yet, or unusable)
			// leaves whatever the guide had.
			if ( array() !== $ids ) {
				$terms[ $item['key'] ] = $ids;
			} elseif ( '' === trim( $raw ) ) {
				unset( $terms[ $item['key'] ] );
			}
		}

		if ( array() === $terms ) {
			delete_post_meta( $guide_id, self::GUIDE_TERMS );
		} else {
			update_post_meta( $guide_id, self::GUIDE_TERMS, $terms );
		}
	}

	/**
	 * Store the store-wide specs a guide hides.
	 *
	 * @param int   $guide_id Size guide ID.
	 * @param mixed $keys     Item keys.
	 */
	public static function save_hidden( int $guide_id, $keys ): void {
		$keys = Product_Specs_Meta::sanitize_keys( $keys );
		if ( array() === $keys ) {
			delete_post_meta( $guide_id, self::GUIDE_HIDDEN );
		} else {
			update_post_meta( $guide_id, self::GUIDE_HIDDEN, $keys );
		}
	}

	/**
	 * Whether a guide sets anything for its products' specs: a value, or a
	 * store-wide spec hidden. A guide with neither and no chart is empty
	 * (still being built), so products keep their category or store guide.
	 *
	 * @param int $guide_id Size guide post ID.
	 */
	public static function guide_has_specs( int $guide_id ): bool {
		$guide = self::guide( $guide_id );

		return array() !== $guide['hidden'] || array() !== array_filter( $guide['values'], static fn( string $value ): bool => '' !== $value );
	}

	/**
	 * A guide's values for every item (no fallbacks) and the keys it hides.
	 *
	 * @param int $guide_id Size guide ID.
	 * @return GuideSpecs
	 */
	public static function guide( int $guide_id ): array {
		$terms  = self::guide_terms( $guide_id );
		$values = array();
		foreach ( Product_Specs_Registry::get()['items'] as $item ) {
			$values[ $item['key'] ] = isset( $terms[ $item['key'] ] )
				? Product_Specs_Taxonomy::value_of_terms( $item, $terms[ $item['key'] ] )
				: '';
		}

		return array(
			'values' => $values,
			'hidden' => Product_Specs_Meta::sanitize_keys( get_post_meta( $guide_id, self::GUIDE_HIDDEN, true ) ),
		);
	}

	/**
	 * A value normalized for an item, or '' when it is not usable.
	 *
	 * @param mixed $raw  Raw value.
	 * @param array $item Item.
	 * @phpstan-param Item $item
	 */
	public static function clean( $raw, array $item ): string {
		$value = Product_Specs_Meta::sanitize_value( $raw );

		if ( Product_Specs_Registry::TYPE_NUMBER === $item['type'] ) {
			return Product_Specs_Meta::number( $value ) > 0 ? $value : '';
		}
		if ( Product_Specs_Registry::TYPE_PICK === $item['type'] ) {
			return Product_Specs_Meta::step( $value, $item ) >= 0 ? $value : '';
		}

		return $value;
	}

	/**
	 * A guide's stored term IDs by item key.
	 *
	 * @param int $guide_id Size guide ID.
	 * @return array<string, list<int>>
	 */
	private static function guide_terms( int $guide_id ): array {
		$raw   = get_post_meta( $guide_id, self::GUIDE_TERMS, true );
		$terms = array();
		foreach ( is_array( $raw ) ? $raw : array() as $key => $ids ) {
			$key = sanitize_key( (string) $key );
			$ids = array_values( array_filter( array_map( 'absint', is_array( $ids ) ? $ids : array( $ids ) ) ) );
			if ( '' !== $key && array() !== $ids ) {
				$terms[ $key ] = $ids;
			}
		}

		return $terms;
	}
}
