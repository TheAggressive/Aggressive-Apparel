<?php
/**
 * Product Specs Registry
 *
 * The store-wide list of spec items, edited on Products → Size Guides → Specs. Each item is
 * either a scale (picked per product, or derived from a number such as g/m²)
 * or a text detail (such as Fabric). Items decide which categories they apply
 * to, which product attributes can supply their value, the default value
 * every product gets otherwise, and whether they add a row to the Additional
 * information tab.
 *
 * Each item is backed by a WooCommerce global attribute (`taxonomy`), whose
 * terms hold every value (Product_Specs_Taxonomy); the registry keeps only
 * what WooCommerce has no place for: scale labels, advice, number bands,
 * and where the spec shows.
 *
 * An item with no `guides` is store-wide. One created inside a size guide
 * lists that guide (and any it was shared with) and shows only on products
 * using one of them (Size_Guide_Spec_Items).
 *
 * Until the store saves its own list, the defaults below apply: Fit and Fabric
 * weight scales plus Fabric and Construction details.
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
 * Spec item registry.
 *
 * @phpstan-type Item array{key: string, type: string, name: string, enabled: bool, steps: list<string>, start: string, mid: string, end: string, centered: bool, unit: string, thresholds: list<float>, advice: list<string>, attributes: list<string>, scope: string, categories: list<int>, category_steps: list<CategorySteps>, in_table: bool, default: string, default_term: int, guides: list<int>, taxonomy: string, step_terms: list<int>}
 * @phpstan-type CategorySteps array{category: int, thresholds: list<float>}
 * @phpstan-type RegistryInput array{items: list<Item>, hide_dimensions: bool}
 * @phpstan-type Registry array{items: list<Item>, hide_dimensions: bool, revision: int}
 */
final class Product_Specs_Registry {

	/**
	 * Option storing the registry.
	 *
	 * @var string
	 */
	public const OPTION_KEY = 'aggressive_apparel_spec_items';

	/**
	 * Scale whose step is picked per product or as the spec default.
	 *
	 * @var string
	 */
	public const TYPE_PICK = 'pick';

	/**
	 * Scale whose step is derived from a number and thresholds.
	 *
	 * @var string
	 */
	public const TYPE_NUMBER = 'number';

	/**
	 * Free-text detail.
	 *
	 * @var string
	 */
	public const TYPE_TEXT = 'text';

	/**
	 * Applies to every category.
	 *
	 * @var string
	 */
	public const SCOPE_ALL = 'all';

	/**
	 * Applies only to the listed categories (and their children).
	 *
	 * @var string
	 */
	public const SCOPE_ONLY = 'only';

	/**
	 * Applies to every category except the listed ones (and their children).
	 *
	 * @var string
	 */
	public const SCOPE_EXCEPT = 'except';

	/**
	 * Most items a store can define.
	 *
	 * @var int
	 */
	public const MAX_ITEMS = 100;

	/**
	 * Fewest steps a scale can have.
	 *
	 * @var int
	 */
	public const MIN_STEPS = 3;

	/**
	 * Most steps a scale can have.
	 *
	 * @var int
	 */
	public const MAX_STEPS = 7;

	/**
	 * Keys that would collide with the shared spec meta keys.
	 *
	 * @var list<string>
	 */
	private const RESERVED_KEYS = array( 'hidden', 'wording', 'thresholds' );

	/**
	 * Longest accepted label.
	 *
	 * @var int
	 */
	private const LABEL_MAX = 60;

	/**
	 * Request-scoped cache.
	 *
	 * @var Registry|null
	 */
	private static ?array $cache = null;

	/**
	 * The registry: stored list, or the defaults.
	 *
	 * @return Registry
	 */
	public static function get(): array {
		if ( null === self::$cache ) {
			$stored = get_option( self::OPTION_KEY, null );
			if ( is_array( $stored ) && isset( $stored['items'] ) ) {
				$result      = self::validate( $stored );
				self::$cache = array(
					'items'           => $result['items'],
					'hide_dimensions' => $result['hide_dimensions'],
					'revision'        => absint( $stored['revision'] ?? 0 ),
				);
			} else {
				self::$cache = array(
					'items'           => self::defaults(),
					'hide_dimensions' => false,
					'revision'        => 0,
				);
			}
		}

		return self::$cache;
	}

	/**
	 * Enabled items, in display order.
	 *
	 * @return list<Item>
	 */
	public static function items(): array {
		return array_values( array_filter( self::get()['items'], static fn( array $item ): bool => $item['enabled'] ) );
	}

	/**
	 * Persist a validated registry under the next revision, so an editor
	 * that loaded an older one can tell (Product_Specs_Settings).
	 *
	 * @param array $registry Validated registry.
	 * @phpstan-param RegistryInput $registry
	 */
	public static function save( array $registry ): void {
		update_option(
			self::OPTION_KEY,
			array(
				'items'           => $registry['items'],
				'hide_dimensions' => $registry['hide_dimensions'],
				'revision'        => self::get()['revision'] + 1,
			),
			true
		);
		self::flush();
	}

	/**
	 * Connect a validated registry's items to WooCommerce attributes, then
	 * save it. Nothing is saved when an item cannot be connected.
	 *
	 * @param array $registry Validated registry.
	 * @phpstan-param RegistryInput $registry
	 * @return list<string> Errors; empty when saved.
	 */
	public static function save_connected( array $registry ): array {
		$connected = Product_Specs_Taxonomy::connect_all( $registry['items'] );
		if ( array() !== $connected['errors'] ) {
			return $connected['errors'];
		}

		self::save(
			array(
				'items'           => $connected['items'],
				'hide_dimensions' => $registry['hide_dimensions'],
			)
		);

		return array();
	}

	/**
	 * Whether a saved item still lacks its attribute, or a default its term.
	 */
	public static function needs_connecting(): bool {
		foreach ( self::get()['items'] as $item ) {
			if ( '' === $item['taxonomy'] || ( '' !== $item['default'] && 0 === $item['default_term'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Connect the items before a value is written, so a store still on the
	 * built-in specs gets their attributes the first time it sets a value,
	 * never before. Errors leave the registry as it was.
	 *
	 * @return list<string> Errors.
	 */
	public static function ensure_connected(): array {
		if ( ! self::needs_connecting() ) {
			return array();
		}

		$registry = self::get();

		return self::save_connected(
			array(
				'items'           => $registry['items'],
				'hide_dimensions' => $registry['hide_dimensions'],
			)
		);
	}

	/**
	 * Forget the request cache (after a save or in tests).
	 */
	public static function flush(): void {
		self::$cache = null;
		Product_Specs::flush();
	}

	/**
	 * Whether an item applies to a product with these categories.
	 *
	 * @param array      $item         Item.
	 * @param array<int> $category_ids Product categories and their ancestors.
	 * @phpstan-param Item $item
	 */
	public static function applies( array $item, array $category_ids ): bool {
		if ( self::SCOPE_ALL === $item['scope'] || array() === $item['categories'] ) {
			return true;
		}

		$match = array() !== array_intersect( $item['categories'], $category_ids );

		return self::SCOPE_ONLY === $item['scope'] ? $match : ! $match;
	}

	/**
	 * Whether an item shows with this size guide: store-wide items always do,
	 * guide-owned items only with one of their guides.
	 *
	 * @param array $item     Item.
	 * @param int   $guide_id The product's size guide, or 0 for none.
	 * @phpstan-param Item $item
	 */
	public static function on_guide( array $item, int $guide_id ): bool {
		return array() === $item['guides'] || in_array( $guide_id, $item['guides'], true );
	}

	/**
	 * Labels under a scale: the given start/middle/end, or the first,
	 * middle (odd step counts only), and last step names when left empty.
	 *
	 * @param array  $steps Step names (already reworded, if any).
	 * @param string $start Start label.
	 * @param string $mid   Middle label.
	 * @param string $end   End label.
	 * @phpstan-param list<string> $steps
	 * @return array{start: string, mid: string, end: string}
	 */
	public static function edge_labels( array $steps, string $start, string $mid, string $end ): array {
		$count = count( $steps );

		return array(
			'start' => '' !== $start ? $start : ( $steps[0] ?? '' ),
			'mid'   => '' !== $mid ? $mid : ( 1 === $count % 2 ? $steps[ intdiv( $count, 2 ) ] : '' ),
			'end'   => '' !== $end ? $end : ( $steps[ $count - 1 ] ?? '' ),
		);
	}

	/**
	 * Whether an item is a scale (has steps).
	 *
	 * @param array $item Item.
	 * @phpstan-param Item $item
	 */
	private static function is_scale( array $item ): bool {
		return self::TYPE_TEXT !== $item['type'];
	}

	/**
	 * Default registry items.
	 *
	 * @return list<Item>
	 */
	public static function defaults(): array {
		return array(
			self::item(
				array(
					'key'      => 'fit',
					'type'     => self::TYPE_PICK,
					'name'     => __( 'Fit', 'aggressive-apparel' ),
					'steps'    => array(
						__( 'Snug', 'aggressive-apparel' ),
						__( 'Fitted', 'aggressive-apparel' ),
						__( 'Regular', 'aggressive-apparel' ),
						__( 'Relaxed', 'aggressive-apparel' ),
						__( 'Oversized', 'aggressive-apparel' ),
					),
					'centered' => true,
					'advice'   => array(
						__( 'Fitted by design. Size up if you want room.', 'aggressive-apparel' ),
						__( 'Runs a little small. Consider sizing up.', 'aggressive-apparel' ),
						__( 'True to size. Take your usual size.', 'aggressive-apparel' ),
						__( 'Relaxed cut. Size down for a closer fit.', 'aggressive-apparel' ),
						__( 'Oversized on purpose. Take your usual size.', 'aggressive-apparel' ),
					),
					'in_table' => true,
				)
			),
			self::item(
				array(
					'key'        => 'weight',
					'type'       => self::TYPE_NUMBER,
					'name'       => __( 'Fabric weight', 'aggressive-apparel' ),
					'steps'      => array(
						__( 'Lightweight', 'aggressive-apparel' ),
						__( 'Light-midweight', 'aggressive-apparel' ),
						__( 'Midweight', 'aggressive-apparel' ),
						__( 'Mid-heavyweight', 'aggressive-apparel' ),
						__( 'Heavyweight', 'aggressive-apparel' ),
					),
					'start'      => _x( 'Light', 'fabric weight scale, lightest end', 'aggressive-apparel' ),
					'end'        => _x( 'Heavy', 'fabric weight scale, heaviest end', 'aggressive-apparel' ),
					'unit'       => 'g/m²',
					'thresholds' => array( 145, 170, 195, 220 ),
					'attributes' => array( 'Fabric Weight', 'Fabric weight (gsm)', 'GSM' ),
					'in_table'   => true,
				)
			),
			self::item(
				array(
					'key'        => 'fabric',
					'type'       => self::TYPE_TEXT,
					'name'       => __( 'Fabric', 'aggressive-apparel' ),
					'attributes' => array( 'Fabric', 'Material', 'Composition' ),
					'in_table'   => true,
				)
			),
			self::item(
				array(
					'key'        => 'construction',
					'type'       => self::TYPE_TEXT,
					'name'       => __( 'Construction', 'aggressive-apparel' ),
					'attributes' => array( 'Construction' ),
					'in_table'   => true,
				)
			),
		);
	}

	/**
	 * Validate a submitted or stored registry.
	 *
	 * Invalid items are dropped and reported, never half-saved.
	 *
	 * @param mixed $raw Raw registry.
	 * @return array{items: list<Item>, hide_dimensions: bool, errors: list<string>}
	 */
	public static function validate( $raw ): array {
		$raw    = is_array( $raw ) ? $raw : array();
		$list   = isset( $raw['items'] ) && is_array( $raw['items'] ) ? array_values( $raw['items'] ) : array();
		$items  = array();
		$errors = array();
		$keys   = array();

		if ( count( $list ) > self::MAX_ITEMS ) {
			/* translators: %d: maximum number of spec items. */
			$errors[] = sprintf( __( 'Keep it to %d items or fewer.', 'aggressive-apparel' ), self::MAX_ITEMS );
			$list     = array_slice( $list, 0, self::MAX_ITEMS );
		}

		foreach ( $list as $index => $entry ) {
			$entry = is_array( $entry ) ? $entry : array();
			$name  = self::label( $entry['name'] ?? '' );
			/* translators: %d: item position in the list. */
			$label = '' !== $name ? $name : sprintf( __( 'Item %d', 'aggressive-apparel' ), $index + 1 );
			$key   = sanitize_key( is_scalar( $entry['key'] ?? null ) ? (string) $entry['key'] : '' );
			if ( '' === $key ) {
				$key = sanitize_key( str_replace( '-', '_', sanitize_title( $name ) ) );
			}
			$key = substr( str_replace( '-', '_', $key ), 0, 32 );

			if ( '' === $name ) {
				/* translators: %s: item label. */
				$errors[] = sprintf( __( '%s needs a name.', 'aggressive-apparel' ), $label );
				continue;
			}
			if ( '' === $key || in_array( $key, self::RESERVED_KEYS, true ) ) {
				/* translators: %s: item name. */
				$errors[] = sprintf( __( '“%s” needs a different name.', 'aggressive-apparel' ), $label );
				continue;
			}
			if ( isset( $keys[ $key ] ) ) {
				/* translators: %s: item name. */
				$errors[] = sprintf( __( '“%s” has the same key as another item. Rename one of them.', 'aggressive-apparel' ), $label );
				continue;
			}

			$type = in_array( $entry['type'] ?? '', array( self::TYPE_PICK, self::TYPE_NUMBER, self::TYPE_TEXT ), true )
				? (string) $entry['type']
				: self::TYPE_PICK;

			$item = self::item(
				array(
					'key'            => $key,
					'type'           => $type,
					'name'           => $name,
					'enabled'        => ! isset( $entry['enabled'] ) || (bool) $entry['enabled'],
					'steps'          => self::labels( $entry['steps'] ?? array() ),
					'start'          => self::label( $entry['start'] ?? '' ),
					'mid'            => self::label( $entry['mid'] ?? '' ),
					'end'            => self::label( $entry['end'] ?? '' ),
					'centered'       => ! empty( $entry['centered'] ),
					'unit'           => self::label( $entry['unit'] ?? '' ),
					'thresholds'     => self::numbers( $entry['thresholds'] ?? array() ),
					'advice'         => self::labels( $entry['advice'] ?? array(), true ),
					'attributes'     => self::labels( $entry['attributes'] ?? array() ),
					'scope'          => in_array( $entry['scope'] ?? '', array( self::SCOPE_ONLY, self::SCOPE_EXCEPT ), true ) ? (string) $entry['scope'] : self::SCOPE_ALL,
					'categories'     => array_values( array_unique( array_filter( array_map( 'absint', is_array( $entry['categories'] ?? null ) ? $entry['categories'] : array() ) ) ) ),
					'in_table'       => ! empty( $entry['in_table'] ),
					'category_steps' => self::category_steps( $entry['category_steps'] ?? array() ),
					'guides'         => array_values( array_unique( array_filter( array_map( 'absint', is_array( $entry['guides'] ?? null ) ? $entry['guides'] : array() ) ) ) ),
					'taxonomy'       => is_string( $entry['taxonomy'] ?? null ) && 1 === preg_match( '/^pa_[\p{Ll}\p{Lo}\p{N}_\-]{1,29}$/u', $entry['taxonomy'] ) ? $entry['taxonomy'] : '',
					'step_terms'     => array_values( array_map( 'absint', is_array( $entry['step_terms'] ?? null ) ? $entry['step_terms'] : array() ) ),
					'default_term'   => absint( $entry['default_term'] ?? 0 ),
				)
			);

			if ( self::TYPE_NUMBER !== $item['type'] ) {
				$item['category_steps'] = array();
			}

			$problem = self::problem( $item );
			if ( null !== $problem ) {
				$errors[] = sprintf( '“%1$s”: %2$s', $label, $problem );
				continue;
			}

			// Checked against the final steps, so a stale step index is dropped.
			$item['default'] = Product_Specs_Values::clean( $entry['default'] ?? '', $item );

			$keys[ $key ] = true;
			$items[]      = $item;
		}

		return array(
			'items'           => $items,
			'hide_dimensions' => ! empty( $raw['hide_dimensions'] ),
			'errors'          => $errors,
		);
	}

	/**
	 * Why a scale item is unusable, or null when it is fine.
	 *
	 * @param array $item Item.
	 * @phpstan-param Item $item
	 */
	private static function problem( array $item ): ?string {
		if ( ! self::is_scale( $item ) ) {
			return null;
		}

		$count = count( $item['steps'] );
		if ( $count < self::MIN_STEPS || $count > self::MAX_STEPS ) {
			/* translators: 1: minimum steps, 2: maximum steps. */
			return sprintf( __( 'give the scale between %1$d and %2$d step names.', 'aggressive-apparel' ), self::MIN_STEPS, self::MAX_STEPS );
		}

		if ( self::TYPE_NUMBER === $item['type'] ) {
			if ( count( $item['thresholds'] ) !== $count - 1 || ! self::ascending( $item['thresholds'] ) ) {
				/* translators: %d: number of thresholds needed. */
				return sprintf( __( 'enter %d step boundaries, each higher than the one before.', 'aggressive-apparel' ), $count - 1 );
			}

			$seen = array();
			foreach ( $item['category_steps'] as $override ) {
				if ( $override['category'] <= 0 || isset( $seen[ $override['category'] ] ) ) {
					return __( 'pick a different category for each set of category boundaries.', 'aggressive-apparel' );
				}
				if ( count( $override['thresholds'] ) !== $count - 1 || ! self::ascending( $override['thresholds'] ) ) {
					/* translators: %d: number of thresholds needed. */
					return sprintf( __( 'each category needs %d boundaries, each higher than the one before.', 'aggressive-apparel' ), $count - 1 );
				}
				$seen[ $override['category'] ] = true;
			}
		}

		return null;
	}

	/**
	 * Per-category boundary overrides from raw input.
	 *
	 * @param mixed $raw Raw list of {category, thresholds}.
	 * @return list<CategorySteps>
	 */
	private static function category_steps( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$list = array();
		foreach ( $raw as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$list[] = array(
				'category'   => absint( $entry['category'] ?? 0 ),
				'thresholds' => self::numbers( $entry['thresholds'] ?? array() ),
			);
		}

		return $list;
	}

	/**
	 * A complete item from partial values.
	 *
	 * @param array<string, mixed> $values Partial item.
	 * @return Item
	 */
	private static function item( array $values ): array {
		/**
		 * Defaults overlaid with the given values.
		 *
		 * @var Item $item
		 */
		$item = array_merge(
			array(
				'key'            => '',
				'type'           => self::TYPE_PICK,
				'name'           => '',
				'enabled'        => true,
				'steps'          => array(),
				'start'          => '',
				'mid'            => '',
				'end'            => '',
				'centered'       => false,
				'unit'           => '',
				'thresholds'     => array(),
				'advice'         => array(),
				'attributes'     => array(),
				'scope'          => self::SCOPE_ALL,
				'categories'     => array(),
				'category_steps' => array(),
				'in_table'       => true,
				'default'        => '',
				'default_term'   => 0,
				'guides'         => array(),
				'taxonomy'       => '',
				'step_terms'     => array(),
			),
			$values
		);

		return $item;
	}

	/**
	 * Whether numbers strictly increase.
	 *
	 * @param array $numbers Numbers.
	 * @phpstan-param list<float> $numbers
	 */
	private static function ascending( array $numbers ): bool {
		$count = count( $numbers );
		for ( $i = 1; $i < $count; $i++ ) {
			if ( $numbers[ $i ] <= $numbers[ $i - 1 ] ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * One sanitized label.
	 *
	 * @param mixed $value Raw value.
	 */
	private static function label( $value ): string {
		return is_scalar( $value ) ? mb_substr( trim( sanitize_text_field( (string) $value ) ), 0, self::LABEL_MAX ) : '';
	}

	/**
	 * A list of sanitized labels.
	 *
	 * @param mixed $values    Raw list (or comma-separated string).
	 * @param bool  $keep_empty Keep empty entries (positional lists like advice).
	 * @return list<string>
	 */
	private static function labels( $values, bool $keep_empty = false ): array {
		if ( is_string( $values ) ) {
			$values = explode( ',', $values );
		}
		if ( ! is_array( $values ) ) {
			return array();
		}

		$labels = array_map( array( self::class, 'label' ), array_values( $values ) );

		return $keep_empty ? array_slice( $labels, 0, self::MAX_STEPS ) : array_values( array_filter( $labels, static fn( string $label ): bool => '' !== $label ) );
	}

	/**
	 * A list of numbers.
	 *
	 * @param mixed $values Raw list.
	 * @return list<float>
	 */
	private static function numbers( $values ): array {
		if ( ! is_array( $values ) ) {
			return array();
		}

		$numbers = array();
		foreach ( $values as $value ) {
			if ( is_numeric( $value ) ) {
				$numbers[] = (float) $value;
			}
		}

		return $numbers;
	}
}
