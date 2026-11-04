<?php
/**
 * Garment Kinds
 *
 * The garments the store sells, and how to tell them from a name: a
 * Printful blank's title ("Unisex Heavy Blend Hoodie") or a product
 * category ("Clothing › Hoodies"). One list, used wherever the theme needs
 * to know what kind of garment something is: where an imported guide
 * applies (Printful_Import_Categories) and how heavy a fabric reads for it
 * (Fabric_Weight_Bands).
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
 * Garment kinds and their names.
 */
final class Garment_Kinds {

	/**
	 * Garments, most specific first (a "T-Shirt Dress" is a dress, a "Crew Neck
	 * T-Shirt" a tee, "Jogger Pants" joggers), and the category words each
	 * fits, best first.
	 *
	 * @var array<string, array{match: string, stems: list<string>}>
	 */
	private const GARMENTS = array(
		'dress'      => array(
			'match' => '/\bdress(es)?\b/i',
			'stems' => array( 'dress' ),
		),
		'hat'        => array(
			'match' => '/\b(hats?|caps?|beanies?|visors?|snapbacks?)\b/i',
			'stems' => array( 'hat', 'cap', 'beanie', 'headwear' ),
		),
		'jacket'     => array(
			'match' => '/\b(jackets?|vests?|windbreakers?|bombers?|coats?|outerwear)\b/i',
			'stems' => array( 'jacket', 'outerwear', 'coat' ),
		),
		'hoodie'     => array(
			'match' => '/\b(hoodies?|hooded)\b/i',
			'stems' => array( 'hoodie', 'sweatshirt', 'sweater' ),
		),
		'polo'       => array(
			'match' => '/\bpolos?\b/i',
			'stems' => array( 'polo', 'shirt' ),
		),
		'tank'       => array(
			'match' => '/\btanks?\b/i',
			'stems' => array( 'tank', 'top' ),
		),
		'crop-top'   => array(
			'match' => '/\bcrop\b/i',
			'stems' => array( 'crop', 'top' ),
		),
		'longsleeve' => array(
			'match' => '/\blong[\s-]?sleeves?\b/i',
			'stems' => array( 'longsleeve', 'tshirt', 'tee', 'shirt' ),
		),
		'tshirt'     => array(
			'match' => '/\b(t-?shirts?|tees?)\b/i',
			'stems' => array( 'tshirt', 'tee', 'shirt', 'top' ),
		),
		'sweater'    => array(
			'match' => '/\b(sweaters?|knit(ted|wear)?|cardigans?)\b/i',
			'stems' => array( 'sweater', 'knitwear' ),
		),
		'sweatshirt' => array(
			'match' => '/\b(sweatshirts?|crew\s?necks?|pullovers?)\b/i',
			'stems' => array( 'sweatshirt', 'sweater', 'hoodie' ),
		),
		'shirt'      => array(
			'match' => '/\b(shirts?|jerseys?|blouses?)\b/i',
			'stems' => array( 'shirt', 'top' ),
		),
		'leggings'   => array(
			'match' => '/\b(leggings?|tights)\b/i',
			'stems' => array( 'legging', 'bottom' ),
		),
		'jeans'      => array(
			'match' => '/\b(jeans|denim)\b/i',
			'stems' => array( 'jean', 'denim', 'pant', 'bottom' ),
		),
		'joggers'    => array(
			'match' => '/\b(joggers?|sweatpants?|track\s?pants?)\b/i',
			'stems' => array( 'jogger', 'sweatpant', 'pant', 'bottom' ),
		),
		'pants'      => array(
			'match' => '/\b(pants?|trousers?|chinos?|slacks?|cargos?)\b/i',
			'stems' => array( 'pant', 'trouser', 'chino', 'bottom' ),
		),
		'shorts'     => array(
			'match' => '/\bshorts\b/i',
			'stems' => array( 'short', 'bottom' ),
		),
		'skirt'      => array(
			'match' => '/\bskirts?\b/i',
			'stems' => array( 'skirt', 'bottom' ),
		),
		'swimwear'   => array(
			'match' => '/\b(swim\w*|bikinis?)\b/i',
			'stems' => array( 'swimwear', 'swim' ),
		),
	);

	/**
	 * The garment a name names ('' when it names none).
	 *
	 * @param string $text A title or category name.
	 */
	public static function of( string $text ): string {
		// A sleeve's length isn't a garment: "Cap Sleeve T-Shirt" is no hat.
		$text = (string) preg_replace( '/\b(short|cap)[\s-]?sleeves?\b/i', '', $text );
		foreach ( self::GARMENTS as $key => $garment ) {
			if ( 1 === preg_match( $garment['match'], $text ) ) {
				return $key;
			}
		}

		return '';
	}

	/**
	 * The category words a garment fits, best first.
	 *
	 * @param string $key Garment key.
	 * @return list<string>
	 */
	public static function stems( string $key ): array {
		return self::GARMENTS[ $key ]['stems'] ?? array();
	}

	/**
	 * A garment's name for people.
	 *
	 * @param string $key Garment key.
	 */
	public static function label( string $key ): string {
		$labels = array(
			'dress'      => __( 'Dresses', 'aggressive-apparel' ),
			'hat'        => __( 'Hats', 'aggressive-apparel' ),
			'jacket'     => __( 'Jackets and outerwear', 'aggressive-apparel' ),
			'hoodie'     => __( 'Hoodies', 'aggressive-apparel' ),
			'polo'       => __( 'Polos', 'aggressive-apparel' ),
			'tank'       => __( 'Tank tops', 'aggressive-apparel' ),
			'crop-top'   => __( 'Crop tops', 'aggressive-apparel' ),
			'longsleeve' => __( 'Long sleeve tees', 'aggressive-apparel' ),
			'tshirt'     => __( 'T-shirts', 'aggressive-apparel' ),
			'sweater'    => __( 'Sweaters and knitwear', 'aggressive-apparel' ),
			'sweatshirt' => __( 'Sweatshirts', 'aggressive-apparel' ),
			'shirt'      => __( 'Woven shirts', 'aggressive-apparel' ),
			'leggings'   => __( 'Leggings', 'aggressive-apparel' ),
			'jeans'      => __( 'Jeans and denim', 'aggressive-apparel' ),
			'joggers'    => __( 'Joggers and sweatpants', 'aggressive-apparel' ),
			'pants'      => __( 'Pants and chinos', 'aggressive-apparel' ),
			'shorts'     => __( 'Shorts', 'aggressive-apparel' ),
			'skirt'      => __( 'Skirts', 'aggressive-apparel' ),
			'swimwear'   => __( 'Swimwear', 'aggressive-apparel' ),
		);

		return $labels[ $key ] ?? $key;
	}
}
