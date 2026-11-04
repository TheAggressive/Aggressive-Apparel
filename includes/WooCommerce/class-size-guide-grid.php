<?php
/**
 * Size Guide Grid
 *
 * Every size guide's spec values in one grid, so a value shared by several
 * guides ("Care: machine wash cold" on every tee) is set once rather than
 * guide by guide. Values save through the same store as the guide editor
 * (Product_Specs_Values::save_guide), and only the ones sent change.
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
 * Guides by spec value.
 *
 * @phpstan-type Row array{id: int, title: string, categories: list<string>, values: array<string, string>, hidden: list<string>}
 */
final class Size_Guide_Grid {

	/**
	 * Every guide's values.
	 *
	 * @return list<Row>
	 */
	public static function rows(): array {
		$ids   = Size_Guide_Post_Type::ids();
		$usage = Size_Guide_Assignments::usage_map( $ids );
		$rows  = array();
		foreach ( $ids as $id ) {
			$specs  = Product_Specs_Values::guide( $id );
			$rows[] = array(
				'id'         => $id,
				'title'      => get_the_title( $id ),
				'categories' => $usage[ $id ]['categories'],
				'values'     => $specs['values'],
				'hidden'     => $specs['hidden'],
			);
		}

		return $rows;
	}

	/**
	 * Save values on many guides: guide ID => item key => value. A guide the
	 * user can't edit, or that isn't a size guide, is skipped.
	 *
	 * @param mixed $guides Submitted values.
	 * @return array{saved: int, skipped: int}
	 */
	public static function save( $guides ): array {
		$saved   = 0;
		$skipped = 0;
		foreach ( is_array( $guides ) ? $guides : array() as $id => $values ) {
			$id = absint( $id );
			if ( ! is_array( $values ) || Size_Guide_Post_Type::POST_TYPE !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
				++$skipped;
				continue;
			}
			Product_Specs_Values::save_guide( $id, $values );
			++$saved;
		}
		if ( $saved > 0 ) {
			( new Size_Guide() )->flush_all_caches();
		}

		return array(
			'saved'   => $saved,
			'skipped' => $skipped,
		);
	}
}
