<?php
/**
 * Product Specs — a spec changing type.
 *
 * Values are terms read by name for the spec's current type
 * (Product_Specs_Taxonomy::value_from_text), so a type change rewrites
 * nothing on products or guides. Two things on the spec itself are in the
 * old type's terms, though: its default (a step index, a number, or text)
 * and its step terms (a scale's steps, in order). This puts both right for
 * the new type before the registry is validated, whatever the editor sent,
 * so a stale editor or a direct API call can't leave a scale's step index
 * showing as "Fit: 1", or old step terms renaming the wrong values.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

/**
 * Makes submitted specs whose type changed right for their new type.
 *
 * @phpstan-import-type Item from Product_Specs_Registry
 */
final class Product_Specs_Retype {

	/**
	 * Submitted items, each one whose type changed made right for it.
	 *
	 * @param array $submitted Raw items, as sent.
	 * @param array $saved     Items as saved.
	 * @phpstan-param list<mixed> $submitted
	 * @phpstan-param list<Item> $saved
	 * @return list<mixed>
	 */
	public static function apply( array $submitted, array $saved ): array {
		$before = array_column( $saved, null, 'key' );
		foreach ( $submitted as $i => $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$key = sanitize_key( is_scalar( $entry['key'] ?? null ) ? (string) $entry['key'] : '' );
			$old = $before[ $key ] ?? null;
			if ( null === $old || ( $entry['type'] ?? '' ) === $old['type'] ) {
				continue;
			}

			// The old steps' terms say nothing about the new steps.
			$entry['step_terms']   = array();
			$entry['default_term'] = 0;
			// A default the editor converted is in the new type already; one
			// left as saved is still in the old type's terms.
			if ( ( is_scalar( $entry['default'] ?? null ) ? (string) $entry['default'] : '' ) === $old['default'] ) {
				$entry['default'] = self::convert( $old, $entry );
			}
			$submitted[ $i ] = $entry;
		}

		return $submitted;
	}

	/**
	 * The saved default, as words, read as the new type ('' when it can't be).
	 *
	 * @param array                $old   Item as saved.
	 * @param array<string, mixed> $entry Submitted item with its new type.
	 * @phpstan-param Item $old
	 */
	private static function convert( array $old, array $entry ): string {
		$text = self::text( $old );
		if ( '' === $text ) {
			return '';
		}

		$entry['default'] = '';
		$new              = Product_Specs_Registry::validate( array( 'items' => array( $entry ) ) )['items'][0] ?? null;

		return null === $new ? '' : Product_Specs_Taxonomy::value_from_text( $new, $text );
	}

	/**
	 * A spec's default as words: its term's name, else the value as shown.
	 *
	 * @param array $item Item.
	 * @phpstan-param Item $item
	 */
	private static function text( array $item ): string {
		$term = $item['default_term'] > 0 && '' !== $item['taxonomy'] ? get_term( $item['default_term'], $item['taxonomy'] ) : null;
		if ( $term instanceof \WP_Term ) {
			return $term->name;
		}
		if ( '' === $item['default'] ) {
			return '';
		}
		if ( Product_Specs_Registry::TYPE_PICK === $item['type'] ) {
			return $item['steps'][ (int) $item['default'] ] ?? '';
		}
		if ( Product_Specs_Registry::TYPE_NUMBER === $item['type'] ) {
			return trim( $item['default'] . ' ' . $item['unit'] );
		}

		// A scale or number holds one value: a list's first.
		return $item['multiple'] ? ( Product_Specs_Taxonomy::split( $item['default'] )[0] ?? '' ) : $item['default'];
	}
}
