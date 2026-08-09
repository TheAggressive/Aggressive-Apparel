<?php
/**
 * Quick View Renderer
 *
 * Emits the card action stack (Quick View + Wishlist triggers) and the modal
 * shell markup. Split out of Quick_View so the coordinator class stays focused
 * on hooks, Store API data, and asset loading.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Core\Icons;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Quick View card triggers and modal shell.
 */
class Quick_View_Renderer {

	/**
	 * Build the card media action stack markup for a product.
	 *
	 * @param \WC_Product $product Product instance.
	 * @return string
	 */
	public function build_card_actions_markup( \WC_Product $product ): string {
		$product_id = $product->get_id();
		if ( $product_id <= 0 ) {
			return '';
		}

		$style    = Feature_Settings::get_quick_view_trigger_style();
		$position = Feature_Settings::get_quick_view_trigger_position();

		$allowed_styles = array( 'corner', 'bottom-bar' );
		if ( ! in_array( $style, $allowed_styles, true ) ) {
			$style = 'corner';
		}

		$allowed_positions = array( 'top-right', 'top-left', 'bottom-right', 'bottom-left' );
		if ( ! in_array( $position, $allowed_positions, true ) ) {
			$position = 'top-right';
		}

		$include_wishlist = Feature_Settings::quick_view_includes_wishlist();
		$quick_view_text  = Feature_Settings::get_quick_view_button_text();
		$icon             = Icons::get(
			'eye',
			array(
				'width'       => 18,
				'height'      => 18,
				'aria-hidden' => 'true',
			)
		);

		$context_json = wp_json_encode( array( 'productId' => $product_id ) );
		if ( ! is_string( $context_json ) ) {
			return '';
		}

		// Clicks are handled by capture-phase document delegation in quick-view.js —
		// keep data-wp-interactive + context for product id, omit data-wp-on--click
		// to avoid double-open when hydration succeeds.
		$trigger = sprintf(
			'<button type="button" class="aggressive-apparel-card-action aggressive-apparel-quick-view__trigger aa-icon-button aa-icon-button--only" data-wp-interactive="aggressive-apparel/quick-view" data-wp-context="%1$s" aria-label="%2$s" title="%3$s"><span class="aggressive-apparel-quick-view__trigger-icon" aria-hidden="true">%4$s</span><span class="aggressive-apparel-quick-view__trigger-label" aria-hidden="true">%5$s</span></button>',
			esc_attr( $context_json ),
			esc_attr(
				sprintf(
					/* translators: 1: Quick View label, 2: product name. */
					__( '%1$s: %2$s', 'aggressive-apparel' ),
					$quick_view_text,
					$product->get_name(),
				),
			),
			esc_attr( $quick_view_text ),
			$icon,
			esc_html( $quick_view_text ),
		);

		$wishlist_html = '';
		if ( $include_wishlist ) {
			$wishlist_html = Wishlist::get_heart_button_html(
				$product_id,
				false,
				'aggressive-apparel-card-action aggressive-apparel-wishlist__toggle--card-media'
			);
			Wishlist::mark_button_block_rendered( $product_id );
		}

		$stack_inner = $wishlist_html . $trigger;
		if ( 'bottom-bar' === $style ) {
			$stack_inner = sprintf(
				'<div class="aggressive-apparel-card-actions__bar">%s</div>',
				$stack_inner
			);
		}

		$group_label = $include_wishlist
			? __( 'Product actions', 'aggressive-apparel' )
			: $quick_view_text;

		$stack = sprintf(
			'<div class="aggressive-apparel-card-actions aggressive-apparel-card-actions--%1$s aggressive-apparel-card-actions--%2$s" role="group" aria-label="%3$s">%4$s</div>',
			esc_attr( $style ),
			esc_attr( $position ),
			esc_attr( $group_label ),
			$stack_inner
		);

		/**
		 * Filters the Quick View card media action stack markup.
		 *
		 * @since 1.81.0
		 *
		 * @param string      $stack   Action stack HTML.
		 * @param \WC_Product $product Current product.
		 * @param string      $style   Trigger style slug.
		 * @param string      $position Corner position slug.
		 */
		return (string) apply_filters(
			'aggressive_apparel_quick_view_card_actions_html',
			$stack,
			$product,
			$style,
			$position
		);
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
		if ( ! Product_Context::is_product_display_page() ) {
			return;
		}

		// Provide initial state values to the Interactivity API store.
		if ( function_exists( 'wp_interactivity_state' ) ) {
			$price_config = Price_Display::interactivity_price_config();

			wp_interactivity_state(
				'aggressive-apparel/quick-view',
				array(
					'restBase'              => esc_url_raw( rest_url( 'wc/store/v1/products/' ) ),
					'cartApiUrl'            => esc_url_raw( rest_url( 'wc/store/v1/cart' ) ),

					// Smart Price Display: collapse the variable-product range to
					// a single "From $X" starting price, matching every other
					// surface. The exact variation price replaces it on selection.
					'collapseVariablePrice' => $price_config['collapseVariablePrice'],
					'priceStartingPrefix'   => $price_config['priceStartingPrefix'],

					'isOpen'                => false,
					'isSuccessOpen'         => false,
					'isLoading'             => false,
					'hasError'              => false,
					'hasProduct'            => false,
					'productImage'          => '',
					'productImageAlt'       => '',
					'productName'           => '',
					'productPrice'          => '',
					'productRegularPrice'   => '',
					'productOnSale'         => false,
					'productDescription'    => '',
					'productLink'           => '',
					'productType'           => 'simple',
					'productAttributes'     => array(),
					'productVariations'     => array(),
					'selectedAttributes'    => (object) array(),
					'matchedVariationId'    => 0,
					'quantity'              => 1,
					'cartNonce'             => wp_create_nonce( 'wc_store_api' ),
					'isAddingToCart'        => false,
					'cartError'             => '',

					// Gallery support.
					'productImages'         => array(),
					'activeImageIndex'      => 0,

					// Stock status.
					'stockStatus'           => 'instock',
					'stockQuantity'         => null,
					'stockStatusLabel'      => '',

					// Sale badge.
					'salePercentage'        => 0,

					// Color swatch data.
					'colorSwatchData'       => Color_Data_Manager::get_safe_swatch_data(),

					'cartUrl'               => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '/cart/',
					'checkoutUrl'           => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '/checkout/',
					'isBuyingNow'           => false,

					// Variation options drawer.
					'isDrawerOpen'          => false,

					// Accessibility.
					'announcement'          => '',
					'i18n'                  => array(
						'addToCartText'        => Feature_Settings::get_simple_product_button_text(),
						'addingToCartText'     => __( 'Adding…', 'aggressive-apparel' ),
						'addedToCartText'      => __( '✓ Added!', 'aggressive-apparel' ),
						'outOfStockButtonText' => Feature_Settings::get_out_of_stock_button_text(),
						'variableButtonText'   => Feature_Settings::get_variable_product_button_text(),
						'buyNowText'           => Feature_Settings::get_buy_now_button_text(),
						'redirectingText'      => __( 'Redirecting…', 'aggressive-apparel' ),
						'viewCartText'         => Feature_Settings::get_view_cart_button_text(),
						'continueShoppingText' => Feature_Settings::get_continue_shopping_button_text(),
						'viewProductText'      => Feature_Settings::get_view_product_button_text(),
						'addedToCartMessage'   => __( 'Added to cart!', 'aggressive-apparel' ),
						'outOfStockLabel'      => __( 'Out of Stock', 'aggressive-apparel' ),
						'inStockLabel'         => __( 'In Stock', 'aggressive-apparel' ),
						/* translators: %d: remaining stock quantity. */
						'onlyNLeft'            => __( 'Only %d left!', 'aggressive-apparel' ),
						'addedSuccessAnnounce' => __( 'Product added to cart successfully', 'aggressive-apparel' ),
						'addToCartError'       => __( 'Could not add to cart.', 'aggressive-apparel' ),
						/* translators: %s: error message from the cart API. */
						'errorAnnounce'        => __( 'Error: %s', 'aggressive-apparel' ),
						'unavailableLabel'     => __( 'Unavailable', 'aggressive-apparel' ),
					),
				),
			);
		}

		?>
		<div
			id="aggressive-apparel-quick-view"
			class="aggressive-apparel-overlay aggressive-apparel-quick-view"
			data-wp-interactive="aggressive-apparel/quick-view"
			data-wp-class--is-open="state.isOpen"
			data-wp-on-document--keydown="actions.handleKeydown"
			hidden
		>
			<div class="aggressive-apparel-overlay__backdrop aggressive-apparel-quick-view__backdrop" data-wp-on--click="actions.close"></div>

			<div
				class="aggressive-apparel-panel aggressive-apparel-panel--xl aggressive-apparel-quick-view__modal"
				role="dialog"
				aria-modal="true"
				aria-labelledby="aggressive-apparel-quick-view-title"
			>
				<button
					type="button"
					class="aggressive-apparel-quick-view__close aa-icon-button aa-icon-button--only aa-icon-button--square"
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
								alt=""
								data-wp-bind--alt="state.productName"
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
								class="aggressive-apparel-quick-view__thumb-arrow aggressive-apparel-quick-view__thumb-arrow--prev aa-icon-button aa-icon-button--only"
								data-wp-on--click="actions.scrollThumbnails"
								data-scroll-dir="left"
								data-wp-bind--hidden="state.thumbnailsFitContainer"
								aria-label="<?php echo esc_attr__( 'Scroll thumbnails left', 'aggressive-apparel' ); ?>"
								hidden
							>
								<?php
								aggressive_apparel_render_icon(
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
										data-wp-bind--aria-label="state.imagePositionLabel"
										data-wp-bind--aria-current="state.isActiveImage ? 'true' : null"
									>
									<img
										class="no-lazy"
										alt=""
											data-wp-watch="callbacks.syncThumbnail"
										/>
									</button>
								</template>
							</div>
							<button
								type="button"
								class="aggressive-apparel-quick-view__thumb-arrow aggressive-apparel-quick-view__thumb-arrow--next aa-icon-button aa-icon-button--only"
								data-wp-on--click="actions.scrollThumbnails"
								data-scroll-dir="right"
								data-wp-bind--hidden="state.thumbnailsFitContainer"
								aria-label="<?php echo esc_attr__( 'Scroll thumbnails right', 'aggressive-apparel' ); ?>"
								hidden
							>
								<?php
								aggressive_apparel_render_icon(
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
									data-wp-bind--aria-label="state.imagePositionLabel"
									data-wp-bind--aria-current="state.isActiveImage ? 'true' : null"
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
							<div class="aggressive-apparel-quick-view__cart-row">
								<div class="aggressive-apparel-quick-view__quantity" data-wp-bind--hidden="state.hideInlineAddToCart">
									<button
										type="button"
										class="aggressive-apparel-quick-view__qty-btn aa-stepper-button"
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
										class="aggressive-apparel-quick-view__qty-btn aa-stepper-button"
										data-wp-on--click="actions.incrementQty"
										aria-label="<?php echo esc_attr__( 'Increase quantity', 'aggressive-apparel' ); ?>"
									>&plus;</button>
								</div>

								<button
									type="button"
									class="aggressive-apparel-quick-view__add-to-cart aggressive-apparel-button aggressive-apparel-button--outline aggressive-apparel-button--sm wp-element-button"
									data-wp-on--click="actions.addToCart"
									data-wp-bind--disabled="state.cannotAddToCart"
									data-wp-bind--hidden="state.hideInlineAddToCart"
									data-wp-text="state.addToCartLabel"
									data-wp-class--is-adding="state.isAddingToCart"
									data-wp-class--is-success="state.isCartSuccess"
								><?php echo esc_html( Feature_Settings::get_simple_product_button_text() ); ?></button>

								<button
									type="button"
									class="aggressive-apparel-quick-view__buy-now aggressive-apparel-button aggressive-apparel-button--primary aggressive-apparel-button--sm wp-element-button"
									data-wp-on--click="actions.buyNow"
									data-wp-bind--disabled="state.cannotAddToCart"
									data-wp-bind--hidden="state.hideInlineAddToCart"
									data-wp-text="state.buyNowLabel"
									data-wp-class--is-adding="state.isBuyingNow"
								><?php echo esc_html( Feature_Settings::get_buy_now_button_text() ); ?></button>

								<!-- Select Options — replaces Add to Cart for variable products. -->
								<button
									type="button"
									class="aggressive-apparel-quick-view__select-options aggressive-apparel-button aggressive-apparel-button--primary aggressive-apparel-button--sm wp-element-button"
									data-wp-on--click="actions.openDrawer"
									data-wp-bind--hidden="state.hideSelectOptionsBtn"
									data-wp-text="state.selectOptionsLabel"
									hidden
								><?php echo esc_html( Feature_Settings::get_variable_product_button_text() ); ?></button>
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
							class="aggressive-apparel-quick-view__link aggressive-apparel-button aggressive-apparel-button--text wp-element-button"
							data-wp-bind--href="state.productLink"
							data-wp-text="state.viewProductLabel"
						><?php echo esc_html( Feature_Settings::get_view_product_button_text() ); ?></a>

						</div><!-- /.aggressive-apparel-quick-view__bottom-group -->
					</div>
				</div>

				<!-- Variation options drawer. -->
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
						<div class="aggressive-apparel-quick-view__drawer-selection">
							<!-- Large product image — visible on desktop (left column). -->
							<div class="aggressive-apparel-quick-view__drawer-image">
							<img
								class="no-lazy"
								alt=""
									data-wp-bind--alt="state.productName"
									data-wp-watch="callbacks.syncCurrentImage"
								/>
							</div>

							<!-- Product header row. -->
							<div class="aggressive-apparel-quick-view__drawer-header">
								<img
									class="aggressive-apparel-quick-view__drawer-thumb no-lazy"
									alt=""
									data-wp-bind--alt="state.productName"
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
														class="aggressive-apparel-quick-view__attribute-option is-color-swatch aa-choice-pill"
														data-wp-on--click="actions.selectAttribute"
														data-wp-class--is-selected="state.isOptionSelected"
														data-wp-class--is-unavailable="state.isOptionUnavailable"
														data-wp-bind--aria-disabled="state.isOptionUnavailable"
														data-wp-style--background-color="state.colorSwatchValue"
														data-wp-init="callbacks.syncSwatchColor"
														data-wp-bind--title="state.colorSwatchName"
														data-wp-bind--aria-label="state.optionAccessibleName"
														data-wp-bind--aria-pressed="state.isOptionSelected"
													></button>
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
														class="aggressive-apparel-quick-view__attribute-option aa-choice-pill"
														data-wp-on--click="actions.selectAttribute"
														data-wp-class--is-selected="state.isOptionSelected"
														data-wp-class--is-unavailable="state.isOptionUnavailable"
														data-wp-bind--aria-disabled="state.isOptionUnavailable"
														data-wp-bind--aria-label="state.optionAccessibleName"
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
											class="aggressive-apparel-quick-view__qty-btn aa-stepper-button"
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
											class="aggressive-apparel-quick-view__qty-btn aa-stepper-button"
										data-wp-on--click="actions.incrementQty"
										aria-label="<?php echo esc_attr__( 'Increase quantity', 'aggressive-apparel' ); ?>"
									>&plus;</button>
								</div>

								<button
									type="button"
									class="aggressive-apparel-quick-view__add-to-cart aggressive-apparel-button aggressive-apparel-button--outline aggressive-apparel-button--sm wp-element-button"
									data-wp-on--click="actions.addToCart"
									data-wp-bind--disabled="state.cannotAddToCart"
									data-wp-text="state.addToCartLabel"
									data-wp-class--is-adding="state.isAddingToCart"
									data-wp-class--is-success="state.isCartSuccess"
								><?php echo esc_html( Feature_Settings::get_simple_product_button_text() ); ?></button>

								<button
									type="button"
									class="aggressive-apparel-quick-view__buy-now aggressive-apparel-button aggressive-apparel-button--primary aggressive-apparel-button--sm wp-element-button"
									data-wp-on--click="actions.buyNow"
									data-wp-bind--disabled="state.cannotAddToCart"
									data-wp-text="state.buyNowLabel"
									data-wp-class--is-adding="state.isBuyingNow"
								><?php echo esc_html( Feature_Settings::get_buy_now_button_text() ); ?></button>

								<a
									href="#"
									class="aggressive-apparel-quick-view__drawer-view-product aggressive-apparel-button aggressive-apparel-button--text wp-element-button"
									data-wp-bind--href="state.productLink"
									data-wp-text="state.viewProductLabel"
								><?php echo esc_html( Feature_Settings::get_view_product_button_text() ); ?></a>

								<!-- Cart error (shown inside drawer). -->
								<p
									class="aggressive-apparel-quick-view__cart-error"
									data-wp-bind--hidden="state.hasNoCartError"
									data-wp-text="state.cartError"
									hidden
								></p>
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

		<!-- Standalone add-to-cart confirmation. -->
		<div
			id="aggressive-apparel-cart-success"
			class="aggressive-apparel-overlay aggressive-apparel-quick-view-success"
			data-wp-interactive="aggressive-apparel/quick-view"
			data-wp-class--is-open="state.isSuccessOpen"
			hidden
		>
			<div
				class="aggressive-apparel-overlay__backdrop aggressive-apparel-quick-view-success__backdrop"
				data-wp-on--click="actions.closeCartSuccess"
			></div>

			<div
				class="aggressive-apparel-panel aggressive-apparel-panel--md aggressive-apparel-quick-view-success__panel"
				role="dialog"
				aria-modal="true"
				aria-labelledby="aggressive-apparel-cart-success-title"
				aria-describedby="aggressive-apparel-cart-success-product"
			>
				<button
					type="button"
					class="aggressive-apparel-quick-view-success__close aa-icon-button aa-icon-button--only aa-icon-button--square"
					data-wp-on--click="actions.closeCartSuccess"
					aria-label="<?php echo esc_attr__( 'Close cart confirmation', 'aggressive-apparel' ); ?>"
				>
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</button>

				<div class="aggressive-apparel-quick-view-success__icon" aria-hidden="true">&#10003;</div>
				<h2
					id="aggressive-apparel-cart-success-title"
					class="aggressive-apparel-quick-view-success__title"
					data-wp-text="state.addedToCartMessage"
				><?php esc_html_e( 'Added to cart!', 'aggressive-apparel' ); ?></h2>

				<div
					id="aggressive-apparel-cart-success-product"
					class="aggressive-apparel-quick-view-success__product"
				>
					<img
						class="aggressive-apparel-quick-view-success__image no-lazy"
						alt=""
						data-wp-watch="callbacks.syncCurrentImage"
					/>
					<div class="aggressive-apparel-quick-view-success__product-info">
						<span
							class="aggressive-apparel-quick-view-success__product-name"
							data-wp-text="state.productName"
						></span>
						<span
							class="aggressive-apparel-quick-view-success__options"
							data-wp-text="state.selectedOptionsLabel"
						></span>
					</div>
				</div>

				<div class="aggressive-apparel-quick-view-success__actions">
					<button
						type="button"
						class="aggressive-apparel-quick-view-success__button aggressive-apparel-quick-view-success__button--continue aggressive-apparel-button aggressive-apparel-button--outline aggressive-apparel-button--sm wp-element-button"
						data-wp-on--click="actions.closeCartSuccess"
						data-wp-text="state.continueShoppingLabel"
					><?php echo esc_html( Feature_Settings::get_continue_shopping_button_text() ); ?></button>
					<a
						href="#"
						class="aggressive-apparel-quick-view-success__button aggressive-apparel-quick-view-success__button--view-cart aggressive-apparel-button aggressive-apparel-button--primary aggressive-apparel-button--sm wp-element-button"
						data-wp-bind--href="state.cartUrl"
						data-wp-text="state.viewCartLabel"
					><?php echo esc_html( Feature_Settings::get_view_cart_button_text() ); ?></a>
				</div>
			</div>
		</div>
		<?php
	}
}
