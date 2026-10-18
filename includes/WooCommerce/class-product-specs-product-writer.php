<?php
/**
 * Product Specs Product Writer
 *
 * The one write path for a product's own spec values, which are ordinary
 * WooCommerce product attributes: the product's Size & specs tab, the
 * Products grid, and a size guide's "Apply to products" all go through it.
 * Writes use WooCommerce CRUD, keep every other attribute as it is, and
 * never touch an attribute the product uses for variations.
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
 * Spec values → product attributes.
 *
 * @phpstan-type WriteResult array{applied: int, kept: int, skipped: list<string>}
 */
final class Product_Specs_Product_Writer {

	/**
	 * Set or clear spec values on a product.
	 *
	 * @param \WC_Product          $product      Product.
	 * @param array<string, mixed> $values       Item key => value (step index, number, or text); '' clears it.
	 * @param bool                 $only_missing Only add values the product does not have yet (Apply to products).
	 * @return WriteResult Values written, values kept because the product had its own, and keys skipped because they drive variations.
	 */
	public static function save( \WC_Product $product, array $values, bool $only_missing = false ): array {
		Product_Specs_Registry::ensure_connected();
		$items      = array_column( Product_Specs_Registry::get()['items'], null, 'key' );
		$attributes = $product->get_attributes();
		$result     = array(
			'applied' => 0,
			'kept'    => 0,
			'skipped' => array(),
		);

		foreach ( $values as $key => $raw ) {
			$item = $items[ (string) $key ] ?? null;
			if ( null === $item || '' === $item['taxonomy'] || ! is_scalar( $raw ) ) {
				continue;
			}

			$slug     = sanitize_title( $item['taxonomy'] );
			$existing = $attributes[ $slug ] ?? null;
			$existing = $existing instanceof \WC_Product_Attribute ? $existing : null;
			if ( null !== $existing && $existing->get_variation() ) {
				$result['skipped'][] = $item['key'];
				continue;
			}
			if ( $only_missing && null !== $existing && array() !== $existing->get_options() ) {
				++$result['kept'];
				continue;
			}

			$raw  = (string) $raw;
			$term = Product_Specs_Taxonomy::term_for( $item, $raw );
			if ( 0 === $term ) {
				// Only an empty value clears; an unusable one changes nothing.
				if ( '' === trim( $raw ) && null !== $existing ) {
					unset( $attributes[ $slug ] );
					++$result['applied'];
				}
				continue;
			}

			$attribute = $existing ?? new \WC_Product_Attribute();
			if ( null === $existing ) {
				$attribute->set_position( count( $attributes ) );
			}
			$attribute->set_id( wc_attribute_taxonomy_id_by_name( $item['taxonomy'] ) );
			$attribute->set_name( $item['taxonomy'] );
			$attribute->set_options( array( $term ) );
			$attribute->set_visible( $item['in_table'] );
			$attribute->set_variation( false );
			$attributes[ $slug ] = $attribute;
			++$result['applied'];
		}

		if ( $result['applied'] > 0 ) {
			$product->set_attributes( $attributes );
			$product->save();
		}
		// A value saved before specs were attributes would otherwise show
		// again once the attribute is cleared.
		foreach ( array_keys( $values ) as $key ) {
			if ( ! in_array( (string) $key, $result['skipped'], true ) && ! $only_missing ) {
				delete_post_meta( $product->get_id(), Product_Specs_Meta::key( (string) $key ) );
			}
		}

		return $result;
	}

	/**
	 * Whether a product uses an item's attribute for variations (shown read
	 * only, since changing it would orphan the variations).
	 *
	 * @param \WC_Product $product  Product.
	 * @param string      $taxonomy Attribute taxonomy.
	 */
	public static function drives_variations( \WC_Product $product, string $taxonomy ): bool {
		$attribute = $product->get_attributes()[ sanitize_title( $taxonomy ) ] ?? null;

		return $attribute instanceof \WC_Product_Attribute && $attribute->get_variation();
	}
}
