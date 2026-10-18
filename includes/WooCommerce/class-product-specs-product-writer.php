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
 * @phpstan-type WriteResult array{applied: int, kept: int, skipped: list<string>, failed: list<string>, written: list<string>}
 */
final class Product_Specs_Product_Writer {

	/**
	 * Set or clear spec values on a product.
	 *
	 * Only what is written changes: a value that can't be stored (its spec
	 * has no attribute, the value isn't usable, or it would need a new term
	 * the caller may not add) is reported in `failed` and leaves everything
	 * as it was, including any value saved before specs were attributes.
	 *
	 * Adding terms and attributes is catalog management: callers pass
	 * $create false for users without `manage_product_terms`, who can then
	 * pick existing values only.
	 *
	 * @param \WC_Product          $product      Product.
	 * @param array<string, mixed> $values       Item key => value (step index, number, or text); '' clears it.
	 * @param bool                 $only_missing Only add values the product does not have yet (Apply, migration).
	 * @param bool                 $create       May add new terms, and connect specs to (new) attributes.
	 * @return WriteResult Values changed; values kept because the product had its own; keys skipped because they drive variations; keys that couldn't be stored; keys written or cleared.
	 */
	public static function save( \WC_Product $product, array $values, bool $only_missing = false, bool $create = true ): array {
		if ( $create ) {
			Product_Specs_Registry::ensure_connected();
		}
		$items      = array_column( Product_Specs_Registry::get()['items'], null, 'key' );
		$attributes = $product->get_attributes();
		$result     = array(
			'applied' => 0,
			'kept'    => 0,
			'skipped' => array(),
			'failed'  => array(),
			'written' => array(),
		);

		foreach ( $values as $key => $raw ) {
			$item = $items[ (string) $key ] ?? null;
			if ( null === $item || ! is_scalar( $raw ) ) {
				continue;
			}
			if ( '' === $item['taxonomy'] ) {
				$result['failed'][] = $item['key'];
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
			$term = Product_Specs_Taxonomy::term_for( $item, $raw, $create );
			if ( 0 === $term ) {
				// Only an empty value clears; an unusable one changes nothing.
				if ( '' !== trim( $raw ) ) {
					$result['failed'][] = $item['key'];
					continue;
				}
				if ( null !== $existing ) {
					unset( $attributes[ $slug ] );
					++$result['applied'];
				}
				$result['written'][] = $item['key'];
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
			$result['written'][] = $item['key'];
			++$result['applied'];
		}

		if ( $result['applied'] > 0 ) {
			$product->set_attributes( $attributes );
			$product->save();
		}
		// The value now lives in the attribute (or was cleared on purpose), so
		// an older theme-meta value must not show through again.
		foreach ( $result['written'] as $key ) {
			delete_post_meta( $product->get_id(), Product_Specs_Meta::key( $key ) );
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
