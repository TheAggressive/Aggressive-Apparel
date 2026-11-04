<?php
/**
 * Product Categories
 *
 * The store's product categories, read once and shared by everything in
 * Size & Specs that lists, names or matches them: the settings screen's
 * pickers, which categories use each size guide, garment matching, weight
 * presets, and moving the setup between sites. One query (with term meta
 * primed), no cap, and one way of naming a category: by its place for
 * people ("Clothing › Hoodies") or by its slug path for other sites
 * ("clothing/hoodies").
 *
 * WordPress marks terms as changed whenever a category or its meta changes
 * (wp_cache_set_terms_last_changed), so the copy here is read again only
 * then: a category given a guide mid-request is seen at once.
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
 * The store's product categories, per request.
 */
final class Product_Categories {

	/**
	 * What's been read, and when terms last changed when it was.
	 *
	 * @var array{changed: string, terms: array<int, \WP_Term>, labels: array<int, string>, paths: array<int, string>, ids: array<string, int>, guides: array<int, list<int>>}|null
	 */
	private static ?array $read = null;

	/**
	 * Every category, by name.
	 *
	 * @return array<int, \WP_Term> ID => term.
	 */
	public static function all(): array {
		return self::read()['terms'];
	}

	/**
	 * A category's name with its parents, for people ("Clothing › Hoodies");
	 * '' when it's gone.
	 *
	 * @param int $id Category ID.
	 */
	public static function label( int $id ): string {
		return self::read()['labels'][ $id ] ?? '';
	}

	/**
	 * Every category as ID => label, sorted by label.
	 *
	 * @return array<int, string>
	 */
	public static function labels(): array {
		$labels = self::read()['labels'];
		asort( $labels, SORT_NATURAL | SORT_FLAG_CASE );

		return $labels;
	}

	/**
	 * A category's slug path from the top ("clothing/hoodies"); '' when gone.
	 *
	 * @param int $id Category ID.
	 */
	public static function path( int $id ): string {
		return self::read()['paths'][ $id ] ?? '';
	}

	/**
	 * The category at a slug path, or 0.
	 *
	 * @param string $path Slug path.
	 */
	public static function at_path( string $path ): int {
		return self::read()['ids'][ trim( $path, '/' ) ] ?? 0;
	}

	/**
	 * The categories each size guide is the default for, by name.
	 *
	 * @return array<int, list<int>> Guide ID => category IDs.
	 */
	public static function by_guide(): array {
		return self::read()['guides'];
	}

	/**
	 * Read the categories, unless they haven't changed since last time.
	 *
	 * @return array{changed: string, terms: array<int, \WP_Term>, labels: array<int, string>, paths: array<int, string>, ids: array<string, int>, guides: array<int, list<int>>}
	 */
	private static function read(): array {
		$changed = (string) wp_cache_get_last_changed( 'terms' );
		if ( null !== self::$read && $changed === self::$read['changed'] ) {
			return self::$read;
		}

		$found = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		$terms = array();
		foreach ( is_array( $found ) ? $found : array() as $term ) {
			if ( $term instanceof \WP_Term ) {
				$terms[ $term->term_id ] = $term;
			}
		}

		$labels = array();
		$paths  = array();
		$guides = array();
		foreach ( $terms as $id => $term ) {
			$names = array( html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) );
			$slugs = array( $term->slug );
			$seen  = array( $id => true );
			for ( $up = $term->parent; 0 < $up && isset( $terms[ $up ] ) && ! isset( $seen[ $up ] ); $up = $terms[ $up ]->parent ) {
				$seen[ $up ] = true;
				array_unshift( $names, html_entity_decode( $terms[ $up ]->name, ENT_QUOTES, 'UTF-8' ) );
				array_unshift( $slugs, $terms[ $up ]->slug );
			}
			$labels[ $id ] = implode( ' › ', $names );
			$paths[ $id ]  = implode( '/', $slugs );

			// Term meta was primed with the terms, so this reads no database.
			$guide = (int) get_term_meta( $id, Size_Guide::CPT_META_KEY, true );
			if ( 0 < $guide ) {
				$guides[ $guide ][] = $id;
			}
		}

		self::$read = array(
			'changed' => $changed,
			'terms'   => $terms,
			'labels'  => $labels,
			'paths'   => $paths,
			'ids'     => array_flip( $paths ),
			'guides'  => $guides,
		);

		return self::$read;
	}
}
