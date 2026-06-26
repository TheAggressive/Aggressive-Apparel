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

use Aggressive_Apparel\Assets\Asset_Loader;

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
		Asset_Loader::enqueue_feature_style(
			'aggressive-apparel-load-more',
			'build/styles/woocommerce/load-more'
		);

		Asset_Loader::enqueue_interactivity_module(
			'@aggressive-apparel/load-more',
			'build/interactivity/load-more'
		);
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

		$mode = Feature_Settings::get_load_more_mode();

		$load_more_html = '<div class="aa-load-more aggressive-apparel-stack aggressive-apparel-stack--lg aggressive-apparel-stack--center" data-wp-interactive="aggressive-apparel/load-more" data-wp-init="callbacks.init">';

		// Status text.
		$load_more_html .= '<div class="aa-load-more__status">';
		$load_more_html .= '<span class="aa-load-more__count" data-wp-text="state.statusText"></span>';
		$load_more_html .= '</div>';

		// Load More button (hidden in infinite scroll mode or when all loaded).
		$load_more_html .= sprintf(
			'<button class="aa-load-more__btn aggressive-apparel-button aggressive-apparel-button--outline wp-element-button" data-wp-on--click="actions.loadMore"'
			. ' data-wp-bind--hidden="state.hideButton"'
			// aria-disabled (not the disabled attribute) so the button keeps
			// keyboard focus while loading; the loadMore action already no-ops
			// when isLoading, preventing duplicate requests.
			. ' data-wp-bind--aria-disabled="state.isLoading"'
			. ' data-wp-class--is-loading="state.isLoading"'
			. ' data-wp-bind--aria-busy="state.isLoading">%s</button>',
			esc_html( Feature_Settings::get_load_more_button_text() )
		);

		// Loading spinner (purely visual; aria-hidden). The loading status is
		// announced to assistive tech via the live region below. In button mode
		// the button hides while a page loads and this replaces it; in
		// infinite-scroll mode it's the only on-screen "more loading" cue.
		$load_more_html .= '<div class="aa-load-more__loading" data-wp-bind--hidden="!state.showSentinelLoader" aria-hidden="true">'
			. '<span class="aa-load-more__spinner"></span>'
			. '</div>';

		// Infinite scroll sentinel.
		if ( 'infinite_scroll' === $mode ) {
			$load_more_html .= '<div class="aa-load-more__sentinel" data-wp-bind--hidden="state.hideSentinel" aria-hidden="true"></div>';
		}

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

		$mode           = Feature_Settings::get_load_more_mode();
		$per_page       = (int) get_option( 'posts_per_page', 12 );
		$total_products = (int) ( $wp_query->found_posts ?? 0 );
		$total_pages    = (int) ( $wp_query->max_num_pages ?? 1 );

		// Current taxonomy term archive (category, tag, brand or attribute) so the
		// REST endpoint can reproduce the same query when loading further pages.
		$term_archive = Product_Context::get_current_product_term_archive();

		wp_interactivity_state(
			'aggressive-apparel/load-more',
			array(
				'mode'            => $mode,
				'restBase'        => esc_url_raw( rest_url( 'aggressive-apparel/v1/products/rendered' ) ),
				'templateSlug'    => $this->get_current_template_slug(),
				'perPage'         => $per_page,
				'currentPage'     => 1,
				'totalPages'      => $total_pages,
				'totalProducts'   => $total_products,
				'loadedCount'     => min( $per_page, $total_products ),
				'isLoading'       => false,
				'allLoaded'       => $total_pages <= 1,
				'announcement'    => '',
				'loadingText'     => __( 'Loading more products…', 'aggressive-apparel' ),
				/* translators: 1: number loaded so far, 2: total products. */
				'statusFormat'    => __( 'Showing %1$d of %2$d products', 'aggressive-apparel' ),
				/* translators: %d: number of products just loaded. */
				'loadedFormat'    => __( '%d more products loaded.', 'aggressive-apparel' ),
				'currentTaxonomy' => $term_archive['taxonomy'],
				'currentTerm'     => $term_archive['term'],
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
		// Shop, plus any product taxonomy archive — category, tag, brand and
		// product-attribute (pa_*) archives all render a product grid.
		return Product_Context::is_product_archive()
			|| ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() );
	}

	/**
	 * Return the FSE template slug used to render the product grid.
	 *
	 * Only product categories have a dedicated template; tag, brand and
	 * attribute archives fall through WordPress's hierarchy to archive-product.
	 *
	 * @return string
	 */
	private function get_current_template_slug(): string {
		if ( function_exists( 'is_product_category' ) && is_product_category() ) {
			return 'taxonomy-product_cat';
		}
		return 'archive-product';
	}
}
