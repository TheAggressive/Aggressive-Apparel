<?php
/**
 * Printful Import Categories
 *
 * Suggests where an imported guide applies, so a guide doesn't arrive reaching
 * no products: the store's last choice for that kind of garment if it made one,
 * else its categories named like the garment ("Tshirts" for a tee). It suggests
 * nothing rather than a broad category like "Clothing".
 *
 * A category holds one guide and saving takes it over, so a category another
 * guide already uses is never suggested, only reported: the store takes it
 * deliberately or not at all. Printful's product `type` is the print method,
 * not the garment, so the garment is read from its title and catalog category.
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
 * Category suggestions for imported guides.
 *
 * @phpstan-type Taken array{id: int, name: string, guide: string}
 * @phpstan-type Where array{key: string, categories: list<int>, source: string, taken: list<Taken>}
 */
final class Printful_Import_Categories {

	/**
	 * Option: the store's category choices, provider => garment => category IDs.
	 *
	 * @var string
	 */
	public const RULES = 'aggressive_apparel_import_category_rules';

	/**
	 * Suggested from the store's last choice for this garment.
	 *
	 * @var string
	 */
	public const REMEMBERED = 'remembered';

	/**
	 * Suggested because a category is named like the garment.
	 *
	 * @var string
	 */
	public const MATCHED = 'matched';

	/**
	 * Most remembered garments per provider.
	 *
	 * @var int
	 */
	private const MAX_RULES = 100;

	/**
	 * Category words that mean something else on their own ("Top Sellers", "Short
	 * Sleeve"), so singular they only count as a category's whole name.
	 *
	 * @var list<string>
	 */
	private const LOOSE_WORDS = array( 'top', 'short', 'cap', 'bottom', 'crop' );

	/**
	 * Garments, most specific first (a "T-Shirt Dress" is a dress, a "Crew Neck
	 * T-Shirt" a tee), and the category words each fits, best first.
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
			'match' => '/\b(jackets?|vests?|windbreakers?|bombers?|coats?)\b/i',
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
			'match' => '/\b(shirts?|jerseys?)\b/i',
			'stems' => array( 'shirt', 'top' ),
		),
		'leggings'   => array(
			'match' => '/\bleggings?\b/i',
			'stems' => array( 'legging', 'bottom' ),
		),
		'joggers'    => array(
			'match' => '/\b(joggers?|sweatpants?|pants?)\b/i',
			'stems' => array( 'jogger', 'sweatpant', 'pant', 'bottom' ),
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
	 * Where a blank's guide should apply.
	 *
	 * @param string $provider Provider.
	 * @param string $title    The blank's title.
	 * @param string $category The blank's catalog category.
	 * @return Where
	 */
	public static function suggest( string $provider, string $title, string $category ): array {
		$title = trim( explode( '|', $title )[0] );
		$key   = self::garment( $title );
		if ( '' === $key ) {
			$key = self::garment( $category );
		}
		if ( '' === $key ) {
			$key = sanitize_title( $category );
		}
		$terms = self::terms();

		// The last choice, unless its categories have since been deleted.
		$found  = array_intersect_key( $terms, array_flip( self::rules( $provider )[ $key ] ?? array() ) );
		$source = self::REMEMBERED;
		if ( array() === $found ) {
			$found  = self::named_like( $key, $title, $terms );
			$source = array() !== $found ? self::MATCHED : '';
		}

		$categories = array();
		$taken      = array();
		foreach ( $found as $id => $name ) {
			$owner = (int) get_term_meta( $id, Size_Guide::CPT_META_KEY, true );
			$guide = $owner > 0 ? get_post( $owner ) : null;
			if ( $guide instanceof \WP_Post && 'publish' === $guide->post_status ) {
				$taken[] = array(
					'id'    => $id,
					'name'  => $name,
					'guide' => $guide->post_title,
				);
			} else {
				$categories[] = $id;
			}
		}

		return array(
			'key'        => $key,
			'categories' => $categories,
			'source'     => $source,
			'taken'      => $taken,
		);
	}

	/**
	 * Remember where the store put this kind of garment. Leaving a guide with
	 * no category isn't a preference (the category may just be taken), so only
	 * a real choice replaces the last one.
	 *
	 * @param string $provider Provider.
	 * @param mixed  $key      Garment key from the draft.
	 * @param mixed  $chosen   Category IDs chosen.
	 */
	public static function remember( string $provider, $key, $chosen ): void {
		$key    = is_string( $key ) ? sanitize_title( $key ) : '';
		$chosen = is_array( $chosen ) ? array_values( array_filter( array_map( 'absint', $chosen ) ) ) : array();
		if ( '' === $key || array() === $chosen ) {
			return;
		}

		$rules = self::rules( $provider );
		unset( $rules[ $key ] );
		$rules[ $key ] = $chosen;

		$all              = get_option( self::RULES, array() );
		$all              = is_array( $all ) ? $all : array();
		$all[ $provider ] = array_slice( $rules, -self::MAX_RULES, null, true );
		update_option( self::RULES, $all, false );
	}

	/**
	 * The garment a text names ('' when it names none).
	 *
	 * @param string $text Title or category.
	 */
	private static function garment( string $text ): string {
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
	 * The categories named like a garment: those matching its best-fitting word
	 * that any category uses, minus the other gender's when the title says one.
	 *
	 * @param string             $key   Garment key.
	 * @param string             $title The blank's title.
	 * @param array<int, string> $terms Category ID => name.
	 * @return array<int, string>
	 */
	private static function named_like( string $key, string $title, array $terms ): array {
		$not = array();
		if ( 1 === preg_match( '/\b(women|ladies)/i', $title ) ) {
			$not = array( 'men', 'mens' );
		} elseif ( 1 === preg_match( '/\bmen\b/i', $title ) ) {
			$not = array( 'women', 'womens', 'ladies' );
		}

		foreach ( self::GARMENTS[ $key ]['stems'] ?? array() as $stem ) {
			$found = array();
			foreach ( $terms as $id => $name ) {
				$words = self::words( $name );
				$forms = array( $stem . 's', $stem . 'es' );
				if ( ! in_array( $stem, self::LOOSE_WORDS, true ) || array( $stem ) === $words ) {
					$forms[] = $stem;
				}
				if ( array() === array_intersect( $words, $not ) && array() !== array_intersect( $words, $forms ) ) {
					$found[ $id ] = $name;
				}
			}
			if ( array() !== $found ) {
				return $found;
			}
		}

		return array();
	}

	/**
	 * A category name as comparable words ("Men's T-Shirts" → mens, tshirts).
	 *
	 * @param string $name Category name.
	 * @return list<string>
	 */
	private static function words( string $name ): array {
		$name  = strtolower( wp_specialchars_decode( $name, ENT_QUOTES ) );
		$name  = (string) preg_replace( array( '/\bt[\s-]?shirt/', '/\b(long|short|cap)[\s-]?sleeve/', '/[\'’]s\b/' ), array( 'tshirt', '$1sleeve', 's' ), $name );
		$words = preg_split( '/[^a-z0-9]+/', $name, -1, PREG_SPLIT_NO_EMPTY );

		return false !== $words ? $words : array();
	}

	/**
	 * The store's product categories.
	 *
	 * @return array<int, string> ID => name.
	 */
	private static function terms(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 500,
			)
		);

		$names = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( $term instanceof \WP_Term ) {
				$names[ $term->term_id ] = html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' );
			}
		}

		return $names;
	}

	/**
	 * A provider's remembered choices.
	 *
	 * @param string $provider Provider.
	 * @return array<string, list<int>> Garment key => category IDs.
	 */
	private static function rules( string $provider ): array {
		$all   = get_option( self::RULES, array() );
		$rules = is_array( $all ) && is_array( $all[ $provider ] ?? null ) ? $all[ $provider ] : array();

		$clean = array();
		foreach ( $rules as $key => $ids ) {
			if ( is_string( $key ) && is_array( $ids ) ) {
				$clean[ $key ] = array_values( array_filter( array_map( 'absint', $ids ) ) );
			}
		}

		return $clean;
	}
}
