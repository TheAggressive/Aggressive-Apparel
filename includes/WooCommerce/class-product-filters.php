<?php
/**
 * Product Filters Class
 *
 * AJAX product filters with categories, color swatches, sizes, price range,
 * stock status, and sale status. Supports drawer, sidebar, and horizontal bar layouts.
 *
 * @package Aggressive_Apparel
 * @since 1.22.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Assets\Asset_Loader;
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
	 * Whether filter source data was already invalidated this request.
	 *
	 * @var bool
	 */
	private bool $cache_flushed = false;

	/**
	 * Active layout.
	 *
	 * @var string
	 */
	private string $layout = 'drawer';

	/**
	 * Filter source-data provider.
	 *
	 * @var Product_Filter_Data
	 */
	private Product_Filter_Data $data_provider;

	/**
	 * Filter control renderer.
	 *
	 * @var Product_Filter_Renderer
	 */
	private Product_Filter_Renderer $renderer;

	/**
	 * Active layout for the current request, cached statically so it can be
	 * exposed via `get_active_layout()` without re-querying options.
	 *
	 * @var string|null
	 */
	private static ?string $active_layout = null;

	/**
	 * Whether CSS/JS/state were already prepared for this request.
	 *
	 * @var bool
	 */
	private static bool $assets_enqueued = false;

	/**
	 * Construct the filter coordinator.
	 *
	 * @param Product_Filter_Data|null     $data_provider Optional provider override.
	 * @param Product_Filter_Renderer|null $renderer      Optional renderer override.
	 */
	public function __construct( ?Product_Filter_Data $data_provider = null, ?Product_Filter_Renderer $renderer = null ) {
		$this->data_provider = $data_provider ?? new Product_Filter_Data();
		$this->renderer      = $renderer ?? new Product_Filter_Renderer();
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->layout        = $this->get_layout();
		self::$active_layout = $this->layout;

		// Register script module early so it's included in the wp_head import map.
		// Enqueuing happens later in ensure_assets() when we know we're on a shop page.
		add_action( 'wp_enqueue_scripts', array( $this, 'register_script_module' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'render_block_woocommerce/catalog-sorting', array( $this, 'inject_filter_ui' ), 10, 2 );
		add_filter( 'render_block_woocommerce/product-collection', array( $this, 'inject_filter_ui' ), 10, 2 );
		add_action( 'wp_footer', array( $this, 'render_drawer_shell' ) );
		add_filter( 'body_class', array( $this, 'add_body_class' ) );

		// Invalidate the filter data cache when products or attributes change.
		add_action( 'created_term', array( $this, 'flush_cache' ) );
		add_action( 'edited_term', array( $this, 'flush_cache' ) );
		add_action( 'delete_term', array( $this, 'flush_cache' ) );
		add_action( 'woocommerce_update_product', array( $this, 'flush_cache' ) );
		add_action( 'woocommerce_new_product', array( $this, 'flush_cache' ) );
		add_action( 'aggressive_apparel_sale_category_updated', array( $this, 'flush_cache' ) );
	}

	/**
	 * Delete the cached filter data transient.
	 *
	 * @return void
	 */
	public function flush_cache(): void {
		if ( $this->cache_flushed ) {
			return;
		}
		$this->cache_flushed = true;
		$this->data_provider->flush();
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
		// Register only — registration is free and guarantees the module can
		// be resolved by the wp_head import map on any request. The actual
		// enqueue (CSS + JS + state) happens in ensure_assets(), which only
		// runs on shop pages: gated via wp_enqueue_scripts, plus a lazy
		// render_block fallback (which fires before wp_head in block themes,
		// so assets enqueued there still print in the head). This keeps
		// ~40 KB of filter CSS/JS off every non-shop page.
		Asset_Loader::register_interactivity_module(
			'@aggressive-apparel/product-filters',
			'build/interactivity/product-filters',
			array(
				'@aggressive-apparel/scroll-lock',
				'@aggressive-apparel/helpers',
				'@aggressive-apparel/use-overlay',
			)
		);
	}

	/**
	 * Enqueue filter assets on filterable archives.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! $this->request_needs_assets() ) {
			return;
		}

		self::ensure_assets();
	}

	/**
	 * Whether the current request should load product-filters assets.
	 *
	 * Defaults to shop / product category / product tag archives. Custom
	 * archive integrations can opt in via the filter.
	 *
	 * @return bool
	 */
	private function request_needs_assets(): bool {
		$needed = ! is_admin() && self::is_filterable_archive();

		/**
		 * Filters whether product-filters frontend assets should load.
		 *
		 * @since 1.142.0
		 *
		 * @param bool $needed Whether the current request needs the assets.
		 */
		return (bool) apply_filters( 'aggressive_apparel_product_filters_needs_assets', $needed );
	}

	/**
	 * Enqueue CSS, JS, and interactivity state (idempotent).
	 *
	 * Public so filter-toggle / filter-active-bar render callbacks can recover
	 * when archive detection was missed during `wp_enqueue_scripts` (FSE timing).
	 * WordPress de-duplicates repeated style/module enqueues.
	 *
	 * @return void
	 */
	public static function ensure_assets(): void {
		if ( is_admin() || ! Feature_Settings::is_enabled( 'product_filters' ) ) {
			return;
		}

		if ( self::$assets_enqueued ) {
			return;
		}
		self::$assets_enqueued = true;

		Asset_Loader::enqueue_feature_style(
			'aggressive-apparel-product-filters',
			'build/styles/woocommerce/product-filters'
		);

		// Enqueue by ID — registration happens in register_script_module()
		// during wp_enqueue_scripts; WP_Script_Modules supports
		// enqueue-before-register, so the render_block path is safe too.
		if ( function_exists( 'wp_enqueue_script_module' ) ) {
			wp_enqueue_script_module( '@aggressive-apparel/product-filters' );
		}

		if ( ! function_exists( 'wp_interactivity_state' ) ) {
			return;
		}

		$data_provider    = new Product_Filter_Data();
		$data             = $data_provider->get();
		$current_cat_slug = Product_Context::get_current_category_slug();
		$layout           = self::get_active_layout();

		// Interactivity state only needs slug/name for pills and URL sync.
		// Swatch styles, counts, and links are SSR'd by Product_Filter_Renderer.
		$slim_terms = static function ( array $terms ): array {
			return array_values(
				array_map(
					static fn( array $term ): array => array(
						'slug' => (string) ( $term['slug'] ?? '' ),
						'name' => (string) ( $term['name'] ?? '' ),
					),
					$terms
				)
			);
		};

		wp_interactivity_state(
			'aggressive-apparel/product-filters',
			array(
				'restBase'            => esc_url_raw( rest_url( 'wc/store/v1/products' ) ),
				// REST nonce so logged-in shop managers/admins are authenticated
				// for the rendered-products endpoint while the store is in
				// "coming soon" mode (otherwise the fetch is treated as
				// anonymous and the gated catalogue comes back empty).
				'restNonce'           => wp_create_nonce( 'wp_rest' ),
				'shopUrl'             => esc_url_raw( \wc_get_page_permalink( 'shop' ) ),
				'salesCategoryUrl'    => $data_provider->sales_category_url(),
				'layout'              => $layout,
				'perPage'             => 12,
				'currentCategorySlug' => $current_cat_slug,
				// Render filtered cards from the same template the current page
				// uses (e.g. a customised "Products by Category" template),
				// not the default archive-product card.
				'templateSlug'        => Product_Context::get_current_template_slug(),
				'categories'          => $slim_terms( $data['categories'] ),
				'colorTerms'          => $slim_terms( $data['colorTerms'] ),
				'sizeTerms'           => $slim_terms( $data['sizeTerms'] ),
				'fitTerms'            => $slim_terms( $data['fitTerms'] ),
				'priceRange'          => array_merge(
					$data['priceRange'],
					array(
						'currencyPrefix' => html_entity_decode(
							$data['priceRange']['currencyPrefix'] ?? '$',
							ENT_QUOTES,
							'UTF-8'
						),
					)
				),
				'stockStatuses'       => $data['stockStatuses'],
				'selectedCategories'  => array(),
				'selectedColors'      => array(),
				'selectedSizes'       => array(),
				'selectedFit'         => array(),
				'priceMin'            => $data['priceRange']['min'],
				'priceMax'            => $data['priceRange']['max'],
				'inStockOnly'         => false,
				'onSaleOnly'          => false,
				// Seed derived flags for SSR directive processing. Client
				// getters in product-filters.ts override these after hydration.
				// Without them, data-wp-bind--hidden="state.hasNoActiveFilters"
				// evaluates falsy on the server and strips the hidden attribute,
				// flashing Clear All until JS runs.
				'hasActiveFilters'    => false,
				'hasNoActiveFilters'  => true,
				'orderBy'             => 'date',
				'orderDir'            => 'desc',
				'_customSort'         => '',
				'isDrawerOpen'        => false,
				'isLoading'           => false,
				'hasError'            => false,
				'currentPage'         => 1,
				'totalPages'          => 1,
				'totalProducts'       => 0,
				'nextCursor'          => '',
				'products'            => array(),
				'announcement'        => '',
				'openDropdown'        => '',

				/*
				 * Translatable strings consumed by the Interactivity store.
				 * Sprintf-style templates use `%s` for the active count.
				 * Wrapping copy in parentheses keeps the screen-reader
				 * announcement separated from the visible button label
				 * when the two are concatenated by the AT name algorithm.
				 */
				'i18n'                => array(
					/* translators: %s is the number of currently active filters (always 1). */
					'filtersAppliedSingular'       => __( '(%s filter applied)', 'aggressive-apparel' ),
					/* translators: %s is the number of currently active filters (2 or more). */
					'filtersAppliedPlural'         => __( '(%s filters applied)', 'aggressive-apparel' ),
					/* translators: %s is a comma-separated list of filter names not shown in the pill row. */
					'activeFiltersOverflowTooltip' => __( 'Additional filters: %s', 'aggressive-apparel' ),
					'inStockLabel'                 => __( 'In stock', 'aggressive-apparel' ),
					'onSaleLabel'                  => __( 'On sale', 'aggressive-apparel' ),
					'allFiltersCleared'            => __( 'All filters cleared.', 'aggressive-apparel' ),
					'loadError'                    => __( 'Something went wrong loading products.', 'aggressive-apparel' ),
					'noProductsFound'              => __( 'No products found.', 'aggressive-apparel' ),
					'oneProductFound'              => __( '1 product found.', 'aggressive-apparel' ),
					/* translators: %d: number of products found after filtering. */
					'productsFound'                => __( '%d products found.', 'aggressive-apparel' ),
					/* translators: %s: active filter label to remove. */
					'removeFilterAria'             => __( 'Remove %s filter', 'aggressive-apparel' ),
				),
				'salesCategorySlug'   => Sale_Category::TERM_SLUG,
			)
		);
	}

	/**
	 * Inject filter UI into blocks via render_block filter.
	 *
	 * @param string $block_content Block HTML.
	 * @param array  $block         Block data.
	 * @return string Modified HTML.
	 */
	public function inject_filter_ui( string $block_content, array $block ): string {
		if ( ! isset( $block['blockName'] ) || ! $this->request_needs_assets() ) {
			return $block_content;
		}

		try {
			// Lazily enqueue assets on first matching block (render_block fires before wp_enqueue_scripts in block themes).
			self::ensure_assets();

			// Product sorting is owned by this theme's rendered-products endpoint.
			// Remove WooCommerce's competing Interactivity API handler at render
			// time so the normal change event remains available to analytics and
			// extensions without triggering two grid renders.
			if ( 'woocommerce/catalog-sorting' === $block['blockName'] ) {
				return $this->remove_native_sort_handler( $block_content );
			}

			// Wrap product-collection with AJAX grid container.
			if ( 'woocommerce/product-collection' === $block['blockName'] ) {
				return $this->wrap_product_collection( $block_content );
			}
		} catch ( \Throwable $e ) {
			// Return original block content on error to avoid breaking the page.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				aggressive_apparel_debug_log(
					'Product Filters inject_filter_ui error.',
					array( 'error' => $e->getMessage() )
				);
			}
		}

		return $block_content;
	}

	/**
	 * Remove WooCommerce's client-side sort action from its order-by select.
	 *
	 * @param string $block_content Rendered catalog-sorting markup.
	 * @return string Updated markup.
	 */
	private function remove_native_sort_handler( string $block_content ): string {
		$processor = new \WP_HTML_Tag_Processor( $block_content );
		while ( $processor->next_tag( array( 'tag_name' => 'SELECT' ) ) ) {
			if ( 'orderby' !== $processor->get_attribute( 'name' ) ) {
				continue;
			}

			$processor->remove_attribute( 'data-wp-on--change' );
			break;
		}

		return $processor->get_updated_html();
	}

	/**
	 * Render the drawer shell in wp_footer.
	 *
	 * @return void
	 */
	public function render_drawer_shell(): void {
		if ( ! self::$assets_enqueued || ! $this->request_needs_assets() ) {
			return;
		}

		try {
			$data = $this->data_provider->get();
			?>
			<div
				id="aa-product-filters-drawer"
				class="aggressive-apparel-overlay aggressive-apparel-overlay--drawer-left aa-product-filters__drawer"
				data-wp-interactive="aggressive-apparel/product-filters"
				data-wp-class--is-open="state.isDrawerOpen"
				hidden
				role="dialog"
				aria-modal="true"
				aria-label="<?php esc_attr_e( 'Product filters', 'aggressive-apparel' ); ?>"
			>
				<div
					class="aggressive-apparel-overlay__backdrop aa-product-filters__drawer-backdrop"
					data-wp-on--click="actions.closeDrawer"
				></div>
				<div class="aggressive-apparel-panel aggressive-apparel-panel--drawer-left aa-product-filters__drawer-panel">
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
						<?php $this->renderer->render_sections( $data ); ?>
					</div>

					<div class="aa-product-filters__drawer-footer">
						<button
							class="aa-product-filters__clear-btn wp-element-button"
							data-wp-on--click="actions.clearAllFilters"
							data-wp-bind--hidden="state.hasNoActiveFilters"
						>
							<?php esc_html_e( 'Clear All', 'aggressive-apparel' ); ?>
						</button>
						<button
							class="aa-product-filters__apply-btn wp-element-button"
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
				aggressive_apparel_debug_log(
					'Product Filters drawer error.',
					array( 'error' => $e->getMessage() )
				);
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
		if ( $this->request_needs_assets() ) {
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
			$data          = $this->data_provider->get();
			$sidebar_html  = '<aside class="aa-product-filters__sidebar" data-wp-interactive="aggressive-apparel/product-filters" aria-label="' . esc_attr__( 'Product filters', 'aggressive-apparel' ) . '">';
			$sidebar_html .= '<div class="aa-product-filters__sidebar-inner">';

			ob_start();
			$this->renderer->render_sections( $data );
			$sidebar_html .= ob_get_clean();

			// Sidebar has no panel to close, so results apply via this button
			// (selections only stage until it's pressed).
			$sidebar_html .= '<button class="aa-product-filters__apply-btn aa-product-filters__apply-btn--sidebar wp-element-button" data-wp-on--click="actions.applyFilters">';
			$sidebar_html .= esc_html__( 'View Results', 'aggressive-apparel' );
			$sidebar_html .= '</button>';

			$sidebar_html .= '<button class="aa-product-filters__clear-btn aa-product-filters__clear-btn--sidebar wp-element-button" data-wp-on--click="actions.clearAllFilters" data-wp-bind--hidden="state.hasNoActiveFilters">';
			$sidebar_html .= esc_html__( 'Clear All Filters', 'aggressive-apparel' );
			$sidebar_html .= '</button>';
			$sidebar_html .= '</div></aside>';
		}

		// For horizontal layout, render the filter bar.
		$bar_html = '';
		if ( 'horizontal' === $this->layout ) {
			$bar_html = $this->renderer->build_horizontal_bar( $this->data_provider->get() );
		}

		$grid_class = 'sidebar' === $this->layout ? ' aa-product-filters__grid-wrapper--sidebar' : '';

		$output = sprintf(
			'<div class="aa-product-filters aa-product-filters--%s" data-wp-interactive="aggressive-apparel/product-filters" data-wp-init="callbacks.init" data-wp-on-window--keydown="actions.handleKeydown" data-wp-on-document--click="actions.handleClickOutside">',
			esc_attr( $this->layout ),
		);

		$output .= $bar_html;

		$output .= '<div class="aa-product-filters__grid-wrapper' . esc_attr( $grid_class ) . '">';

		$output .= $sidebar_html;

		$output .= '<div class="aa-product-filters__main">';

		// Loading skeleton — overlays the grid only while a filtered request is
		// in flight.
		$output .= '<div class="aa-product-filters__skeleton" data-wp-bind--hidden="!state.isLoading" aria-hidden="true" hidden>';
		for ( $i = 0; $i < 6; $i++ ) {
			$output .= '<div class="aa-product-filters__skeleton-card" role="presentation"><div class="aa-product-filters__skeleton-image"></div><div class="aa-product-filters__skeleton-title"></div><div class="aa-product-filters__skeleton-price"></div></div>';
		}
		$output .= '</div>';

		// The real, native product-collection grid. Filtered/sorted results are
		// injected by JS straight into its product-template <ul> — the same
		// element infinite-scroll appends to — so columns, gap, alignment and the
		// "Inner blocks use content width" constraint are always exactly what the
		// block editor produced. Hidden only while a request is loading.
		$output .= '<div class="aa-product-filters__grid" data-wp-bind--hidden="state.isLoading">';
		$output .= $block_content;
		$output .= '</div>';

		// No results — only while filters are active and the grid came back empty.
		$output .= '<div class="aa-product-filters__no-results" data-wp-bind--hidden="state.hideNoResults">';
		$output .= '<p>' . esc_html__( 'No products were found matching your selection.', 'aggressive-apparel' ) . '</p>';
		$output .= '</div>';

		// Error notice with retry — shown when a request fails.
		$output .= '<div class="aa-product-filters__error" role="alert" data-wp-bind--hidden="state.hideError">';
		$output .= '<p>' . esc_html__( 'Something went wrong loading products.', 'aggressive-apparel' ) . '</p>';
		$output .= '<button type="button" class="aa-product-filters__retry wp-element-button" data-wp-on--click="actions.retry">';
		$output .= esc_html__( 'Try again', 'aggressive-apparel' );
		$output .= '</button>';
		$output .= '</div>';

		// Fallback pagination for filtered results. The frontend suppresses it only
		// when this collection actually rendered a Load More control.
		$output .= '<nav class="aa-product-filters__pagination-nav" data-wp-bind--hidden="state.hasSinglePage" aria-label="' . esc_attr__( 'Filtered products pagination', 'aggressive-apparel' ) . '">';
		$output .= '<div class="aa-product-filters__pagination">';
		$output .= '</div></nav>';

		$output .= '</div>'; // End .aa-product-filters__main.
		$output .= '</div>'; // End .aa-product-filters__grid-wrapper.

		// Screen reader announcer.
		$output .= '<div class="aa-product-filters__announcer screen-reader-text" role="status" aria-live="polite" data-wp-text="state.announcement"></div>';

		$output .= '</div>'; // End .aa-product-filters.

		return $output;
	}

	/**
	 * Get the active filter layout.
	 *
	 * @return string Layout name.
	 */
	private function get_layout(): string {
		return self::get_active_layout();
	}

	/**
	 * Whether the current request is a filterable product archive.
	 *
	 * Used by Filter Toggle / Active Bar block render callbacks to decide
	 * whether to emit filter-related markup.
	 *
	 * @since 1.22.0
	 *
	 * @return bool True when the current request is a shop, product category,
	 *              or product tag archive.
	 */
	public static function is_filterable_archive(): bool {
		return Product_Context::is_product_archive();
	}

	/**
	 * Get the active layout for the current request, mirroring the
	 * instance-side resolution used by `init()`.
	 *
	 * Used by the Filter Toggle block to apply the same "mobile-only"
	 * default behavior as the legacy automatic placement.
	 *
	 * @since 1.22.0
	 *
	 * @return string One of `drawer`, `sidebar`, `horizontal`.
	 */
	public static function get_active_layout(): string {
		if ( null !== self::$active_layout ) {
			return self::$active_layout;
		}

		$layout = Feature_Settings::get_filter_layout();
		$valid  = array( 'drawer', 'sidebar', 'horizontal' );

		self::$active_layout = in_array( $layout, $valid, true ) ? $layout : 'drawer';

		return self::$active_layout;
	}
}
