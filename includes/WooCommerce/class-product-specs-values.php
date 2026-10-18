<?php
/**
 * Product Specs Values
 *
 * The one write path for spec values on a product or a size guide, shared by
 * the product's Size & specs tab, the Products tab of Products → Size Guides,
 * and the size guide editor.
 * Values are normalized per item type, and empty values delete the meta so
 * the next fallback (attribute, spec default) shows through.
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
 * Spec value normalization and storage.
 *
 * @phpstan-import-type Item from Product_Specs_Registry
 */
final class Product_Specs_Values {

	/**
	 * Store submitted values. Items missing from $values are left untouched.
	 *
	 * @param int                  $post_id Product or size guide ID.
	 * @param array<string, mixed> $values  Item key => raw value.
	 */
	public static function save( int $post_id, array $values ): void {
		foreach ( Product_Specs_Registry::get()['items'] as $item ) {
			if ( ! array_key_exists( $item['key'], $values ) ) {
				continue;
			}

			$value = self::clean( $values[ $item['key'] ], $item );
			$key   = Product_Specs_Meta::key( $item['key'] );
			if ( '' === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}
	}

	/**
	 * A post's stored values for every item (no fallbacks).
	 *
	 * @param int $post_id Product or size guide ID.
	 * @return array<string, string> Item key => stored value ('' when unset).
	 */
	public static function stored( int $post_id ): array {
		$values = array();
		foreach ( Product_Specs_Registry::get()['items'] as $item ) {
			$values[ $item['key'] ] = self::clean( get_post_meta( $post_id, Product_Specs_Meta::key( $item['key'] ), true ), $item );
		}

		return $values;
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
}
