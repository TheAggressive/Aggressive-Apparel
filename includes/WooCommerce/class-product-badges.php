<?php
/**
 * Product Badges Class
 *
 * Injects sale-percentage, "New", "Low Stock", and "Bestseller" badges
 * onto WooCommerce product cards via the render_block filter.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product Badges
 *
 * @since 1.17.0
 */
class Product_Badges {

	/**
	 * Block names that receive badge injection.
	 *
	 * @var array<int, string>
	 */
	private const TARGET_BLOCKS = array(
		'core/post-featured-image',
		'woocommerce/product-image',
	);

	/**
	 * Number of days a product is considered "new".
	 *
	 * @var int
	 */
	private int $new_days = 14;

	/**
	 * Low-stock threshold.
	 *
	 * @var int
	 */
	private int $low_stock_threshold = 5;

	/**
	 * Bestseller total-sales threshold.
	 *
	 * @var int
	 */
	private int $bestseller_threshold = 50;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'render_block', array( $this, 'inject_badges' ), 10, 2 );
		add_filter( 'render_block', array( $this, 'suppress_native_sale_badge' ), 10, 2 );
		add_filter( 'woocommerce_sale_flash', '__return_empty_string' );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		/**
		 * Filter the number of days a product is considered new.
		 *
		 * @param int $days Default 14.
		 */
		$this->new_days = (int) apply_filters( 'aggressive_apparel_badge_new_days', $this->new_days );

		/**
		 * Filter the low-stock threshold.
		 *
		 * @param int $threshold Default 5.
		 */
		$this->low_stock_threshold = (int) apply_filters( 'aggressive_apparel_badge_low_stock_threshold', $this->low_stock_threshold );

		/**
		 * Filter the bestseller total-sales threshold.
		 *
		 * @param int $threshold Default 50.
		 */
		$this->bestseller_threshold = (int) apply_filters( 'aggressive_apparel_badge_bestseller_threshold', $this->bestseller_threshold );
	}

	/**
	 * Enqueue badge styles on relevant pages only.
	 *
	 * @return void
	 */
	public function enqueue_styles(): void {
		if ( ! $this->is_product_page() ) {
			return;
		}

		$css_file = AGGRESSIVE_APPAREL_DIR . '/build/styles/woocommerce/product-badges.css';
		if ( ! file_exists( $css_file ) ) {
			return;
		}

		wp_enqueue_style(
			'aggressive-apparel-product-badges',
			AGGRESSIVE_APPAREL_URI . '/build/styles/woocommerce/product-badges.css',
			array(),
			(string) filemtime( $css_file ),
		);
	}

	/**
	 * Inject badges into product image blocks within product templates.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Block data.
	 * @return string Modified HTML.
	 */
	public function inject_badges( string $block_content, array $block ): string {
		if ( ! isset( $block['blockName'] ) ) {
			return $block_content;
		}

		// Target product image blocks (archives use core/post-featured-image,
		// single product related/cross-sells use woocommerce/product-image).
		if ( ! in_array( $block['blockName'], self::TARGET_BLOCKS, true ) ) {
			return $block_content;
		}

		// Must be on a page that displays products.
		if ( ! $this->is_product_page() ) {
			return $block_content;
		}

		$product = $this->get_current_product();
		if ( ! $product ) {
			return $block_content;
		}

		$badges_html = $this->build_badges_html( $product );
		if ( '' === $badges_html ) {
			return $block_content;
		}

		// Append badges before closing tag of the wrapper figure/div.
		return preg_replace(
			'/(<\/(?:figure|div)>\s*)$/i',
			$badges_html . '$1',
			$block_content,
			1,
		) ?? $block_content;
	}

	/**
	 * Suppress the native WooCommerce sale badge block.
	 *
	 * The custom badge system renders a sale-percentage badge (e.g. "-25%").
	 * The native "Sale!" badge from woocommerce/product-sale-badge is redundant.
	 *
	 * @param string               $block_content Rendered block HTML.
	 * @param array<string, mixed> $block         Block data.
	 * @return string Empty string for sale badge blocks, original content otherwise.
	 */
	public function suppress_native_sale_badge( string $block_content, array $block ): string {
		if ( isset( $block['blockName'] ) && 'woocommerce/product-sale-badge' === $block['blockName'] ) {
			return '';
		}
		return $block_content;
	}

	/**
	 * Build the combined badges HTML for a product.
	 *
	 * System badges (sale, new, low-stock, bestseller) are styled via their
	 * taxonomy term data and auto-applied based on product conditions.
	 * Custom badges render in their configured position. All badges within
	 * the same position group are sorted by priority.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string Badge markup or empty string.
	 */
	private function build_badges_html( \WC_Product $product ): string {
		$groups = array(
			'top-left'     => array(),
			'top-right'    => array(),
			'bottom-left'  => array(),
			'bottom-right' => array(),
		);

		// --- System badges (condition-based, styled via taxonomy term) ---
		$system_badges = Custom_Badge_Taxonomy::get_system_badges();
		$sale_pct      = $product->is_on_sale() ? $this->get_sale_percentage( $product ) : 0;

		$system_conditions = array(
			'sale'       => $sale_pct > 0,
			'new'        => $this->is_new_product( $product ),
			'low_stock'  => $this->is_low_stock( $product ),
			'bestseller' => $this->is_bestseller( $product ),
		);

		foreach ( $system_conditions as $type => $active ) {
			if ( ! $active || ! isset( $system_badges[ $type ] ) ) {
				continue;
			}

			$badge = $system_badges[ $type ];
			$label = 'sale' === $type ? sprintf( '-%d%%', $sale_pct ) : $badge['name'];

			$this->add_badge_to_group( $groups, $badge, $label );
		}

		// --- Custom badges (taxonomy-assigned to products) ---
		$custom_badges = Custom_Badge_Taxonomy::get_product_badges( $product->get_id() );
		foreach ( $custom_badges as $badge ) {
			// Skip system badges — they are applied by condition, not taxonomy.
			if ( 'custom' !== $badge['badge_type'] ) {
				continue;
			}

			$this->add_badge_to_group( $groups, $badge, $badge['name'] );
		}

		// Sort each position group by priority, then build output.
		$output = '';
		foreach ( $groups as $position => $badges ) {
			if ( empty( $badges ) ) {
				continue;
			}

			usort( $badges, fn( array $a, array $b ): int => $a['priority'] <=> $b['priority'] );

			$output .= sprintf(
				'<div class="aggressive-apparel-product-badge__wrapper aggressive-apparel-product-badge__wrapper--%s">%s</div>',
				esc_attr( $position ),
				implode( '', array_column( $badges, 'html' ) ),
			);
		}

		return $output;
	}

	/**
	 * Build a single badge <span> from badge data.
	 *
	 * @param array<string, mixed> $badge Badge data from get_badge_data().
	 * @param string               $label Display text.
	 * @return string Badge HTML.
	 */
	private function build_badge_span( array $badge, string $label ): string {
		$icon_html = Custom_Badge_Taxonomy::build_badge_icon_html(
			$badge['svg_icon'],
			$badge['library_icon'],
			$badge['icon'],
			$badge['icon_color'],
			$badge['icon_size'],
		);

		$style_parts = array(
			'--badge-bg:' . $badge['bg_color'],
			'--badge-text:' . $badge['text_color'],
		);

		if ( $badge['border_width'] > 0 && '' !== $badge['border_color'] && 'none' !== $badge['border_style'] ) {
			$style_parts[] = '--badge-border-width:' . $badge['border_width'] . 'px';
			$style_parts[] = '--badge-border-style:' . $badge['border_style'];
			$style_parts[] = '--badge-border-color:' . $badge['border_color'];
		}

		$style_parts[] = sprintf(
			'--badge-radius:%dpx %dpx %dpx %dpx',
			$badge['radius_tl'],
			$badge['radius_tr'],
			$badge['radius_br'],
			$badge['radius_bl'],
		);

		$style_parts[] = sprintf(
			'--badge-padding:%dpx %dpx',
			$badge['padding_y'],
			$badge['padding_x'],
		);

		return sprintf(
			'<span class="aggressive-apparel-product-badge aggressive-apparel-product-badge--custom" style="%s">%s%s</span>',
			esc_attr( implode( ';', $style_parts ) ),
			$icon_html,
			esc_html( $label ),
		);
	}

	/**
	 * Add a badge to the appropriate position group.
	 *
	 * @param array<string, array<int, array{priority: int, html: string}>> $groups Position groups (by reference).
	 * @param array<string, mixed>                                          $badge  Badge data.
	 * @param string                                                        $label  Display text.
	 * @return void
	 */
	private function add_badge_to_group( array &$groups, array $badge, string $label ): void {
		$pos              = isset( $groups[ $badge['position'] ] ) ? $badge['position'] : 'top-left';
		$groups[ $pos ][] = array(
			'priority' => $badge['priority'],
			'html'     => $this->build_badge_span( $badge, $label ),
		);
	}

	/**
	 * Calculate the sale discount percentage.
	 *
	 * @param \WC_Product $product Product object.
	 * @return int Percentage (0-100).
	 */
	private function get_sale_percentage( \WC_Product $product ): int {
		$regular = (float) $product->get_regular_price();
		$sale    = (float) $product->get_sale_price();

		if ( $regular <= 0 || $sale <= 0 ) {
			// Try variable product min prices.
			if ( $product instanceof \WC_Product_Variable ) {
				$regular = (float) $product->get_variation_regular_price( 'min' );
				$sale    = (float) $product->get_variation_sale_price( 'min' );
			}
		}

		if ( $regular <= 0 || $sale >= $regular ) {
			return 0;
		}

		return (int) round( ( ( $regular - $sale ) / $regular ) * 100 );
	}

	/**
	 * Check if product was published within the "new" window.
	 *
	 * @param \WC_Product $product Product object.
	 * @return bool
	 */
	private function is_new_product( \WC_Product $product ): bool {
		$date = $product->get_date_created();
		if ( ! $date ) {
			return false;
		}

		$diff = time() - $date->getTimestamp();
		return $diff < ( $this->new_days * DAY_IN_SECONDS );
	}

	/**
	 * Check if product stock is at or below the low-stock threshold.
	 *
	 * @param \WC_Product $product Product object.
	 * @return bool
	 */
	private function is_low_stock( \WC_Product $product ): bool {
		if ( ! $product->managing_stock() ) {
			return false;
		}

		$stock = $product->get_stock_quantity();
		return null !== $stock && $stock > 0 && $stock <= $this->low_stock_threshold;
	}

	/**
	 * Check if product total sales exceed the bestseller threshold.
	 *
	 * @param \WC_Product $product Product object.
	 * @return bool
	 */
	private function is_bestseller( \WC_Product $product ): bool {
		return (int) $product->get_total_sales() >= $this->bestseller_threshold;
	}

	/**
	 * Get the WC_Product for the current post in the loop.
	 *
	 * @return \WC_Product|null
	 */
	private function get_current_product(): ?\WC_Product {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$product = wc_get_product( get_the_ID() );
		return $product instanceof \WC_Product ? $product : null;
	}

	/**
	 * Determine if the current page displays products.
	 *
	 * Covers shop archives, category/tag pages, single product pages,
	 * cart (cross-sells), and search results.
	 *
	 * @return bool
	 */
	private function is_product_page(): bool {
		if ( ! function_exists( 'is_shop' ) ) {
			return false;
		}

		return is_shop() || is_product_category() || is_product_tag() || is_product() || is_cart() || is_search();
	}
}
