<?php
/**
 * My Account single-order page: header, progress card, item thumbnails.
 *
 * @package Aggressive_Apparel
 * @since 1.186.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

/**
 * Restyles myaccount/view-order.php through its own hooks, no override:
 *
 * - The "Order #1 was placed on … and is currently …" sentence becomes the
 *   date and a status chip (`woocommerce_order_details_status`); the page
 *   title already carries the order number.
 * - A progress card prints ahead of the order details.
 * - Line items gain a product thumbnail and the grand total a class (both
 *   view-order only, so order emails, which share the filters, are untouched).
 */
class Account_Order_View {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'woocommerce_order_details_status', array( self::class, 'filter_status_text' ), 10, 2 );
		add_action( 'woocommerce_view_order', array( self::class, 'render_progress' ), 5 );
		add_filter( 'woocommerce_order_item_name', array( self::class, 'filter_item_name' ), 10, 2 );
		add_filter( 'woocommerce_get_order_item_totals', array( self::class, 'filter_totals' ) );
	}

	/**
	 * Tag the grand total so its (classless) row can be emphasised.
	 *
	 * @param mixed $totals Key => array{label, value}.
	 * @return mixed
	 */
	public static function filter_totals( $totals ) {
		if ( ! is_array( $totals ) || ! isset( $totals['order_total']['value'] ) || ! is_wc_endpoint_url( 'view-order' ) ) {
			return $totals;
		}

		$totals['order_total']['value'] = '<span class="aa-order-total">' . $totals['order_total']['value'] . '</span>';

		return $totals;
	}

	/**
	 * Replace the status sentence with the date and a status chip.
	 *
	 * Printed inside a `<p>` and run through wp_kses_post, so phrasing
	 * content only.
	 *
	 * @param mixed $text  Status sentence.
	 * @param mixed $order The order.
	 * @return mixed
	 */
	public static function filter_status_text( $text, $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return $text;
		}

		ob_start();
		Account_Page::render_order_status( $order );
		$chip = (string) ob_get_clean();

		$created = $order->get_date_created();
		$date    = $created
			/* translators: %s: order date. */
			? sprintf( '<span class="aa-order-head__date">%s</span>', esc_html( sprintf( __( 'Placed on %s', 'aggressive-apparel' ), wc_format_datetime( $created ) ) ) )
			: '';

		// The page title already reads "Order #123", so: date, then chip.
		return $date . $chip;
	}

	/**
	 * Progress card ahead of the order details table.
	 *
	 * @param mixed $order_id Order ID.
	 * @return void
	 */
	public static function render_progress( $order_id ): void {
		$order = wc_get_order( is_numeric( $order_id ) ? (int) $order_id : 0 );

		if ( ! $order instanceof \WC_Order || ! Order_Progress::applies( $order->get_status() ) ) {
			return;
		}

		echo '<div class="aa-account-card aa-order-progress-card">';
		Order_Progress::render( $order );
		echo '</div>';
	}

	/**
	 * Prefix the line-item name with its product thumbnail.
	 *
	 * @param mixed $name Item name HTML (often a product link).
	 * @param mixed $item Order item.
	 * @return mixed
	 */
	public static function filter_item_name( $name, $item ) {
		if ( ! is_string( $name ) || ! $item instanceof \WC_Order_Item_Product || ! is_wc_endpoint_url( 'view-order' ) ) {
			return $name;
		}

		$product = $item->get_product();
		$attrs   = array(
			'class'   => 'aa-order-line__img',
			'alt'     => '',
			'loading' => 'lazy',
		);

		$image = $product instanceof \WC_Product
			? $product->get_image( 'woocommerce_gallery_thumbnail', $attrs )
			: wc_placeholder_img( 'woocommerce_gallery_thumbnail', $attrs );

		return $image . $name;
	}
}
