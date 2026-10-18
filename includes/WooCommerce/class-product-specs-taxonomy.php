<?php
/**
 * Product Specs Taxonomy
 *
 * Specs are WooCommerce global attributes: each spec item names one attribute
 * taxonomy (`pa_*`), and every value is one of its terms. Scale steps are the
 * attribute's terms in order, fabric weights are terms like "240 g/m²", and
 * text details are plain terms, so values typed once become picks.
 *
 * This class connects items to attributes (reusing a compatible one or
 * creating it) and turns values into terms and back. It never deletes an
 * attribute or a term: those are catalog data that products may use.
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
 * Spec items ↔ WooCommerce attribute taxonomies.
 *
 * @phpstan-import-type Item from Product_Specs_Registry
 */
final class Product_Specs_Taxonomy {

	/**
	 * Longest attribute slug WooCommerce accepts.
	 *
	 * @var int
	 */
	private const SLUG_MAX = 28;

	/**
	 * Term names per taxonomy, for this request.
	 *
	 * @var array<string, list<string>>
	 */
	private static array $names = array();

	/**
	 * Global attributes as taxonomy => label.
	 *
	 * @return array<string, string>
	 */
	public static function attributes(): array {
		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return array();
		}

		$list = array();
		foreach ( wc_get_attribute_taxonomies() as $attribute ) {
			$list[ wc_attribute_taxonomy_name( $attribute->attribute_name ) ] = (string) $attribute->attribute_label;
		}

		return $list;
	}

	/**
	 * Connect an item to an attribute (a compatible existing one, or a new
	 * one) and keep a picked scale's steps as its terms.
	 *
	 * @param array $item  Item.
	 * @param array $taken Taxonomies other items already use.
	 * @phpstan-param Item $item
	 * @phpstan-param list<string> $taken
	 * @return Item|\WP_Error
	 */
	public static function connect( array $item, array $taken = array() ) {
		// An attribute picked in the editor (no step terms yet) must fit too,
		// or its terms would gain the scale's steps, like "Relaxed" in Size.
		if ( '' !== $item['taxonomy'] && taxonomy_exists( $item['taxonomy'] ) && array() === $item['step_terms'] && ! self::fits( $item, $item['taxonomy'] ) ) {
			return new \WP_Error(
				'aa_specs_attribute_mismatch',
				sprintf(
					/* translators: %s: attribute name. */
					__( 'the “%s” attribute has values that aren’t steps of this scale. Pick another attribute, or let the spec create its own.', 'aggressive-apparel' ),
					wc_attribute_label( $item['taxonomy'] )
				)
			);
		}

		if ( '' === $item['taxonomy'] || ! taxonomy_exists( $item['taxonomy'] ) ) {
			$taxonomy = self::find( $item, $taken ) ?? self::create( $item );
			if ( is_wp_error( $taxonomy ) ) {
				return $taxonomy;
			}
			$item['taxonomy'] = $taxonomy;
		}

		if ( Product_Specs_Registry::TYPE_PICK === $item['type'] ) {
			$item['step_terms'] = self::sync_steps( $item );
		}

		return $item;
	}

	/**
	 * Connect every item, one attribute each, and turn each default into
	 * its term. Items that cannot be connected are reported, not dropped.
	 *
	 * @param array $items Validated items.
	 * @phpstan-param list<Item> $items
	 * @return array{items: list<Item>, errors: list<string>}
	 */
	public static function connect_all( array $items ): array {
		$errors = array();
		$used   = array();
		foreach ( $items as $i => $item ) {
			$taken     = array_values( array_diff( array_filter( array_column( $items, 'taxonomy' ) ), array( $item['taxonomy'] ) ) );
			$connected = self::connect( $item, array_merge( $taken, array_keys( $used ) ) );
			if ( is_wp_error( $connected ) ) {
				$errors[] = sprintf( '“%1$s”: %2$s', $item['name'], $connected->get_error_message() );
				continue;
			}
			if ( isset( $used[ $connected['taxonomy'] ] ) ) {
				/* translators: %s: spec name. */
				$errors[] = sprintf( __( '“%s” uses the same WooCommerce attribute as another spec.', 'aggressive-apparel' ), $item['name'] );
				continue;
			}

			$connected['default_term']      = self::term_for( $connected, $connected['default'] );
			$used[ $connected['taxonomy'] ] = true;
			$items[ $i ]                    = $connected;
		}

		return array(
			'items'  => $items,
			'errors' => $errors,
		);
	}

	/**
	 * An existing attribute the item can use: one named like the item (or
	 * one of its attribute names) whose terms fit it. A picked scale only
	 * takes an attribute whose terms are all its steps, so "Fit: Mens" never
	 * becomes a fit scale.
	 *
	 * @param array $item  Item.
	 * @param array $taken Taxonomies other items already use.
	 * @phpstan-param Item $item
	 * @phpstan-param list<string> $taken
	 */
	public static function find( array $item, array $taken = array() ): ?string {
		$wanted = array_map( array( self::class, 'normalize' ), array_merge( array( $item['name'], str_replace( '_', ' ', $item['key'] ) ), $item['attributes'] ) );

		foreach ( self::attributes() as $taxonomy => $label ) {
			$slug = str_replace( array( '-', '_' ), ' ', substr( $taxonomy, 3 ) );
			if ( in_array( $taxonomy, $taken, true ) || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			if ( ! in_array( self::normalize( $label ), $wanted, true ) && ! in_array( self::normalize( $slug ), $wanted, true ) ) {
				continue;
			}
			if ( self::fits( $item, $taxonomy ) ) {
				return $taxonomy;
			}
		}

		return null;
	}

	/**
	 * Display names of a picked scale's steps: its terms' current names (so a
	 * rename in WooCommerce shows), or the stored names.
	 *
	 * @param array $item Item.
	 * @phpstan-param Item $item
	 * @return list<string>
	 */
	public static function step_names( array $item ): array {
		if ( Product_Specs_Registry::TYPE_PICK !== $item['type'] || '' === $item['taxonomy'] ) {
			return $item['steps'];
		}

		$names = array();
		foreach ( $item['steps'] as $i => $stored ) {
			$term    = isset( $item['step_terms'][ $i ] ) ? get_term( $item['step_terms'][ $i ], $item['taxonomy'] ) : null;
			$names[] = $term instanceof \WP_Term ? $term->name : $stored;
		}

		return $names;
	}

	/**
	 * The term for a value (a step index, a number, or text), created when
	 * new; 0 for an empty or unusable value.
	 *
	 * @param array  $item Item.
	 * @param string $raw  Value.
	 * @phpstan-param Item $item
	 */
	public static function term_for( array $item, string $raw ): int {
		$raw = Product_Specs_Meta::sanitize_value( $raw );
		if ( '' === $raw || '' === $item['taxonomy'] ) {
			return 0;
		}

		if ( Product_Specs_Registry::TYPE_PICK === $item['type'] ) {
			$step = Product_Specs_Meta::step( $raw, $item );

			return $step >= 0 ? (int) ( $item['step_terms'][ $step ] ?? 0 ) : 0;
		}

		if ( Product_Specs_Registry::TYPE_NUMBER === $item['type'] ) {
			$number = Product_Specs_Meta::number( $raw );
			if ( $number <= 0 ) {
				return 0;
			}
			// A dot decimal, never localized, so the term parses back the same.
			$raw = trim( ( floor( $number ) === $number ? (string) (int) $number : (string) round( $number, 1 ) ) . ' ' . $item['unit'] );
		}

		return self::term_id( $item['taxonomy'], $raw );
	}

	/**
	 * The value (step index, number, or text) a term stands for; '' when the
	 * term is gone or does not fit the item.
	 *
	 * @param array $item    Item.
	 * @param int   $term_id Term ID.
	 * @phpstan-param Item $item
	 */
	public static function value_of( array $item, int $term_id ): string {
		$term = $term_id > 0 && '' !== $item['taxonomy'] ? get_term( $term_id, $item['taxonomy'] ) : null;
		if ( ! $term instanceof \WP_Term ) {
			return '';
		}

		return self::value_from_text( $item, $term->name, $term_id );
	}

	/**
	 * The value (step index, number, or text) attribute text stands for.
	 *
	 * @param array  $item    Item.
	 * @param string $text    Term name or attribute text.
	 * @param int    $term_id Term ID, when known.
	 * @phpstan-param Item $item
	 */
	public static function value_from_text( array $item, string $text, int $term_id = 0 ): string {
		if ( Product_Specs_Registry::TYPE_PICK === $item['type'] ) {
			$step = $term_id > 0 ? array_search( $term_id, $item['step_terms'], true ) : false;
			$step = false !== $step ? (int) $step : Product_Specs_Attributes::match_step( $text, self::step_names( $item ) );

			return $step >= 0 ? (string) $step : '';
		}

		if ( Product_Specs_Registry::TYPE_NUMBER === $item['type'] ) {
			$number = Product_Specs_Attributes::parse_number( $text, $item['unit'] );
			if ( $number <= 0 ) {
				$number = Product_Specs_Attributes::parse_number( $text, '' );
			}

			return $number > 0 ? (string) $number : '';
		}

		return $text;
	}

	/**
	 * Term names in a taxonomy, for pickers (at most 200, by name).
	 *
	 * @param string $taxonomy Attribute taxonomy.
	 * @return list<string>
	 */
	public static function names( string $taxonomy ): array {
		if ( ! isset( self::$names[ $taxonomy ] ) ) {
			$terms                    = '' !== $taxonomy && taxonomy_exists( $taxonomy )
				? get_terms(
					array(
						'taxonomy'   => $taxonomy,
						'hide_empty' => false,
						'fields'     => 'names',
						'number'     => 200,
						'orderby'    => 'name',
					)
				)
				: array();
			self::$names[ $taxonomy ] = is_array( $terms ) ? array_values( array_map( 'strval', $terms ) ) : array();
		}

		return self::$names[ $taxonomy ];
	}

	/**
	 * Terms in a taxonomy with how many products use each, most used first
	 * (at most 200), for the editor's value picker.
	 *
	 * @param string $taxonomy Attribute taxonomy.
	 * @return list<array{name: string, count: int}>
	 */
	public static function usage( string $taxonomy ): array {
		if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 200,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);

		return is_array( $terms ) ? array_values(
			array_map(
				static fn( \WP_Term $term ): array => array(
					'name'  => $term->name,
					'count' => $term->count,
				),
				array_filter( $terms, static fn( $term ): bool => $term instanceof \WP_Term )
			)
		) : array();
	}

	/**
	 * Forget memoized term names (after terms change, and in tests).
	 */
	public static function flush(): void {
		self::$names = array();
	}

	/**
	 * Whether an attribute's terms fit an item: any terms suit text and
	 * numbers; a picked scale needs every term to be one of its steps.
	 *
	 * @param array  $item     Item.
	 * @param string $taxonomy Attribute taxonomy.
	 * @phpstan-param Item $item
	 */
	private static function fits( array $item, string $taxonomy ): bool {
		if ( Product_Specs_Registry::TYPE_PICK !== $item['type'] ) {
			return true;
		}

		foreach ( self::names( $taxonomy ) as $name ) {
			if ( Product_Specs_Attributes::match_step( $name, $item['steps'] ) < 0 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * The taxonomy a new attribute for the item would get: its key, made
	 * unique ("pa_fit_2" when "pa_fit" is taken).
	 *
	 * @param array $item Item.
	 * @phpstan-param Item $item
	 */
	public static function new_taxonomy( array $item ): string {
		$base = substr( wc_sanitize_taxonomy_name( '' !== $item['key'] ? $item['key'] : $item['name'] ), 0, self::SLUG_MAX );
		$slug = $base;
		$n    = 1;
		while ( '' === $slug || self::slug_taken( $slug ) ) {
			$suffix = '_' . ( ++$n );
			$slug   = substr( '' !== $base ? $base : 'spec', 0, self::SLUG_MAX - strlen( $suffix ) ) . $suffix;
		}

		return wc_attribute_taxonomy_name( $slug );
	}

	/**
	 * Whether a new attribute can't use a slug: an attribute or taxonomy has
	 * it, or WooCommerce reserves it ("type", "name", "order"…).
	 *
	 * @param string $slug Attribute slug.
	 */
	private static function slug_taken( string $slug ): bool {
		return wc_attribute_taxonomy_id_by_name( $slug ) > 0
			|| taxonomy_exists( wc_attribute_taxonomy_name( $slug ) )
			|| ( function_exists( 'wc_check_if_attribute_name_is_reserved' ) && wc_check_if_attribute_name_is_reserved( $slug ) );
	}

	/**
	 * Create a global attribute for an item, its slug made unique.
	 *
	 * @param array $item Item.
	 * @phpstan-param Item $item
	 * @return string|\WP_Error Taxonomy.
	 */
	private static function create( array $item ) {
		if ( ! function_exists( 'wc_create_attribute' ) ) {
			return new \WP_Error( 'aa_specs_no_woocommerce', __( 'WooCommerce is needed to save specs.', 'aggressive-apparel' ) );
		}

		$slug = substr( self::new_taxonomy( $item ), 3 );
		$id   = wc_create_attribute(
			array(
				'name'         => $item['name'],
				'slug'         => $slug,
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => false,
			)
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		// WooCommerce registers attribute taxonomies on init; this request
		// needs it now to add terms.
		$taxonomy = wc_attribute_taxonomy_name( $slug );
		if ( ! taxonomy_exists( $taxonomy ) ) {
			register_taxonomy(
				$taxonomy,
				array( 'product' ),
				array(
					'hierarchical' => false,
					'label'        => $item['name'],
					'public'       => false,
					'show_ui'      => true,
				)
			);
		}

		return $taxonomy;
	}

	/**
	 * Terms for a picked scale's steps, in step order. A step keeps its term
	 * when renamed; a new step finds or adds one. Terms of removed steps stay.
	 *
	 * A step whose name is already a term uses that term, and a term is only
	 * renamed when no term has the new name and its current name is no other
	 * step's. So IDs that are out of step with the names (a stale editor)
	 * are matched by name instead of relabeling products catalog-wide.
	 *
	 * @param array $item Item.
	 * @phpstan-param Item $item
	 * @return list<int>
	 */
	private static function sync_steps( array $item ): array {
		$ids = array();
		foreach ( $item['steps'] as $i => $name ) {
			$id    = (int) ( $item['step_terms'][ $i ] ?? 0 );
			$term  = $id > 0 ? get_term( $id, $item['taxonomy'] ) : null;
			$named = get_term_by( 'name', $name, $item['taxonomy'] );
			if ( $named instanceof \WP_Term ) {
				$id = $named->term_id;
			} elseif ( $term instanceof \WP_Term && ! in_array( $term->name, $item['steps'], true ) ) {
				wp_update_term( $id, $item['taxonomy'], array( 'name' => $name ) );
			} else {
				$id = self::term_id( $item['taxonomy'], $name );
			}

			if ( $id > 0 ) {
				if ( function_exists( 'wc_set_term_order' ) ) {
					wc_set_term_order( $id, $i, $item['taxonomy'] );
				}
				$ids[] = $id;
			}
		}
		self::flush();

		return $ids;
	}

	/**
	 * A term by name in a taxonomy, added when missing; 0 on failure.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $name     Term name.
	 */
	private static function term_id( string $taxonomy, string $name ): int {
		if ( '' === $name || ! taxonomy_exists( $taxonomy ) ) {
			return 0;
		}

		$term = get_term_by( 'name', $name, $taxonomy );
		if ( $term instanceof \WP_Term ) {
			return $term->term_id;
		}

		$created = wp_insert_term( $name, $taxonomy );
		self::flush();
		if ( is_wp_error( $created ) ) {
			$existing = $created->get_error_data( 'term_exists' );

			return is_numeric( $existing ) ? (int) $existing : 0;
		}

		return (int) $created['term_id'];
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
