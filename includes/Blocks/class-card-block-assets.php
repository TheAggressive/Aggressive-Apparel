<?php
/**
 * Card Block Assets
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Blocks;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the assets of the theme blocks in a product collection's card.
 *
 * A card block reads its product from `postId` context and can render nothing
 * for one product and not the next (colour swatches for a product without
 * colours, a rating for one without reviews). WordPress (6.9+) drops a block's
 * assets when it renders nothing, and never loads them when the block never
 * renders (an empty first page). Cards that Load More or a filter adds later
 * then arrive unstyled and inert. So the collection, which always renders,
 * loads them for its card template.
 */
final class Card_Block_Assets {

	/**
	 * Blocks whose inner template repeats per product.
	 */
	private const COLLECTIONS = array( 'woocommerce/product-collection', 'core/query' );

	/**
	 * Theme block namespace prefix.
	 */
	private const NAMESPACE_PREFIX = 'aggressive-apparel/';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		foreach ( self::COLLECTIONS as $collection ) {
			\add_filter( "render_block_{$collection}", array( self::class, 'enqueue_card_assets' ), 10, 2 );
		}
	}

	/**
	 * Enqueue the assets of the card blocks inside a rendered collection.
	 *
	 * @param mixed $block_content Rendered collection HTML.
	 * @param mixed $block         Parsed collection block.
	 * @return mixed The collection HTML, unchanged.
	 */
	public static function enqueue_card_assets( $block_content, $block ) {
		$inner = is_array( $block ) && is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : array();

		foreach ( self::card_blocks( $inner ) as $type ) {
			foreach ( array_merge( (array) $type->style_handles, (array) $type->view_style_handles ) as $handle ) {
				\wp_enqueue_style( (string) $handle );
			}
			foreach ( (array) $type->view_script_module_ids as $module ) {
				\wp_enqueue_script_module( (string) $module );
			}
		}

		return $block_content;
	}

	/**
	 * The theme blocks in a block tree that read their product from context.
	 *
	 * @param array<int, mixed> $blocks Parsed blocks.
	 * @return array<string, \WP_Block_Type> Block types keyed by name.
	 */
	private static function card_blocks( array $blocks ): array {
		$found    = array();
		$registry = \WP_Block_Type_Registry::get_instance();

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$name = (string) ( $block['blockName'] ?? '' );
			$type = str_starts_with( $name, self::NAMESPACE_PREFIX ) ? $registry->get_registered( $name ) : null;
			if ( $type && property_exists( $type, 'uses_context' ) && in_array( 'postId', (array) $type->uses_context, true ) ) {
				$found[ $name ] = $type;
			}
			if ( is_array( $block['innerBlocks'] ?? null ) ) {
				$found += self::card_blocks( $block['innerBlocks'] );
			}
		}

		return $found;
	}
}
