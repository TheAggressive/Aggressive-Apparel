<?php
/**
 * Sticky Add to Cart Class
 *
 * Renders a fixed bar at the bottom of the viewport on single product
 * pages when the main add-to-cart button scrolls out of view. Shows
 * product info, variant selector, and an add-to-cart CTA.
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
 * Sticky Add to Cart
 *
 * @since 1.18.0
 */
class Sticky_Add_To_Cart {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_sticky_bar' ) );
	}

	/**
	 * Enqueue styles and register Interactivity API script module on product pages.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! $this->is_product_page() ) {
			return;
		}

		$css_file = AGGRESSIVE_APPAREL_DIR . '/build/styles/woocommerce/sticky-add-to-cart.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'aggressive-apparel-sticky-add-to-cart',
				AGGRESSIVE_APPAREL_URI . '/build/styles/woocommerce/sticky-add-to-cart.css',
				array(),
				(string) filemtime( $css_file ),
			);
		}

		if ( function_exists( 'wp_register_script_module' ) ) {
			wp_register_script_module(
				'@aggressive-apparel/sticky-add-to-cart',
				AGGRESSIVE_APPAREL_URI . '/assets/interactivity/sticky-add-to-cart.js',
				array( '@wordpress/interactivity' ),
				AGGRESSIVE_APPAREL_VERSION,
			);
			wp_enqueue_script_module( '@aggressive-apparel/sticky-add-to-cart' );
		}
	}

	/**
	 * Render the sticky add-to-cart bar in the footer.
	 *
	 * @return void
	 */
	public function render_sticky_bar(): void {
		if ( ! $this->is_product_page() ) {
			return;
		}

		$product = wc_get_product( get_the_ID() );
		if ( ! $product ) {
			return;
		}

		$product_data = $this->get_product_data( $product );

		if ( function_exists( 'wp_interactivity_state' ) ) {
			wp_interactivity_state(
				'aggressive-apparel/sticky-add-to-cart',
				array(
					'productId'          => $product->get_id(),
					'productType'        => $product->get_type(),
					'isVisible'          => false,
					'isAdding'           => false,
					'isSuccess'          => false,
					'hasError'           => false,
					'displayPrice'       => $product_data['price_html'],
					'cartApiUrl'         => esc_url_raw( rest_url( 'wc/store/v1/cart/add-item' ) ),
					'nonce'              => wp_create_nonce( 'wc_store_api' ),
					'variations'         => $product_data['variations'],
					'attributes'         => $product_data['attributes'],
					'selectedAttrs'      => $product_data['default_attrs'],
					'matchedVariationId' => 0,
					'quantity'           => 1,
					'isDrawerOpen'       => false,
				),
			);
		}

		$thumbnail = $product->get_image(
			'woocommerce_gallery_thumbnail',
			array(
				'class'   => 'aa-sticky-cart__image',
				'loading' => 'lazy',
			)
		);
		?>

		<div
			class="aa-sticky-cart"
			data-wp-interactive="aggressive-apparel/sticky-add-to-cart"
			data-wp-init="callbacks.init"
			data-wp-class--is-visible="state.isVisible"
			aria-hidden="true"
			data-wp-bind--aria-hidden="state.ariaHidden"
		>
			<div class="aa-sticky-cart__inner">
				<div class="aa-sticky-cart__product">
					<?php echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce generates safe HTML. ?>
					<div class="aa-sticky-cart__info">
						<span class="aa-sticky-cart__title"><?php echo esc_html( $product->get_name() ); ?></span>
						<span class="aa-sticky-cart__price" data-wp-text="state.displayPrice">
							<?php echo $product_data['price_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce generates safe HTML. ?>
						</span>
					</div>
				</div>

				<div class="aa-sticky-cart__actions">
					<div class="aa-sticky-cart__quantity">
						<button
							type="button"
							class="aa-sticky-cart__qty-btn"
							data-wp-on--click="actions.decrementQty"
							aria-label="<?php esc_attr_e( 'Decrease quantity', 'aggressive-apparel' ); ?>"
						>&minus;</button>
						<input
							type="number"
							class="aa-sticky-cart__qty-input"
							min="1"
							value="1"
							data-wp-bind--value="state.quantity"
							data-wp-on--change="actions.setQuantity"
							aria-label="<?php esc_attr_e( 'Quantity', 'aggressive-apparel' ); ?>"
						/>
						<button
							type="button"
							class="aa-sticky-cart__qty-btn"
							data-wp-on--click="actions.incrementQty"
							aria-label="<?php esc_attr_e( 'Increase quantity', 'aggressive-apparel' ); ?>"
						>&plus;</button>
					</div>

					<button
						type="button"
						class="aa-sticky-cart__button"
						data-wp-on--click="actions.addToCart"
						data-wp-class--is-loading="state.isAdding"
						data-wp-class--is-success="state.isSuccess"
						data-wp-bind--disabled="state.isAddDisabled"
						<?php if ( ! $product->is_in_stock() ) : ?>
							disabled
						<?php endif; ?>
					>
						<span class="aa-sticky-cart__button-text" data-wp-text="state.buttonText">
							<?php echo $product->is_in_stock() ? esc_html__( 'Add to Cart', 'aggressive-apparel' ) : esc_html__( 'Out of Stock', 'aggressive-apparel' ); ?>
						</span>
					</button>
				</div>
			</div>
		</div>

		<?php if ( $product->is_type( 'variable' ) && ! empty( $product_data['attributes'] ) ) : ?>
			<div
				class="aa-sticky-cart__drawer"
				data-wp-interactive="aggressive-apparel/sticky-add-to-cart"
				hidden
				role="dialog"
				aria-label="<?php esc_attr_e( 'Select product options', 'aggressive-apparel' ); ?>"
			>
				<div class="aa-sticky-cart__drawer-backdrop" data-wp-on--click="actions.closeDrawer"></div>
				<div class="aa-sticky-cart__drawer-panel">
					<div class="aa-sticky-cart__drawer-handle" aria-hidden="true"></div>
					<div class="aa-sticky-cart__drawer-header">
						<?php
						echo $product->get_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce generates safe HTML.
							'woocommerce_gallery_thumbnail',
							array( 'class' => 'aa-sticky-cart__drawer-image' )
						);
						?>
						<div class="aa-sticky-cart__drawer-info">
							<span class="aa-sticky-cart__drawer-title"><?php echo esc_html( $product->get_name() ); ?></span>
							<span class="aa-sticky-cart__drawer-price" data-wp-text="state.displayPrice">
								<?php echo $product_data['price_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce generates safe HTML. ?>
							</span>
						</div>
						<button
							type="button"
							class="aa-sticky-cart__drawer-close"
							data-wp-on--click="actions.closeDrawer"
							aria-label="<?php esc_attr_e( 'Close', 'aggressive-apparel' ); ?>"
						>
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
								<line x1="18" y1="6" x2="6" y2="18" />
								<line x1="6" y1="6" x2="18" y2="18" />
							</svg>
						</button>
					</div>

					<div class="aa-sticky-cart__drawer-body">
						<?php foreach ( $product_data['attributes'] as $attr ) : ?>
							<div class="aa-sticky-cart__drawer-attribute">
								<span class="aa-sticky-cart__drawer-attribute-label"><?php echo esc_html( $attr['label'] ); ?></span>
								<div class="aa-sticky-cart__drawer-options<?php echo $attr['is_color'] ? ' is-color-attribute' : ''; ?>" role="group" aria-label="<?php echo esc_attr( $attr['label'] ); ?>">
									<?php foreach ( $attr['options'] as $option ) : ?>
										<?php if ( $attr['is_color'] && ! empty( $attr['swatch_data'][ $option ] ) ) : ?>
											<?php
											$swatch       = $attr['swatch_data'][ $option ];
											$swatch_name  = $swatch['name'];
											$is_pattern   = 'pattern' === ( $swatch['type'] ?? 'solid' );
											$swatch_style = $is_pattern
												? 'background-image: url(' . esc_url( $swatch['value'] ) . '); background-size: cover; background-position: center;'
												: 'background-color: ' . esc_attr( $swatch['value'] ) . ';';
											?>
											<button
												type="button"
												class="aa-sticky-cart__drawer-option is-color-swatch"
												data-wp-on--click="actions.selectDrawerOption"
												data-attribute="<?php echo esc_attr( $attr['name'] ); ?>"
												data-value="<?php echo esc_attr( $option ); ?>"
												style="<?php echo $swatch_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>"
												title="<?php echo esc_attr( $swatch_name ); ?>"
												aria-label="<?php echo esc_attr( $swatch_name ); ?>"
											>
												<span class="screen-reader-text"><?php echo esc_html( $swatch_name ); ?></span>
											</button>
										<?php else : ?>
											<button
												type="button"
												class="aa-sticky-cart__drawer-option"
												data-wp-on--click="actions.selectDrawerOption"
												data-attribute="<?php echo esc_attr( $attr['name'] ); ?>"
												data-value="<?php echo esc_attr( $option ); ?>"
											>
												<span class="aa-sticky-cart__drawer-option-check" aria-hidden="true">
													<svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
														<polyline points="2.5 6.5 5 9 9.5 3.5" />
													</svg>
												</span>
												<span class="aa-sticky-cart__drawer-option-name"><?php echo esc_html( $option ); ?></span>
											</button>
										<?php endif; ?>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>

					<div class="aa-sticky-cart__drawer-footer">
						<div class="aa-sticky-cart__quantity">
							<button
								type="button"
								class="aa-sticky-cart__qty-btn"
								data-wp-on--click="actions.decrementQty"
								aria-label="<?php esc_attr_e( 'Decrease quantity', 'aggressive-apparel' ); ?>"
							>&minus;</button>
							<input
								type="number"
								class="aa-sticky-cart__qty-input"
								min="1"
								value="1"
								data-wp-bind--value="state.quantity"
								data-wp-on--change="actions.setQuantity"
								aria-label="<?php esc_attr_e( 'Quantity', 'aggressive-apparel' ); ?>"
							/>
							<button
								type="button"
								class="aa-sticky-cart__qty-btn"
								data-wp-on--click="actions.incrementQty"
								aria-label="<?php esc_attr_e( 'Increase quantity', 'aggressive-apparel' ); ?>"
							>&plus;</button>
						</div>
						<button
							type="button"
							class="aa-sticky-cart__drawer-add"
							data-wp-on--click="actions.addToCart"
							data-wp-bind--disabled="state.isDrawerAddDisabled"
							data-wp-class--is-loading="state.isAdding"
							data-wp-class--is-success="state.isSuccess"
						>
							<span data-wp-text="state.drawerButtonText">
								<?php esc_html_e( 'Add to Cart', 'aggressive-apparel' ); ?>
							</span>
						</button>
					</div>
				</div>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Extract product data for the sticky bar.
	 *
	 * @param \WC_Product $product Product object.
	 * @return array{price_html: string, variations: array, attributes: array, default_attrs: array}
	 */
	private function get_product_data( \WC_Product $product ): array {
		$data = array(
			'price_html'    => html_entity_decode( wp_strip_all_tags( $product->get_price_html() ), ENT_QUOTES, 'UTF-8' ),
			'variations'    => array(),
			'attributes'    => array(),
			'default_attrs' => array(),
		);

		if ( ! $product->is_type( 'variable' ) ) {
			return $data;
		}

		/**
		 * Variable product cast.
		 *
		 * @var \WC_Product_Variable $product
		 */
		$available_variations = $product->get_available_variations( 'objects' );

		// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- PHPStan type narrowing.
		/** @var \WC_Product_Variation[] $available_variations */
		foreach ( $available_variations as $variation ) {
			$data['variations'][] = array(
				'id'         => $variation->get_id(),
				'attributes' => $variation->get_variation_attributes(),
				'price'      => html_entity_decode( wp_strip_all_tags( $variation->get_price_html() ), ENT_QUOTES, 'UTF-8' ),
				'in_stock'   => $variation->is_in_stock(),
			);
		}

		// Load color swatch data — same approach as Quick_View::get_color_swatch_data().
		$color_swatch_data = $this->get_color_swatch_data();

		foreach ( $product->get_variation_attributes() as $attribute_name => $options ) {
			$taxonomy = str_replace( 'attribute_', '', $attribute_name );
			$label    = wc_attribute_label( $taxonomy, $product );
			$is_color = $this->is_color_attribute( $taxonomy );

			$swatch_data = array();
			if ( $is_color && ! empty( $color_swatch_data ) ) {
				foreach ( $options as $option_slug ) {
					if ( isset( $color_swatch_data[ $option_slug ] ) ) {
						$swatch_data[ $option_slug ] = $color_swatch_data[ $option_slug ];
					}
				}
			}

			$data['attributes'][] = array(
				'name'        => $attribute_name,
				'label'       => $label,
				'options'     => array_values( $options ),
				'is_color'    => $is_color,
				'swatch_data' => $swatch_data,
			);
		}

		$data['default_attrs'] = $product->get_default_attributes();

		return $data;
	}

	/**
	 * Get color swatch data for all color terms.
	 *
	 * Delegates to Color_Data_Manager::get_swatch_data().
	 *
	 * @return array<string, array{value: string, type: string, name: string}>
	 */
	private function get_color_swatch_data(): array {
		try {
			if ( ! class_exists( Color_Data_Manager::class ) ) {
				return array();
			}

			return ( new Color_Data_Manager() )->get_swatch_data();
		} catch ( \Throwable $e ) {
			return array();
		}
	}

	/**
	 * Check if a taxonomy slug is a color attribute.
	 *
	 * Mirrors the isColorSlug() helper from quick-view.js.
	 *
	 * @param string $taxonomy Taxonomy slug (e.g. 'pa_color').
	 * @return bool
	 */
	private function is_color_attribute( string $taxonomy ): bool {
		$slug = strtolower( $taxonomy );
		return in_array(
			$slug,
			array( 'pa_color', 'pa_colour', 'color', 'colour' ),
			true,
		);
	}

	/**
	 * Check if the current page is a single product page.
	 *
	 * @return bool
	 */
	private function is_product_page(): bool {
		return function_exists( 'is_product' ) && is_product();
	}
}
