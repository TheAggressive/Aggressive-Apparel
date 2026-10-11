<?php
/**
 * Variation selector accessibility.
 *
 * WooCommerce's "Add to Cart with Options" variation selector is a
 * `role="list"`, but each attribute row inside it is a plain div, so the list
 * has no list items (axe: aria-required-children) and screen readers can't
 * count or navigate the attributes. Each row is marked as a list item.
 *
 * The selector's own render is filtered, not each row's: WooCommerce renders
 * the rows itself, so per-row filters don't reach every row's final markup.
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
 * Gives the variation selector's attribute rows the list item role.
 */
final class Variation_Selector_A11y {

	/**
	 * The variation selector (the list).
	 *
	 * @var string
	 */
	private const SELECTOR_BLOCK = 'woocommerce/add-to-cart-with-options-variation-selector';

	/**
	 * Class on each attribute row (the list's items).
	 *
	 * @var string
	 */
	private const ROW_CLASS = 'wp-block-woocommerce-add-to-cart-with-options-variation-selector-attribute';

	/**
	 * Register hooks.
	 */
	public function init(): void {
		add_filter( 'render_block_' . self::SELECTOR_BLOCK, array( $this, 'mark_list_items' ), 99 );
	}

	/**
	 * Add `role="listitem"` to every attribute row that has no role yet.
	 *
	 * @param string $content Rendered variation selector.
	 */
	public function mark_list_items( string $content ): string {
		$tags = new \WP_HTML_Tag_Processor( $content );
		while ( $tags->next_tag( array( 'class_name' => self::ROW_CLASS ) ) ) {
			if ( null === $tags->get_attribute( 'role' ) ) {
				$tags->set_attribute( 'role', 'listitem' );
			}
		}

		return $tags->get_updated_html();
	}
}
