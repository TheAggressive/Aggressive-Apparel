<?php
/**
 * Size Guide Spec Items
 *
 * Specs made while editing a size guide or importing one. Every spec is
 * site-wide (a guide uses one by giving it a value), so these are simply new
 * specs added to the registry. Adding never changes or removes a spec already
 * there, whatever the request sends: a key that's taken gets a fresh one.
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
 * New specs from size guides.
 */
final class Size_Guide_Spec_Items {

	/**
	 * Add new specs and save the registry. Nothing is saved when any is invalid.
	 *
	 * @param mixed                 $submitted Raw new specs.
	 * @param array<string, string> $renamed   Filled with submitted key => saved key, for keys that had to change.
	 * @return list<string> Errors; empty when saved.
	 */
	public static function add( $submitted, array &$renamed = array() ): array {
		$renamed   = array();
		$registry  = Product_Specs_Registry::get();
		$items     = $registry['items'];
		$submitted = is_array( $submitted ) ? array_values( array_filter( $submitted, 'is_array' ) ) : array();
		if ( array() === $submitted ) {
			return array();
		}

		$taken = array_fill_keys( array_column( $items, 'key' ), true );
		foreach ( $submitted as $entry ) {
			$key = self::free_key( self::key( $entry ), $taken );
			self::renamed( $renamed, $entry, $key );
			$entry['key']  = $key;
			$taken[ $key ] = true;
			$items[]       = $entry;
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
	 * Note a submitted key the save had to change.
	 *
	 * @param array<string, string> $renamed Submitted key => saved key.
	 * @param array<mixed>          $entry   Submitted item.
	 * @param string                $key     Saved key.
	 */
	private static function renamed( array &$renamed, array $entry, string $key ): void {
		$submitted = is_scalar( $entry['key'] ?? null ) ? (string) $entry['key'] : '';
		if ( '' !== $submitted && $submitted !== $key ) {
			$renamed[ $submitted ] = $key;
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
