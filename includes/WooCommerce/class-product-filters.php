<?php
/**
 * Product Filters Class
 *
 * AJAX product filters with categories, color swatches, sizes, price range,
 * and stock status. Supports drawer, sidebar, and horizontal bar layouts.
 *
 * @package Aggressive_Apparel
 * @since 1.22.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Core\Icons;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product Filters
 *
 * @since 1.22.0
 */
class Product_Filters {

	/**
	 * Transient cache key prefix.
	 *
	 * @var string
	 */
	private const CACHE_PREFIX = 'aa_pf_';

	/**
	 * Cache TTL in seconds (15 minutes).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 900;

	/**
	 * Whether assets are enqueued (gate for render_block / wp_footer output).
	 *
	 * @var bool
	 */
	private bool $assets_enqueued = false;

	/**
	 * Active layout.
	 *
	 * @var string
	 */
	private string $layout = 'drawer';

	/**
	 * Cached filter data.
	 *
	 * @var array|null
	 */
	private ?array $filter_data = null;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->layout = $this->get_layout();

		// Register script module early so it's included in the wp_head import map.
		// Enqueuing happens later in ensure_assets() when we know we're on a shop page.
		add_action( 'wp_enqueue_scripts', array( $this, 'register_script_module' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'render_block', array( $this, 'inject_filter_ui' ), 10, 2 );
		add_action( 'wp_footer', array( $this, 'render_drawer_shell' ) );
		add_filter( 'body_class', array( $this, 'add_body_class' ) );

		// Invalidate the filter data cache when products or attributes change.
		add_action( 'created_term', array( $this, 'flush_cache' ) );
		add_action( 'edited_term', array( $this, 'flush_cache' ) );
		add_action( 'delete_term', array( $this, 'flush_cache' ) );
		add_action( 'woocommerce_update_product', array( $this, 'flush_cache' ) );
		add_action( 'woocommerce_new_product', array( $this, 'flush_cache' ) );
	}

	/**
	 * Delete the cached filter data transient.
	 *
	 * @return void
	 */
	public function flush_cache(): void {
		delete_transient( self::CACHE_PREFIX . 'data' );
	}

	/**
	 * Register the script module early so it appears in the wp_head import map.
	 *
	 * Must run during wp_enqueue_scripts (which fires inside wp_head) rather
	 * than during render_block, because the import map is printed in wp_head
	 * and any modules registered after that are not included.
	 *
	 * @return void
	 */
	public function register_script_module(): void {
		// Enqueue CSS unconditionally — all selectors are scoped to
		// .aa-product-filters__* so there is zero visual impact on
		// non-shop pages. This avoids the is_shop_page() timing issue
		// where WooCommerce conditional tags may not be available yet.
		$css_file = AGGRESSIVE_APPAREL_DIR . '/build/styles/woocommerce/product-filters.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'aggressive-apparel-product-filters',
				AGGRESSIVE_APPAREL_URI . '/build/styles/woocommerce/product-filters.css',
				array(),
				(string) filemtime( $css_file ),
			);
		}

		if ( ! function_exists( 'wp_register_script_module' ) ) {
			return;
		}

		wp_register_script_module(
			'@aggressive-apparel/product-filters',
			AGGRESSIVE_APPAREL_URI . '/assets/interactivity/product-filters.js',
			array( '@wordpress/interactivity', '@aggressive-apparel/scroll-lock', '@aggressive-apparel/helpers' ),
			AGGRESSIVE_APPAREL_VERSION,
		);

		// Enqueue unconditionally so the module appears in the wp_head import
		// map. On non-shop pages the JS loads but does nothing because no
		// data-wp-interactive directive exists in the HTML.
		wp_enqueue_script_module( '@aggressive-apparel/product-filters' );
	}

	/**
	 * Enqueue filter assets on shop pages.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! $this->is_shop_page() ) {
			return;
		}

		$this->ensure_assets();
	}

	/**
	 * Enqueue CSS, JS, and interactivity state (idempotent).
	 *
	 * Called from wp_enqueue_scripts AND lazily from render_block,
	 * because in block themes render_block fires before wp_enqueue_scripts.
	 *
	 * @return void
	 */
	private function ensure_assets(): void {
		if ( $this->assets_enqueued ) {
			return;
		}
		$this->assets_enqueued = true;

		// CSS and JS module are enqueued unconditionally in
		// register_script_module(). Only the interactivity state
		// needs to be loaded conditionally on shop pages.

		// Output interactivity state.
		if ( function_exists( 'wp_interactivity_state' ) ) {
			$data             = $this->gather_filter_data();
			$current_cat_slug = '';

			if ( is_product_category() ) {
				$term = get_queried_object();
				if ( $term instanceof \WP_Term ) {
					$current_cat_slug = $term->slug;
				}
			}

			wp_interactivity_state(
				'aggressive-apparel/product-filters',
				array(
					'restBase'             => esc_url_raw( rest_url( 'wc/store/v1/products' ) ),
					'layout'               => $this->layout,
					'perPage'              => 12,
					'currentCategorySlug'  => $current_cat_slug,
					'categories'           => $data['categories'],
					'colorTerms'           => $data['colorTerms'],
					'sizeTerms'            => $data['sizeTerms'],
					'priceRange'           => array_merge(
						$data['priceRange'],
						array(
							'currencyPrefix' => html_entity_decode(
								$data['priceRange']['currencyPrefix'] ?? '$',
								ENT_QUOTES,
								'UTF-8'
							),
						)
					),
					'stockStatuses'        => $data['stockStatuses'],
					'categoryAttributeMap' => $data['categoryAttributeMap'],
					'selectedCategories'   => array(),
					'selectedColors'       => array(),
					'selectedSizes'        => array(),
					'priceMin'             => $data['priceRange']['min'],
					'priceMax'             => $data['priceRange']['max'],
					'inStockOnly'          => false,
					'orderBy'              => 'date',
					'orderDir'             => 'desc',
					'isDrawerOpen'         => false,
					'isLoading'            => false,
					'hasError'             => false,
					'currentPage'          => 1,
					'totalPages'           => 1,
					'totalProducts'        => 0,
					'products'             => array(),
					'announcement'         => '',
					'openDropdown'         => '',
				)
			);
		}
	}

	/**
	 * Inject filter UI into blocks via render_block filter.
	 *
	 * @param string $block_content Block HTML.
	 * @param array  $block         Block data.
	 * @return string Modified HTML.
	 */
	public function inject_filter_ui( string $block_content, array $block ): string {
		if ( ! isset( $block['blockName'] ) || ! $this->is_shop_page() ) {
			return $block_content;
		}

		try {
			// Lazily enqueue assets on first matching block (render_block fires before wp_enqueue_scripts in block themes).
			$this->ensure_assets();

			// Inject filter toggle button before the catalog sorting dropdown.
			if ( 'woocommerce/catalog-sorting' === $block['blockName'] ) {
				$mobile_class = 'drawer' === $this->layout ? '' : ' aa-product-filters__trigger--mobile-only';
				$button       = sprintf(
					'<button class="aa-product-filters__trigger%s" data-wp-interactive="aggressive-apparel/product-filters" data-wp-on--click="actions.openDrawer" aria-label="%s">%s<span class="aa-product-filters__trigger-label">%s</span><span class="aa-product-filters__trigger-count" data-wp-text="state.activeFilterCount" data-wp-bind--hidden="state.hasNoActiveFilters" hidden></span></button>',
					esc_attr( $mobile_class ),
					esc_attr__( 'Open product filters', 'aggressive-apparel' ),
					Icons::get(
						'filter',
						array(
							'width'       => 20,
							'height'      => 20,
							'aria-hidden' => 'true',
						)
					),
					esc_html__( 'Filter', 'aggressive-apparel' ),
				);

				return $button . $block_content;
			}

			// Wrap product-collection with AJAX grid container.
			if ( 'woocommerce/product-collection' === $block['blockName'] ) {
				return $this->wrap_product_collection( $block_content );
			}
		} catch ( \Throwable $e ) {
			// Return original block content on error to avoid breaking the page.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Product Filters inject_filter_ui error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}

		return $block_content;
	}

	/**
	 * Render the drawer shell in wp_footer.
	 *
	 * @return void
	 */
	public function render_drawer_shell(): void {
		if ( ! $this->assets_enqueued || ! $this->is_shop_page() ) {
			return;
		}

		try {
			$data = $this->gather_filter_data();
			?>
			<div
				id="aa-product-filters-drawer"
				class="aa-product-filters__drawer"
				data-wp-interactive="aggressive-apparel/product-filters"
				data-wp-class--is-open="state.isDrawerOpen"
				hidden
				role="dialog"
				aria-modal="true"
				aria-label="<?php esc_attr_e( 'Product filters', 'aggressive-apparel' ); ?>"
			>
				<div
					class="aa-product-filters__drawer-backdrop"
					data-wp-on--click="actions.closeDrawer"
				></div>
				<div class="aa-product-filters__drawer-panel">
					<div class="aa-product-filters__drawer-header">
						<h2 class="aa-product-filters__drawer-title">
							<?php esc_html_e( 'Filters', 'aggressive-apparel' ); ?>
						</h2>
						<button
							class="aa-product-filters__close"
							data-wp-on--click="actions.closeDrawer"
							aria-label="<?php esc_attr_e( 'Close filters', 'aggressive-apparel' ); ?>"
						>
							<?php
							Icons::render(
								'close',
								array(
									'width'       => 20,
									'height'      => 20,
									'aria-hidden' => 'true',
								)
							);
							?>
						</button>
					</div>

					<div class="aa-product-filters__drawer-body">
						<?php $this->render_filter_sections( $data ); ?>
					</div>

					<div class="aa-product-filters__drawer-footer">
						<button
							class="aa-product-filters__clear-btn"
							data-wp-on--click="actions.clearAllFilters"
							data-wp-bind--hidden="state.hasNoActiveFilters"
						>
							<?php esc_html_e( 'Clear All', 'aggressive-apparel' ); ?>
						</button>
						<button
							class="aa-product-filters__apply-btn"
							data-wp-on--click="actions.closeDrawer"
						>
							<?php esc_html_e( 'View Results', 'aggressive-apparel' ); ?>
						</button>
					</div>
				</div>
			</div>
			<?php
		} catch ( \Throwable $e ) {
			// Prevent a crash here from killing all subsequent wp_footer hooks.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Product Filters drawer error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
	}

	/**
	 * Add body classes for filter layout.
	 *
	 * @param array $classes Existing body classes.
	 * @return array Modified classes.
	 */
	public function add_body_class( array $classes ): array {
		if ( $this->is_shop_page() ) {
			$classes[] = 'has-product-filters';
			$classes[] = 'product-filters--' . $this->layout;
		}
		return $classes;
	}

	/**
	 * Wrap the product-collection block with AJAX grid container.
	 *
	 * @param string $block_content Original block HTML.
	 * @return string Wrapped HTML.
	 */
	private function wrap_product_collection( string $block_content ): string {
		$sidebar_html = '';

		// For sidebar layout, render the inline sidebar.
		if ( 'sidebar' === $this->layout ) {
			$data          = $this->gather_filter_data();
			$sidebar_html  = '<aside class="aa-product-filters__sidebar" data-wp-interactive="aggressive-apparel/product-filters" aria-label="' . esc_attr__( 'Product filters', 'aggressive-apparel' ) . '">';
			$sidebar_html .= '<div class="aa-product-filters__sidebar-inner">';

			ob_start();
			$this->render_filter_sections( $data );
			$sidebar_html .= ob_get_clean();

			$sidebar_html .= '<button class="aa-product-filters__clear-btn aa-product-filters__clear-btn--sidebar" data-wp-on--click="actions.clearAllFilters" data-wp-bind--hidden="state.hasNoActiveFilters">';
			$sidebar_html .= esc_html__( 'Clear All Filters', 'aggressive-apparel' );
			$sidebar_html .= '</button>';
			$sidebar_html .= '</div></aside>';
		}

		// For horizontal layout, render the filter bar.
		$bar_html = '';
		if ( 'horizontal' === $this->layout ) {
			$bar_html = $this->build_horizontal_bar();
		}

		$grid_class = 'sidebar' === $this->layout ? ' aa-product-filters__grid-wrapper--sidebar' : '';

		$output = sprintf(
			'<div class="aa-product-filters aa-product-filters--%s" data-wp-interactive="aggressive-apparel/product-filters" data-wp-init="callbacks.init" data-wp-on-window--keydown="actions.handleKeydown" data-wp-on-document--click="actions.handleClickOutside">',
			esc_attr( $this->layout ),
		);

		$output .= $bar_html;

		// Active filter pills bar (visible when filters are active).
		$output .= '<div class="aa-product-filters__active-bar" data-wp-bind--hidden="state.hasNoActiveFilters">';
		$output .= '<div class="aa-product-filters__pills" data-wp-ignore>';
		$output .= '</div>';
		$output .= '<button class="aa-product-filters__clear-all" data-wp-on--click="actions.clearAllFilters">' . esc_html__( 'Clear All', 'aggressive-apparel' ) . '</button>';
		$output .= '</div>';

		$output .= '<div class="aa-product-filters__grid-wrapper' . esc_attr( $grid_class ) . '">';

		$output .= $sidebar_html;

		$output .= '<div class="aa-product-filters__main">';

		// Original SSR grid — hidden when filters are active.
		$output .= '<div class="aa-product-filters__ssr-grid" data-wp-bind--hidden="state.hasActiveFilters">';
		$output .= $block_content;
		$output .= '</div>';

		// AJAX grid — shown when filters are active.
		$output .= '<div class="aa-product-filters__ajax-grid" data-wp-bind--hidden="state.hasNoActiveFilters">';

		// Loading skeleton.
		$output .= '<div class="aa-product-filters__skeleton" data-wp-bind--hidden="state.isNotLoading" aria-hidden="true">';
		for ( $i = 0; $i < 6; $i++ ) {
			$output .= '<div class="aa-product-filters__skeleton-card" role="presentation"><div class="aa-product-filters__skeleton-image"></div><div class="aa-product-filters__skeleton-title"></div><div class="aa-product-filters__skeleton-price"></div></div>';
		}
		$output .= '</div>';

		// Product grid (populated by JS via innerHTML).
		$output .= '<div class="aa-product-filters__products" data-wp-bind--hidden="state.isLoading" data-wp-ignore>';
		$output .= '</div>';

		// No results.
		$output .= '<div class="aa-product-filters__no-results" data-wp-bind--hidden="state.hasProducts">';
		$output .= '<p>' . esc_html__( 'No products were found matching your selection.', 'aggressive-apparel' ) . '</p>';
		$output .= '</div>';

		// Pagination.
		$output .= '<nav class="aa-product-filters__pagination" data-wp-bind--hidden="state.hasSinglePage" aria-label="' . esc_attr__( 'Filtered products pagination', 'aggressive-apparel' ) . '" data-wp-ignore>';
		$output .= '</nav>';

		$output .= '</div>'; // End .aa-product-filters__ajax-grid.

		$output .= '</div>'; // End .aa-product-filters__main.
		$output .= '</div>'; // End .aa-product-filters__grid-wrapper.

		// Screen reader announcer.
		$output .= '<div class="aa-product-filters__announcer screen-reader-text" role="status" aria-live="polite" data-wp-text="state.announcement"></div>';

		$output .= '</div>'; // End .aa-product-filters.

		return $output;
	}

	/**
	 * Build the horizontal filter bar HTML.
	 *
	 * @return string Bar HTML.
	 */
	private function build_horizontal_bar(): string {
		$data = $this->gather_filter_data();

		$bar = '<div class="aa-product-filters__bar">';

		// Category dropdown.
		if ( ! empty( $data['categories'] ) ) {
			$bar .= $this->build_bar_dropdown(
				'categories',
				__( 'Category', 'aggressive-apparel' ),
				'state.isCategoryDropdownOpen',
			);
		}

		// Color dropdown.
		if ( ! empty( $data['colorTerms'] ) ) {
			$bar .= $this->build_bar_dropdown(
				'colors',
				__( 'Color', 'aggressive-apparel' ),
				'state.isColorDropdownOpen',
			);
		}

		// Size dropdown.
		if ( ! empty( $data['sizeTerms'] ) ) {
			$bar .= $this->build_bar_dropdown(
				'sizes',
				__( 'Size', 'aggressive-apparel' ),
				'state.isSizeDropdownOpen',
			);
		}

		// Price dropdown.
		$bar .= $this->build_bar_dropdown(
			'price',
			__( 'Price', 'aggressive-apparel' ),
			'state.isPriceDropdownOpen',
		);

		// Stock dropdown.
		$bar .= $this->build_bar_dropdown(
			'stock',
			__( 'Availability', 'aggressive-apparel' ),
			'state.isStockDropdownOpen',
		);

		$bar .= '</div>';

		return $bar;
	}

	/**
	 * Build a single horizontal bar dropdown trigger.
	 *
	 * @param string $id    Dropdown identifier.
	 * @param string $label Display label.
	 * @param string $state State getter for open/close.
	 * @return string Dropdown trigger HTML.
	 */
	private function build_bar_dropdown( string $id, string $label, string $state ): string {
		$html  = sprintf(
			'<div class="aa-product-filters__bar-item" data-wp-context=\'{"dropdownId":"%s"}\'>',
			esc_attr( $id ),
		);
		$html .= sprintf(
			'<button class="aa-product-filters__bar-trigger" data-wp-on--click="actions.toggleDropdown" aria-expanded="false" data-wp-bind--aria-expanded="%s">',
			esc_attr( $state ),
		);
		$html .= esc_html( $label );
		$html .= Icons::get(
			'chevron-down',
			array(
				'width'       => 16,
				'height'      => 16,
				'aria-hidden' => 'true',
			)
		);
		$html .= '</button>';
		$html .= sprintf(
			'<div class="aa-product-filters__bar-dropdown" data-wp-bind--hidden="!%s" data-wp-ignore>',
			esc_attr( $state ),
		);
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Render shared filter sections HTML.
	 *
	 * @param array $data Filter data from gather_filter_data().
	 * @return void
	 */
	private function render_filter_sections( array $data ): void {
		// Categories (hierarchical).
		if ( ! empty( $data['categories'] ) ) {
			$tree = $this->build_category_tree( $data['categories'] );
			if ( ! empty( $tree ) ) {
				$this->render_section_start( 'categories', __( 'Categories', 'aggressive-apparel' ) );
				$this->render_category_tree( $tree );
				$this->render_section_end();
			}
		}

		// Colors.
		if ( ! empty( $data['colorTerms'] ) ) {
			$this->render_section_start( 'colors', __( 'Color', 'aggressive-apparel' ) );
			echo '<div class="aa-product-filters__color-list" role="group" aria-label="' . esc_attr__( 'Filter by color', 'aggressive-apparel' ) . '">';
			foreach ( $data['colorTerms'] as $color ) {
				if ( 'pattern' === $color['type'] ) {
					$style = sprintf( 'background-image:url(%s);background-size:cover;', esc_url( $color['value'] ) );
				} else {
					$style = sprintf(
						'background-color:%s;--swatch-color:%s;',
						esc_attr( $color['value'] ),
						esc_attr( $color['value'] ),
					);
				}

				printf(
					'<button class="aa-product-filters__color-swatch" data-wp-on--click="actions.toggleColor" data-filter-value="%s" style="%s" title="%s" aria-label="%s" aria-pressed="false"><span class="screen-reader-text">%s</span></button>',
					esc_attr( $color['slug'] ),
					esc_attr( $style ),
					esc_attr( $color['name'] ),
					/* translators: %s: color name */
					esc_attr( sprintf( __( 'Filter by %s', 'aggressive-apparel' ), $color['name'] ) ),
					esc_html( $color['name'] ),
				);
			}
			echo '</div>';
			$this->render_section_end();
		}

		// Sizes.
		if ( ! empty( $data['sizeTerms'] ) ) {
			$this->render_section_start( 'sizes', __( 'Size', 'aggressive-apparel' ) );
			echo '<div class="aa-product-filters__size-list" role="group" aria-label="' . esc_attr__( 'Filter by size', 'aggressive-apparel' ) . '">';
			foreach ( $data['sizeTerms'] as $size ) {
				printf(
					'<button class="aa-product-filters__size-chip" data-wp-on--click="actions.toggleSize" data-filter-value="%s" aria-pressed="false" aria-label="%s"><span class="aa-product-filters__size-chip-check" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><polyline points="2.5 6.5 5 9 9.5 3.5"/></svg></span><span class="aa-product-filters__size-chip-name">%s</span></button>',
					esc_attr( $size['slug'] ),
					/* translators: %s: size name */
					esc_attr( sprintf( __( 'Filter by size %s', 'aggressive-apparel' ), $size['name'] ) ),
					esc_html( $size['name'] ),
				);
			}
			echo '</div>';
			$this->render_section_end();
		}

		// Price range.
		$range = $data['priceRange'];
		if ( $range['max'] > $range['min'] ) {
			$this->render_section_start( 'price', __( 'Price', 'aggressive-apparel' ) );
			printf(
				'<div class="aa-product-filters__price-slider" data-min="%d" data-max="%d">',
				(int) $range['min'],
				(int) $range['max'],
			);
			echo '<div class="aa-product-filters__price-track"><div class="aa-product-filters__price-range"></div></div>';
			echo '<span class="aa-product-filters__price-tooltip aa-product-filters__price-tooltip--min" data-wp-text="state.priceMinDisplay" aria-hidden="true"></span>';
			echo '<span class="aa-product-filters__price-tooltip aa-product-filters__price-tooltip--max" data-wp-text="state.priceMaxDisplay" aria-hidden="true"></span>';
			printf(
				'<input type="range" class="aa-product-filters__price-thumb aa-product-filters__price-thumb--min" min="%d" max="%d" step="1" value="%d" data-wp-on--input="actions.setPriceMin" aria-label="%s" aria-valuemin="%d" aria-valuemax="%d" aria-valuenow="%d" />',
				(int) $range['min'],
				(int) $range['max'],
				(int) $range['min'],
				esc_attr__( 'Minimum price', 'aggressive-apparel' ),
				(int) $range['min'],
				(int) $range['max'],
				(int) $range['min'],
			);
			printf(
				'<input type="range" class="aa-product-filters__price-thumb aa-product-filters__price-thumb--max" min="%d" max="%d" step="1" value="%d" data-wp-on--input="actions.setPriceMax" aria-label="%s" aria-valuemin="%d" aria-valuemax="%d" aria-valuenow="%d" />',
				(int) $range['min'],
				(int) $range['max'],
				(int) $range['max'],
				esc_attr__( 'Maximum price', 'aggressive-apparel' ),
				(int) $range['min'],
				(int) $range['max'],
				(int) $range['max'],
			);
			echo '</div>';
			$this->render_section_end();
		}

		// Stock status.
		$this->render_section_start( 'stock', __( 'Availability', 'aggressive-apparel' ) );
		echo '<label class="aa-product-filters__stock-toggle">';
		echo '<span class="aa-product-filters__stock-label">' . esc_html__( 'In stock only', 'aggressive-apparel' ) . '</span>';
		echo '<input type="checkbox" class="aa-product-filters__stock-checkbox" data-wp-on--change="actions.toggleInStockOnly" role="switch" />';
		echo '<span class="aa-product-filters__stock-switch"></span>';
		echo '</label>';
		$this->render_section_end();
	}

	/**
	 * Render a collapsible section start.
	 *
	 * @param string $id    Section identifier.
	 * @param string $title Section title.
	 * @return void
	 */
	private function render_section_start( string $id, string $title ): void {
		printf(
			'<div class="aa-product-filters__section" data-section="%s">',
			esc_attr( $id ),
		);
		printf(
			'<button class="aa-product-filters__section-toggle" data-wp-on--click="actions.toggleSection" aria-expanded="true"><span class="aa-product-filters__section-title">%s</span>%s</button>',
			esc_html( $title ),
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::get() returns safe SVG.
			Icons::get(
				'chevron-down',
				array(
					'width'       => 16,
					'height'      => 16,
					'class'       => 'aa-product-filters__section-icon',
					'aria-hidden' => 'true',
				)
			),
		);
		echo '<div class="aa-product-filters__section-body"><div class="aa-product-filters__section-inner">';
	}

	/**
	 * Render a section end.
	 *
	 * @return void
	 */
	private function render_section_end(): void {
		echo '</div></div></div>';
	}

	/**
	 * Build a hierarchical category tree from a flat list.
	 *
	 * Groups child categories under their parents. Top-level parents
	 * that have only one child (and no products of their own) are
	 * skipped — the child is promoted to top level.
	 *
	 * @param array $categories Flat array with 'id', 'parent', etc.
	 * @return array Tree array with 'children' key on parents.
	 */
	private function build_category_tree( array $categories ): array {
		$by_id = array();
		foreach ( $categories as $cat ) {
			$cat['children']     = array();
			$by_id[ $cat['id'] ] = $cat;
		}

		$tree = array();
		foreach ( $by_id as &$cat ) {
			if ( $cat['parent'] && isset( $by_id[ $cat['parent'] ] ) ) {
				$by_id[ $cat['parent'] ]['children'][] = &$cat;
			} else {
				$tree[] = &$cat;
			}
		}
		unset( $cat );

		return $tree;
	}

	/**
	 * Render a category tree as nested lists.
	 *
	 * @param array $nodes   Category nodes (each may have 'children').
	 * @param bool  $is_root Whether this is the top-level list.
	 * @return void
	 */
	private function render_category_tree( array $nodes, bool $is_root = true ): void {
		$class = 'aa-product-filters__category-list';
		if ( ! $is_root ) {
			$class .= ' aa-product-filters__category-list--children';
		}

		if ( $is_root ) {
			printf(
				'<ul class="%s" role="group" aria-label="%s">',
				esc_attr( $class ),
				esc_attr__( 'Filter by category', 'aggressive-apparel' ),
			);
		} else {
			printf( '<ul class="%s">', esc_attr( $class ) );
		}

		foreach ( $nodes as $cat ) {
			echo '<li class="aa-product-filters__category-item">';
			printf(
				'<button class="aa-product-filters__category-chip" data-wp-on--click="actions.toggleCategory" data-filter-value="%s" data-filter-type="category" aria-pressed="false"><span class="aa-product-filters__category-chip-check" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><polyline points="2.5 6.5 5 9 9.5 3.5"/></svg></span><span class="aa-product-filters__category-chip-name">%s</span><span class="aa-product-filters__category-chip-count">%d</span></button>',
				esc_attr( $cat['slug'] ),
				esc_html( $cat['name'] ),
				(int) $cat['count'],
			);

			if ( ! empty( $cat['children'] ) ) {
				$this->render_category_tree( $cat['children'], false );
			}

			echo '</li>';
		}

		echo '</ul>';
	}

	/**
	 * Gather all filter source data (transient-cached).
	 *
	 * @return array Filter data array.
	 */
	private function gather_filter_data(): array {
		if ( null !== $this->filter_data ) {
			return $this->filter_data;
		}

		$cache_key = self::CACHE_PREFIX . 'data';
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) && isset( $cached['categoryAttributeMap'] ) ) {
			$this->filter_data = $cached;
			return $cached;
		}

		$default_price = array(
			'min'            => 0,
			'max'            => 0,
			'currencyPrefix' => '$',
			'currencySuffix' => '',
			'minorUnit'      => 2,
		);

		try {
			$categories = $this->get_categories_with_counts();
		} catch ( \Throwable $e ) {
			$categories = array();
		}

		try {
			$color_terms = $this->get_color_terms();
		} catch ( \Throwable $e ) {
			$color_terms = array();
		}

		try {
			$size_terms = $this->get_size_terms();
		} catch ( \Throwable $e ) {
			$size_terms = array();
		}

		try {
			$price_range = $this->get_price_range();
		} catch ( \Throwable $e ) {
			$price_range = $default_price;
		}

		try {
			$category_attribute_map = $this->get_category_attribute_map();
		} catch ( \Throwable $e ) {
			$category_attribute_map = array();
		}

		$data = array(
			'categories'           => $categories,
			'colorTerms'           => $color_terms,
			'sizeTerms'            => $size_terms,
			'priceRange'           => $price_range,
			'stockStatuses'        => $this->get_stock_statuses(),
			'categoryAttributeMap' => $category_attribute_map,
		);

		set_transient( $cache_key, $data, self::CACHE_TTL );
		$this->filter_data = $data;

		return $data;
	}

	/**
	 * Get product categories with counts.
	 *
	 * @return array Categories array.
	 */
	private function get_categories_with_counts(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$categories = array();
		foreach ( $terms as $term ) {
			// Skip "Uncategorized" default category.
			if ( 'uncategorized' === $term->slug ) {
				continue;
			}

			$categories[] = array(
				'id'     => $term->term_id,
				'name'   => $term->name,
				'slug'   => $term->slug,
				'count'  => $term->count,
				'parent' => $term->parent,
			);
		}

		return $categories;
	}

	/**
	 * Get color attribute terms with hex/pattern values.
	 *
	 * @return array Color terms array.
	 */
	private function get_color_terms(): array {
		$manager = new Color_Data_Manager();
		$colors  = $manager->get_color_terms();

		// Format for frontend. Include all terms — term counts can be
		// stale or zero in "Coming Soon" mode where products exist but
		// aren't yet publicly visible.
		$result = array();
		foreach ( $colors as $color ) {
			$result[] = array(
				'slug'  => $color['slug'],
				'name'  => $color['name'],
				'value' => $color['value'] ?? $color['hex'] ?? '#000000',
				'type'  => $color['type'] ?? 'solid',
				'count' => $color['count'] ?? 0,
			);
		}

		return $result;
	}

	/**
	 * Get size attribute terms.
	 *
	 * @return array Size terms array.
	 */
	private function get_size_terms(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'pa_size',
				'hide_empty' => false,
				'orderby'    => 'menu_order',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$sizes = array();
		foreach ( $terms as $term ) {
			$sizes[] = array(
				'slug'  => $term->slug,
				'name'  => $term->name,
				'count' => $term->count,
			);
		}

		return $sizes;
	}

	/**
	 * Get min/max price range across all products.
	 *
	 * @return array Price range data.
	 */
	private function get_price_range(): array {
		global $wpdb;

		$default = array(
			'min'            => 0,
			'max'            => 0,
			'currencyPrefix' => '$',
			'currencySuffix' => '',
			'minorUnit'      => 2,
		);

		if ( ! function_exists( 'wc_get_price_decimals' ) ) {
			return $default;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_row(
			"SELECT FLOOR(MIN(meta_value + 0)) as min_price, CEIL(MAX(meta_value + 0)) as max_price
			FROM {$wpdb->postmeta}
			WHERE meta_key = '_price'
			AND meta_value > 0
			AND post_id IN (
				SELECT ID FROM {$wpdb->posts}
				WHERE post_type = 'product'
				AND post_status = 'publish'
			)"
		);

		if ( ! $result || ! $result->min_price ) {
			return $default;
		}

		return array(
			'min'            => (int) $result->min_price,
			'max'            => (int) $result->max_price,
			'currencyPrefix' => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
			'currencySuffix' => '',
			'minorUnit'      => wc_get_price_decimals(),
		);
	}

	/**
	 * Build a map of category slug → available size/color slugs.
	 *
	 * Used by the JS to hide irrelevant filter options when
	 * a category is selected. Cached in the same transient as
	 * all other filter data.
	 *
	 * @return array<string, array{sizes: string[], colors: string[]}>
	 */
	private function get_category_attribute_map(): array {
		global $wpdb;

		/*
		 * Three-part UNION covering all WooCommerce attribute storage:
		 * 1. Attribute terms on the parent product (simple products).
		 * 2. Attribute terms on variation posts via term_relationships.
		 * 3. Attribute values in variation postmeta (most common for
		 *    variable products: attribute_pa_size, attribute_pa_color).
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			"SELECT t_cat.slug AS cat_slug,
			        tt_attr.taxonomy AS attr_tax,
			        t_attr.slug AS attr_slug
			 FROM {$wpdb->term_relationships} tr_cat
			 INNER JOIN {$wpdb->term_taxonomy} tt_cat
			   ON tr_cat.term_taxonomy_id = tt_cat.term_taxonomy_id
			 INNER JOIN {$wpdb->terms} t_cat
			   ON tt_cat.term_id = t_cat.term_id
			 INNER JOIN {$wpdb->term_relationships} tr_attr
			   ON tr_cat.object_id = tr_attr.object_id
			 INNER JOIN {$wpdb->term_taxonomy} tt_attr
			   ON tr_attr.term_taxonomy_id = tt_attr.term_taxonomy_id
			 INNER JOIN {$wpdb->terms} t_attr
			   ON tt_attr.term_id = t_attr.term_id
			 INNER JOIN {$wpdb->posts} p
			   ON tr_cat.object_id = p.ID
			 WHERE tt_cat.taxonomy = 'product_cat'
			   AND tt_attr.taxonomy IN ('pa_size', 'pa_color')
			   AND p.post_type = 'product'
			   AND p.post_status = 'publish'

			 UNION

			 SELECT t_cat.slug AS cat_slug,
			        tt_attr.taxonomy AS attr_tax,
			        t_attr.slug AS attr_slug
			 FROM {$wpdb->posts} v
			 INNER JOIN {$wpdb->posts} p
			   ON v.post_parent = p.ID
			 INNER JOIN {$wpdb->term_relationships} tr_cat
			   ON p.ID = tr_cat.object_id
			 INNER JOIN {$wpdb->term_taxonomy} tt_cat
			   ON tr_cat.term_taxonomy_id = tt_cat.term_taxonomy_id
			 INNER JOIN {$wpdb->terms} t_cat
			   ON tt_cat.term_id = t_cat.term_id
			 INNER JOIN {$wpdb->term_relationships} tr_attr
			   ON v.ID = tr_attr.object_id
			 INNER JOIN {$wpdb->term_taxonomy} tt_attr
			   ON tr_attr.term_taxonomy_id = tt_attr.term_taxonomy_id
			 INNER JOIN {$wpdb->terms} t_attr
			   ON tt_attr.term_id = t_attr.term_id
			 WHERE tt_cat.taxonomy = 'product_cat'
			   AND tt_attr.taxonomy IN ('pa_size', 'pa_color')
			   AND v.post_type = 'product_variation'
			   AND p.post_type = 'product'
			   AND p.post_status = 'publish'

			 UNION

			 SELECT t_cat.slug AS cat_slug,
			        CONCAT('pa_', SUBSTRING(pm.meta_key, 14)) AS attr_tax,
			        pm.meta_value AS attr_slug
			 FROM {$wpdb->posts} v
			 INNER JOIN {$wpdb->posts} p
			   ON v.post_parent = p.ID
			 INNER JOIN {$wpdb->term_relationships} tr_cat
			   ON p.ID = tr_cat.object_id
			 INNER JOIN {$wpdb->term_taxonomy} tt_cat
			   ON tr_cat.term_taxonomy_id = tt_cat.term_taxonomy_id
			 INNER JOIN {$wpdb->terms} t_cat
			   ON tt_cat.term_id = t_cat.term_id
			 INNER JOIN {$wpdb->postmeta} pm
			   ON v.ID = pm.post_id
			 WHERE tt_cat.taxonomy = 'product_cat'
			   AND pm.meta_key IN ('attribute_pa_size', 'attribute_pa_color')
			   AND pm.meta_value != ''
			   AND v.post_type = 'product_variation'
			   AND p.post_type = 'product'
			   AND p.post_status = 'publish'"
		);

		$map = array();
		if ( is_array( $results ) ) {
			foreach ( $results as $row ) {
				$key = 'pa_size' === $row->attr_tax ? 'sizes' : 'colors';

				if ( ! isset( $map[ $row->cat_slug ] ) ) {
					$map[ $row->cat_slug ] = array(
						'sizes'  => array(),
						'colors' => array(),
					);
				}

				$map[ $row->cat_slug ][ $key ][] = $row->attr_slug;
			}

			// Deduplicate.
			foreach ( $map as &$entry ) {
				$entry['sizes']  = array_values( array_unique( $entry['sizes'] ) );
				$entry['colors'] = array_values( array_unique( $entry['colors'] ) );
			}
			unset( $entry );
		}

		return $map;
	}

	/**
	 * Get stock status options.
	 *
	 * @return array Stock statuses.
	 */
	private function get_stock_statuses(): array {
		return array(
			array(
				'value' => 'instock',
				'label' => __( 'In Stock', 'aggressive-apparel' ),
			),
			array(
				'value' => 'outofstock',
				'label' => __( 'Out of Stock', 'aggressive-apparel' ),
			),
			array(
				'value' => 'onbackorder',
				'label' => __( 'On Backorder', 'aggressive-apparel' ),
			),
		);
	}

	/**
	 * Get the active filter layout.
	 *
	 * @return string Layout name.
	 */
	private function get_layout(): string {
		$layout = get_option( Feature_Settings::FILTER_LAYOUT_OPTION, 'drawer' );
		$valid  = array( 'drawer', 'sidebar', 'horizontal' );
		return in_array( $layout, $valid, true ) ? $layout : 'drawer';
	}

	/**
	 * Check if the current page is a shop/archive page.
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
