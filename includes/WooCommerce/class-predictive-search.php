<?php
/**
 * Predictive Search Class
 *
 * Enhances every core/search block with live product search results,
 * showing thumbnails, prices, and category suggestions as the user types.
 *
 * @package Aggressive_Apparel
 * @since 1.18.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Predictive Search
 *
 * @since 1.18.0
 */
class Predictive_Search {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'render_block', array( $this, 'enhance_search_block' ), 10, 2 );
		add_action( 'wp_footer', array( $this, 'output_interactivity_state' ) );
	}

	/**
	 * Enqueue styles and register the Interactivity API script module.
	 *
	 * Loads on all frontend pages since search blocks can appear anywhere.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( is_admin() ) {
			return;
		}

		$css_file = AGGRESSIVE_APPAREL_DIR . '/build/styles/woocommerce/predictive-search.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'aggressive-apparel-predictive-search',
				AGGRESSIVE_APPAREL_URI . '/build/styles/woocommerce/predictive-search.css',
				array(),
				(string) filemtime( $css_file ),
			);
		}

		if ( function_exists( 'wp_register_script_module' ) ) {
			wp_register_script_module(
				'@aggressive-apparel/predictive-search',
				AGGRESSIVE_APPAREL_URI . '/assets/interactivity/predictive-search.js',
				array( '@wordpress/interactivity', '@aggressive-apparel/helpers' ),
				AGGRESSIVE_APPAREL_VERSION,
			);
			wp_enqueue_script_module( '@aggressive-apparel/predictive-search' );
		}
	}

	/**
	 * Wrap core/search blocks with Interactivity API directives and inject the results dropdown.
	 *
	 * @param string $block_content Block HTML.
	 * @param array  $block         Block data.
	 * @return string Modified HTML.
	 */
	public function enhance_search_block( string $block_content, array $block ): string {
		if ( ! isset( $block['blockName'] ) || 'core/search' !== $block['blockName'] ) {
			return $block_content;
		}

		if ( is_admin() ) {
			return $block_content;
		}

		$instance_id = wp_unique_id( 'ps-' );

		// Add interactivity directives to the search input.
		$block_content = (string) preg_replace(
			'/<input([^>]*type=["\']search["\'][^>]*)>/i',
			'<input$1 data-wp-on--input="actions.handleInput" data-wp-on--focus="actions.handleFocus" data-wp-on--keydown="actions.handleKeydown" autocomplete="off" role="combobox" aria-expanded="false" data-wp-bind--aria-expanded="state.ariaExpanded" aria-controls="' . esc_attr( $instance_id ) . '-results" aria-autocomplete="list" aria-activedescendant="" data-wp-bind--aria-activedescendant="state.activeDescendant">',
			$block_content,
			1
		);

		// Build the results dropdown HTML.
		$dropdown = $this->build_dropdown_html( $instance_id );

		// Wrap everything in an interactive container.
		$wrapper = sprintf(
			'<div data-wp-interactive="aggressive-apparel/predictive-search" data-wp-context=\'%s\' class="aa-predictive-search" data-wp-on-document--click="actions.handleClickOutside">',
			wp_json_encode( array( 'instanceId' => $instance_id ) )
		);

		return $wrapper . $block_content . $dropdown . '</div>';
	}

	/**
	 * Output initial Interactivity API state.
	 *
	 * @return void
	 */
	public function output_interactivity_state(): void {
		if ( is_admin() ) {
			return;
		}

		if ( ! function_exists( 'wp_interactivity_state' ) ) {
			return;
		}

		wp_interactivity_state(
			'aggressive-apparel/predictive-search',
			array(
				'restBase'     => esc_url_raw( rest_url( 'wc/store/v1/products' ) ),
				'query'        => '',
				'products'     => array(),
				'categories'   => array(),
				'isOpen'       => false,
				'isLoading'    => false,
				'focusedIndex' => -1,
				'totalResults' => 0,
				'searchUrl'    => home_url( '/' ),
			),
		);
	}

	/**
	 * Build the results dropdown HTML shell.
	 *
	 * @param string $instance_id Unique instance identifier.
	 * @return string HTML markup.
	 */
	private function build_dropdown_html( string $instance_id ): string {
		$results_id = esc_attr( $instance_id ) . '-results';

		ob_start();
		?>
		<div
			id="<?php echo $results_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped above. ?>"
			class="aa-predictive-search__results"
			role="listbox"
			aria-label="<?php esc_attr_e( 'Search suggestions', 'aggressive-apparel' ); ?>"
			data-wp-bind--hidden="state.isClosed"
			hidden
		>
			<div class="aa-predictive-search__loading" data-wp-bind--hidden="state.isNotLoading">
				<span><?php esc_html_e( 'Searching…', 'aggressive-apparel' ); ?></span>
			</div>

			<div class="aa-predictive-search__products" data-wp-bind--hidden="state.hasNoProducts">
				<h3 class="aa-predictive-search__heading"><?php esc_html_e( 'Products', 'aggressive-apparel' ); ?></h3>
				<ul class="aa-predictive-search__product-list" role="group">
					<template data-wp-each="state.products">
						<li class="aa-predictive-search__product-item" role="option">
							<a
								class="aa-predictive-search__product-link"
								data-wp-bind--href="context.item.permalink"
							>
								<img
									class="aa-predictive-search__product-image no-lazy"
									src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="
									data-wp-bind--src="context.item.thumbnail"
									data-wp-bind--alt="context.item.name"
									width="40"
									height="40"
								/>
								<div class="aa-predictive-search__product-info">
									<span class="aa-predictive-search__product-name" data-wp-text="context.item.name"></span>
									<span class="aa-predictive-search__product-price" data-wp-text="context.item.price"></span>
								</div>
							</a>
						</li>
					</template>
				</ul>
			</div>

			<div class="aa-predictive-search__categories" data-wp-bind--hidden="state.hasNoCategories">
				<h3 class="aa-predictive-search__heading"><?php esc_html_e( 'Categories', 'aggressive-apparel' ); ?></h3>
				<ul class="aa-predictive-search__category-list" role="group">
					<template data-wp-each="state.categories">
						<li role="option">
							<a
								class="aa-predictive-search__category-link"
								data-wp-bind--href="context.item.permalink"
								data-wp-text="context.item.name"
							></a>
						</li>
					</template>
				</ul>
			</div>

			<div class="aa-predictive-search__empty" data-wp-bind--hidden="state.hasResults">
				<p><?php esc_html_e( 'No products found.', 'aggressive-apparel' ); ?></p>
			</div>

			<div class="aa-predictive-search__footer" data-wp-bind--hidden="state.hasNoProducts">
				<a class="aa-predictive-search__view-all" data-wp-bind--href="state.viewAllUrl">
					<?php esc_html_e( 'View all results', 'aggressive-apparel' ); ?> →
				</a>
			</div>

			<div class="screen-reader-text" aria-live="polite" data-wp-text="state.resultAnnouncement"></div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
