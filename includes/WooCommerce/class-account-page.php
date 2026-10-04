<?php
/**
 * My Account presentation: nav icons, order cards, dashboard, account stylesheet.
 *
 * @package Aggressive_Apparel
 * @since 1.186.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Assets\Asset_Loader;
use Aggressive_Apparel\Core\Icons;

/**
 * Restyles the classic `[woocommerce_my_account]` shortcode without
 * overriding WooCommerce templates.
 *
 * - Nav icons ship as CSS mask images keyed by WooCommerce's per-endpoint
 *   `woocommerce-MyAccount-navigation-link--<endpoint>` classes, because the
 *   nav template escapes its labels and can't carry markup.
 * - The orders table gains an item-thumbnail column and a status chip, which
 *   account.css lays out as one clickable card per row. The Actions column
 *   (View / Pay / Cancel) is dropped: the order-number link covers the card,
 *   and the order page keeps Pay / Cancel.
 * - "Downloads" is dropped for customers with nothing to download (apparel).
 * - The dashboard template is swapped for Account_Dashboard's.
 * - The edit-account profile fields get a fieldset to match "Password change".
 *
 * Account pages are never page-cached, so per-customer markup is safe here.
 */
class Account_Page {

	/**
	 * Icon per account endpoint (Icons registry names).
	 *
	 * @var array<string, string>
	 */
	private const NAV_ICONS = array(
		'dashboard'       => 'grid-view',
		'orders'          => 'shopping-bag',
		'downloads'       => 'list-view',
		'edit-address'    => 'home',
		'payment-methods' => 'payment-card',
		'edit-account'    => 'user',
		'customer-logout' => 'arrow-right',
	);

	/**
	 * Thumbnails shown per order card before collapsing into "+N".
	 *
	 * @var int
	 */
	private const MAX_THUMBNAILS = 3;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_assets' ), 20 );
		add_filter( 'woocommerce_account_menu_items', array( self::class, 'filter_menu_items' ) );
		add_filter( 'woocommerce_account_orders_columns', array( self::class, 'filter_order_columns' ) );
		add_action( 'woocommerce_my_account_my_orders_column_order-items', array( self::class, 'render_order_items' ) );
		add_action( 'woocommerce_my_account_my_orders_column_order-status', array( self::class, 'render_order_status' ) );
		add_filter( 'wc_get_template', array( self::class, 'locate_template' ), 10, 2 );
		add_filter( 'page_template_hierarchy', array( self::class, 'filter_page_templates' ) );
		add_action( 'woocommerce_edit_account_form_start', array( self::class, 'open_profile_fieldset' ) );
		add_action( 'woocommerce_edit_account_form_fields', array( self::class, 'close_profile_fieldset' ), PHP_INT_MAX );

		Account_Order_View::init();
	}

	/**
	 * Offer templates/page-my-account.html (gutters around the account grid)
	 * to the account page whatever its slug, ahead of the generic page.html.
	 * A template assigned to the page in the editor still wins.
	 *
	 * @param mixed $templates Candidate template file names, most specific first.
	 * @return mixed
	 */
	public static function filter_page_templates( $templates ) {
		if ( ! is_array( $templates ) || ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return $templates;
		}

		$index = array_search( 'page.php', $templates, true );

		array_splice( $templates, false === $index ? count( $templates ) : (int) $index, 0, array( 'page-my-account.php' ) );

		return $templates;
	}

	/**
	 * Serve the theme's dashboard unless a (child) theme overrides WooCommerce's.
	 *
	 * @param mixed  $template      Located template path.
	 * @param string $template_name Template name relative to the templates dir.
	 * @return mixed
	 */
	public static function locate_template( $template, $template_name ) {
		if ( 'myaccount/dashboard.php' !== $template_name || ! is_string( $template ) || ! function_exists( 'WC' ) ) {
			return $template;
		}

		if ( ! str_starts_with( wp_normalize_path( $template ), wp_normalize_path( WC()->plugin_path() ) ) ) {
			return $template;
		}

		$file = AGGRESSIVE_APPAREL_DIR . '/templates/myaccount/dashboard.php';

		return file_exists( $file ) ? $file : $template;
	}

	/**
	 * Open a "Profile" fieldset around the name/email rows.
	 *
	 * @return void
	 */
	public static function open_profile_fieldset(): void {
		printf( '<fieldset class="aa-account-fieldset"><legend>%s</legend>', esc_html__( 'Profile', 'aggressive-apparel' ) );
	}

	/**
	 * Close the "Profile" fieldset, after any plugin-added profile fields.
	 *
	 * @return void
	 */
	public static function close_profile_fieldset(): void {
		echo '</fieldset>';
	}

	/**
	 * Enqueue account.css plus the nav icon masks on account pages.
	 *
	 * @return void
	 */
	public static function enqueue_assets(): void {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}

		if ( ! Asset_Loader::enqueue_feature_style( 'aggressive-apparel-account', 'build/styles/woocommerce/account' ) ) {
			return;
		}

		wp_add_inline_style( 'aggressive-apparel-account', self::nav_icon_css() );
	}

	/**
	 * Per-endpoint `--aa-account-icon` declarations as SVG data URIs.
	 *
	 * @return string
	 */
	private static function nav_icon_css(): string {
		$css = '';

		foreach ( self::NAV_ICONS as $endpoint => $icon ) {
			$svg = Icons::get( $icon );

			if ( '' === $svg ) {
				continue;
			}

			$css .= sprintf(
				'.woocommerce-MyAccount-navigation-link--%s{--aa-account-icon:url("data:image/svg+xml,%s")}',
				sanitize_html_class( $endpoint ),
				rawurlencode( $svg )
			);
		}

		return $css;
	}

	/**
	 * Drop "Downloads" when the customer has no downloadable purchases.
	 *
	 * @param mixed $items Endpoint => label.
	 * @return mixed
	 */
	public static function filter_menu_items( $items ) {
		if ( ! is_array( $items ) || ! isset( $items['downloads'] ) || ! function_exists( 'wc_get_customer_available_downloads' ) ) {
			return $items;
		}

		if ( array() === wc_get_customer_available_downloads( get_current_user_id() ) ) {
			unset( $items['downloads'] );
		}

		return $items;
	}

	/**
	 * Insert an "Items" column after the order number and drop "Actions".
	 *
	 * Actions are removed by column, not via the actions filter, because
	 * WooCommerce reuses that filter for the order page's Pay / Cancel.
	 *
	 * @param mixed $columns Column id => label.
	 * @return mixed
	 */
	public static function filter_order_columns( $columns ) {
		if ( ! is_array( $columns ) ) {
			return $columns;
		}

		unset( $columns['order-actions'] );

		if ( isset( $columns['order-items'] ) ) {
			return $columns;
		}

		$out = array();

		foreach ( $columns as $id => $label ) {
			$out[ $id ] = $label;

			if ( 'order-number' === $id ) {
				$out['order-items'] = __( 'Items', 'aggressive-apparel' );
			}
		}

		if ( ! isset( $out['order-items'] ) ) {
			$out['order-items'] = __( 'Items', 'aggressive-apparel' );
		}

		return $out;
	}

	/**
	 * Print up to MAX_THUMBNAILS product images, then a "+N" overflow chip.
	 *
	 * @param mixed $order The row's order.
	 * @return void
	 */
	public static function render_order_items( $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$items = array_values( $order->get_items() );
		$total = count( $items );

		if ( 0 === $total ) {
			return;
		}

		echo '<span class="aa-order-items">';

		foreach ( array_slice( $items, 0, self::MAX_THUMBNAILS ) as $item ) {
			$product = $item instanceof \WC_Order_Item_Product ? $item->get_product() : null;
			$attrs   = array(
				'class'   => 'aa-order-items__img',
				'alt'     => $item->get_name(),
				'loading' => 'lazy',
			);

			$image = $product instanceof \WC_Product
				? $product->get_image( 'woocommerce_gallery_thumbnail', $attrs )
				: wc_placeholder_img( 'woocommerce_gallery_thumbnail', $attrs );

			echo wp_kses_post( $image );
		}

		$overflow = $total - self::MAX_THUMBNAILS;

		if ( $overflow > 0 ) {
			// Visible "+N" for sighted users, words for screen readers (an
			// aria-label on a role-less span isn't reliably announced).
			printf(
				'<span class="aa-order-items__more"><span aria-hidden="true">+%d</span><span class="screen-reader-text">%s</span></span>',
				(int) $overflow,
				esc_html(
					sprintf(
						/* translators: %d: number of further items in the order. */
						_n( 'and %d more item', 'and %d more items', $overflow, 'aggressive-apparel' ),
						$overflow
					)
				)
			);
		}

		echo '</span>';
	}

	/**
	 * Print the order status as a toned chip.
	 *
	 * @param mixed $order The row's order.
	 * @return void
	 */
	public static function render_order_status( $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$status = $order->get_status();

		printf(
			'<span class="aa-order-status aa-order-status--%s">%s</span>',
			esc_attr( self::status_tone( $status ) ),
			esc_html( wc_get_order_status_name( $status ) )
		);
	}

	/**
	 * Map a WooCommerce order status to a chip tone.
	 *
	 * @param string $status Status slug, without the `wc-` prefix.
	 * @return string
	 */
	private static function status_tone( string $status ): string {
		switch ( $status ) {
			case 'completed':
				return 'success';
			case 'processing':
				return 'info';
			case 'pending':
			case 'on-hold':
				return 'warning';
			case 'failed':
				return 'error';
			default:
				return 'neutral';
		}
	}
}
