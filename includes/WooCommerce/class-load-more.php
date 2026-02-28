<?php
/**
 * Load More / Infinite Scroll Class
 *
 * Replaces standard pagination with a Load More button or infinite scroll.
 *
 * @package Aggressive_Apparel
 * @since 1.51.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load More
 *
 * Replaces the standard query-pagination block on shop archives with
 * a Load More button or IntersectionObserver-based infinite scroll.
 * Coordinates with product-filters via custom events when AJAX
 * filtering is active.
 *
 * @since 1.51.0
 */
class Load_More {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'render_block', array( $this, 'replace_pagination' ), 20, 2 );
		add_action( 'wp_footer', array( $this, 'output_interactivity_state' ) );
	}

	/**
	 * Enqueue CSS and register JS module.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		$css_file = AGGRESSIVE_APPAREL_DIR . '/build/styles/woocommerce/load-more.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'aggressive-apparel-load-more',
				AGGRESSIVE_APPAREL_URI . '/build/styles/woocommerce/load-more.css',
				array(),
				(string) filemtime( $css_file ),
			);
		}

		if ( function_exists( 'wp_register_script_module' ) ) {
			wp_register_script_module(
				'@aggressive-apparel/load-more',
				AGGRESSIVE_APPAREL_URI . '/assets/interactivity/load-more.js',
				array( '@wordpress/interactivity' ),
				AGGRESSIVE_APPAREL_VERSION,
			);
			wp_enqueue_script_module( '@aggressive-apparel/load-more' );
		}
	}

	/**
	 * Replace pagination block with Load More UI on shop archives.
	 *
	 * @param string               $block_content Rendered block HTML.
	 * @param array<string, mixed> $block         Block attributes.
	 * @return string Modified block HTML.
	 */
	public function replace_pagination( string $block_content, array $block ): string {
		if ( 'core/query-pagination' !== ( $block['blockName'] ?? '' ) ) {
			return $block_content;
		}

		if ( ! $this->is_shop_page() ) {
			return $block_content;
		}

		$mode = get_option( Feature_Settings::LOAD_MORE_MODE_OPTION, 'load_more' );

		$load_more_html = '<div class="aa-load-more" data-wp-interactive="aggressive-apparel/load-more" data-wp-init="callbacks.init">';

		// Status text.
		$load_more_html .= '<div class="aa-load-more__status" data-wp-bind--hidden="state.allLoaded">';
		$load_more_html .= '<span class="aa-load-more__count" data-wp-text="state.statusText"></span>';
		$load_more_html .= '</div>';

		// Load More button (hidden in infinite scroll mode or when all loaded).
		$load_more_html .= sprintf(
			'<button class="aa-load-more__btn" data-wp-on--click="actions.loadMore"'
			. ' data-wp-bind--hidden="state.hideButton"'
			. ' data-wp-bind--disabled="state.isLoading"'
			. ' data-wp-class--is-loading="state.isLoading"'
			. ' data-wp-bind--aria-busy="state.isLoading">%s</button>',
			esc_html__( 'Load More Products', 'aggressive-apparel' )
		);

		// Infinite scroll sentinel.
		if ( 'infinite_scroll' === $mode ) {
			$load_more_html .= '<div class="aa-load-more__sentinel" data-wp-bind--hidden="state.hideSentinel" aria-hidden="true"></div>';
		}

		// All loaded message.
		$load_more_html .= sprintf(
			'<p class="aa-load-more__all-loaded" data-wp-bind--hidden="state.notAllLoaded">%s</p>',
			esc_html__( 'All products loaded', 'aggressive-apparel' )
		);

		// Screen reader announcements.
		$load_more_html .= '<div class="aa-load-more__announcer screen-reader-text" role="status" aria-live="polite" data-wp-text="state.announcement"></div>';

		$load_more_html .= '</div>';

		return $load_more_html;
	}

	/**
	 * Output interactivity state in the footer.
	 *
	 * @return void
	 */
	public function output_interactivity_state(): void {
		if ( ! $this->is_shop_page() ) {
			return;
		}

		if ( ! function_exists( 'wp_interactivity_state' ) ) {
			return;
		}

		global $wp_query;

		$mode           = get_option( Feature_Settings::LOAD_MORE_MODE_OPTION, 'load_more' );
		$per_page       = (int) get_option( 'posts_per_page', 12 );
		$total_products = (int) ( $wp_query->found_posts ?? 0 );
		$total_pages    = (int) ( $wp_query->max_num_pages ?? 1 );

		// Detect current category for SSR mode API calls.
		$current_cat_slug = '';
		if ( is_product_category() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$current_cat_slug = $term->slug;
			}
		}

		wp_interactivity_state(
			'aggressive-apparel/load-more',
			array(
				'mode'            => $mode,
				'restBase'        => esc_url_raw( rest_url( 'wc/store/v1/products' ) ),
				'perPage'         => $per_page,
				'currentPage'     => 1,
				'totalPages'      => $total_pages,
				'totalProducts'   => $total_products,
				'loadedCount'     => min( $per_page, $total_products ),
				'isLoading'       => false,
				'allLoaded'       => $total_pages <= 1,
				'announcement'    => '',
				'currentCategory' => $current_cat_slug,
				'filtersActive'   => false,
			)
		);
	}

	/**
	 * Check if the current page is a shop archive.
	 *
	 * @return bool
	 */
	private function is_shop_page(): bool {
		if ( ! function_exists( 'is_shop' ) ) {
			return false;
		}

		return is_shop() || is_product_category() || is_product_tag();
	}
}
