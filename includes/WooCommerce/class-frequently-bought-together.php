<?php
/**
 * Frequently Bought Together Class
 *
 * Displays a bundling section on single product pages with recommended
 * companion products, checkbox selection, and combined add-to-cart.
 *
 * Uses cross-sells first, then related products as fallback.
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
 * Frequently Bought Together
 *
 * @since 1.18.0
 */
class Frequently_Bought_Together {

	/**
	 * Maximum companion products to show.
	 *
	 * @var int
	 */
	private const MAX_COMPANIONS = 3;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'render_block', array( $this, 'inject_fbt_section' ), 10, 2 );
		add_action( 'wp_footer', array( $this, 'output_interactivity_state' ) );
	}

	/**
	 * Enqueue styles and register Interactivity API script module.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! $this->is_product_page() ) {
			return;
		}

		$css_file = AGGRESSIVE_APPAREL_DIR . '/build/styles/woocommerce/frequently-bought-together.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'aggressive-apparel-fbt',
				AGGRESSIVE_APPAREL_URI . '/build/styles/woocommerce/frequently-bought-together.css',
				array(),
				(string) filemtime( $css_file ),
			);
		}

		if ( function_exists( 'wp_register_script_module' ) ) {
			wp_register_script_module(
				'@aggressive-apparel/frequently-bought-together',
				AGGRESSIVE_APPAREL_URI . '/assets/interactivity/frequently-bought-together.js',
				array( '@wordpress/interactivity' ),
				AGGRESSIVE_APPAREL_VERSION,
			);
			wp_enqueue_script_module( '@aggressive-apparel/frequently-bought-together' );
		}
	}

	/**
	 * Inject the FBT section after the product details block.
	 *
	 * @param string $block_content Block HTML.
	 * @param array  $block         Block data.
	 * @return string Modified HTML.
	 */
	public function inject_fbt_section( string $block_content, array $block ): string {
		if ( ! isset( $block['blockName'] ) || 'woocommerce/product-details' !== $block['blockName'] ) {
			return $block_content;
		}

		if ( ! $this->is_product_page() ) {
			return $block_content;
		}

		$product = wc_get_product( get_the_ID() );
		if ( ! $product || ! $product->is_in_stock() ) {
			return $block_content;
		}

		$companions = $this->get_companion_products( $product );
		if ( empty( $companions ) ) {
			return $block_content;
		}

		$fbt_html = $this->build_fbt_html( $product, $companions );

		return $block_content . $fbt_html;
	}

	/**
	 * Output Interactivity API state for the FBT section.
	 *
	 * @return void
	 */
	public function output_interactivity_state(): void {
		if ( ! $this->is_product_page() ) {
			return;
		}

		if ( ! function_exists( 'wp_interactivity_state' ) ) {
			return;
		}

		$product = wc_get_product( get_the_ID() );
		if ( ! $product ) {
			return;
		}

		$companions = $this->get_companion_products( $product );
		if ( empty( $companions ) ) {
			return;
		}

		$items = array();

		// Add the current product first.
		$items[] = array(
			'id'         => $product->get_id(),
			'name'       => $product->get_name(),
			'price'      => (float) $product->get_price(),
			'priceHtml'  => wp_strip_all_tags( $product->get_price_html() ),
			'thumbnail'  => $this->get_thumbnail_url( $product ),
			'permalink'  => $product->get_permalink(),
			'isSelected' => true,
			'isCurrent'  => true,
		);

		foreach ( $companions as $companion ) {
			$items[] = array(
				'id'         => $companion->get_id(),
				'name'       => $companion->get_name(),
				'price'      => (float) $companion->get_price(),
				'priceHtml'  => wp_strip_all_tags( $companion->get_price_html() ),
				'thumbnail'  => $this->get_thumbnail_url( $companion ),
				'permalink'  => $companion->get_permalink(),
				'isSelected' => true,
				'isCurrent'  => false,
			);
		}

		$total = array_sum( array_column( $items, 'price' ) );

		wp_interactivity_state(
			'aggressive-apparel/frequently-bought-together',
			array(
				'items'          => $items,
				'totalPrice'     => $total,
				'selectedCount'  => count( $items ),
				'isAdding'       => false,
				'isSuccess'      => false,
				'hasError'       => false,
				'cartApiUrl'     => esc_url_raw( rest_url( 'wc/store/v1/cart/add-item' ) ),
				'nonce'          => wp_create_nonce( 'wc_store_api' ),
				'currencyPrefix' => get_woocommerce_currency_symbol(),
			),
		);
	}

	/**
	 * Get companion product IDs using cross-sells first, then related products.
	 *
	 * @param \WC_Product $product Current product.
	 * @return \WC_Product[] Array of companion product objects.
	 */
	private function get_companion_products( \WC_Product $product ): array {
		$companion_ids = $product->get_cross_sell_ids();

		// Fallback to related products if no cross-sells.
		if ( empty( $companion_ids ) ) {
			$companion_ids = wc_get_related_products( $product->get_id(), self::MAX_COMPANIONS );
		}

		$companion_ids = array_slice( $companion_ids, 0, self::MAX_COMPANIONS );

		$companions = array();
		foreach ( $companion_ids as $id ) {
			$companion = wc_get_product( $id );
			if ( $companion && $companion->is_in_stock() && $companion->is_purchasable() ) {
				$companions[] = $companion;
			}
		}

		return $companions;
	}

	/**
	 * Build the FBT section HTML.
	 *
	 * @param \WC_Product   $product    Current product.
	 * @param \WC_Product[] $companions Companion products.
	 * @return string HTML markup.
	 */
	private function build_fbt_html( \WC_Product $product, array $companions ): string {
		ob_start();
		?>
		<section
			class="aa-fbt"
			data-wp-interactive="aggressive-apparel/frequently-bought-together"
			aria-label="<?php esc_attr_e( 'Frequently bought together', 'aggressive-apparel' ); ?>"
		>
			<h2 class="aa-fbt__heading"><?php esc_html_e( 'Frequently Bought Together', 'aggressive-apparel' ); ?></h2>

			<div class="aa-fbt__products">
				<?php echo $this->render_product_card( $product, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML built with escaping below. ?>
				<?php foreach ( $companions as $companion ) : ?>
					<span class="aa-fbt__separator" aria-hidden="true">+</span>
					<?php echo $this->render_product_card( $companion, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endforeach; ?>
			</div>

			<div class="aa-fbt__footer">
				<div class="aa-fbt__total">
					<span class="aa-fbt__total-label"><?php esc_html_e( 'Total price:', 'aggressive-apparel' ); ?></span>
					<span class="aa-fbt__total-amount" data-wp-text="state.formattedTotal"></span>
				</div>

				<button
					type="button"
					class="aa-fbt__add-all"
					data-wp-on--click="actions.addAllToCart"
					data-wp-class--is-loading="state.isAdding"
					data-wp-class--is-success="state.isSuccess"
					data-wp-bind--disabled="state.isAdding"
				>
					<span data-wp-text="state.buttonText">
						<?php
						printf(
							/* translators: %d: number of items. */
							esc_html__( 'Add all %d items to cart', 'aggressive-apparel' ),
							count( $companions ) + 1
						);
						?>
					</span>
				</button>
			</div>

			<div class="screen-reader-text" role="status" aria-live="polite" data-wp-text="state.announcement"></div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render a single product card within the FBT section.
	 *
	 * @param \WC_Product $product    Product to render.
	 * @param bool        $is_current Whether this is the current (main) product.
	 * @return string HTML markup.
	 */
	private function render_product_card( \WC_Product $product, bool $is_current ): string {
		$product_id  = $product->get_id();
		$thumbnail   = $this->get_thumbnail_url( $product );
		$checkbox_id = 'fbt-' . $product_id;

		ob_start();
		?>
		<div class="aa-fbt__card" data-product-id="<?php echo esc_attr( (string) $product_id ); ?>">
			<a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="aa-fbt__card-link">
				<img
					src="<?php echo esc_url( $thumbnail ); ?>"
					alt="<?php echo esc_attr( $product->get_name() ); ?>"
					class="aa-fbt__card-image"
					width="120"
					height="120"
					loading="lazy"
				/>
			</a>
			<div class="aa-fbt__card-info">
				<a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="aa-fbt__card-title">
					<?php echo esc_html( $product->get_name() ); ?>
				</a>
				<span class="aa-fbt__card-price">
					<?php echo wp_strip_all_tags( $product->get_price_html() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce generates safe HTML, stripped to text. ?>
				</span>
			</div>
			<label class="aa-fbt__checkbox" for="<?php echo esc_attr( $checkbox_id ); ?>">
				<input
					type="checkbox"
					id="<?php echo esc_attr( $checkbox_id ); ?>"
					checked
					data-wp-on--change="actions.toggleItem"
					data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
					<?php if ( $is_current ) : ?>
						disabled
					<?php endif; ?>
				/>
				<span class="screen-reader-text">
					<?php
					printf(
						/* translators: %s: product name. */
						esc_html__( 'Include %s', 'aggressive-apparel' ),
						esc_html( $product->get_name() )
					);
					?>
				</span>
			</label>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Get the thumbnail URL for a product.
	 *
	 * @param \WC_Product $product Product.
	 * @return string Thumbnail URL.
	 */
	private function get_thumbnail_url( \WC_Product $product ): string {
		$image_id = $product->get_image_id();
		if ( $image_id ) {
			$url = wp_get_attachment_image_url( (int) $image_id, 'woocommerce_gallery_thumbnail' );
			if ( $url ) {
				return $url;
			}
		}
		return wc_placeholder_img_src( 'woocommerce_gallery_thumbnail' );
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
