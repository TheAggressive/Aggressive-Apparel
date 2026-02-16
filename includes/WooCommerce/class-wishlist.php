<?php
/**
 * Wishlist Class
 *
 * Heart-icon toggle on product cards and single product pages.
 * All storage is handled client-side via localStorage for zero database impact.
 * Product details for the wishlist page are fetched from the public
 * WooCommerce Store API (read-only, no auth required).
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
 * Wishlist
 *
 * @since 1.17.0
 */
class Wishlist {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'render_block', array( $this, 'inject_heart_icon' ), 10, 2 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_single_heart' ), 6 );
		add_shortcode( 'aggressive_apparel_wishlist', array( $this, 'render_wishlist_page' ) );
	}

	/**
	 * Enqueue styles and register Interactivity API script module on relevant pages.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		$css_file = AGGRESSIVE_APPAREL_DIR . '/build/styles/woocommerce/wishlist.css';
		if ( ! file_exists( $css_file ) ) {
			return;
		}

		wp_enqueue_style(
			'aggressive-apparel-wishlist',
			AGGRESSIVE_APPAREL_URI . '/build/styles/woocommerce/wishlist.css',
			array(),
			(string) filemtime( $css_file ),
		);

		if ( function_exists( 'wp_register_script_module' ) ) {
			wp_register_script_module(
				'@aggressive-apparel/wishlist',
				AGGRESSIVE_APPAREL_URI . '/assets/interactivity/wishlist.js',
				array( '@wordpress/interactivity' ),
				AGGRESSIVE_APPAREL_VERSION,
			);
			wp_enqueue_script_module( '@aggressive-apparel/wishlist' );
		}

		// Provide the public Store API URL so the wishlist page can fetch product details.
		if ( function_exists( 'wp_interactivity_state' ) ) {
			wp_interactivity_state(
				'aggressive-apparel/wishlist',
				array(
					'productsApiUrl' => esc_url_raw( rest_url( 'wc/store/v1/products' ) ),
				),
			);
		}
	}

	/**
	 * Inject heart icon on product card images in archives.
	 *
	 * @param string $block_content Block HTML.
	 * @param array  $block         Block data.
	 * @return string Modified HTML.
	 */
	public function inject_heart_icon( string $block_content, array $block ): string {
		if ( ! isset( $block['blockName'] ) || 'core/post-featured-image' !== $block['blockName'] ) {
			return $block_content;
		}

		if ( ! $this->is_wc_context() ) {
			return $block_content;
		}

		$product_id = (int) get_the_ID();
		if ( $product_id <= 0 ) {
			return $block_content;
		}

		$button = $this->get_heart_button_html( $product_id );

		return preg_replace(
			'/(<\/(?:figure|div)>\s*)$/i',
			$button . '$1',
			$block_content,
			1,
		) ?? $block_content;
	}

	/**
	 * Render heart icon on single product pages (next to title area).
	 *
	 * @return void
	 */
	public function render_single_heart(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$product_id = (int) get_the_ID();
		if ( $product_id <= 0 ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML built with esc_ functions.
		echo $this->get_heart_button_html( $product_id, true );
	}

	/**
	 * Build heart button HTML with Interactivity API directives.
	 *
	 * @param int  $product_id Product ID.
	 * @param bool $large      Whether to use the large variant.
	 * @return string
	 */
	private function get_heart_button_html( int $product_id, bool $large = false ): string {
		$class = 'aggressive-apparel-wishlist__toggle';
		if ( $large ) {
			$class .= ' aggressive-apparel-wishlist__toggle--large';
		}

		$context = (string) wp_json_encode(
			array(
				'productId' => $product_id,
				'justAdded' => false,
			)
		);

		return sprintf(
			'<button type="button" class="%s" data-wp-interactive="aggressive-apparel/wishlist" data-wp-context=\'%s\' data-wp-on--click="actions.toggle" data-wp-class--is-active="state.isInWishlist" data-wp-class--is-beating="context.justAdded" data-wp-bind--aria-pressed="state.isInWishlist" aria-label="%s" title="%s">
				<svg class="aggressive-apparel-wishlist__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
			</button>',
			esc_attr( $class ),
			esc_attr( $context ),
			esc_attr__( 'Add to wishlist', 'aggressive-apparel' ),
			esc_attr__( 'Wishlist', 'aggressive-apparel' ),
		);
	}

	/**
	 * Render the wishlist shortcode output.
	 *
	 * Uses data-wp-each to iterate over wishlist products fetched client-side
	 * from the public WooCommerce Store API. The template element defines the
	 * markup for each product card.
	 *
	 * @return string Shortcode HTML.
	 */
	public function render_wishlist_page(): string {
		$context = (string) wp_json_encode(
			array(
				'loaded' => false,
			),
		);

		// phpcs:disable Generic.WhiteSpace.ScopeIndent -- Inline HTML concatenation.
		$html = '<div class="aggressive-apparel-wishlist-page"'
			. ' data-wp-interactive="aggressive-apparel/wishlist"'
			. ' data-wp-context=\'' . esc_attr( $context ) . '\''
			. ' data-wp-init="callbacks.loadWishlistPage">';

		// Product grid — rendered via data-wp-each over state.wishlistProducts.
		$html .= '<div class="aggressive-apparel-wishlist-page__grid"'
			. ' data-wp-bind--hidden="!context.loaded"'
			. ' hidden>';

		$html .= '<template data-wp-each="state.wishlistProducts">';
		$html .= '<div class="aggressive-apparel-wishlist-page__item">';
		$html .= '<a class="aggressive-apparel-wishlist-page__item-link" data-wp-bind--href="context.item.permalink">';
		$html .= '<img class="aggressive-apparel-wishlist-page__item-image no-lazy" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" alt=""'
			. ' data-wp-watch="callbacks.syncItemImage" />';
		$html .= '</a>';
		$html .= '<a class="aggressive-apparel-wishlist-page__item-name" data-wp-bind--href="context.item.permalink" data-wp-text="context.item.name"></a>';
		$html .= '<span class="aggressive-apparel-wishlist-page__item-price" data-wp-text="context.item.price"></span>';
		$html .= '</div>';
		$html .= '</template>';

		$html .= '</div>';

		// Empty state.
		$html .= '<p class="aggressive-apparel-wishlist-page__empty"'
			. ' data-wp-bind--hidden="state.hasWishlistItems">'
			. esc_html__( 'Your wishlist is empty.', 'aggressive-apparel' )
			. '</p>';

		// Loading state.
		$html .= '<p class="aggressive-apparel-wishlist-page__loading"'
			. ' data-wp-bind--hidden="context.loaded">'
			. esc_html__( 'Loading wishlist…', 'aggressive-apparel' )
			. '</p>';

		$html .= '</div>';
		// phpcs:enable Generic.WhiteSpace.ScopeIndent

		return $html;
	}

	/**
	 * Check if we are on a WooCommerce context page.
	 *
	 * @return bool
	 */
	private function is_wc_context(): bool {
		if ( ! function_exists( 'is_shop' ) ) {
			return false;
		}
		return is_shop() || is_product_category() || is_product_tag() || is_search() || is_product();
	}
}
