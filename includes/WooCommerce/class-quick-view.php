<?php
/**
 * Quick View Class
 *
 * Injects a "Quick View" button on product cards in archives and renders
 * a modal shell in the footer. Product data is fetched via the WooCommerce
 * Store API on click.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Assets\Asset_Loader;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Quick View
 *
 * @since 1.17.0
 */
class Quick_View {

	/**
	 * Markup renderer (card action triggers + modal shell).
	 *
	 * @var Quick_View_Renderer
	 */
	private Quick_View_Renderer $renderer;

	/**
	 * Construct the coordinator.
	 *
	 * @param Quick_View_Renderer|null $renderer Optional renderer override (tests).
	 */
	public function __construct( ?Quick_View_Renderer $renderer = null ) {
		$this->renderer = $renderer ?? new Quick_View_Renderer();
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		Block_Filter_Hooks::add_featured_image( array( $this, 'inject_trigger_button' ), 10, 3 );
		Block_Filter_Hooks::add( 'woocommerce/product-image', array( $this, 'inject_trigger_button' ), 10, 3 );
		add_action( 'wp_footer', array( $this->renderer, 'render_modal_shell' ) );
		add_action( 'rest_api_init', array( $this, 'register_store_api_extension' ) );
	}

	/**
	 * Extend the Store API product response with per-variation prices + stock.
	 *
	 * The embedded variations in the Store API only include id + attributes.
	 * This adds a keyed map of variation prices (and an `is_in_stock` flag) so
	 * the quick view JS can read them directly — same pattern as the sticky
	 * cart PHP data.
	 *
	 * Staleness note: this payload is cached for {@see $ttl}, so the
	 * `is_in_stock` flag can lag real inventory by up to that window. The flag
	 * only drives an *advisory* UX affordance (dimming sold-out option
	 * combinations). Purchase correctness never depends on it: add-to-cart goes
	 * through the WooCommerce Store API, which re-validates stock server-side
	 * and rejects an out-of-stock variation regardless of what the client
	 * showed. Keep the TTL modest so the affordance stays roughly accurate.
	 *
	 * @return void
	 */
	public function register_store_api_extension(): void {
		Store_Api_Extension::register_product_data(
			'aggressive-apparel/variation-prices',
			array( $this, 'get_variation_prices_data' ),
			array(
				'cache' => true,
				'ttl'   => 5 * MINUTE_IN_SECONDS,
			)
		);
	}

	/**
	 * Build per-variation price data for the Store API extension.
	 *
	 * @param \WC_Product $product The product object.
	 * @return array<int, array{price: string, regular_price: string, sale_price: string, currency_minor_unit: int, currency_prefix: string, currency_suffix: string, is_in_stock: bool}> Keyed by variation ID.
	 */
	public function get_variation_prices_data( \WC_Product $product ): array {
		if ( ! $product instanceof \WC_Product_Variable ) {
			return array();
		}

		$decimals   = wc_get_price_decimals();
		$minor_unit = pow( 10, $decimals );

		// Derive currency prefix/suffix from WooCommerce settings so the
		// values work for any currency, not just USD.
		$symbol   = html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' );
		$position = get_option( 'woocommerce_currency_pos', 'left' );
		$prefix   = in_array( $position, array( 'left', 'left_space' ), true ) ? $symbol : '';
		$suffix   = in_array( $position, array( 'right', 'right_space' ), true ) ? $symbol : '';

		$result        = array();
		$variation_ids = $product->get_visible_children();

		// Batch-prime the post cache to avoid N+1 queries.
		if ( function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $variation_ids );
		}

		foreach ( $variation_ids as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation ) {
				continue;
			}

			$price         = (string) (int) round( (float) $variation->get_price() * $minor_unit );
			$regular_price = (string) (int) round( (float) $variation->get_regular_price() * $minor_unit );
			$sale_raw      = $variation->get_sale_price();
			$sale_price    = '' !== $sale_raw
				? (string) (int) round( (float) $sale_raw * $minor_unit )
				: $price;

			$result[ $variation_id ] = array(
				'price'               => $price,
				'regular_price'       => $regular_price,
				'sale_price'          => $sale_price,
				'currency_minor_unit' => $decimals,
				'currency_prefix'     => $prefix,
				'currency_suffix'     => $suffix,
				// Per-variation stock so the client can dim option combinations
				// that exist but are sold out (matches the sticky cart + filters).
				'is_in_stock'         => $variation->is_in_stock(),
			);
		}

		return $result;
	}

	/**
	 * Enqueue CSS and register the Interactivity API module where product cards
	 * can render, including related products on single-product pages.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! Product_Context::is_product_display_page() ) {
			return;
		}

		Asset_Loader::enqueue_feature_style(
			'aggressive-apparel-option-pills',
			'build/styles/woocommerce/option-pills'
		);

		Asset_Loader::enqueue_feature_style(
			'aggressive-apparel-quick-view',
			'build/styles/woocommerce/quick-view',
			array( 'aggressive-apparel-option-pills' )
		);

		Asset_Loader::enqueue_interactivity_module(
			'@aggressive-apparel/quick-view',
			'build/interactivity/quick-view',
			array(
				'@aggressive-apparel/scroll-lock',
				'@aggressive-apparel/helpers',
				'@aggressive-apparel/use-overlay',
			)
		);
	}

	/**
	 * Inject the Quick View media action stack onto product card images.
	 *
	 * @param string               $block_content  Block HTML.
	 * @param array<string, mixed> $block          Block data.
	 * @param mixed                $block_instance Rendered block instance.
	 * @return string Modified HTML.
	 */
	public function inject_trigger_button( string $block_content, array $block, $block_instance = null ): string {
		if ( '' === trim( $block_content ) || ! $this->should_inject_trigger( $block ) ) {
			return $block_content;
		}

		$product = $this->get_current_product( $block, $block_instance );
		if ( ! $product ) {
			return $block_content;
		}

		$stack = $this->renderer->build_card_actions_markup( $product );
		if ( '' === $stack ) {
			return $block_content;
		}

		return Block_Render_Helper::append_before_wrapper_close( $block_content, $stack );
	}

	/**
	 * Get the WC_Product from the current block or loop context.
	 *
	 * @param array<string, mixed> $block          Block data.
	 * @param mixed                $block_instance Rendered block instance.
	 * @return \WC_Product|null
	 */
	private function get_current_product( array $block, $block_instance = null ): ?\WC_Product {
		$product_id = Product_Context::resolve_product_id( $block, $block_instance );
		if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$product = wc_get_product( $product_id );

		return $product instanceof \WC_Product ? $product : null;
	}

	/**
	 * Whether this image is a product-card surface that should receive Quick View.
	 *
	 * Archive/search images remain eligible as before. On broader product display
	 * pages, only images explicitly marked as query-loop descendants are eligible,
	 * which enables related/cross-sell cards without decorating the main gallery.
	 *
	 * @param array<string, mixed> $block Block data.
	 * @return bool
	 */
	private function should_inject_trigger( array $block ): bool {
		if ( $this->is_listing_page() ) {
			return true;
		}

		return Product_Context::is_product_display_page()
			&& ! empty( $block['attrs']['isDescendentOfQueryLoop'] );
	}

	/**
	 * Check if the current page is a product listing.
	 *
	 * @return bool
	 */
	private function is_listing_page(): bool {
		return Product_Context::is_product_listing();
	}
}
