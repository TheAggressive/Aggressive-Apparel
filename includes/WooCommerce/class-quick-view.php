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

use Aggressive_Apparel\Core\Icons;

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
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'render_block', array( $this, 'inject_trigger_button' ), 10, 2 );
		add_action( 'wp_footer', array( $this, 'render_modal_shell' ) );
		add_action( 'rest_api_init', array( $this, 'register_store_api_extension' ) );
	}

	/**
	 * Extend the Store API product response with per-variation prices.
	 *
	 * The embedded variations in the Store API only include id + attributes.
	 * This adds a keyed map of variation prices so the quick view JS can
	 * read them directly — same pattern as the sticky cart PHP data.
	 *
	 * @return void
	 */
	public function register_store_api_extension(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => 'product',
				'namespace'       => 'aggressive-apparel/variation-prices',
				'data_callback'   => array( $this, 'get_variation_prices_data' ),
				'schema_callback' => static function () {
					return array();
				},
				'schema_type'     => ARRAY_A,
			)
		);
	}

	/**
	 * Build per-variation price data for the Store API extension.
	 *
	 * @param \WC_Product $product The product object.
	 * @return array<int, array{price: string, regular_price: string, sale_price: string, currency_minor_unit: int, currency_prefix: string, currency_suffix: string}> Keyed by variation ID.
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

		$result = array();

		foreach ( $product->get_visible_children() as $variation_id ) {
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
			);
		}

		return $result;
	}

	/**
	 * Enqueue CSS and register Interactivity API script module on shop/archive pages.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! $this->is_listing_page() ) {
			return;
		}

		$css_file = AGGRESSIVE_APPAREL_DIR . '/build/styles/woocommerce/quick-view.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'aggressive-apparel-quick-view',
				AGGRESSIVE_APPAREL_URI . '/build/styles/woocommerce/quick-view.css',
				array(),
				(string) filemtime( $css_file ),
			);
		}

		$js_file = AGGRESSIVE_APPAREL_DIR . '/assets/interactivity/quick-view.js';
		if ( function_exists( 'wp_register_script_module' ) ) {
			wp_register_script_module(
				'@aggressive-apparel/quick-view',
				AGGRESSIVE_APPAREL_URI . '/assets/interactivity/quick-view.js',
				array( '@wordpress/interactivity', '@aggressive-apparel/scroll-lock', '@aggressive-apparel/helpers' ),
				(string) filemtime( $js_file ),
			);
			wp_enqueue_script_module( '@aggressive-apparel/quick-view' );
		}
	}

	/**
	 * Inject the quick-view trigger button onto product card images.
	 *
	 * @param string $block_content Block HTML.
	 * @param array  $block         Block data.
	 * @return string Modified HTML.
	 */
	public function inject_trigger_button( string $block_content, array $block ): string {
		if ( ! isset( $block['blockName'] ) || 'core/post-featured-image' !== $block['blockName'] ) {
			return $block_content;
		}

		if ( ! $this->is_listing_page() ) {
			return $block_content;
		}

		$product = $this->get_current_product();
		if ( ! $product ) {
			return $block_content;
		}

		$icon = Icons::get(
			'eye',
			array(
				'width'       => 20,
				'height'      => 20,
				'aria-hidden' => 'true',
			)
		);

		$button = sprintf(
			'<button type="button" class="aggressive-apparel-quick-view__trigger" data-wp-interactive="aggressive-apparel/quick-view" data-wp-on--click="actions.open" data-wp-context=\'{"productId":%d}\' aria-label="%s">%s</button>',
			$product->get_id(),
			esc_attr(
				sprintf(
					/* translators: %s: product name. */
					__( 'Quick view %s', 'aggressive-apparel' ),
					$product->get_name(),
				),
			),
			$icon,
		);

		// Append the button inside the figure/div wrapper.
		return preg_replace(
			'/(<\/(?:figure|div)>\s*)$/i',
			$button . '$1',
			$block_content,
			1,
		) ?? $block_content;
	}

	/**
	 * Render the modal shell in the footer.
	 *
	 * Uses individual data-wp-bind and data-wp-text directives on each element
	 * because the Interactivity API does not support a data-wp-html directive.
	 *
	 * @return void
	 */
	public function render_modal_shell(): void {
		if ( ! $this->is_listing_page() ) {
			return;
		}

		// Provide initial state values to the Interactivity API store.
		if ( function_exists( 'wp_interactivity_state' ) ) {
			wp_interactivity_state(
				'aggressive-apparel/quick-view',
				array(
					'restBase'            => esc_url_raw( rest_url( 'wc/store/v1/products/' ) ),
					'cartApiUrl'          => esc_url_raw( rest_url( 'wc/store/v1/cart' ) ),
					'isOpen'              => false,
					'isLoading'           => false,
					'hasError'            => false,
					'hasProduct'          => false,
					'productImage'        => '',
					'productImageAlt'     => '',
					'productName'         => '',
					'productPrice'        => '',
					'productRegularPrice' => '',
					'productOnSale'       => false,
					'productDescription'  => '',
					'productLink'         => '',
					'productType'         => 'simple',
					'productAttributes'   => array(),
					'productVariations'   => array(),
					'selectedAttributes'  => (object) array(),
					'matchedVariationId'  => 0,
					'quantity'            => 1,
					'cartNonce'           => wp_create_nonce( 'wc_store_api' ),
					'isAddingToCart'      => false,
					'addedToCart'         => false,
					'cartError'           => '',

					// Gallery support.
					'productImages'       => array(),
					'activeImageIndex'    => 0,

					// Stock status.
					'stockStatus'         => 'instock',
					'stockQuantity'       => null,
					'stockStatusLabel'    => '',

					// Sale badge.
					'salePercentage'      => 0,

					// Color swatch data.
					'colorSwatchData'     => $this->get_color_swatch_data(),

					// Post-cart actions.
					'showPostCartActions' => false,
					'cartUrl'             => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '/cart/',

					// Mobile drawer.
					'isDrawerOpen'        => false,
					'drawerView'          => 'selection',

					// Accessibility.
					'announcement'        => '',
				),
			);
		}

		// phpcs:disable Generic.WhiteSpace.ScopeIndent -- Inline HTML output.
		?>
		<div
			id="aggressive-apparel-quick-view"
			class="aggressive-apparel-quick-view"
			data-wp-interactive="aggressive-apparel/quick-view"
			data-wp-class--is-open="state.isOpen"
			data-wp-on-document--keydown="actions.handleKeydown"
			hidden
		>
			<div class="aggressive-apparel-quick-view__backdrop" data-wp-on--click="actions.close"></div>

			<div
				class="aggressive-apparel-quick-view__modal"
				role="dialog"
				aria-modal="true"
				aria-labelledby="aggressive-apparel-quick-view-title"
			>
				<button
					type="button"
					class="aggressive-apparel-quick-view__close"
					data-wp-on--click="actions.close"
					aria-label="<?php echo esc_attr__( 'Close quick view', 'aggressive-apparel' ); ?>"
				>
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</button>

				<!-- Skeleton loading state. -->
				<div
					class="aggressive-apparel-quick-view__skeleton"
					data-wp-bind--hidden="state.isNotLoading"
					aria-hidden="true"
				>
					<div class="aggressive-apparel-quick-view__skeleton-image"></div>
					<div class="aggressive-apparel-quick-view__skeleton-details">
						<div class="aggressive-apparel-quick-view__skeleton-line aggressive-apparel-quick-view__skeleton-line--title"></div>
						<div class="aggressive-apparel-quick-view__skeleton-line aggressive-apparel-quick-view__skeleton-line--price"></div>
						<div class="aggressive-apparel-quick-view__skeleton-line"></div>
						<div class="aggressive-apparel-quick-view__skeleton-line"></div>
						<div class="aggressive-apparel-quick-view__skeleton-line aggressive-apparel-quick-view__skeleton-line--short"></div>
					</div>
				</div>

				<!-- Product content — shown when data is loaded. -->
				<div
					class="aggressive-apparel-quick-view__content"
					data-wp-bind--hidden="state.hasNoProduct"
					hidden
				>
					<!-- Gallery section (60% width). -->
					<div class="aggressive-apparel-quick-view__gallery">
						<div
							class="aggressive-apparel-quick-view__main-image"
							data-wp-on--touchstart="actions.handleTouchStart"
							data-wp-on--touchmove="actions.handleTouchMove"
							data-wp-on--touchend="actions.handleTouchEnd"
						>
							<img
								class="no-lazy"
								src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="
								alt=""
								data-wp-watch="callbacks.syncCurrentImage"
							/>
							<!-- Sale badge. -->
							<span
								class="aggressive-apparel-quick-view__sale-badge"
								data-wp-bind--hidden="state.isNotOnSale"
								data-wp-text="state.saleBadgeText"
								hidden
							></span>
						</div>

						<!-- Thumbnail navigation with arrows. -->
						<div
							class="aggressive-apparel-quick-view__thumbnail-nav"
							data-wp-bind--hidden="state.hasOneImage"
							hidden
						>
							<button
								type="button"
								class="aggressive-apparel-quick-view__thumb-arrow aggressive-apparel-quick-view__thumb-arrow--prev"
								data-wp-on--click="actions.scrollThumbnails"
								data-scroll-dir="left"
								data-wp-bind--hidden="state.thumbnailsFitContainer"
								aria-label="<?php echo esc_attr__( 'Scroll thumbnails left', 'aggressive-apparel' ); ?>"
								hidden
							>
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::get() returns safe SVG.
								echo Icons::get(
									'chevron-left',
									array(
										'width'  => 16,
										'height' => 16,
									)
								);
								?>
							</button>
							<div class="aggressive-apparel-quick-view__thumbnails">
								<template data-wp-each="state.productImages">
									<button
										type="button"
										class="aggressive-apparel-quick-view__thumbnail"
										data-wp-on--click="actions.selectImage"
										data-wp-class--is-active="state.isActiveImage"
										aria-label="<?php echo esc_attr__( 'View image', 'aggressive-apparel' ); ?>"
									>
										<img
											class="no-lazy"
											src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="
											alt=""
											data-wp-watch="callbacks.syncThumbnail"
										/>
									</button>
								</template>
							</div>
							<button
								type="button"
								class="aggressive-apparel-quick-view__thumb-arrow aggressive-apparel-quick-view__thumb-arrow--next"
								data-wp-on--click="actions.scrollThumbnails"
								data-scroll-dir="right"
								data-wp-bind--hidden="state.thumbnailsFitContainer"
								aria-label="<?php echo esc_attr__( 'Scroll thumbnails right', 'aggressive-apparel' ); ?>"
								hidden
							>
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::get() returns safe SVG.
								echo Icons::get(
									'chevron-right',
									array(
										'width'  => 16,
										'height' => 16,
									)
								);
								?>
							</button>
						</div>

						<!-- Dot indicators — mobile alternative to thumbnails. -->
						<div
							class="aggressive-apparel-quick-view__dots"
							data-wp-bind--hidden="state.hasOneImage"
							hidden
						>
							<template data-wp-each="state.productImages">
								<button
									type="button"
									class="aggressive-apparel-quick-view__dot"
									data-wp-on--click="actions.selectImage"
									data-wp-class--is-active="state.isActiveImage"
									aria-label="<?php echo esc_attr__( 'View image', 'aggressive-apparel' ); ?>"
								></button>
							</template>
						</div>
					</div>

					<!-- Details section (40% width). -->
					<div class="aggressive-apparel-quick-view__details">
						<h2
							id="aggressive-apparel-quick-view-title"
							class="aggressive-apparel-quick-view__name"
							data-wp-text="state.productName"
						></h2>

						<?php if ( Feature_Settings::is_enabled( 'stock_status' ) ) : ?>
						<!-- Stock status indicator (toggled via Store Enhancements). -->
						<div
							class="aggressive-apparel-quick-view__stock"
							data-wp-class--is-in-stock="state.isInStock"
							data-wp-class--is-low-stock="state.isLowStock"
							data-wp-class--is-out-of-stock="state.isOutOfStock"
						>
							<span class="aggressive-apparel-quick-view__stock-dot"></span>
							<span
								class="aggressive-apparel-quick-view__stock-label"
								data-wp-text="state.stockStatusLabel"
							></span>
						</div>
						<?php endif; ?>

						<div class="aggressive-apparel-quick-view__price">
							<!-- Regular price (visible only when on sale). -->
							<span
								class="aggressive-apparel-quick-view__price-regular"
								data-wp-text="state.productRegularPrice"
								data-wp-bind--hidden="state.isNotOnSale"
								hidden
							></span>
							<!-- Current/sale price. -->
							<span
								class="aggressive-apparel-quick-view__price-current"
								data-wp-text="state.productPrice"
							></span>
						</div>

						<!-- Short description (hidden on mobile to save space). -->
						<p
							class="aggressive-apparel-quick-view__description"
							data-wp-text="state.productDescription"
						></p>

						<!-- Bottom group: cart actions pushed to bottom. -->
						<div class="aggressive-apparel-quick-view__bottom-group">

						<!-- Cart actions. -->
						<div class="aggressive-apparel-quick-view__actions">
							<!-- Quantity + action button row. -->
							<div class="aggressive-apparel-quick-view__cart-row" data-wp-bind--hidden="state.hideInlineCartRow">
								<div class="aggressive-apparel-quick-view__quantity" data-wp-bind--hidden="state.hideInlineAddToCart">
									<button
										type="button"
										class="aggressive-apparel-quick-view__qty-btn"
										data-wp-on--click="actions.decrementQty"
										aria-label="<?php echo esc_attr__( 'Decrease quantity', 'aggressive-apparel' ); ?>"
									>&minus;</button>
									<input
										type="number"
										class="aggressive-apparel-quick-view__qty-input"
										min="1"
										data-wp-bind--value="state.quantity"
										data-wp-on--change="actions.setQuantity"
										aria-label="<?php echo esc_attr__( 'Quantity', 'aggressive-apparel' ); ?>"
									/>
									<button
										type="button"
										class="aggressive-apparel-quick-view__qty-btn"
										data-wp-on--click="actions.incrementQty"
										aria-label="<?php echo esc_attr__( 'Increase quantity', 'aggressive-apparel' ); ?>"
									>&plus;</button>
								</div>

								<button
									type="button"
									class="aggressive-apparel-quick-view__add-to-cart"
									data-wp-on--click="actions.addToCart"
									data-wp-bind--disabled="state.cannotAddToCart"
									data-wp-bind--hidden="state.hideInlineAddToCart"
									data-wp-text="state.addToCartLabel"
									data-wp-class--is-adding="state.isAddingToCart"
									data-wp-class--is-success="state.isCartSuccess"
								><?php echo esc_html__( 'Add to Cart', 'aggressive-apparel' ); ?></button>

								<!-- Select Options — replaces Add to Cart for variable products. -->
								<button
									type="button"
									class="aggressive-apparel-quick-view__select-options"
									data-wp-on--click="actions.openDrawer"
									data-wp-bind--hidden="state.hideSelectOptionsBtn"
									hidden
								><?php echo esc_html__( 'Select Options', 'aggressive-apparel' ); ?></button>
							</div>

							<!-- Post-cart actions panel. -->
							<div
								class="aggressive-apparel-quick-view__post-cart"
								data-wp-bind--hidden="state.hidePostCartActions"
								hidden
							>
								<p class="aggressive-apparel-quick-view__post-cart-message">
									<?php echo esc_html__( 'Added to cart!', 'aggressive-apparel' ); ?>
								</p>
								<div class="aggressive-apparel-quick-view__post-cart-actions">
									<button
										type="button"
										class="aggressive-apparel-quick-view__btn aggressive-apparel-quick-view__btn--continue"
										data-wp-on--click="actions.continueShopping"
									><?php echo esc_html__( 'Continue Shopping', 'aggressive-apparel' ); ?></button>
									<a
										href="#"
										class="aggressive-apparel-quick-view__btn aggressive-apparel-quick-view__btn--view-cart"
										data-wp-bind--href="state.cartUrl"
									><?php echo esc_html__( 'View Cart', 'aggressive-apparel' ); ?></a>
								</div>
							</div>

							<!-- Cart error. -->
							<p
								class="aggressive-apparel-quick-view__cart-error"
								data-wp-bind--hidden="state.hasNoCartError"
								data-wp-text="state.cartError"
								hidden
							></p>
						</div>

						<!-- View Full Product. -->
						<a
							href="#"
							class="aggressive-apparel-quick-view__link"
							data-wp-bind--href="state.productLink"
						><?php echo esc_html__( 'View Full Product', 'aggressive-apparel' ); ?></a>

						</div><!-- /.aggressive-apparel-quick-view__bottom-group -->
					</div>
				</div>

				<!-- Mobile bottom drawer for options + success. -->
				<div
					class="aggressive-apparel-quick-view__drawer"
					data-wp-class--is-open="state.isDrawerOpen"
					data-wp-bind--hidden="state.isDrawerClosed"
					hidden
				>
					<div class="aggressive-apparel-quick-view__drawer-scrim" data-wp-on--click="actions.closeDrawer"></div>
					<div
						class="aggressive-apparel-quick-view__drawer-panel"
						role="dialog"
						aria-label="<?php echo esc_attr__( 'Select product options', 'aggressive-apparel' ); ?>"
					>
						<!-- Selection view (default). -->
						<div
							class="aggressive-apparel-quick-view__drawer-selection"
							data-wp-bind--hidden="state.isDrawerSuccessView"
						>
							<!-- Large product image — visible on desktop (left column). -->
							<div class="aggressive-apparel-quick-view__drawer-image">
								<img
									class="no-lazy"
									src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="
									alt=""
									data-wp-watch="callbacks.syncCurrentImage"
								/>
							</div>

							<!-- Product header row. -->
							<div class="aggressive-apparel-quick-view__drawer-header">
								<img
									class="aggressive-apparel-quick-view__drawer-thumb no-lazy"
									src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="
									alt=""
									data-wp-watch="callbacks.syncCurrentImage"
								/>
								<div class="aggressive-apparel-quick-view__drawer-product-info">
									<span
										class="aggressive-apparel-quick-view__drawer-name"
										data-wp-text="state.productName"
									></span>
									<span class="aggressive-apparel-quick-view__drawer-price">
										<del
											class="aggressive-apparel-quick-view__price-regular"
											data-wp-text="state.productRegularPrice"
											data-wp-bind--hidden="state.isNotOnSale"
											hidden
										></del>
										<ins
											class="aggressive-apparel-quick-view__price-current"
											data-wp-text="state.productPrice"
										></ins>
									</span>
								</div>
							</div>

							<!-- Attribute selectors (duplicated from inline — shared state). -->
							<div class="aggressive-apparel-quick-view__drawer-body">
								<template data-wp-each="state.productAttributes">
									<div class="aggressive-apparel-quick-view__attribute">
										<!-- Color attributes: shrink-reveal swatches. -->
										<div data-wp-bind--hidden="state.isNotColorAttribute">
											<span
												class="aggressive-apparel-quick-view__attribute-label"
												data-wp-text="context.item.name"
											></span>
											<div class="aggressive-apparel-quick-view__attribute-options is-color-attribute" role="group" data-wp-bind--aria-label="context.item.name">
												<template data-wp-each="context.item.options">
													<button
														type="button"
														class="aggressive-apparel-quick-view__attribute-option is-color-swatch"
														data-wp-on--click="actions.selectAttribute"
														data-wp-class--is-selected="state.isOptionSelected"
														data-wp-style--background-color="state.colorSwatchValue"
														data-wp-init="callbacks.syncSwatchColor"
														data-wp-bind--title="state.colorSwatchName"
														data-wp-bind--aria-label="state.colorSwatchName"
														data-wp-bind--aria-pressed="state.isOptionSelected"
													><span class="screen-reader-text" data-wp-text="state.colorSwatchName"></span></button>
												</template>
											</div>
										</div>
										<!-- Non-color attributes: morphing pill buttons. -->
										<div data-wp-bind--hidden="state.isColorAttribute">
											<span
												class="aggressive-apparel-quick-view__attribute-label"
												data-wp-text="context.item.name"
											></span>
											<div class="aggressive-apparel-quick-view__attribute-options" role="group" data-wp-bind--aria-label="context.item.name">
												<template data-wp-each="context.item.options">
													<button
														type="button"
														class="aggressive-apparel-quick-view__attribute-option"
														data-wp-on--click="actions.selectAttribute"
														data-wp-class--is-selected="state.isOptionSelected"
														data-wp-bind--aria-pressed="state.isOptionSelected"
													><span class="aggressive-apparel-quick-view__option-check" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><polyline points="2.5 6.5 5 9 9.5 3.5"/></svg></span><span class="aggressive-apparel-quick-view__option-name" data-wp-text="context.item.name"></span></button>
												</template>
											</div>
										</div>
									</div>
								</template>
							</div>

							<!-- Footer: qty + add to cart. -->
							<div class="aggressive-apparel-quick-view__drawer-footer">
								<div class="aggressive-apparel-quick-view__quantity">
									<button
										type="button"
										class="aggressive-apparel-quick-view__qty-btn"
										data-wp-on--click="actions.decrementQty"
										aria-label="<?php echo esc_attr__( 'Decrease quantity', 'aggressive-apparel' ); ?>"
									>&minus;</button>
									<input
										type="number"
										class="aggressive-apparel-quick-view__qty-input"
										min="1"
										data-wp-bind--value="state.quantity"
										data-wp-on--change="actions.setQuantity"
										aria-label="<?php echo esc_attr__( 'Quantity', 'aggressive-apparel' ); ?>"
									/>
									<button
										type="button"
										class="aggressive-apparel-quick-view__qty-btn"
										data-wp-on--click="actions.incrementQty"
										aria-label="<?php echo esc_attr__( 'Increase quantity', 'aggressive-apparel' ); ?>"
									>&plus;</button>
								</div>

								<button
									type="button"
									class="aggressive-apparel-quick-view__add-to-cart"
									data-wp-on--click="actions.addToCart"
									data-wp-bind--disabled="state.cannotAddToCart"
									data-wp-text="state.addToCartLabel"
									data-wp-class--is-adding="state.isAddingToCart"
									data-wp-class--is-success="state.isCartSuccess"
								><?php echo esc_html__( 'Add to Cart', 'aggressive-apparel' ); ?></button>

								<a
									href="#"
									class="aggressive-apparel-quick-view__drawer-view-product"
									data-wp-bind--href="state.productLink"
								><?php echo esc_html__( 'View Full Product', 'aggressive-apparel' ); ?></a>

								<!-- Cart error (shown inside drawer). -->
								<p
									class="aggressive-apparel-quick-view__cart-error"
									data-wp-bind--hidden="state.hasNoCartError"
									data-wp-text="state.cartError"
									hidden
								></p>
							</div>
						</div>

						<!-- Success view (shown after add-to-cart). -->
						<div
							class="aggressive-apparel-quick-view__drawer-success"
							data-wp-bind--hidden="state.hideDrawerSuccess"
							hidden
						>
							<div class="aggressive-apparel-quick-view__drawer-success-icon">&#10003;</div>
							<p class="aggressive-apparel-quick-view__drawer-success-message">
								<?php echo esc_html__( 'Added to cart!', 'aggressive-apparel' ); ?>
							</p>
							<div class="aggressive-apparel-quick-view__drawer-success-product">
								<img
									class="aggressive-apparel-quick-view__drawer-thumb no-lazy"
									src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="
									alt=""
									data-wp-watch="callbacks.syncCurrentImage"
								/>
								<div class="aggressive-apparel-quick-view__drawer-product-info">
									<span data-wp-text="state.productName"></span>
									<span data-wp-text="state.selectedOptionsLabel"></span>
								</div>
							</div>
							<div class="aggressive-apparel-quick-view__drawer-success-actions">
								<button
									type="button"
									class="aggressive-apparel-quick-view__btn aggressive-apparel-quick-view__btn--continue"
									data-wp-on--click="actions.continueShopping"
								><?php echo esc_html__( 'Continue Shopping', 'aggressive-apparel' ); ?></button>
								<a
									href="#"
									class="aggressive-apparel-quick-view__btn aggressive-apparel-quick-view__btn--view-cart"
									data-wp-bind--href="state.cartUrl"
								><?php echo esc_html__( 'View Cart', 'aggressive-apparel' ); ?></a>
							</div>
						</div>
					</div>
				</div>

				<!-- Error state. -->
				<div
					class="aggressive-apparel-quick-view__error"
					data-wp-bind--hidden="state.hasNoError"
					hidden
				>
					<p><?php echo esc_html__( 'Could not load product details. Please try again.', 'aggressive-apparel' ); ?></p>
				</div>
			</div>

			<!-- Screen reader announcements. -->
			<div
				class="aggressive-apparel-quick-view__announcer"
				role="status"
				aria-live="polite"
				aria-atomic="true"
				data-wp-text="state.announcement"
			></div>
		</div>
		<?php
		// phpcs:enable Generic.WhiteSpace.ScopeIndent
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
	 * Check if the current page is a product listing.
	 *
	 * @return bool
	 */
	private function is_listing_page(): bool {
		if ( ! function_exists( 'is_shop' ) ) {
			return false;
		}
		return is_shop() || is_product_category() || is_product_tag() || is_search();
	}

	/**
	 * Get color swatch data for all color terms.
	 *
	 * @return array<string, array{value: string, type: string, name: string}>
	 */
	private function get_color_swatch_data(): array {
		return Color_Data_Manager::get_safe_swatch_data();
	}
}
