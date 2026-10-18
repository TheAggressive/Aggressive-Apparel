<?php
/**
 * Product Specs Attributes
 *
 * Reads spec values from product attributes that already exist in the catalog
 * (a "Fabric" or "Fabric Weight" attribute, global or custom), so products
 * show specs without retyping them. Each spec item lists the attribute names
 * that can supply it; names match case-insensitively.
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
 * Product attribute fallback for spec values.
 *
 * @phpstan-import-type Item from Product_Specs_Registry
 * @phpstan-type AttributeValue array{text: string, names: list<string>, label: string, visible: bool, own: bool}
 */
final class Product_Specs_Attributes {

	/**
	 * Grams per square metre in one ounce per square yard.
	 *
	 * @var float
	 */
	private const GSM_PER_OZ = 33.906;

	/**
	 * Attribute text for each item: from the item's own attribute taxonomy
	 * (`own`, the product's own value), else from an attribute named like
	 * one of the item's attribute names (custom attributes included).
	 *
	 * @param \WC_Product $product Product.
	 * @param array       $items   Spec items.
	 * @phpstan-param list<Item> $items
	 * @return array<string, AttributeValue> Keyed by item key.
	 */
	public static function values( \WC_Product $product, array $items ): array {
		$lookup = array();
		$owned  = array();
		foreach ( $items as $item ) {
			if ( '' !== $item['taxonomy'] ) {
				$owned[ $item['taxonomy'] ] = $item['key'];
			}
			/**
			 * Filters the attribute names that can supply a spec item.
			 *
			 * @param list<string> $names Attribute names.
			 * @param string       $key   Spec item key.
			 */
			$names = apply_filters( 'aggressive_apparel_product_specs_attribute_names', $item['attributes'], $item['key'] );
			if ( ! is_array( $names ) ) {
				continue;
			}
			foreach ( $names as $name ) {
				if ( is_scalar( $name ) ) {
					$lookup[ self::normalize( (string) $name ) ] ??= $item['key'];
				}
			}
		}

		if ( array() === $lookup && array() === $owned ) {
			return array();
		}

		$found = array();
		// Own taxonomies first, so a name match never shadows them.
		$is_own     = static fn( $attribute ): bool => $attribute instanceof \WC_Product_Attribute && isset( $owned[ $attribute->get_name() ] );
		$attributes = $product->get_attributes();
		$attributes = array_merge(
			array_filter( $attributes, $is_own ),
			array_filter( $attributes, static fn( $attribute ): bool => ! $is_own( $attribute ) )
		);
		foreach ( $attributes as $attribute ) {
			if ( ! $attribute instanceof \WC_Product_Attribute ) {
				continue;
			}

			$label = $attribute->is_taxonomy()
				? wc_attribute_label( $attribute->get_name() )
				: $attribute->get_name();
			$own   = $attribute->is_taxonomy() && isset( $owned[ $attribute->get_name() ] );
			$key   = $own ? $owned[ $attribute->get_name() ] : ( $lookup[ self::normalize( $label ) ] ?? null );
			if ( null === $key || isset( $found[ $key ] ) ) {
				continue;
			}

			$names = array_values( array_filter( array_map( 'sanitize_text_field', self::texts( $product, $attribute ) ) ) );
			$text  = implode( ', ', $names );
			if ( '' === $text ) {
				continue;
			}

			$found[ $key ] = array(
				'text'    => $text,
				'names'   => $names,
				'label'   => $label,
				'visible' => $attribute->get_visible(),
				'own'     => $own,
			);
		}

		return $found;
	}

	/**
	 * A number from free text, in the item's unit.
	 *
	 * Fabric weights ("g/m²" units) prefer an explicit metric value
	 * ("142 g/m²", "142gsm") and otherwise convert ounces ("4.2 oz./yd.²").
	 * Other units take the first number. Returns 0 when none is found.
	 *
	 * @param string $text Attribute text.
	 * @param string $unit Item unit.
	 */
	public static function parse_number( string $text, string $unit ): float {
		$text = str_replace( ',', '.', $text );

		if ( self::is_gsm_unit( $unit ) ) {
			if ( 1 === preg_match( '/(\d+(?:\.\d+)?)\s*(?:g\s*\/\s*m(?:²|2|\^2)?|gsm)(?![a-z])/iu', $text, $metric ) ) {
				return (float) $metric[1];
			}
			if ( 1 === preg_match( '/(\d+(?:\.\d+)?)\s*oz(?![a-z])/iu', $text, $imperial ) ) {
				return round( (float) $imperial[1] * self::GSM_PER_OZ );
			}

			return 0.0;
		}

		return 1 === preg_match( '/\d+(?:\.\d+)?/', $text, $number ) ? (float) $number[0] : 0.0;
	}

	/**
	 * Picked step whose name matches attribute text, or -1.
	 *
	 * Lets an existing "Fit: Relaxed" attribute drive a Fit scale.
	 *
	 * @param string $text  Attribute text.
	 * @param array  $steps Step names.
	 * @phpstan-param list<string> $steps
	 */
	public static function match_step( string $text, array $steps ): int {
		$needle = self::normalize( $text );
		foreach ( $steps as $i => $name ) {
			if ( self::normalize( $name ) === $needle ) {
				return $i;
			}
		}

		return -1;
	}

	/**
	 * Whether a unit is a fabric weight in g/m².
	 *
	 * @param string $unit Unit.
	 */
	public static function is_gsm_unit( string $unit ): bool {
		return in_array( strtolower( str_replace( ' ', '', $unit ) ), array( 'g/m²', 'g/m2', 'gsm' ), true );
	}

	/**
	 * Fabric weight in oz/yd², formatted.
	 *
	 * @param float $gsm Grams per square metre.
	 */
	public static function format_ounces( float $gsm ): string {
		return number_format_i18n( $gsm / self::GSM_PER_OZ, 1 );
	}

	/**
	 * Display values of one attribute.
	 *
	 * @param \WC_Product           $product   Product.
	 * @param \WC_Product_Attribute $attribute Attribute.
	 * @return list<string>
	 */
	private static function texts( \WC_Product $product, \WC_Product_Attribute $attribute ): array {
		if ( $attribute->is_taxonomy() ) {
			$names = wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) );

			return array_values( array_filter( array_map( 'strval', $names ) ) );
		}

		return array_values( array_filter( array_map( 'strval', $attribute->get_options() ) ) );
	}

	/**
	 * Case- and spacing-insensitive name.
	 *
	 * @param string $name Name.
	 */
	private static function normalize( string $name ): string {
		return strtolower( trim( (string) preg_replace( '/\s+/', ' ', $name ) ) );
	}
}
