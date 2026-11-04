<?php
/**
 * Product Specs — upgrading specs that belonged to particular guides.
 *
 * Specs used to carry a `guides` list and show only on those guides'
 * products. Every spec is site-wide now: a product shows one when it has a
 * value from itself, its size guide, or the spec's default. Read as is, an
 * old guide-only spec's default would show on every product in the store.
 *
 * So, once: each listed guide with no value of its own for the spec takes
 * the default as its value, and the spec drops its default and its list.
 * The guides' products show what they showed before; other products show
 * the spec only where they have a value of their own.
 *
 * Runs from Product_Specs_Registry::get() the first time the stored list
 * still has a `guides` entry, so it's done before anything renders with the
 * old data. It writes the option before touching guides, so nothing it
 * calls can start it again.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

/**
 * One-time upgrade of guide-only specs.
 */
final class Product_Specs_Guide_Upgrade {

	/**
	 * Whether the stored list still has the old `guides` entries.
	 *
	 * @param array<mixed> $stored Stored option.
	 */
	public static function needed( array $stored ): bool {
		foreach ( is_array( $stored['items'] ?? null ) ? $stored['items'] : array() as $entry ) {
			if ( is_array( $entry ) && array_key_exists( 'guides', $entry ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Upgrade the stored list and its guides; returns the list as saved.
	 *
	 * @param array<mixed> $stored Stored option.
	 * @return array<mixed>
	 */
	public static function run( array $stored ): array {
		$adopt = array();
		$items = is_array( $stored['items'] ?? null ) ? $stored['items'] : array();
		foreach ( $items as $i => $entry ) {
			if ( ! is_array( $entry ) || ! array_key_exists( 'guides', $entry ) ) {
				continue;
			}

			$guides = array_values( array_filter( array_map( 'absint', is_array( $entry['guides'] ) ? $entry['guides'] : array() ) ) );
			$term   = absint( $entry['default_term'] ?? 0 );
			if ( array() !== $guides && $term > 0 ) {
				$adopt[] = array(
					'key'    => sanitize_key( is_scalar( $entry['key'] ?? null ) ? (string) $entry['key'] : '' ),
					'term'   => $term,
					'guides' => $guides,
				);

				// The default now lives on those guides only.
				$entry['default']      = '';
				$entry['default_term'] = 0;
			}
			unset( $entry['guides'] );
			$items[ $i ] = $entry;
		}

		$stored['items']    = array_values( $items );
		$stored['revision'] = absint( $stored['revision'] ?? 0 ) + 1;
		update_option( Product_Specs_Registry::OPTION_KEY, $stored, true );

		foreach ( $adopt as $spec ) {
			foreach ( $spec['guides'] as $guide_id ) {
				if ( Size_Guide_Post_Type::POST_TYPE === get_post_type( $guide_id ) ) {
					Product_Specs_Values::fill_guide_value( $guide_id, $spec['key'], array( $spec['term'] ) );
				}
			}
		}

		return $stored;
	}
}
