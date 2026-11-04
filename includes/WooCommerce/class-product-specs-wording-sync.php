<?php
/**
 * Product Specs — keeping reworded step labels on their steps.
 *
 * Guides and products reword a spec's steps by position ("step0", "step1"…,
 * Product_Specs::sanitize_wording). When a registry save moves, removes or
 * retypes those steps, each label moves with its step, and a label whose
 * step is gone is dropped, so "Boxy" never lands on another step. A deleted
 * spec's wording goes too, so a later spec with the same key starts clean.
 *
 * A step is followed by its term (a scale's step_terms, which a rename
 * keeps), else by its name, else, when the count is unchanged, by its place
 * (a number scale's band renamed in place).
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

/**
 * Moves step wording when steps change.
 *
 * @phpstan-import-type Item from Product_Specs_Registry
 */
final class Product_Specs_Wording_Sync {

	/**
	 * Posts read per query.
	 *
	 * @var int
	 */
	private const BATCH = 200;

	/**
	 * After a registry save: move each step's wording to where the step went.
	 *
	 * @param array $before Items before the save.
	 * @param array $after  Items after it.
	 * @phpstan-param list<Item> $before
	 * @phpstan-param list<Item> $after
	 */
	public static function sync( array $before, array $after ): void {
		$saved = array_column( $after, null, 'key' );
		$moves = array();
		foreach ( $before as $item ) {
			$move = self::moves( $item, $saved[ $item['key'] ] ?? null );
			if ( null !== $move ) {
				$moves[ $item['key'] ] = $move;
			}
		}
		if ( array() === $moves ) {
			return;
		}

		foreach ( array( Product_Specs_Values::GUIDE_WORDING, Product_Specs_Meta::WORDING ) as $meta_key ) {
			self::rewrite( $meta_key, $moves );
		}
	}

	/**
	 * Where each old step's wording goes (old index => new index; an index
	 * left out is dropped), false to drop the spec's wording altogether, or
	 * null when nothing moves.
	 *
	 * @param array      $before Item before.
	 * @param array|null $after  Item after, or null when deleted.
	 * @phpstan-param Item $before
	 * @phpstan-param Item|null $after
	 * @return array<int, int>|false|null
	 */
	public static function moves( array $before, ?array $after ) {
		if ( null === $after ) {
			return false;
		}
		if ( array() === $before['steps'] ) {
			return null;
		}
		if ( Product_Specs_Registry::TYPE_TEXT === $after['type'] ) {
			return array();
		}

		$moves = array();
		foreach ( array_keys( $before['steps'] ) as $i ) {
			$to = self::follow( $before, $after, $i );
			if ( null !== $to ) {
				$moves[ $i ] = $to;
			}
		}

		$same = count( $moves ) === count( $before['steps'] )
			&& array() === array_filter( $moves, static fn( int $to, int $from ): bool => $to !== $from, ARRAY_FILTER_USE_BOTH );

		return $same ? null : $moves;
	}

	/**
	 * Wording with the moves applied.
	 *
	 * @param array<string, array<string, string>> $wording Item key => field => text.
	 * @param array<string, array<int, int>|false> $moves   Item key => moves.
	 * @return array<string, array<string, string>>
	 */
	public static function apply( array $wording, array $moves ): array {
		foreach ( $moves as $key => $move ) {
			if ( ! isset( $wording[ $key ] ) ) {
				continue;
			}
			if ( false === $move ) {
				unset( $wording[ $key ] );
				continue;
			}

			$fields = array();
			foreach ( $wording[ $key ] as $field => $text ) {
				if ( 1 !== preg_match( '/^step(\d+)$/', $field, $match ) ) {
					$fields[ $field ] = $text;
				} elseif ( isset( $move[ (int) $match[1] ] ) ) {
					$fields[ 'step' . $move[ (int) $match[1] ] ] = $text;
				}
			}
			if ( array() === $fields ) {
				unset( $wording[ $key ] );
			} else {
				$wording[ $key ] = $fields;
			}
		}

		return $wording;
	}

	/**
	 * The new index of old step $i, or null when it's gone.
	 *
	 * @param array $before Item before.
	 * @param array $after  Item after.
	 * @param int   $i      Old step index.
	 * @phpstan-param Item $before
	 * @phpstan-param Item $after
	 */
	private static function follow( array $before, array $after, int $i ): ?int {
		$term = $before['step_terms'][ $i ] ?? 0;
		if ( $term > 0 ) {
			$to = array_search( $term, $after['step_terms'], true );
			if ( false !== $to ) {
				return (int) $to;
			}
		}

		$name = self::normalize( $before['steps'][ $i ] );
		foreach ( $after['steps'] as $j => $step ) {
			if ( self::normalize( $step ) === $name ) {
				return (int) $j;
			}
		}

		// Renamed in place: same count, no term to say otherwise.
		return count( $before['steps'] ) === count( $after['steps'] ) && $term <= 0 ? $i : null;
	}

	/**
	 * Apply the moves to every post with wording in this meta key.
	 *
	 * @param string                               $meta_key Wording meta key.
	 * @param array<string, array<int, int>|false> $moves    Item key => moves.
	 */
	private static function rewrite( string $meta_key, array $moves ): void {
		$last = 0;
		do {
			$ids = self::posts_after( $meta_key, $last );
			foreach ( $ids as $id ) {
				$last    = $id;
				$wording = Product_Specs::sanitize_wording( get_post_meta( $id, $meta_key, true ) );
				$next    = self::apply( $wording, $moves );
				if ( $next === $wording ) {
					continue;
				}
				if ( array() === $next ) {
					delete_post_meta( $id, $meta_key );
				} else {
					update_post_meta( $id, $meta_key, $next );
				}
			}
			$full = count( $ids ) === self::BATCH;
		} while ( $full );
	}

	/**
	 * IDs of posts (any type) holding this meta key, after an ID, in order.
	 *
	 * @param string $meta_key Meta key.
	 * @param int    $after    Last ID already seen.
	 * @return list<int>
	 */
	private static function posts_after( string $meta_key, int $after ): array {
		global $wpdb;

		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One sweep after a registry save; the meta it reads is rewritten here.
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id > %d ORDER BY post_id ASC LIMIT %d",
				$meta_key,
				$after,
				self::BATCH
			)
		);

		return array_values( array_map( 'intval', $ids ) );
	}

	/**
	 * A step name for matching: trimmed, spaces collapsed, lowercase.
	 *
	 * @param string $name Step name.
	 */
	private static function normalize( string $name ): string {
		return mb_strtolower( trim( (string) preg_replace( '/\s+/u', ' ', $name ) ) );
	}
}
