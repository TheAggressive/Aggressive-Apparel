<?php
/**
 * Fabric Weight Bands
 *
 * How heavy a fabric reads depends on the garment: 308 g/m² is a heavy tee
 * but a midweight hoodie. These are typical boundaries for each garment
 * kind, so a store can give a category the right scale without looking the
 * numbers up: the settings screen suggests them for categories named like a
 * garment and offers each as a preset (_weightBands.ts), and an imported
 * guide's categories get theirs (fill()). They're starting points; every
 * row stays editable.
 *
 * Bands are where the 2nd…5th steps of a five-step scale start, in g/m². A
 * spec in oz/yd², or with another number of steps, gets them converted and
 * spread to fit (fit(), mirrored by fitBands in _weightBands.ts).
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
 * Typical fabric weights by garment.
 *
 * @phpstan-import-type Item from Product_Specs_Registry
 */
final class Fabric_Weight_Bands {

	/**
	 * Grams per square metre in an ounce per square yard.
	 *
	 * @var float
	 */
	public const GSM_PER_OZ = 33.906;

	/**
	 * Boundaries by garment kind (Garment_Kinds), g/m². Tees and light tops
	 * are the built-in Fabric weight spec's own scale.
	 *
	 * @var array<string, list<int>>
	 */
	public const BANDS = array(
		'tshirt'     => array( 145, 170, 195, 220 ),
		'longsleeve' => array( 145, 170, 195, 220 ),
		'tank'       => array( 130, 150, 175, 200 ),
		'crop-top'   => array( 145, 170, 195, 220 ),
		'polo'       => array( 170, 195, 220, 250 ),
		'shirt'      => array( 100, 125, 150, 180 ),
		'dress'      => array( 120, 150, 180, 220 ),
		'hoodie'     => array( 240, 270, 330, 360 ),
		'sweatshirt' => array( 240, 270, 330, 360 ),
		'joggers'    => array( 240, 270, 330, 360 ),
		'sweater'    => array( 200, 250, 300, 350 ),
		'jacket'     => array( 250, 300, 350, 400 ),
		'leggings'   => array( 200, 230, 260, 290 ),
		'jeans'      => array( 340, 400, 440, 480 ),
		'pants'      => array( 200, 240, 290, 330 ),
		'shorts'     => array( 150, 200, 250, 300 ),
		'skirt'      => array( 150, 190, 230, 270 ),
		'swimwear'   => array( 150, 180, 200, 230 ),
	);

	/**
	 * Garments a category name can't settle: a store's "Shirts" often holds
	 * tees, and its "Sweater" sweatshirts. They're offered as presets but
	 * never suggested from a name; an import knows the garment, so it fills
	 * them.
	 *
	 * @var list<string>
	 */
	private const NAMED_LOOSELY = array( 'shirt', 'sweater' );

	/**
	 * Units that mean grams per square metre, spaces and "²" aside.
	 *
	 * @var list<string>
	 */
	private const GSM_UNITS = array( 'g/m2', 'gsm', 'gm2', 'g/sqm' );

	/**
	 * Units that mean ounces per square yard.
	 *
	 * @var list<string>
	 */
	private const OZ_UNITS = array( 'oz/yd2', 'ozyd2', 'oz/sqyd', 'oz' );

	/**
	 * The presets for the settings screen: every garment with bands, and the
	 * garment each product category is clearly named like.
	 *
	 * @return array{garments: list<array{key: string, label: string, bands: list<int>}>, categories: list<array{category: int, garment: string}>}
	 */
	public static function presets(): array {
		$garments = array();
		foreach ( self::BANDS as $key => $bands ) {
			$garments[] = array(
				'key'   => $key,
				'label' => Garment_Kinds::label( $key ),
				'bands' => $bands,
			);
		}

		$categories = array();
		$terms      = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 500,
			)
		);
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$key = $term instanceof \WP_Term ? Garment_Kinds::of( html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) ) : '';
			if ( '' !== $key && isset( self::BANDS[ $key ] ) && ! in_array( $key, self::NAMED_LOOSELY, true ) ) {
				$categories[] = array(
					'category' => $term->term_id,
					'garment'  => $key,
				);
			}
		}

		return array(
			'garments'   => $garments,
			'categories' => $categories,
		);
	}

	/**
	 * Whether a spec measures fabric weight: a number in g/m², or in oz/yd²
	 * (bare "oz" only when the spec is named for weight).
	 *
	 * @param array $item Item.
	 * @phpstan-param Item $item
	 */
	public static function is_weight( array $item ): bool {
		if ( Product_Specs_Registry::TYPE_NUMBER !== $item['type'] ) {
			return false;
		}
		$unit = self::unit( $item['unit'] );
		if ( in_array( $unit, self::GSM_UNITS, true ) ) {
			return true;
		}

		return in_array( $unit, self::OZ_UNITS, true ) && ( 'oz' !== $unit || 1 === preg_match( '/weight/i', $item['name'] ) );
	}

	/**
	 * Bands fitted to a spec: in its unit, one boundary per step after the
	 * first, spread evenly between the bands' ends when the count differs.
	 *
	 * @param list<int|float> $bands Boundaries in g/m².
	 * @param array           $item  Item.
	 * @phpstan-param Item $item
	 * @return list<float>
	 */
	public static function fit( array $bands, array $item ): array {
		$count = count( $item['steps'] ) - 1;
		if ( $count < 1 || array() === $bands ) {
			return array();
		}

		$first  = (float) $bands[0];
		$last   = (float) $bands[ count( $bands ) - 1 ];
		$spread = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$spread[] = count( $bands ) === $count
				? (float) $bands[ $i ]
				: ( 1 === $count ? ( $first + $last ) / 2 : $first + ( $last - $first ) * $i / ( $count - 1 ) );
		}

		$oz = in_array( self::unit( $item['unit'] ), self::OZ_UNITS, true );

		return array_map(
			static fn( float $gsm ): float => $oz ? round( $gsm / self::GSM_PER_OZ, 1 ) : round( $gsm ),
			$spread
		);
	}

	/**
	 * Give an imported guide's categories the bands for its garment, on every
	 * fabric weight spec where they differ from the spec's own scale. A
	 * category that has boundaries, or whose parent has, is left alone.
	 *
	 * @param mixed $garment    Garment key (Garment_Kinds).
	 * @param mixed $categories Category IDs.
	 */
	public static function fill( $garment, $categories ): void {
		$garment    = is_string( $garment ) ? sanitize_title( $garment ) : '';
		$categories = is_array( $categories ) ? array_values( array_filter( array_map( 'absint', $categories ) ) ) : array();
		if ( ! isset( self::BANDS[ $garment ] ) || array() === $categories ) {
			return;
		}

		$registry = Product_Specs_Registry::get();
		$items    = $registry['items'];
		$changed  = false;
		foreach ( $items as $i => $item ) {
			$bands = self::is_weight( $item ) ? self::fit( self::BANDS[ $garment ], $item ) : array();
			if ( array() === $bands || $bands === $item['thresholds'] ) {
				continue;
			}
			$set = array_column( $item['category_steps'], 'category' );
			foreach ( $categories as $category ) {
				$lineage = array_merge( array( $category ), array_map( 'intval', get_ancestors( $category, 'product_cat', 'taxonomy' ) ) );
				if ( array() === array_intersect( $lineage, $set ) ) {
					$items[ $i ]['category_steps'][] = array(
						'category'   => $category,
						'thresholds' => $bands,
					);

					$set[]   = $category;
					$changed = true;
				}
			}
		}
		if ( ! $changed ) {
			return;
		}

		$result = Product_Specs_Registry::validate(
			array(
				'items'           => $items,
				'hide_dimensions' => $registry['hide_dimensions'],
			)
		);
		if ( array() === $result['errors'] ) {
			Product_Specs_Registry::save(
				array(
					'items'           => $result['items'],
					'hide_dimensions' => $result['hide_dimensions'],
				)
			);
		}
	}

	/**
	 * A unit for comparing: lowercase, no spaces, "²" as "2".
	 *
	 * @param string $unit Unit.
	 */
	private static function unit( string $unit ): string {
		return str_replace( array( ' ', '²' ), array( '', '2' ), mb_strtolower( trim( $unit ) ) );
	}
}
