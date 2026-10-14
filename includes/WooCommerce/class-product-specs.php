<?php
/**
 * Product Specs
 *
 * Resolves every spec item (Products → Size Guides → Specs) for one product. Each value
 * falls back independently:
 *
 *   the product's own Specs fields → its attributes → its size guides
 *
 * Size guides are tried in assignment order (the product's own, then its
 * categories', then the store-wide default), per item, so a store-wide guide
 * can supply Care while a category guide supplies Fit.
 *
 * Items apply only to the categories they are scoped to (children included);
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
 * @phpstan-type Guide array{id: int, title: string}
 * @phpstan-type Value array{step: int, number: float, text: string, source: string, label: string}
 * @phpstan-type ResolvedItem array{key: string, type: string, title: string, steps: list<string>, start: string, mid: string, end: string, centered: bool, unit: string, thresholds: list<float>, step: int, number: float, text: string, display: string, advice: string, source: string, label: string, own: string, inherited: Value, hidden: bool, in_table: bool, attribute_visible: bool}
 * @phpstan-type Resolved array{items: list<ResolvedItem>, guide: Guide|null}
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
	 * Source: an assigned size guide.
	 *
	 * @var string
	 */
	public const SOURCE_GUIDE = 'guide';

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
	private const WATCHED_OPTIONS = array( Product_Specs_Registry::OPTION_KEY, Size_Guide::CPT_OPTION_KEY, Feature_Settings::OPTION_KEY );

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
	 * changes: post or term meta, a product's terms, or the spec, default
	 * guide, and feature options.
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

		$items = array_values(
			array_filter(
				Product_Specs_Registry::items(),
				static fn( array $item ): bool => Product_Specs_Registry::applies( $item, $scope_ids )
			)
		);

		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		$attrs   = $product instanceof \WC_Product ? Product_Specs_Attributes::values( $product, $items ) : array();
		$guides  = self::guides( $product_id );
		$guide   = self::primary_guide( $guides, $items );
		$hidden  = Product_Specs_Meta::sanitize_keys( get_post_meta( $product_id, Product_Specs_Meta::HIDDEN, true ) );

		// Nearest category first: a product's own categories, then ancestors.
		$category_order = array_values( array_unique( $scope_ids ) );
		$wording        = self::sanitize_wording( get_post_meta( $product_id, Product_Specs_Meta::WORDING, true ) );

		$resolved = array();
		foreach ( $items as $item ) {
			$resolved[] = self::resolve_item( $item, $product_id, $attrs, $guides, $hidden, $wording, $category_order );
		}

		return array(
			'items' => $resolved,
			'guide' => $guide,
		);
	}

	/**
	 * A product's published size guides, in assignment order.
	 *
	 * @param int $product_id Product ID.
	 * @return list<Guide>
	 */
	public static function guides( int $product_id ): array {
		$guides = array();
		foreach ( Size_Guide::candidate_guide_ids( $product_id ) as $guide_id ) {
			$post = get_post( $guide_id );
			if (
				! $post instanceof \WP_Post
				|| Size_Guide_Post_Type::POST_TYPE !== $post->post_type
				|| 'publish' !== $post->post_status
			) {
				continue;
			}

			$guides[] = array(
				'id'    => $guide_id,
				'title' => '' !== $post->post_title ? $post->post_title : __( '(no title)', 'aggressive-apparel' ),
			);
		}

		return $guides;
	}

	/**
	 * The guide the product editor links to: the first with any spec value
	 * set, otherwise the first assigned.
	 *
	 * @param array $guides Guides in assignment order.
	 * @param array $items  Applicable items.
	 * @phpstan-param list<Guide> $guides
	 * @phpstan-param list<Item>  $items
	 * @return Guide|null
	 */
	private static function primary_guide( array $guides, array $items ): ?array {
		foreach ( $guides as $guide ) {
			foreach ( $items as $item ) {
				if ( '' !== Product_Specs_Meta::sanitize_value( get_post_meta( $guide['id'], Product_Specs_Meta::key( $item['key'] ), true ) ) ) {
					return $guide;
				}
			}
		}

		return $guides[0] ?? null;
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
	 * @param array $guides     Assigned size guides, in order.
	 * @param array $hidden     Item keys hidden on the product.
	 * @param array $wording    The product's wording overrides.
	 * @param array $categories Product categories, nearest first.
	 * @phpstan-param Item $item
	 * @phpstan-param list<string> $hidden
	 * @phpstan-param array<string, array{text: string, label: string, visible: bool}> $attrs
	 * @phpstan-param list<Guide> $guides
	 * @phpstan-param Wording $wording
	 * @phpstan-param list<int> $categories
	 * @return ResolvedItem
	 */
	private static function resolve_item( array $item, int $product_id, array $attrs, array $guides, array $hidden, array $wording, array $categories ): array {
		$key   = $item['key'];
		$steps = array();
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

		$own_raw   = Product_Specs_Meta::sanitize_value( get_post_meta( $product_id, Product_Specs_Meta::key( $key ), true ) );
		$own       = self::read( $item, $own_raw, $boundaries, self::SOURCE_PRODUCT );
		$inherited = self::empty_value();
		if ( isset( $attrs[ $key ] ) ) {
			$inherited          = self::read( $item, $attrs[ $key ]['text'], $boundaries, self::SOURCE_ATTRIBUTE );
			$inherited['label'] = $attrs[ $key ]['label'];
		}
		foreach ( $guides as $guide ) {
			if ( self::has_value( $item, $inherited ) ) {
				break;
			}
			$inherited = self::read( $item, get_post_meta( $guide['id'], Product_Specs_Meta::key( $key ), true ), $boundaries, self::SOURCE_GUIDE );
			if ( self::has_value( $item, $inherited ) ) {
				$inherited['label'] = $guide['title'];
			}
		}

		$value = self::has_value( $item, $own ) ? $own : ( self::has_value( $item, $inherited ) ? $inherited : self::empty_value() );

		if ( Product_Specs_Registry::TYPE_TEXT === $item['type'] ) {
			$display = $value['text'];
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
			'attribute_visible' => self::SOURCE_ATTRIBUTE === $value['source'] && ! empty( $attrs[ $key ]['visible'] ),
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
