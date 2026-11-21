<?php
/**
 * Editorial Product Grid
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
 * Builds the Product Collection block markup the editorial patterns share.
 *
 * One card definition for every editorial section, mirroring the Shop
 * archive's frameless card (media well, title, price, swatches) so a product
 * looks the same on the homepage as in the catalog. The query is a real
 * WooCommerce Product Collection, so editors can retune it in the block
 * inspector after inserting the pattern.
 */
final class Editorial_Product_Grid {

	/**
	 * Product Collection markup.
	 *
	 * Options: query_id (required, unique per page), per_page, offset (skip
	 * the first N, e.g. after a lead card), columns, order_by, featured, term
	 * (filters by a category, tag or attribute),
	 * collection (a WooCommerce preset such as new-arrivals), align, label,
	 * centered (centre a row shorter than the column count), lead (a single
	 * large card: name and price set larger), rail (one scrolling row that
	 * shows four and a half cards, so a longer range reads as more).
	 *
	 * @param array<string, mixed> $args Grid options.
	 * @phpstan-param array{query_id: int, per_page?: int, offset?: int, columns?: int, order_by?: string, featured?: bool, term?: \WP_Term|null, collection?: string, align?: string, label?: string, centered?: bool, lead?: bool, rail?: bool} $args
	 */
	public static function markup( array $args ): string {
		$term    = $args['term'] ?? null;
		$filter  = null !== $term ? Editorial_Catalog::query_filter( $term ) : array(
			'taxQuery'              => array(),
			'woocommerceAttributes' => array(),
		);
		$columns = max( 1, min( 6, $args['columns'] ?? 4 ) );
		$align   = $args['align'] ?? 'wide';

		$attributes = array(
			'queryId'       => $args['query_id'],
			'query'         => array(
				'perPage'                       => max( 1, $args['per_page'] ?? $columns ),
				'pages'                         => 1,
				'offset'                        => max( 0, $args['offset'] ?? 0 ),
				'postType'                      => 'product',
				'order'                         => 'desc',
				'orderBy'                       => $args['order_by'] ?? 'date',
				'search'                        => '',
				'exclude'                       => array(), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Block attribute, not a query argument.
				'inherit'                       => false,
				// An empty taxQuery serializes as [] (as the block saves it).
				'taxQuery'                      => array() === $filter['taxQuery'] ? array() : $filter['taxQuery'],
				'isProductCollectionBlock'      => true,
				'featured'                      => (bool) ( $args['featured'] ?? false ),
				'woocommerceOnSale'             => false,
				'woocommerceStockStatus'        => array( 'instock', 'onbackorder' ),
				'woocommerceAttributes'         => $filter['woocommerceAttributes'],
				'woocommerceHandPickedProducts' => array(),
			),
			'tagName'       => 'div',
			'displayLayout' => array(
				'type'          => 'flex',
				'columns'       => $columns,
				'shrinkColumns' => true,
			),
			'dimensions'    => array( 'widthType' => 'fill' ),
		);
		if ( isset( $args['collection'] ) ) {
			$attributes['collection']           = 'woocommerce/product-collection/' . $args['collection'];
			$attributes['queryContextIncludes'] = array( 'collection' );
		}
		if ( isset( $args['label'] ) ) {
			$attributes['metadata'] = array( 'name' => $args['label'] );
		}
		if ( '' !== $align ) {
			$attributes['align'] = $align;
		}
		// A centred section centres a short row (aa-grid-centered, editorial.css
		// in blocks.css) instead of leaving it flush left under its heading.
		$class_name              = 'is-style-editorial-grid' . ( ! empty( $args['centered'] ) ? ' aa-grid-centered' : '' ) . ( ! empty( $args['lead'] ) ? ' aa-grid-lead' : '' ) . ( ! empty( $args['rail'] ) ? ' aa-grid-rail' : '' );
		$attributes['className'] = $class_name;

		$classes = 'wp-block-woocommerce-product-collection' . ( '' !== $align ? ' align' . $align : '' ) . ' ' . $class_name;

		return '<!-- wp:woocommerce/product-collection ' . self::json( $attributes ) . ' -->' . "\n"
			. '<div class="' . esc_attr( $classes ) . '">' . self::card( ! empty( $args['lead'] ) ) . '</div>' . "\n"
			. '<!-- /wp:woocommerce/product-collection -->';
	}

	/**
	 * The shared card: media well, linked title, price, color swatches.
	 *
	 * @param bool $lead A lead card: name and price one step larger.
	 */
	private static function card( bool $lead = false ): string {
		$card = <<<'HTML'
<!-- wp:woocommerce/product-template -->
<!-- wp:group {"className":"aa-editorial-card","style":{"spacing":{"blockGap":"var:preset|spacing|3"}},"layout":{"type":"default"}} -->
<div class="wp-block-group aa-editorial-card"><!-- wp:group {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}},"backgroundColor":"surface-muted","layout":{"type":"default"}} -->
<div class="wp-block-group has-surface-muted-background-color has-background" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:post-featured-image {"isLink":true,"sizeSlug":"full"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|1"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:post-title {"level":3,"isLink":true,"style":{"elements":{"link":{"color":{"text":"var:preset|color|foreground"},":hover":{"color":{"text":"var:preset|color|accent"}}}}},"fontSize":"medium","__woocommerceNamespace":"woocommerce/product-collection/product-title"} /-->

<!-- wp:woocommerce/product-price {"isDescendentOfQueryLoop":true,"textAlign":"left","fontSize":"medium"} /-->

<!-- wp:aggressive-apparel/product-color-swatches {"swatchSize":"xxs","showTooltip":false,"swatchAlignment":"left"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:woocommerce/product-template -->
HTML;

		return $lead ? str_replace( '"fontSize":"medium"', '"fontSize":"x-large"', $card ) : $card;
	}

	/**
	 * Block-comment JSON, encoded as WordPress serializes attributes.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 */
	private static function json( array $attributes ): string {
		return serialize_block_attributes( $attributes );
	}
}
