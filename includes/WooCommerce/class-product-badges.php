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
		if ( ! $this->is_product_listing_page() ) {
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

		// Target the featured-image block inside a product template context.
		if ( 'core/post-featured-image' !== $block['blockName'] ) {
			return $block_content;
		}

		// Must be on a product-listing page.
		if ( ! $this->is_product_listing_page() ) {
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
	 * Build the combined badges HTML for a product.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string Badge markup or empty string.
	 */
	private function build_badges_html( \WC_Product $product ): string {
		$badges = array();

		// Sale percentage badge.
		if ( $product->is_on_sale() ) {
			$percentage = $this->get_sale_percentage( $product );
			if ( $percentage > 0 ) {
				$badges[] = sprintf(
					'<span class="aggressive-apparel-product-badge aggressive-apparel-product-badge--sale">-%d%%</span>',
					$percentage,
				);
			}
		}

		// New badge.
		if ( $this->is_new_product( $product ) ) {
			$badges[] = sprintf(
				'<span class="aggressive-apparel-product-badge aggressive-apparel-product-badge--new">%s</span>',
				esc_html__( 'New', 'aggressive-apparel' ),
			);
		}

		// Low stock badge.
		if ( $this->is_low_stock( $product ) ) {
			$badges[] = sprintf(
				'<span class="aggressive-apparel-product-badge aggressive-apparel-product-badge--low-stock">%s</span>',
				esc_html__( 'Low Stock', 'aggressive-apparel' ),
			);
		}

		// Bestseller badge.
		if ( $this->is_bestseller( $product ) ) {
			$badges[] = sprintf(
				'<span class="aggressive-apparel-product-badge aggressive-apparel-product-badge--bestseller">%s</span>',
				esc_html__( 'Bestseller', 'aggressive-apparel' ),
			);
		}

		if ( empty( $badges ) ) {
			return '';
		}

		return '<div class="aggressive-apparel-product-badge__wrapper">' . implode( '', $badges ) . '</div>';
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
	 * Determine if the current page is a product listing.
	 *
	 * @return bool
	 */
	private function is_product_listing_page(): bool {
		if ( ! function_exists( 'is_shop' ) ) {
			return false;
		}

		return is_shop() || is_product_category() || is_product_tag() || is_search();
	}
}
