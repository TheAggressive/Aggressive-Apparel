<?php
/**
 * Search Modal
 *
 * Assets, interactivity state seeding, and the portaled modal shell markup.
 *
 * @package Aggressive_Apparel
 */

declare( strict_types=1 );

namespace Aggressive_Apparel\Core;

use Aggressive_Apparel\Assets\Asset_Loader;

defined( 'ABSPATH' ) || exit;

/**
 * Frontend modal + asset wiring for the search block.
 */
class Search_Modal {

	/**
	 * Interactivity store namespace (shared by trigger + modal).
	 *
	 * @var string
	 */
	public const STORE = 'aggressive-apparel/search';

	/**
	 * Modal shell element id (must match nav-shared overlay-coordination).
	 *
	 * @var string
	 */
	private const MODAL_ID = 'aa-search-modal';

	/**
	 * Attribute marking the search modal as an overlay above the nav panel.
	 *
	 * @var string
	 */
	private const YIELDS_NAV_FOCUS_ATTR = 'data-aa-yields-nav-focus';

	/**
	 * Block name for the search trigger.
	 *
	 * @var string
	 */
	private const BLOCK_NAME = 'aggressive-apparel/search';

	/**
	 * Whether the modal shell has already been emitted this request.
	 *
	 * @var bool
	 */
	private bool $shell_rendered = false;

	/**
	 * Whether the search trigger block has rendered this request.
	 *
	 * @var bool
	 */
	private bool $block_rendered = false;

	/**
	 * Whether shared modules, state, and styles are prepared.
	 *
	 * @var bool
	 */
	private bool $assets_prepared = false;

	/**
	 * Mark the search block as present once its render callback runs.
	 *
	 * @return void
	 */
	public function mark_block_rendered(): void {
		$this->block_rendered = true;
		$this->ensure_assets();
	}

	/**
	 * Early asset prep when the block is discoverable before render.
	 *
	 * @return void
	 */
	public function register_assets(): void {
		if ( is_admin() || ! $this->should_load() ) {
			return;
		}

		$this->ensure_assets();
		$this->enqueue_overlay_modules();
	}

	/**
	 * Render the search modal shell once in the footer (portal pattern).
	 *
	 * @return void
	 */
	public function render_shell(): void {
		if ( $this->shell_rendered || ! $this->should_load() ) {
			return;
		}
		$this->shell_rendered = true;

		$scopes = array( 'all' => __( 'All', 'aggressive-apparel' ) );
		if ( Search_Visibility::products_are_public() ) {
			$scopes['product'] = __( 'Products', 'aggressive-apparel' );
		}
		$scopes['post'] = __( 'Articles', 'aggressive-apparel' );
		$scopes['page'] = __( 'Pages', 'aggressive-apparel' );

		$icon_attrs = static fn( int $size ): array => array(
			'width'       => $size,
			'height'      => $size,
			'aria-hidden' => 'true',
		);

		$tabs_html = '';
		foreach ( $scopes as $value => $label ) {
			$tabs_html .= sprintf(
				'<button type="button" role="tab" class="aa-search__tab" data-scope="%1$s" data-wp-on--click="actions.setScope" data-wp-class--is-active="callbacks.isActiveScope" data-wp-bind--aria-selected="callbacks.isActiveScope"><span class="aa-search__tab-check" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><polyline points="2.5 6.5 5 9 9.5 3.5"/></svg></span><span class="aa-search__tab-label">%2$s</span></button>',
				esc_attr( $value ),
				esc_html( $label )
			);
		}

		$hint = isset( $scopes['product'] )
			? __( 'Start typing to search products, articles and pages.', 'aggressive-apparel' )
			: __( 'Start typing to search articles and pages.', 'aggressive-apparel' );

		printf(
			'<div
				class="aa-search"
				id="%1$s"
				hidden
				%2$s
				data-wp-interactive="%3$s"
				data-wp-class--is-open="state.isOpen"
				data-wp-on-document--keydown="actions.handleKeydown"
			>
				<div class="aa-search__overlay" data-wp-on--click="actions.close" aria-hidden="true"></div>
				<div class="aa-search__dialog" role="dialog" aria-modal="true" aria-label="%4$s">
					<div class="aa-search__bar">
						<div class="aa-search__field" data-wp-class--is-filled="state.hasQuery">
							<span class="aa-search__field-icon" aria-hidden="true">%5$s</span>
							<input
								type="search"
								class="aa-search__input"
								placeholder="%6$s"
								aria-label="%6$s"
								autocomplete="off"
								spellcheck="false"
								enterkeyhint="search"
								role="combobox"
								aria-controls="aa-search-results"
								aria-autocomplete="list"
								data-wp-bind--aria-expanded="state.hasResults"
								data-wp-on--input="actions.handleInput"
							/>
							<button type="button" class="aa-search__clear" aria-label="%7$s" hidden data-wp-bind--hidden="!state.hasQuery" data-wp-on--click="actions.clear">%8$s</button>
						</div>
						<button type="button" class="aa-search__close" aria-label="%9$s" data-wp-on--click="actions.close">%10$s</button>
					</div>
					<div class="aa-search__tabs" role="tablist" aria-label="%11$s">%12$s</div>
					<div class="aa-search__body">
						<p class="aa-search__hint" data-wp-bind--hidden="state.hideHint">%13$s</p>
						<p class="aa-search__loading" hidden data-wp-bind--hidden="!state.isLoading" aria-hidden="true">%14$s</p>
						<div class="aa-search__error" role="alert" hidden data-wp-bind--hidden="state.hideError">
							<p class="aa-search__error-message" data-wp-text="state.errorDisplay"></p>
							<button type="button" class="aa-search__retry wp-element-button" data-wp-on--click="actions.retry">%15$s</button>
						</div>
						<p class="aa-search__empty" hidden data-wp-bind--hidden="!state.showEmpty" role="status">%16$s</p>
						<div class="aa-search__tab-empty" hidden data-wp-bind--hidden="!state.showTabEmpty" role="status">
							<p class="aa-search__tab-empty-message" data-wp-text="state.tabEmptyMessage"></p>
							<button type="button" class="aa-search__tab-empty-action wp-element-button" data-wp-on--click="actions.setScopeAll">%17$s</button>
						</div>
						<div id="aa-search-results" class="aa-search__results" role="listbox" aria-label="%18$s"></div>
					</div>
					<div class="aa-search__footer" data-wp-class--is-visible="state.hasResults" data-wp-bind--aria-hidden="!state.hasResults">
						<a class="aa-search__view-all" hidden data-wp-bind--hidden="!state.hasResults" data-wp-bind--href="state.viewAllUrl">%19$s %20$s</a>
						<div class="aa-search__hints" aria-hidden="true">
							<span class="aa-search__hint-item"><kbd>&uarr;</kbd><kbd>&darr;</kbd> %21$s</span>
							<span class="aa-search__hint-item"><kbd>&crarr;</kbd> %22$s</span>
							<span class="aa-search__hint-item"><kbd>esc</kbd> %23$s</span>
						</div>
					</div>
				</div>
				<div class="screen-reader-text" aria-live="polite" data-wp-text="state.announcement"></div>
			</div>',
			esc_attr( self::MODAL_ID ),
			esc_attr( self::YIELDS_NAV_FOCUS_ATTR ),
			esc_attr( self::STORE ),
			esc_attr__( 'Search', 'aggressive-apparel' ),
			\aggressive_apparel_get_icon( 'search', $icon_attrs( 22 ) ),
			esc_attr__( 'Search', 'aggressive-apparel' ),
			esc_attr__( 'Clear search', 'aggressive-apparel' ),
			\aggressive_apparel_get_icon( 'close', $icon_attrs( 18 ) ),
			esc_attr__( 'Close search', 'aggressive-apparel' ),
			\aggressive_apparel_get_icon( 'close', $icon_attrs( 20 ) ),
			esc_attr__( 'Filter results by type', 'aggressive-apparel' ),
			\aggressive_apparel_trusted_html( $tabs_html ),
			esc_html( $hint ),
			esc_html__( 'Searching…', 'aggressive-apparel' ),
			esc_html__( 'Try again', 'aggressive-apparel' ),
			esc_html__( 'No results found.', 'aggressive-apparel' ),
			esc_html__( 'View all results', 'aggressive-apparel' ),
			esc_attr__( 'Search results', 'aggressive-apparel' ),
			esc_html__( 'View all results', 'aggressive-apparel' ),
			\aggressive_apparel_get_icon( 'arrow-right', $icon_attrs( 16 ) ),
			esc_html__( 'navigate', 'aggressive-apparel' ),
			esc_html__( 'open', 'aggressive-apparel' ),
			esc_html__( 'close', 'aggressive-apparel' )
		);
	}

	/**
	 * Register shared overlay modules, seed store state, and enqueue block styles.
	 *
	 * @return void
	 */
	private function ensure_assets(): void {
		if ( $this->assets_prepared || ! function_exists( 'wp_register_script_module' ) ) {
			return;
		}
		$this->assets_prepared = true;

		$this->enqueue_overlay_modules();

		if ( function_exists( 'wp_interactivity_state' ) ) {
			wp_interactivity_state(
				self::STORE,
				array(
					'restUrl'      => esc_url_raw( rest_url( Search_Rest::REST_NAMESPACE . Search_Rest::REST_ROUTE ) ),
					'isOpen'       => false,
					'query'        => '',
					'scope'        => 'all',
					'isLoading'    => false,
					'hasSearched'  => false,
					'hasError'     => false,
					'errorCode'    => '',
					'errorMessage' => '',
					'groups'       => array(),
					'total'        => 0,
					'viewAllUrl'   => '',
					'placeholders' => $this->get_placeholder_phrases(),
					'i18n'         => array(
						'noResults'      => __( 'No results found.', 'aggressive-apparel' ),
						'resultSingular' => __( '1 result found.', 'aggressive-apparel' ),
						/* translators: %d: number of search results. */
						'resultPlural'   => __( '%d results found.', 'aggressive-apparel' ),
						'searchError'    => __( 'Search is temporarily unavailable. Please try again.', 'aggressive-apparel' ),
						'rateLimited'    => __( 'Too many requests. Please slow down and try again.', 'aggressive-apparel' ),
						'retry'          => __( 'Try again', 'aggressive-apparel' ),
						/* translators: 1: active tab label, 2: number of results in other types. */
						'tabEmpty'       => __( 'No %1$s found. View all results (%2$d) instead.', 'aggressive-apparel' ),
						'viewAllTab'     => __( 'View all results', 'aggressive-apparel' ),
						'tabLabels'      => array(
							'product' => __( 'Products', 'aggressive-apparel' ),
							'post'    => __( 'Articles', 'aggressive-apparel' ),
							'page'    => __( 'Pages', 'aggressive-apparel' ),
						),
					),
				)
			);
		}

		wp_enqueue_style( 'aggressive-apparel-search-style' );
	}

	/**
	 * Register and enqueue the overlay module chain (idempotent).
	 *
	 * @return void
	 */
	private function enqueue_overlay_modules(): void {
		if ( ! function_exists( 'wp_enqueue_script_module' ) || ! function_exists( 'wp_register_script_module' ) ) {
			return;
		}

		$this->register_shared_modules();
		wp_enqueue_script_module( '@aggressive-apparel/use-overlay' );
	}

	/**
	 * Register the shared overlay/scroll-lock/helpers modules (idempotent).
	 *
	 * @return void
	 */
	private function register_shared_modules(): void {
		$modules = array(
			'@aggressive-apparel/scroll-lock' => array( 'build/interactivity/scroll-lock.js', array() ),
			'@aggressive-apparel/helpers'     => array( 'build/interactivity/helpers.js', array() ),
			'@aggressive-apparel/use-overlay' => array(
				'build/interactivity/use-overlay.js',
				array( '@aggressive-apparel/scroll-lock', '@aggressive-apparel/helpers' ),
			),
		);

		foreach ( $modules as $id => $config ) {
			list( $path, $deps ) = $config;
			if ( ! file_exists( AGGRESSIVE_APPAREL_DIR . '/' . $path ) ) {
				continue;
			}
			$relative_path = str_ends_with( $path, '.js' ) ? substr( $path, 0, -3 ) : $path;
			Asset_Loader::register_interactivity_module(
				$id,
				$relative_path,
				$deps,
				false
			);
		}
	}

	/**
	 * Whether the search assets + modal shell should load on this request.
	 *
	 * @return bool
	 */
	private function should_load(): bool {
		if ( is_admin() ) {
			return false;
		}

		if ( ! $this->block_rendered && ! $this->is_block_on_page() ) {
			return false;
		}

		return (bool) apply_filters( 'aggressive_apparel_load_search_assets', true );
	}

	/**
	 * Whether the search trigger block is present in the current template.
	 *
	 * @return bool
	 */
	private function is_block_on_page(): bool {
		if ( ! function_exists( 'has_block' ) ) {
			return false;
		}

		return has_block( self::BLOCK_NAME );
	}

	/**
	 * Animated placeholder phrases for the search field.
	 *
	 * @return array<int, string>
	 */
	private function get_placeholder_phrases(): array {
		$phrases = array();
		if ( Search_Visibility::products_are_public() ) {
			$phrases[] = __( 'Find Your Fit…', 'aggressive-apparel' );
		}
		$phrases[] = __( 'Read the Noise…', 'aggressive-apparel' );
		$phrases[] = __( 'Find Your Way…', 'aggressive-apparel' );

		/**
		 * Filter the animated search placeholder phrases.
		 *
		 * @param array<int, string> $phrases Placeholder phrases.
		 */
		return array_values( (array) apply_filters( 'aggressive_apparel_search_placeholders', $phrases ) );
	}
}
