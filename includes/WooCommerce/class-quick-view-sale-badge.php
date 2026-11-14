<?php
/**
 * Quick View sale badge markup.
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
 * The sale badge on Quick View's main image.
 *
 * When this theme draws the catalog's sale badge (Product Badges on, Sale
 * rule on), Quick View renders the same Badge Studio design through
 * `Product_Badges::render_badge()`, in the same corner. Only the text is
 * per-product, so it is a `data-wp-text` slot the store fills. Otherwise
 * WooCommerce owns the sale flash and Quick View keeps its own default chip.
 */
final class Quick_View_Sale_Badge {

	/** Class on the element the store shows and hides. */
	public const CONTAINER_CLASS = 'aggressive-apparel-quick-view__sale-badge';

	/** Class on the element the store writes the badge text into. */
	public const TEXT_CLASS = 'aggressive-apparel-quick-view__sale-badge-text';

	/**
	 * Badge markup for the modal shell.
	 *
	 * @return string Trusted HTML.
	 */
	public static function markup(): string {
		$design = self::design();
		$text   = sprintf(
			'<span class="%s" data-wp-text="state.saleBadgeText"></span>',
			esc_attr( self::TEXT_CLASS )
		);

		if ( null === $design ) {
			return sprintf(
				'<span class="%1$s %1$s--default" data-wp-bind--hidden="state.hasNoSaleBadge" hidden>%2$s</span>',
				esc_attr( self::CONTAINER_CLASS ),
				$text
			);
		}

		$position = (string) ( $design['position'] ?? '' );
		if ( ! in_array( $position, Badge_Style_Schema::POSITIONS, true ) ) {
			$position = 'top-left';
		}

		// The label is a placeholder: the visible text is the slot, and it only
		// becomes the accessible name of an icon-only design, which the store
		// keeps in sync through the aria-label binding added below.
		$badge = new \WP_HTML_Tag_Processor(
			Product_Badges::render_badge(
				$design,
				Feature_Settings::get_sale_badge_no_discount_text(),
				array(),
				$text
			)
		);
		if ( $badge->next_tag() && 'img' === $badge->get_attribute( 'role' ) ) {
			$badge->set_attribute( 'data-wp-bind--aria-label', 'state.saleBadgeText' );
		}

		return sprintf(
			'<div class="%1$s aggressive-apparel-product-badge__wrapper aggressive-apparel-product-badge__wrapper--%2$s" data-wp-bind--hidden="state.hasNoSaleBadge" hidden>%3$s</div>',
			esc_attr( self::CONTAINER_CLASS ),
			esc_attr( $position ),
			$badge->get_updated_html()
		);
	}

	/**
	 * The Badge Studio sale design, when this theme draws the sale badge.
	 *
	 * @return array<string, mixed>|null Null when WooCommerce's sale flash is used instead.
	 */
	private static function design(): ?array {
		if ( ! Feature_Settings::is_enabled( 'product_badges' ) ) {
			return null;
		}

		if ( ! Custom_Badge_Taxonomy::get_badge_rules()['sale_enabled'] ) {
			return null;
		}

		return Custom_Badge_Taxonomy::get_system_badges()['sale'] ?? null;
	}
}
