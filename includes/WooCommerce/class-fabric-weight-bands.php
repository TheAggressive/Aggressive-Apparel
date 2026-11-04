<?php
/**
 * Fabric Weight Bands
 *
 * How heavy a fabric reads depends on the garment: 308 g/m² is a heavy tee
 * but a midweight hoodie. These are typical boundaries for each garment
 * kind, so a store can give a category the right scale without looking the
 * numbers up: the settings screen suggests them for categories named like a
 * garment and offers each as a preset (_weightBands.ts). Nothing is applied
 * without the store choosing it, and every row stays editable.
 *
 * Bands are where the 2nd…5th steps of a five-step scale start, in g/m². A
 * spec in oz/yd², or with another number of steps, gets them converted and
 * spread to fit on the settings screen (fitBands in _weightBands.ts).
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
 */
final class Fabric_Weight_Bands {

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
	 * never suggested from a name.
	 *
	 * @var list<string>
	 */
	private const NAMED_LOOSELY = array( 'shirt', 'sweater' );

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
		foreach ( Product_Categories::all() as $term ) {
			$key = Garment_Kinds::of( html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) );
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
}
