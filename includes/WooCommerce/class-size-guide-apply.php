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
 */
final class Size_Guide_Apply {

	/**
	 * Products checked per page.
	 *
	 * @var int
	 */
	public const PER_PAGE = 50;

	/**
	 * Apply one page of products.
	 *
	 * @param int $guide_id Size guide ID.
	 * @param int $page     1-based page of all products.
	 * @return ApplyPage Products updated and values written on this page.
	 */
	public static function page( int $guide_id, int $page ): array {
		$query = new \WP_Query(
			array(
				'post_type'              => 'product',
				'post_status'            => array( 'publish', 'private', 'draft', 'pending', 'future' ),
				'posts_per_page'         => self::PER_PAGE,
				'paged'                  => max( 1, $page ),
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_term_cache' => true,
			)
		);

		$result = array(
			'page'     => max( 1, $page ),
			'pages'    => (int) $query->max_num_pages,
			'products' => 0,
			'values'   => 0,
		);

		foreach ( $query->posts as $post ) {
			$product_id = $post instanceof \WP_Post ? $post->ID : (int) $post;
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
