<?php
/**
 * Product Specs
 *
 * Resolves every spec item (Products → Size & Specs → Specs) for one product. Each value
 * falls back independently:
 *
 *   the product's own attribute for the spec → an attribute named like the
 *   spec → its size guide's value → the spec's default
 *
 * Specs are WooCommerce attributes, so a product's own value is simply its
 * attribute (Product_Specs_Taxonomy); the guide and the default supply what
 * the product shows without one, and nothing is copied onto products. A
 * size guide can also hide store-wide specs for its products.
 *
 * Items apply only to the categories they are scoped to (children included)
 * and, when a size guide owns them, only to products using that guide;
 * number scales can use different boundaries per category. A product can
 * reword or hide any item.
 *
 * Everything here is product data, never per-visitor, so the rendered markup
 * is safe to serve from a full-page cache.
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
 * Product Specs resolver.
 *
 * @phpstan-import-type Item from Product_Specs_Registry
 * @phpstan-type Wording array<string, array<string, string>>
 * @phpstan-type GuideValues array{values: array<string, string>, hidden: list<string>, label: string}
 * @phpstan-type Value array{step: int, number: float, text: string, source: string, label: string}
 * @phpstan-type ResolvedItem array{key: string, type: string, title: string, steps: list<string>, start: string, mid: string, end: string, centered: bool, unit: string, thresholds: list<float>, step: int, number: float, text: string, display: string, advice: string, source: string, label: string, own: string, inherited: Value, hidden: bool, in_table: bool, attribute_visible: bool, taxonomy: string, multiple: bool, links: list<array{name: string, url: string}>}
 * @phpstan-type Resolved array{items: list<ResolvedItem>}
 */
final class Product_Specs {

	/**
	 * Source: the product's own Specs fields.
	 *
	 * @var string
	 */
	public const SOURCE_PRODUCT = 'product';

	/**
	 * Source: a product attribute.
	 *
	 * @var string
	 */
	public const SOURCE_ATTRIBUTE = 'attribute';

	/**
	 * Source: the product's size guide (Size_Guide::resolved()).
	 *
	 * @var string
	 */
	public const SOURCE_GUIDE = 'guide';

	/**
	 * Source: the spec's store-wide default.
	 *
	 * @var string
	 */
	public const SOURCE_DEFAULT = 'default';

	/**
	 * Fixed wording fields; step names add step0…step6.
	 *
	 * @var list<string>
	 */
	public const LABEL_FIELDS = array( 'title', 'start', 'mid', 'end' );

	/**
	 * Longest accepted wording override.
	 *
	 * @var int
	 */
	private const WORDING_MAX_LENGTH = 60;

	/**
	 * Options whose change alters a product's specs.
	 *
	 * @var list<string>
	 */
	private const WATCHED_OPTIONS = array( Product_Specs_Registry::OPTION_KEY, Feature_Settings::OPTION_KEY );

	/**
	 * Resolved specs per product, for this request.
	 *
	 * @var array<int, Resolved>
	 */
	private static array $memo = array();

	/**
	 * Resolve every applicable spec item for a product.
	 *
	 * Resolved once per request (the block, the Additional information rows,
	 * and the size guide all ask) and forgotten as soon as anything it reads
	 * changes: post or term meta, a product's terms, or the spec and feature
	 * options.
	 *
	 * @param int $product_id Product ID.
	 * @return Resolved
	 */
	public static function for_product( int $product_id ): array {
		self::watch();
		if ( ! isset( self::$memo[ $product_id ] ) ) {
			self::$memo[ $product_id ] = self::resolve( $product_id );
		}

		return self::$memo[ $product_id ];
	}

	/**
	 * Forget every resolved product.
	 */
	public static function flush(): void {
		self::$memo = array();
	}

	/**
	 * Forget resolved products when a watched option changes.
	 *
	 * @param string $option Option name.
	 */
	public static function flush_for_option( $option ): void {
		if ( in_array( $option, self::WATCHED_OPTIONS, true ) ) {
			self::flush();
		}
	}

	/**
	 * Hook the invalidation once, on first use.
	 */
	private static function watch(): void {
		// Checked, not flagged: hooks can be reset (tests do) while statics live.
		if ( false !== has_action( 'clean_post_cache', array( self::class, 'flush' ) ) ) {
			return;
		}
		self::flush();

		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta', 'added_term_meta', 'updated_term_meta', 'deleted_term_meta', 'set_object_terms', 'edited_term', 'clean_post_cache' ) as $hook ) {
			add_action( $hook, array( self::class, 'flush' ) );
		}
		foreach ( array( 'added_option', 'updated_option', 'deleted_option' ) as $hook ) {
			add_action( $hook, array( self::class, 'flush_for_option' ) );
		}
	}

	/**
	 * Resolve every applicable spec item for a product, uncached.
	 *
	 * @param int $product_id Product ID.
	 * @return Resolved
	 */
	private static function resolve( int $product_id ): array {
		$categories = self::category_ids( $product_id );
		$scope_ids  = $categories;
		foreach ( $categories as $term_id ) {
			$scope_ids = array_merge( $scope_ids, array_map( 'intval', get_ancestors( $term_id, 'product_cat', 'taxonomy' ) ) );
		}

		$guide_id = Size_Guide::resolved( $product_id )['id'];
		$guide    = self::guide_values( $guide_id );
		$items    = array_values(
			array_filter(
				Product_Specs_Registry::items(),
				static fn( array $item ): bool => Product_Specs_Registry::applies( $item, $scope_ids )
					&& Product_Specs_Registry::on_guide( $item, $guide_id )
					&& ! in_array( $item['key'], $guide['hidden'], true )
			)
		);

		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		$attrs   = $product instanceof \WC_Product ? Product_Specs_Attributes::values( $product, $items ) : array();
		$hidden  = Product_Specs_Meta::sanitize_keys( get_post_meta( $product_id, Product_Specs_Meta::HIDDEN, true ) );

		// Nearest category first: a product's own categories, then ancestors.
		$category_order = array_values( array_unique( $scope_ids ) );
		$wording        = self::sanitize_wording( get_post_meta( $product_id, Product_Specs_Meta::WORDING, true ) );

		$resolved = array();
		foreach ( $items as $item ) {
			$resolved[] = self::resolve_item( $item, $product_id, $attrs, $guide, $hidden, $wording, $category_order );
		}

		return array( 'items' => $resolved );
	}

	/**
	 * Spec values stored on the size guide a product shows (the same guide
	 * its size guide dialog opens), with the guide's name as their label.
	 *
	 * @param int $guide_id Size guide post ID, or 0 for none.
	 * @return GuideValues
	 */
	private static function guide_values( int $guide_id ): array {
		if ( $guide_id <= 0 ) {
			return array(
				'values' => array(),
				'hidden' => array(),
				'label'  => '',
			);
		}

		$guide = get_post( $guide_id );

		return Product_Specs_Values::guide( $guide_id ) + array( 'label' => $guide instanceof \WP_Post ? $guide->post_title : '' );
	}

	/**
	 * Normalize wording overrides: item key => field => text.
	 *
	 * @param mixed $raw Raw value.
	 * @return Wording
	 */
	public static function sanitize_wording( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$fields = self::LABEL_FIELDS;
		for ( $i = 0; $i < Product_Specs_Registry::MAX_STEPS; $i++ ) {
			$fields[] = 'step' . $i;
		}

		$clean = array();
		foreach ( $raw as $key => $values ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key || ! is_array( $values ) ) {
				continue;
			}
			foreach ( $fields as $field ) {
				$value = is_scalar( $values[ $field ] ?? null ) ? trim( sanitize_text_field( (string) $values[ $field ] ) ) : '';
				if ( '' !== $value ) {
					$clean[ $key ][ $field ] = mb_substr( $value, 0, self::WORDING_MAX_LENGTH );
				}
			}
		}

		return $clean;
	}

	/**
	 * A resolved text spec's value as HTML: each value linked to its archive
	 * page when it has one (like WooCommerce's attribute rows), escaped.
	 *
	 * @param array $item Resolved item.
	 * @phpstan-param ResolvedItem $item
	 */
	public static function value_html( array $item ): string {
		if ( Product_Specs_Registry::TYPE_TEXT !== $item['type'] || array() === $item['links'] ) {
			return esc_html( $item['display'] );
		}

		return implode(
			', ',
			array_map(
				static fn( array $link ): string => '' !== $link['url']
					? '<a href="' . esc_url( $link['url'] ) . '" rel="tag">' . esc_html( $link['name'] ) . '</a>'
					: esc_html( $link['name'] ),
				$item['links']
			)
		);
	}

	/**
	 * Display text for a number in a unit ("180 g/m² (5.3 oz/yd²)").
	 *
	 * @param float  $number Number.
	 * @param string $unit   Unit.
	 */
	public static function format_number( float $number, string $unit ): string {
		$decimals = floor( $number ) === $number ? 0 : 1;
		$text     = trim( number_format_i18n( $number, $decimals ) . ' ' . $unit );

		if ( Product_Specs_Attributes::is_gsm_unit( $unit ) ) {
			$text .= ' (' . Product_Specs_Attributes::format_ounces( $number ) . ' oz/yd²)';
		}

		return $text;
	}

	/**
	 * Resolve one item for a product.
	 *
	 * @param array $item       Item.
	 * @param int   $product_id Product ID.
	 * @param array $attrs      Attribute values by item key.
	 * @param array $guide      The product's size guide values.
	 * @param array $hidden     Item keys hidden on the product.
	 * @param array $wording    The product's wording overrides.
	 * @param array $categories Product categories, nearest first.
	 * @phpstan-param Item $item
	 * @phpstan-param list<string> $hidden
	 * @phpstan-param array<string, array{text: string, names: list<string>, label: string, visible: bool, own: bool}> $attrs
	 * @phpstan-param GuideValues $guide
	 * @phpstan-param Wording $wording
	 * @phpstan-param list<int> $categories
	 * @return ResolvedItem
	 */
	private static function resolve_item( array $item, int $product_id, array $attrs, array $guide, array $hidden, array $wording, array $categories ): array {
		$key           = $item['key'];
		$item['steps'] = Product_Specs_Taxonomy::step_names( $item );
		$steps         = array();
		foreach ( $item['steps'] as $i => $name ) {
			$steps[] = self::override( $wording, $key, 'step' . $i ) ?? $name;
		}

		$edges = Product_Specs_Registry::edge_labels( $steps, $item['start'], $item['mid'], $item['end'] );
		$scale = array(
			'title' => self::override( $wording, $key, 'title' ) ?? $item['name'],
			'start' => self::override( $wording, $key, 'start' ) ?? $edges['start'],
			'mid'   => self::override( $wording, $key, 'mid' ) ?? $edges['mid'],
			'end'   => self::override( $wording, $key, 'end' ) ?? $edges['end'],
		);

		$boundaries = self::boundaries( $item, $categories );

		$attr = $attrs[ $key ] ?? null;
		// A value saved before specs were attributes, not moved yet (or kept
		// because the product's attribute differs): it showed ahead of the
		// attribute before, so it still does.
		$own_raw   = Product_Specs_Values::clean( get_post_meta( $product_id, Product_Specs_Meta::key( $key ), true ), $item );
		$from_attr = '' === $own_raw && null !== $attr && $attr['own'];
		if ( $from_attr ) {
			$own_raw = $item['multiple']
				? implode( ' ' . Product_Specs_Taxonomy::DELIMITER . ' ', $attr['names'] )
				: Product_Specs_Taxonomy::value_from_text( $item, $attr['text'] );
		}
		$own       = self::read( $item, $own_raw, $boundaries, self::SOURCE_PRODUCT );
		$inherited = self::empty_value();
		if ( null !== $attr && ! $attr['own'] ) {
			$inherited          = self::read( $item, $attr['text'], $boundaries, self::SOURCE_ATTRIBUTE );
			$inherited['label'] = $attr['label'];
		}
		if ( ! self::has_value( $item, $inherited ) ) {
			$inherited          = self::read( $item, $guide['values'][ $key ] ?? '', $boundaries, self::SOURCE_GUIDE );
			$inherited['label'] = $guide['label'];
		}
		if ( ! self::has_value( $item, $inherited ) ) {
			$default   = $item['default_term'] > 0 ? Product_Specs_Taxonomy::value_of( $item, $item['default_term'] ) : $item['default'];
			$inherited = self::read( $item, $default, $boundaries, self::SOURCE_DEFAULT );
		}

		$value = self::has_value( $item, $own ) ? $own : ( self::has_value( $item, $inherited ) ? $inherited : self::empty_value() );

		$names = array();
		if ( Product_Specs_Registry::TYPE_TEXT === $item['type'] ) {
			$names   = $item['multiple'] ? Product_Specs_Taxonomy::split( $value['text'] ) : array_filter( array( $value['text'] ) );
			$display = implode( ', ', $names );
		} elseif ( Product_Specs_Registry::TYPE_NUMBER === $item['type'] ) {
			$display = $value['number'] > 0 ? self::format_number( $value['number'], $item['unit'] ) : '';
		} else {
			$display = $value['step'] >= 0 ? ( $steps[ $value['step'] ] ?? '' ) : '';
		}

		return array(
			'key'               => $key,
			'type'              => $item['type'],
			'title'             => $scale['title'],
			'steps'             => $steps,
			'start'             => $scale['start'],
			'mid'               => $scale['mid'],
			'end'               => $scale['end'],
			'centered'          => $item['centered'],
			'unit'              => $item['unit'],
			'thresholds'        => $boundaries,
			'step'              => $value['step'],
			'number'            => $value['number'],
			'text'              => $value['text'],
			'display'           => $display,
			'advice'            => $value['step'] >= 0 ? ( $item['advice'][ $value['step'] ] ?? '' ) : '',
			'source'            => $value['source'],
			'label'             => $value['label'],
			'own'               => $own_raw,
			'inherited'         => $inherited,
			'hidden'            => in_array( $key, $hidden, true ),
			'in_table'          => $item['in_table'],
			'taxonomy'          => $item['taxonomy'],
			'multiple'          => $item['multiple'],
			// Each value and its archive page, when the attribute has archives.
			'links'             => Product_Specs_Taxonomy::links( $item['taxonomy'], array_values( $names ) ),
			// WooCommerce already lists a visible attribute in Additional information.
			'attribute_visible' => null !== $attr && $attr['visible'] && ( self::SOURCE_ATTRIBUTE === $value['source'] || ( $from_attr && self::SOURCE_PRODUCT === $value['source'] ) ),
		);
	}

	/**
	 * Interpret a raw value (stored meta or attribute text) for an item.
	 *
	 * @param array  $item       Item.
	 * @param mixed  $raw        Raw value.
	 * @param array  $boundaries Number-scale boundaries.
	 * @param string $source     Source label.
	 * @phpstan-param Item $item
	 * @phpstan-param list<float> $boundaries
	 * @return Value
	 */
	private static function read( array $item, $raw, array $boundaries, string $source ): array {
		$value = self::empty_value();
		$text  = Product_Specs_Meta::sanitize_value( $raw );
		if ( '' === $text ) {
			return $value;
		}

		if ( Product_Specs_Registry::TYPE_TEXT === $item['type'] ) {
			$value['text'] = $text;
		} elseif ( Product_Specs_Registry::TYPE_NUMBER === $item['type'] ) {
			$number = self::SOURCE_ATTRIBUTE === $source
				? Product_Specs_Attributes::parse_number( $text, $item['unit'] )
				: Product_Specs_Meta::number( $text );
			if ( $number > 0 ) {
				$value['number'] = $number;
				$value['step']   = count( array_filter( $boundaries, static fn( float $boundary ): bool => $number >= $boundary ) );
			}
		} else {
			$value['step'] = self::SOURCE_ATTRIBUTE === $source
				? Product_Specs_Attributes::match_step( $text, $item['steps'] )
				: Product_Specs_Meta::step( $text, $item );
		}

		if ( self::has_value( $item, $value ) ) {
			$value['source'] = $source;
		}

		return $value;
	}

	/**
	 * Whether a value is set for the item's type.
	 *
	 * @param array $item  Item.
	 * @param array $value Value.
	 * @phpstan-param Item  $item
	 * @phpstan-param Value $value
	 */
	private static function has_value( array $item, array $value ): bool {
		if ( Product_Specs_Registry::TYPE_TEXT === $item['type'] ) {
			return '' !== $value['text'];
		}

		return $value['step'] >= 0;
	}

	/**
	 * A value with nothing set.
	 *
	 * @return Value
	 */
	private static function empty_value(): array {
		return array(
			'step'   => -1,
			'number' => 0.0,
			'text'   => '',
			'source' => '',
			'label'  => '',
		);
	}

	/**
	 * A product's wording override for an item field, or null.
	 *
	 * @param array  $wording The product's wording overrides.
	 * @param string $key     Item key.
	 * @param string $field   Wording field.
	 * @phpstan-param Wording $wording
	 */
	private static function override( array $wording, string $key, string $field ): ?string {
		$value = $wording[ $key ][ $field ] ?? '';

		return '' !== $value ? $value : null;
	}

	/**
	 * Number-scale boundaries: the nearest category's override, else the
	 * item's own.
	 *
	 * @param array $item       Item.
	 * @param array $categories Product categories, nearest first.
	 * @phpstan-param Item      $item
	 * @phpstan-param list<int> $categories
	 * @return list<float>
	 */
	private static function boundaries( array $item, array $categories ): array {
		foreach ( $categories as $term_id ) {
			foreach ( $item['category_steps'] as $override ) {
				if ( $override['category'] === $term_id ) {
					return $override['thresholds'];
				}
			}
		}

		return $item['thresholds'];
	}

	/**
	 * The product's own category IDs (from the object term cache).
	 *
	 * @param int $product_id Product ID.
	 * @return list<int>
	 */
	private static function category_ids( int $product_id ): array {
		$terms = get_the_terms( $product_id, 'product_cat' );
		if ( ! is_array( $terms ) ) {
			return array();
		}

		return array_values( array_map( static fn( \WP_Term $term ): int => $term->term_id, $terms ) );
	}
}
