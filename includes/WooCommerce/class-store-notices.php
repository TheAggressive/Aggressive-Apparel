<?php
/**
 * Store notice enrichment: pairs add-to-cart success notices with the added
 * product's featured image so the store-notices toast block can render a
 * product thumbnail (with a success badge) instead of the generic status icon.
 *
 * WooCommerce notices are plain HTML strings with no product context, so the
 * image can't be derived at render time. Instead we capture the featured image
 * URL when `woocommerce_add_to_cart` fires and stash it in the WC session; the
 * classic add-to-cart flow POSTs then redirects, and the session survives to
 * the request that renders the notice. render.php consumes the queue in order,
 * pairing each captured image with a success notice.
 *
 * @package Aggressive_Apparel
 * @since 1.173.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

/**
 * Captures add-to-cart product images for the store-notices toast block.
 */
class Store_Notices {

	/**
	 * WC session key holding the queued image URLs for the next render.
	 *
	 * @var string
	 */
	private const SESSION_KEY = 'aggressive_apparel_notice_images';

	/**
	 * Cap on queued images so a bulk add can't grow the session unbounded.
	 *
	 * @var int
	 */
	private const MAX_IMAGES = 10;

	/**
	 * Register the add-to-cart capture hook. Called from Bootstrap when
	 * WooCommerce is active.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'woocommerce_add_to_cart', array( self::class, 'capture_product_image' ), 10, 4 );
	}

	/**
	 * Queue the featured image URL of a just-added product.
	 *
	 * @param string $cart_item_key Cart item key (unused).
	 * @param int    $product_id    Added product ID.
	 * @param int    $quantity      Quantity added (unused).
	 * @param int    $variation_id  Variation ID when a variation was added.
	 * @return void
	 */
	public static function capture_product_image( $cart_item_key, $product_id, $quantity, $variation_id ): void {
		unset( $cart_item_key, $quantity );

		/*
		 * Only the classic form add-to-cart (a POST that redirects to a
		 * block-rendering page) can pair an image with a notice. AJAX / Store API
		 * adds never render this block, so an image queued there would sit in the
		 * session and later attach to an unrelated success notice — skip them.
		 */
		if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$session = self::session();
		if ( null === $session ) {
			return;
		}

		$url = self::resolve_image_url( (int) $product_id, (int) $variation_id );
		if ( '' === $url ) {
			return;
		}

		$queue = self::read_queue();
		if ( count( $queue ) >= self::MAX_IMAGES ) {
			return;
		}

		$queue[] = $url;
		$session->set( self::SESSION_KEY, $queue );
	}

	/**
	 * Return and clear the queued image URLs. Called by render.php only when
	 * there is at least one success notice to pair them with, so the queue is
	 * not consumed on unrelated renders.
	 *
	 * @return array<int, string>
	 */
	public static function consume_images(): array {
		$session = self::session();
		if ( null === $session ) {
			return array();
		}

		$queue = self::read_queue();
		if ( array() !== $queue ) {
			$session->set( self::SESSION_KEY, array() );
		}

		return $queue;
	}

	/**
	 * Resolve the small featured image URL for a product/variation.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID (0 when not a variation).
	 * @return string Image URL, or '' when none is available.
	 */
	private static function resolve_image_url( int $product_id, int $variation_id ): string {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return '';
		}

		$image_id = 0;

		if ( $variation_id > 0 ) {
			$variation = wc_get_product( $variation_id );
			if ( $variation instanceof \WC_Product ) {
				$image_id = (int) $variation->get_image_id();
			}
		}

		if ( 0 === $image_id && $product_id > 0 ) {
			$product = wc_get_product( $product_id );
			if ( $product instanceof \WC_Product ) {
				$image_id = (int) $product->get_image_id();
			}
		}

		if ( 0 === $image_id ) {
			return '';
		}

		$url = wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' );

		return is_string( $url ) ? $url : '';
	}

	/**
	 * Read the queued URLs from the session, normalised to a list of strings.
	 *
	 * @return array<int, string>
	 */
	private static function read_queue(): array {
		$session = self::session();
		if ( null === $session ) {
			return array();
		}

		$queue = $session->get( self::SESSION_KEY, array() );
		if ( ! is_array( $queue ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'strval', $queue ) ) );
	}

	/**
	 * The active WooCommerce session, or null when WooCommerce (or its session)
	 * is unavailable — e.g. very early requests before the session boots.
	 *
	 * @return \WC_Session|null
	 */
	private static function session(): ?\WC_Session {
		if ( ! function_exists( 'WC' ) ) {
			return null;
		}

		return WC()->session;
	}
}
