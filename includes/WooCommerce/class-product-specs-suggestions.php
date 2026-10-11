<?php
/**
 * Product Specs Suggestions
 *
 * Product attributes the store already uses that could become specs, each
 * with a guessed type, unit, steps, and boundaries from its real values.
 * Shown on the Specs tab so a store can turn existing data into specs in a
 * click instead of retyping it.
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
 * Attribute → spec suggestions.
 *
 * @phpstan-type Suggestion array{name: string, products: int, values: list<string>, type: string, unit: string, steps: list<string>, thresholds: list<float>}
 */
final class Product_Specs_Suggestions {

	/**
	 * Attribute names never suggested (variation axes, not specs).
	 *
	 * @var list<string>
	 */
	private const SKIP_ATTRIBUTES = array( 'size', 'sizes', 'color', 'colour', 'colors', 'colours' );

	/**
	 * Products scanned for custom (per-product) attributes.
	 *
	 * @var int
	 */
	private const SCAN_LIMIT = 300;

	/**
	 * Transient holding the attribute scan; cleared when products or
	 * attributes change (see Product_Specs_Settings::init()).
	 *
	 * @var string
	 */
	private const CACHE_KEY = 'aa_spec_attribute_scan';

	/**
	 * The scan, once read this request.
	 *
	 * @var array<string, array{products: int, values: array<string, int>}>|null
	 */
	private static ?array $scan = null;

	/**
	 * Attributes in use that no spec reads yet, most used first.
	 *
	 * @return list<Suggestion>
	 */
	public static function all(): array {
		$known = array();
		foreach ( Product_Specs_Registry::get()['items'] as $item ) {
			$known[ self::normalize( $item['name'] ) ] = true;
			foreach ( $item['attributes'] as $name ) {
				$known[ self::normalize( $name ) ] = true;
			}
		}

		$found = array();
		foreach ( self::scan() as $name => $data ) {
			$key = self::normalize( $name );
			if (
				isset( $known[ $key ] )
				|| in_array( (string) preg_replace( '/^pa_/', '', $key ), self::SKIP_ATTRIBUTES, true )
				|| $data['products'] < 2
			) {
				continue;
			}
			$found[] = self::suggest( $name, $data['products'], $data['values'] );
		}

		usort( $found, static fn( array $a, array $b ): int => $b['products'] <=> $a['products'] );

		return array_slice( $found, 0, 8 );
	}

	/**
	 * Every attribute name in use, so the editor can flag a spec that reads
	 * an attribute no product has (a typo, or one renamed since).
	 *
	 * @return list<string>
	 */
	public static function names(): array {
		return array_map( 'strval', array_keys( self::scan() ) );
	}

	/**
	 * Forget the attribute scan (a product or attribute changed).
	 */
	public static function flush(): void {
		self::$scan = null;
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Forget the scan when a global attribute's terms change.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy.
	 */
	public static function flush_for_term( $term_id, $tt_id, $taxonomy ): void {
		unset( $term_id, $tt_id );
		if ( is_string( $taxonomy ) && str_starts_with( $taxonomy, 'pa_' ) ) {
			self::flush();
		}
	}

	/**
	 * Global and custom attributes in use. The scan reads hundreds of
	 * products, so it is cached until a product or attribute changes.
	 *
	 * @return array<string, array{products: int, values: array<string, int>}>
	 */
	private static function scan(): array {
		if ( null !== self::$scan ) {
			return self::$scan;
		}

		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			$scan = array();
			foreach ( $cached as $name => $data ) {
				if ( is_array( $data ) && isset( $data['products'], $data['values'] ) && is_array( $data['values'] ) ) {
					$scan[ (string) $name ] = array(
						'products' => (int) $data['products'],
						'values'   => array_map( 'intval', $data['values'] ),
					);
				}
			}
			self::$scan = $scan;

			return $scan;
		}

		self::$scan = self::global_attributes() + self::custom_attributes();
		set_transient( self::CACHE_KEY, self::$scan, 12 * HOUR_IN_SECONDS );

		return self::$scan;
	}

	/**
	 * A suggested spec from an attribute's values.
	 *
	 * Mostly numeric values (g/m², %, cm…) become a number scale whose
	 * boundaries split the store's own values into even groups; a few
	 * repeated values become a picked scale; anything else a text detail.
	 *
	 * @param string             $name     Attribute name.
	 * @param int                $products Products using it.
	 * @param array<string, int> $values   Value => products using it.
	 * @return Suggestion
	 */
	private static function suggest( string $name, int $products, array $values ): array {
		arsort( $values );
		$texts = array_map( 'strval', array_keys( $values ) );

		$unit    = self::unit( $texts );
		$numbers = array();
		foreach ( $texts as $text ) {
			$number = Product_Specs_Attributes::parse_number( $text, $unit );
			if ( $number > 0 ) {
				$numbers[] = $number;
			}
		}

		$suggestion = array(
			'name'       => $name,
			'products'   => $products,
			'values'     => array_slice( $texts, 0, 8 ),
			'type'       => Product_Specs_Registry::TYPE_TEXT,
			'unit'       => '',
			'steps'      => array(),
			'thresholds' => array(),
		);

		if ( count( $numbers ) >= 2 && count( $numbers ) >= 0.8 * count( $texts ) ) {
			$suggestion['type']       = Product_Specs_Registry::TYPE_NUMBER;
			$suggestion['unit']       = $unit;
			$suggestion['steps']      = Product_Specs_Attributes::is_gsm_unit( $unit )
				? array( __( 'Lightweight', 'aggressive-apparel' ), __( 'Light-midweight', 'aggressive-apparel' ), __( 'Midweight', 'aggressive-apparel' ), __( 'Mid-heavyweight', 'aggressive-apparel' ), __( 'Heavyweight', 'aggressive-apparel' ) )
				: array( __( 'Very low', 'aggressive-apparel' ), __( 'Low', 'aggressive-apparel' ), __( 'Medium', 'aggressive-apparel' ), __( 'High', 'aggressive-apparel' ), __( 'Very high', 'aggressive-apparel' ) );
			$suggestion['thresholds'] = self::boundaries( $numbers, count( $suggestion['steps'] ) - 1 );
		} elseif ( count( $texts ) >= Product_Specs_Registry::MIN_STEPS && count( $texts ) <= Product_Specs_Registry::MAX_STEPS ) {
			$suggestion['type']  = Product_Specs_Registry::TYPE_PICK;
			$suggestion['steps'] = $texts;
		}

		return $suggestion;
	}

	/**
	 * Boundaries that split numbers into even groups, strictly ascending.
	 *
	 * @param array $numbers Numbers.
	 * @param int   $count   Boundaries needed.
	 * @phpstan-param list<float> $numbers
	 * @return list<float>
	 */
	private static function boundaries( array $numbers, int $count ): array {
		sort( $numbers );
		$min = $numbers[0];
		$max = $numbers[ count( $numbers ) - 1 ];

		$bounds = array();
		for ( $i = 1; $i <= $count; $i++ ) {
			$value    = $numbers[ (int) floor( $i * count( $numbers ) / ( $count + 1 ) ) ] ?? $max;
			$bounds[] = round( $value );
		}

		$bounds = array_values( array_unique( $bounds ) );
		if ( count( $bounds ) !== $count || $bounds[0] <= 0 ) {
			// Too few distinct values: spread evenly between min and max.
			$step   = max( 1.0, ( $max - $min ) / ( $count + 1 ) );
			$bounds = array();
			for ( $i = 1; $i <= $count; $i++ ) {
				$bounds[] = round( $min + $step * $i );
			}
		}

		return array_map( 'floatval', $bounds );
	}

	/**
	 * The unit most values are written in.
	 *
	 * @param array $texts Values.
	 * @phpstan-param list<string> $texts
	 */
	private static function unit( array $texts ): string {
		$joined = strtolower( implode( ' ', $texts ) );
		foreach ( array(
			'g/m²' => '/g\s*\/\s*m|gsm|\boz\b/u',
			'%'    => '/%/',
			'cm'   => '/\d\s*cm\b/',
			'mm'   => '/\d\s*mm\b/',
			'in'   => '/\d\s*(?:in\b|")/',
		) as $unit => $pattern ) {
			if ( 1 === preg_match( $pattern, $joined ) ) {
				return $unit;
			}
		}

		return '';
	}

	/**
	 * Global attributes: name => products and value counts.
	 *
	 * @return array<string, array{products: int, values: array<string, int>}>
	 */
	private static function global_attributes(): array {
		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return array();
		}

		$list = array();
		foreach ( wc_get_attribute_taxonomies() as $attribute ) {
			$taxonomy = wc_attribute_taxonomy_name( $attribute->attribute_name );
			$terms    = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => true,
					'number'     => 50,
				)
			);
			if ( ! is_array( $terms ) || array() === $terms ) {
				continue;
			}

			$values = array();
			$ids    = array();
			foreach ( $terms as $term ) {
				if ( $term instanceof \WP_Term ) {
					$values[ $term->name ] = (int) $term->count;
					$ids[]                 = $term->term_id;
				}
			}
			$products = get_objects_in_term( $ids, $taxonomy );

			$list[ $attribute->attribute_label ] = array(
				'products' => is_array( $products ) ? count( array_unique( $products ) ) : 0,
				'values'   => $values,
			);
		}

		return $list;
	}

	/**
	 * Custom (per-product) attributes from recent products.
	 *
	 * @return array<string, array{products: int, values: array<string, int>}>
	 */
	private static function custom_attributes(): array {
		$ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => self::SCAN_LIMIT,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		update_meta_cache( 'post', array_map( 'intval', $ids ) );

		$list = array();
		foreach ( $ids as $id ) {
			$attributes = get_post_meta( (int) $id, '_product_attributes', true );
			if ( ! is_array( $attributes ) ) {
				continue;
			}
			foreach ( $attributes as $attribute ) {
				if ( ! is_array( $attribute ) || ! empty( $attribute['is_taxonomy'] ) || ! isset( $attribute['name'], $attribute['value'] ) ) {
					continue;
				}
				$name = sanitize_text_field( (string) $attribute['name'] );
				if ( '' === $name ) {
					continue;
				}
				$list[ $name ]            ??= array(
					'products' => 0,
					'values'   => array(),
				);
				$list[ $name ]['products'] += 1;
				foreach ( array_filter( array_map( 'trim', explode( '|', (string) $attribute['value'] ) ) ) as $value ) {
					$value                             = sanitize_text_field( $value );
					$list[ $name ]['values'][ $value ] = ( $list[ $name ]['values'][ $value ] ?? 0 ) + 1;
				}
			}
		}

		return $list;
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
