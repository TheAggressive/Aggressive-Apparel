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
					<?php if ( $product->is_type( 'variable' ) && ! empty( $product_data['attributes'] ) ) : ?>
						<div class="aa-sticky-cart__variants">
							<?php foreach ( $product_data['attributes'] as $attr ) : ?>
								<select
									class="aa-sticky-cart__select"
									data-wp-on--change="actions.selectAttribute"
									data-attribute="<?php echo esc_attr( $attr['name'] ); ?>"
									aria-label="<?php echo esc_attr( $attr['label'] ); ?>"
								>
									<option value=""><?php echo esc_html( $attr['label'] ); ?></option>
									<?php foreach ( $attr['options'] as $option ) : ?>
										<option value="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $option ); ?></option>
									<?php endforeach; ?>
								</select>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

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

		foreach ( $product->get_variation_attributes() as $attribute_name => $options ) {
			$taxonomy = str_replace( 'attribute_', '', $attribute_name );
			$label    = wc_attribute_label( $taxonomy, $product );

			$data['attributes'][] = array(
				'name'    => $attribute_name,
				'label'   => $label,
				'options' => array_values( $options ),
			);
		}

		$data['default_attrs'] = $product->get_default_attributes();

		return $data;
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
