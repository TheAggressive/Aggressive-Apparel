<?php
/**
 * Size Guide Spec Items
 *
 * Specs a size guide owns: items in the spec registry whose `guides` list
 * names the guide. The size guide editor creates and edits them; they show
 * only on products using one of their guides. Saving a guide replaces just
 * the items it owns, so it never touches store-wide items or another
 * guide's, whatever the request sends.
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
 * Guide-owned spec items.
 *
 * @phpstan-import-type Item from Product_Specs_Registry
 */
final class Size_Guide_Spec_Items {

	/**
	 * Replace a guide's own items with the submitted ones and save the
	 * registry. Nothing is saved when any item is invalid.
	 *
	 * Submitted items keep the guides of the owned item with the same key (so
	 * sharing survives an edit); any other item is new to this guide only and
	 * gets a fresh key if its key is taken. An item marked `join` with the key
	 * of another guide's spec adds this guide to it instead (several guides
	 * imported together share one new spec). Owned items left out are removed
	 * from this guide, and deleted when no other guide has them.
	 *
	 * $store adds new store-wide specs in the same save (a spec made while
	 * importing); existing specs are never changed through it.
	 *
	 * @param int   $guide_id  Size guide post ID.
	 * @param mixed $submitted Raw items the guide owns.
	 * @param mixed $store     Raw new store-wide items.
	 * @return list<string> Errors; empty when saved.
	 */
	public static function save( int $guide_id, $submitted, $store = array() ): array {
		$registry  = Product_Specs_Registry::get();
		$submitted = is_array( $submitted ) ? array_values( array_filter( $submitted, 'is_array' ) ) : array();

		$owned = array();
		$items = array();
		$at    = null;
		foreach ( $registry['items'] as $item ) {
			if ( ! in_array( $guide_id, $item['guides'], true ) ) {
				$items[] = $item;
				continue;
			}
			$at                    = $at ?? count( $items );
			$owned[ $item['key'] ] = $item;
		}

		$kept = array();
		foreach ( $submitted as $entry ) {
			$kept[ self::key( $entry ) ] = true;
		}
		foreach ( $owned as $key => $item ) {
			$others = array_values( array_diff( $item['guides'], array( $guide_id ) ) );
			if ( ! isset( $kept[ $key ] ) && array() !== $others ) {
				$item['guides'] = $others;
				$items[]        = $item;
			}
		}

		$taken = array_fill_keys( array_column( $items, 'key' ), true );
		$added = array();
		foreach ( $submitted as $entry ) {
			$key = self::key( $entry );
			if ( ! isset( $owned[ $key ] ) && ! empty( $entry['join'] ) && self::join( $items, $key, $guide_id ) ) {
				continue;
			}
			if ( isset( $owned[ $key ] ) && ! isset( $taken[ $key ] ) ) {
				$entry['guides'] = $owned[ $key ]['guides'];
			} else {
				$entry['guides'] = array( $guide_id );
				$key             = self::free_key( $key, $taken );
			}
			$entry['key']  = $key;
			$taken[ $key ] = true;
			$added[]       = $entry;
		}

		array_splice( $items, $at ?? count( $items ), 0, $added );

		foreach ( is_array( $store ) ? array_filter( $store, 'is_array' ) : array() as $entry ) {
			$key             = self::free_key( self::key( $entry ), $taken );
			$entry['key']    = $key;
			$entry['guides'] = array();
			$taken[ $key ]   = true;
			$items[]         = $entry;
		}

		$result = Product_Specs_Registry::validate(
			array(
				'items'           => $items,
				'hide_dimensions' => $registry['hide_dimensions'],
			)
		);
		if ( array() !== $result['errors'] ) {
			return $result['errors'];
		}

		return Product_Specs_Registry::save_connected(
			array(
				'items'           => $result['items'],
				'hide_dimensions' => $result['hide_dimensions'],
			)
		);
	}

	/**
	 * Add a guide to another guide's spec with this key; false when there is
	 * none (a store-wide spec isn't joinable: it is on every guide already).
	 *
	 * @param array  $items    Registry items (changed in place).
	 * @param string $key      Item key.
	 * @param int    $guide_id Guide joining.
	 * @phpstan-param list<Item> $items
	 */
	private static function join( array &$items, string $key, int $guide_id ): bool {
		foreach ( $items as $i => $item ) {
			if ( $item['key'] === $key && array() !== $item['guides'] ) {
				$items[ $i ]['guides'] = array_values( array_unique( array_merge( $item['guides'], array( $guide_id ) ) ) );
				return true;
			}
		}

		return false;
	}

	/**
	 * Share a guide's own items with another guide (a duplicate).
	 *
	 * @param int $from Source guide ID.
	 * @param int $to   Guide that gets the same items.
	 */
	public static function share( int $from, int $to ): void {
		$registry = Product_Specs_Registry::get();
		$changed  = false;
		foreach ( $registry['items'] as $i => $item ) {
			if ( in_array( $from, $item['guides'], true ) && ! in_array( $to, $item['guides'], true ) ) {
				$registry['items'][ $i ]['guides'][] = $to;
				$changed                             = true;
			}
		}

		if ( $changed ) {
			Product_Specs_Registry::save( $registry );
		}
	}

	/**
	 * An entry's key as the registry derives it (from the name when empty).
	 *
	 * @param array<mixed> $entry Raw item.
	 */
	private static function key( array $entry ): string {
		$key = sanitize_key( is_scalar( $entry['key'] ?? null ) ? (string) $entry['key'] : '' );
		if ( '' === $key ) {
			$name = is_scalar( $entry['name'] ?? null ) ? (string) $entry['name'] : '';
			$key  = sanitize_key( str_replace( '-', '_', sanitize_title( $name ) ) );
		}

		return substr( str_replace( '-', '_', $key ), 0, 32 );
	}

	/**
	 * The key, or the first "key_N" not yet taken.
	 *
	 * @param string              $key   Wanted key.
	 * @param array<string, true> $taken Keys in use.
	 */
	private static function free_key( string $key, array $taken ): string {
		if ( '' === $key || ! isset( $taken[ $key ] ) ) {
			return $key;
		}

		for ( $n = 2; ; $n++ ) {
			$suffix    = '_' . $n;
			$candidate = substr( $key, 0, 32 - strlen( $suffix ) ) . $suffix;
			if ( ! isset( $taken[ $candidate ] ) ) {
				return $candidate;
			}
		}
	}
}
