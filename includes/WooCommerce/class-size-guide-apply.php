<?php
/**
 * Size Guide Apply
 *
 * "Apply to products": writes a size guide's spec values onto the products
 * using it as real WooCommerce attributes, so shop filters, product feeds,
 * and other plugins see them. Products show the guide's values without this
 * (Product_Specs reads them at render time); applying is an explicit,
 * one-off bulk edit, never a sync.
 *
 * Only values a product currently takes from the guide are written; a
 * product's own value is never overwritten, and attributes that drive
 * variations are left alone. Runs a page at a time so the editor can show
 * progress and no request runs long.
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
 * Apply a guide's spec values to its products.
 *
 * @phpstan-type ApplyPage array{page: int, pages: int, products: int, values: int}
 * @phpstan-type ApplyStatus array{products: int, missing: int}
 */
final class Size_Guide_Apply {

	/**
	 * Products checked per page.
	 *
	 * @var int
	 */
	public const PER_PAGE = 50;

	/**
	 * Most candidates the status check reads one by one; past this it only
	 * counts them (`missing` -1), so opening a guide stays fast on any
	 * catalog.
	 *
	 * @var int
	 */
	public const CHECK_LIMIT = 1000;

	/**
	 * How many products use the guide, and how many of those still show a
	 * guide value that is not saved on the product (what Apply would fill).
	 * For very large guides, the candidate count and `missing` -1 (unknown).
	 *
	 * @param int $guide_id Size guide ID.
	 * @return ApplyStatus
	 */
	public static function status( int $guide_id ): array {
		$candidates = self::candidates( $guide_id );
		if ( count( $candidates ) > self::CHECK_LIMIT ) {
			return array(
				'products' => count( $candidates ),
				'missing'  => -1,
			);
		}

		$status = array(
			'products' => 0,
			'missing'  => 0,
		);
		foreach ( $candidates as $id ) {
			if ( Size_Guide::resolved( $id )['id'] !== $guide_id ) {
				continue;
			}
			++$status['products'];
			if ( array() !== self::from_guide( $id ) ) {
				++$status['missing'];
			}
		}

		return $status;
	}

	/**
	 * Apply one page of the guide's candidate products.
	 *
	 * @param int $guide_id Size guide ID.
	 * @param int $page     1-based page.
	 * @return ApplyPage Products updated and values written on this page.
	 */
	public static function page( int $guide_id, int $page ): array {
		$candidates = self::candidates( $guide_id );
		$page       = max( 1, $page );
		$result     = array(
			'page'     => $page,
			'pages'    => (int) ceil( count( $candidates ) / self::PER_PAGE ),
			'products' => 0,
			'values'   => 0,
		);

		foreach ( array_slice( $candidates, ( $page - 1 ) * self::PER_PAGE, self::PER_PAGE ) as $product_id ) {
			if ( Size_Guide::resolved( $product_id )['id'] !== $guide_id || ! current_user_can( 'edit_post', $product_id ) ) {
				continue;
			}

			$values  = self::from_guide( $product_id );
			$product = array() !== $values ? wc_get_product( $product_id ) : null;
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$written = Product_Specs_Product_Writer::save( $product, $values, true );
			if ( $written['applied'] > 0 ) {
				++$result['products'];
				$result['values'] += $written['applied'];
			}
		}

		return $result;
	}

	/**
	 * Products that can use the guide, by ID: those assigned it directly and
	 * those in its categories, or every product when it is the store-wide
	 * default. Callers confirm each with Size_Guide::resolved(), since a
	 * product's own pick or another category can win.
	 *
	 * @param int $guide_id Size guide ID.
	 * @return list<int>
	 */
	public static function candidates( int $guide_id ): array {
		if ( Size_Guide_Assignments::is_default( $guide_id ) ) {
			return self::product_ids( array() );
		}

		$ids = self::product_ids(
			array(
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin-only, the assignment lives in meta.
				'meta_query' => array(
					array(
						'key'   => Size_Guide::CPT_META_KEY,
						'value' => (string) $guide_id,
					),
				),
			)
		);

		$categories = Size_Guide_Assignments::categories( $guide_id );
		if ( array() !== $categories ) {
			$ids = array_merge(
				$ids,
				self::product_ids(
					array(
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- admin-only.
						'tax_query' => array(
							array(
								'taxonomy'         => 'product_cat',
								'terms'            => $categories,
								'include_children' => false,
							),
						),
					)
				)
			);
		}

		$ids = array_values( array_unique( $ids ) );
		sort( $ids );

		return $ids;
	}

	/**
	 * Every product ID matching extra query args, read a page at a time.
	 *
	 * @param array<string, mixed> $args Extra WP_Query args.
	 * @return list<int>
	 */
	private static function product_ids( array $args ): array {
		$ids = array();
		for ( $page = 1; ; $page++ ) {
			$batch = get_posts(
				$args + array(
					'post_type'      => 'product',
					'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
					'posts_per_page' => 100,
					'paged'          => $page,
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			foreach ( $batch as $id ) {
				$ids[] = $id instanceof \WP_Post ? $id->ID : (int) $id;
			}
			if ( count( $batch ) < 100 ) {
				return $ids;
			}
		}
	}

	/**
	 * The values a product shows from its size guide, as item key => value.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, string>
	 */
	private static function from_guide( int $product_id ): array {
		$values = array();
		foreach ( Product_Specs::for_product( $product_id )['items'] as $item ) {
			if ( Product_Specs::SOURCE_GUIDE !== $item['source'] ) {
				continue;
			}
			if ( Product_Specs_Registry::TYPE_TEXT === $item['type'] ) {
				$values[ $item['key'] ] = $item['text'];
			} elseif ( Product_Specs_Registry::TYPE_NUMBER === $item['type'] ) {
				$values[ $item['key'] ] = (string) $item['number'];
			} else {
				$values[ $item['key'] ] = (string) $item['step'];
			}
		}

		return $values;
	}
}
