<?php
/**
 * Quick View Card Actions
 *
 * The product-card action stack (Quick View + Wishlist) and its touch-device
 * popover. Split out of Quick_View_Renderer for the file-length gate; the
 * renderer uses this trait, so its public API is unchanged.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Core\Icons;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Quick_View_Card_Actions {

	/**
	 * Build the card media action stack markup for a product.
	 *
	 * @param \WC_Product $product Product instance.
	 * @return string
	 */
	public function build_card_actions_markup( \WC_Product $product ): string {
		$product_id = $product->get_id();
		if ( $product_id <= 0 ) {
			return '';
		}

		$style    = Feature_Settings::get_quick_view_trigger_style();
		$position = Feature_Settings::get_quick_view_trigger_position();

		$allowed_styles = array( 'corner', 'bottom-bar' );
		if ( ! in_array( $style, $allowed_styles, true ) ) {
			$style = 'corner';
		}

		$allowed_positions = array( 'top-right', 'top-left', 'bottom-right', 'bottom-left' );
		if ( ! in_array( $position, $allowed_positions, true ) ) {
			$position = 'top-right';
		}

		$include_wishlist = Feature_Settings::quick_view_includes_wishlist();
		$quick_view_text  = Feature_Settings::get_quick_view_button_text();
		$icon             = Icons::get(
			'eye',
			array(
				'width'       => 18,
				'height'      => 18,
				'aria-hidden' => 'true',
			)
		);

		$context_json = wp_json_encode( array( 'productId' => $product_id ) );
		if ( ! is_string( $context_json ) ) {
			return '';
		}

		// Clicks are handled by capture-phase document delegation in quick-view.js —
		// keep data-wp-interactive + context for product id, omit data-wp-on--click
		// to avoid double-open when hydration succeeds.
		$trigger = sprintf(
			'<button type="button" class="aggressive-apparel-card-action aggressive-apparel-quick-view__trigger aa-icon-button aa-icon-button--only" data-wp-interactive="aggressive-apparel/quick-view" data-wp-context="%1$s" aria-label="%2$s" title="%3$s"><span class="aggressive-apparel-quick-view__trigger-icon" aria-hidden="true">%4$s</span><span class="aggressive-apparel-quick-view__trigger-label" aria-hidden="true">%5$s</span></button>',
			esc_attr( $context_json ),
			esc_attr(
				sprintf(
					/* translators: 1: Quick View label, 2: product name. */
					__( '%1$s: %2$s', 'aggressive-apparel' ),
					$quick_view_text,
					$product->get_name(),
				),
			),
			esc_attr( $quick_view_text ),
			$icon,
			esc_html( $quick_view_text ),
		);

		$wishlist_html = '';
		if ( $include_wishlist ) {
			$wishlist_html = Wishlist::get_heart_button_html(
				$product_id,
				false,
				'aggressive-apparel-card-action aggressive-apparel-wishlist__toggle--card-media'
			);
			Wishlist::mark_button_block_rendered( $product_id );
		}

		$touch_mode = Feature_Settings::get_quick_view_touch_mode();
		// Heart-only without a heart would leave an empty stack on touch.
		if ( 'wishlist' === $touch_mode && ! $include_wishlist ) {
			$touch_mode = 'hidden';
		}
		$stack_inner = $wishlist_html . $trigger;
		if ( 'toggle' === $touch_mode ) {
			$stack_inner = $this->wrap_in_touch_popover( $product, $stack_inner );
		}
		if ( 'bottom-bar' === $style ) {
			$stack_inner = sprintf(
				'<div class="aggressive-apparel-card-actions__bar">%s</div>',
				$stack_inner
			);
		}

		$modifiers = array( $style, $position, 'touch-' . $touch_mode );
		if ( $include_wishlist && Feature_Settings::pins_card_wishlist_on_hover_devices() ) {
			$modifiers[] = 'wishlist-pinned';
		}

		$group_label = $include_wishlist
			? __( 'Product actions', 'aggressive-apparel' )
			: $quick_view_text;

		$stack = sprintf(
			'<div class="aggressive-apparel-card-actions %1$s" role="group" aria-label="%2$s">%3$s</div>',
			esc_attr( implode( ' ', array_map( static fn ( string $modifier ): string => 'aggressive-apparel-card-actions--' . $modifier, $modifiers ) ) ),
			esc_attr( $group_label ),
			$stack_inner
		);

		/**
		 * Filters the Quick View card media action stack markup.
		 *
		 * @since 1.81.0
		 *
		 * @param string      $stack   Action stack HTML.
		 * @param \WC_Product $product Current product.
		 * @param string      $style   Trigger style slug.
		 * @param string      $position Corner position slug.
		 */
		return (string) apply_filters(
			'aggressive_apparel_quick_view_card_actions_html',
			$stack,
			$product,
			$style,
			$position
		);
	}

	/**
	 * Put a card's actions behind one chip on touch devices.
	 *
	 * The chip opens the actions as a native popover (`popover="auto"`), so the
	 * browser handles tap-outside, Escape, one-open-at-a-time and the expanded
	 * state; CSS anchor positioning keeps it beside the chip. Devices that can
	 * hover — and browsers without popover or anchor positioning — never see
	 * the chip and get the actions inline (_quick-view-cards.css).
	 *
	 * @param \WC_Product $product Current product.
	 * @param string      $actions Action buttons HTML.
	 * @return string
	 */
	private function wrap_in_touch_popover( \WC_Product $product, string $actions ): string {
		// The product id keeps ids unique across Load More pages, whose
		// renders restart wp_unique_id()'s counter; the counter separates
		// repeats of one product on a page.
		$id        = wp_unique_id( 'aa-card-actions-' . $product->get_id() . '-' );
		$icon_args = array(
			'width'       => 18,
			'height'      => 18,
			'aria-hidden' => 'true',
		);

		return sprintf(
			'<button type="button" class="aggressive-apparel-card-action aggressive-apparel-card-actions__toggle aa-icon-button aa-icon-button--only" popovertarget="%1$s" aria-label="%2$s" style="anchor-name: --%1$s"><span class="aggressive-apparel-card-actions__toggle-open" aria-hidden="true">%3$s</span><span class="aggressive-apparel-card-actions__toggle-close" aria-hidden="true">%4$s</span></button><div id="%1$s" class="aggressive-apparel-card-actions__popover" popover="auto" style="position-anchor: --%1$s">%5$s</div>',
			esc_attr( $id ),
			esc_attr(
				sprintf(
					/* translators: %s: product name. */
					__( 'Product actions: %s', 'aggressive-apparel' ),
					$product->get_name()
				)
			),
			Icons::get( 'dots', $icon_args ),
			Icons::get( 'close', $icon_args ),
			$actions
		);
	}
}
