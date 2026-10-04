<?php
/**
 * My Account dashboard: greeting, latest order, shortcut tiles.
 *
 * @package Aggressive_Apparel
 * @since 1.186.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Core\Icons;

/**
 * Renders the dashboard that replaces WooCommerce's two boilerplate
 * paragraphs. Printed by templates/myaccount/dashboard.php, which
 * Account_Page swaps in for WooCommerce's own template.
 *
 * Account pages are never page-cached, so per-customer markup is safe here.
 */
class Account_Dashboard {

	/**
	 * Shortcut tiles: endpoint => icon. Endpoints missing from the account
	 * menu (removed by a filter) are skipped.
	 *
	 * @var array<string, string>
	 */
	private const TILES = array(
		'orders'       => 'shopping-bag',
		'edit-address' => 'home',
		'edit-account' => 'user',
	);

	/**
	 * Print the dashboard.
	 *
	 * @param \WP_User $user The signed-in customer.
	 * @return void
	 */
	public static function render( \WP_User $user ): void {
		$order = self::latest_order( $user->ID );
		?>
		<div class="aa-account-dash">
			<header class="aa-account-dash__hello">
				<p class="aa-account-dash__eyebrow"><?php esc_html_e( 'Welcome back', 'aggressive-apparel' ); ?></p>
				<h2 class="aa-account-dash__name"><?php echo esc_html( $user->display_name ); ?></h2>
				<p class="aa-account-dash__switch">
					<?php
					/* translators: %s: customer display name. */
					echo esc_html( sprintf( __( 'Not %s?', 'aggressive-apparel' ), $user->display_name ) );
					?>
					<a href="<?php echo esc_url( wc_logout_url() ); ?>"><?php esc_html_e( 'Log out', 'aggressive-apparel' ); ?></a>
				</p>
			</header>

			<section class="aa-account-dash__section" aria-labelledby="aa-account-latest-title">
				<h3 id="aa-account-latest-title" class="aa-account-dash__label"><?php esc_html_e( 'Latest order', 'aggressive-apparel' ); ?></h3>
				<?php
				if ( $order instanceof \WC_Order ) {
					self::render_latest_order( $order );
				} else {
					self::render_no_orders();
				}
				?>
			</section>

			<nav class="aa-account-tiles" aria-label="<?php esc_attr_e( 'Account shortcuts', 'aggressive-apparel' ); ?>">
				<?php self::render_tiles( $user ); ?>
			</nav>
		</div>
		<?php
	}

	/**
	 * The customer's most recent order in any registered status.
	 *
	 * @param int $user_id Customer ID.
	 * @return \WC_Order|null
	 */
	private static function latest_order( int $user_id ): ?\WC_Order {
		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => 1,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'status'      => array_keys( wc_get_order_statuses() ),
			)
		);

		$first = is_array( $orders ) ? reset( $orders ) : false;

		return $first instanceof \WC_Order ? $first : null;
	}

	/**
	 * Latest-order card, linking to the order.
	 *
	 * @param \WC_Order $order The order.
	 * @return void
	 */
	private static function render_latest_order( \WC_Order $order ): void {
		$created = $order->get_date_created();
		?>
		<a class="aa-account-card aa-account-latest" href="<?php echo esc_url( $order->get_view_order_url() ); ?>">
			<span class="aa-account-latest__head">
				<span class="aa-account-latest__number"><?php echo esc_html( _x( '#', 'hash before order number', 'aggressive-apparel' ) . $order->get_order_number() ); ?></span>
				<?php if ( $created ) : ?>
					<time class="aa-account-latest__date" datetime="<?php echo esc_attr( $created->date( 'c' ) ); ?>"><?php echo esc_html( wc_format_datetime( $created ) ); ?></time>
				<?php endif; ?>
				<?php Account_Page::render_order_status( $order ); ?>
			</span>
			<?php Order_Progress::render( $order ); ?>
			<span class="aa-account-latest__body">
				<?php Account_Page::render_order_items( $order ); ?>
				<span class="aa-account-latest__total"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
				<span class="aa-account-latest__cta"><?php esc_html_e( 'View order', 'aggressive-apparel' ); ?></span>
			</span>
		</a>
		<?php
	}

	/**
	 * Empty state for customers without orders.
	 *
	 * @return void
	 */
	private static function render_no_orders(): void {
		?>
		<div class="aa-account-card aa-account-empty">
			<p><?php esc_html_e( "You haven't placed an order yet.", 'aggressive-apparel' ); ?></p>
			<a class="wp-element-button" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Start shopping', 'aggressive-apparel' ); ?></a>
		</div>
		<?php
	}

	/**
	 * Shortcut tiles for the menu endpoints still enabled.
	 *
	 * @param \WP_User $user The signed-in customer.
	 * @return void
	 */
	private static function render_tiles( \WP_User $user ): void {
		$menu = wc_get_account_menu_items();

		foreach ( self::TILES as $endpoint => $icon ) {
			if ( ! isset( $menu[ $endpoint ] ) ) {
				continue;
			}
			?>
			<a class="aa-account-card aa-account-tile" href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>">
				<span class="aa-account-tile__icon">
					<?php
					Icons::render(
						$icon,
						array(
							'width'       => 20,
							'height'      => 20,
							'aria-hidden' => 'true',
						)
					);
					?>
				</span>
				<span class="aa-account-tile__label"><?php echo esc_html( $menu[ $endpoint ] ); ?></span>
				<span class="aa-account-tile__value"><?php echo esc_html( self::tile_value( $endpoint, $user ) ); ?></span>
			</a>
			<?php
		}
	}

	/**
	 * One-line summary shown under a tile's label.
	 *
	 * @param string   $endpoint Account endpoint.
	 * @param \WP_User $user     The signed-in customer.
	 * @return string
	 */
	private static function tile_value( string $endpoint, \WP_User $user ): string {
		switch ( $endpoint ) {
			case 'orders':
				$count = wc_get_customer_order_count( $user->ID );
				/* translators: %d: number of orders. */
				return sprintf( _n( '%d order', '%d orders', $count, 'aggressive-apparel' ), $count );
			case 'edit-address':
				return self::address_summary( $user->ID );
			default:
				return $user->user_email;
		}
	}

	/**
	 * "City, ST" from the shipping address, else billing.
	 *
	 * @param int $user_id Customer ID.
	 * @return string
	 */
	private static function address_summary( int $user_id ): string {
		$customer = new \WC_Customer( $user_id );
		$parts    = array_filter( array( $customer->get_shipping_city(), $customer->get_shipping_state() ) );

		if ( array() === $parts ) {
			$parts = array_filter( array( $customer->get_billing_city(), $customer->get_billing_state() ) );
		}

		return array() === $parts ? __( 'Add an address', 'aggressive-apparel' ) : implode( ', ', $parts );
	}
}
