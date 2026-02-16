<?php
/**
 * Social Proof Class
 *
 * Displays toast notifications of recent purchases to build social proof
 * and urgency. Order data is cached via transients.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Social Proof Notifications
 *
 * @since 1.17.0
 */
class Social_Proof {

	/**
	 * Transient key.
	 *
	 * @var string
	 */
	private const TRANSIENT_KEY = 'aggressive_apparel_social_proof';

	/**
	 * Cache duration in seconds (15 minutes).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

	/**
	 * Maximum notifications to show per page load.
	 *
	 * @var int
	 */
	private const MAX_NOTIFICATIONS = 15;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_toast_container' ) );
	}

	/**
	 * Enqueue styles and register Interactivity API script module on frontend shop pages.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! $this->should_show() ) {
			return;
		}

		$css_file = AGGRESSIVE_APPAREL_DIR . '/build/styles/woocommerce/social-proof.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'aggressive-apparel-social-proof',
				AGGRESSIVE_APPAREL_URI . '/build/styles/woocommerce/social-proof.css',
				array(),
				(string) filemtime( $css_file ),
			);
		}

		if ( function_exists( 'wp_register_script_module' ) ) {
			wp_register_script_module(
				'@aggressive-apparel/social-proof',
				AGGRESSIVE_APPAREL_URI . '/assets/interactivity/social-proof.js',
				array( '@wordpress/interactivity' ),
				AGGRESSIVE_APPAREL_VERSION,
			);
			wp_enqueue_script_module( '@aggressive-apparel/social-proof' );
		}
	}

	/**
	 * Render the toast notification container with Interactivity API directives.
	 *
	 * @return void
	 */
	public function render_toast_container(): void {
		if ( ! $this->should_show() ) {
			return;
		}

		$notifications = $this->get_notifications();
		if ( empty( $notifications ) ) {
			return;
		}

		$context = (string) wp_json_encode(
			array(
				'notifications'     => $notifications,
				'currentIndex'      => 0,
				'isVisible'         => false,
				'isDismissed'       => false,
				'isHovered'         => false,
				'intervalMs'        => 20000,
				'displayDurationMs' => 5000,
			),
		);

		echo '<div class="aggressive-apparel-social-proof" data-wp-interactive="aggressive-apparel/social-proof" data-wp-context=\'' . esc_attr( $context ) . '\' data-wp-init="callbacks.startCycle" role="status" aria-live="polite">';
		echo '<div class="aggressive-apparel-social-proof__toast" data-wp-class--is-visible="context.isVisible" data-wp-bind--hidden="context.isDismissed">';
		echo '<a class="aggressive-apparel-social-proof__link" data-wp-bind--href="state.currentUrl">';
		echo '<div class="aggressive-apparel-social-proof__image"><img src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" data-wp-bind--src="state.currentThumbnailSrc" alt="" /></div>';
		echo '<div class="aggressive-apparel-social-proof__body">';
		echo '<p class="aggressive-apparel-social-proof__message" data-wp-text="state.currentMessage"></p>';
		echo '<p class="aggressive-apparel-social-proof__time" data-wp-text="state.currentTime"></p>';
		echo '</div>';
		echo '</a>';
		echo '<button type="button" class="aggressive-apparel-social-proof__close" data-wp-on--click="actions.dismiss" aria-label="' . esc_attr__( 'Dismiss', 'aggressive-apparel' ) . '">&times;</button>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Get recent purchase notifications (cached).
	 *
	 * @return array<int, array{name: string, city: string, product: string, thumbnail: string, ago: string}>
	 */
	private function get_notifications(): array {
		$cached = get_transient( self::TRANSIENT_KEY );
		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}

		$notifications = $this->build_notifications();

		// Shuffle so the order varies between cache cycles.
		shuffle( $notifications );

		// Only cache non-empty results so we retry on next page load.
		if ( ! empty( $notifications ) ) {
			set_transient( self::TRANSIENT_KEY, $notifications, self::CACHE_TTL );
		}

		return $notifications;
	}

	/**
	 * Query recent orders and extract notification data.
	 *
	 * Falls back to sample notifications built from published products
	 * when no real orders exist yet.
	 *
	 * @return array<int, array{name: string, city: string, product: string, thumbnail: string, ago: string}>
	 */
	private function build_notifications(): array {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return $this->get_sample_notifications();
		}

		$orders = wc_get_orders(
			array(
				'status'  => array( 'wc-completed', 'wc-processing' ),
				'limit'   => 20,
				'orderby' => 'date',
				'order'   => 'DESC',
			),
		);

		if ( ! is_array( $orders ) ) {
			return $this->get_sample_notifications();
		}

		$notifications = array();

		foreach ( $orders as $order ) {
			if ( count( $notifications ) >= self::MAX_NOTIFICATIONS ) {
				break;
			}

			$first_name = $order->get_billing_first_name();
			$city       = $order->get_billing_city();
			$items      = $order->get_items();

			if ( empty( $first_name ) || empty( $items ) ) {
				continue;
			}

			$first_item   = reset( $items );
			$product_name = $first_item->get_name();
			$product_id   = $first_item instanceof \WC_Order_Item_Product ? $first_item->get_product_id() : 0;
			$thumbnail    = '';

			if ( $product_id && function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( $product_id );
				if ( $product ) {
					$thumbnail = $this->get_product_thumbnail( $product );
				}
			}

			$order_date = $order->get_date_created();
			$ago        = $order_date ? sanitize_text_field( human_time_diff( $order_date->getTimestamp(), time() ) ) : '';

			$permalink = $product_id ? get_permalink( $product_id ) : '';

			$notifications[] = array(
				'name'      => sanitize_text_field( $first_name ),
				'city'      => sanitize_text_field( $city ),
				'product'   => sanitize_text_field( $product_name ),
				'thumbnail' => esc_url_raw( $thumbnail ),
				'url'       => esc_url_raw( (string) $permalink ),
				'ago'       => $ago,
			);
		}

		// Fall back to sample data when no real orders exist.
		if ( empty( $notifications ) ) {
			return $this->get_sample_notifications();
		}

		return $notifications;
	}

	/**
	 * Generate sample notifications from published products.
	 *
	 * Used as a preview when the store has no completed orders yet.
	 *
	 * @return array<int, array{name: string, city: string, product: string, thumbnail: string, ago: string}>
	 */
	private function get_sample_notifications(): array {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		$products = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => self::MAX_NOTIFICATIONS,
				'orderby' => 'date',
				'order'   => 'DESC',
			),
		);

		if ( ! is_array( $products ) || empty( $products ) ) {
			return array();
		}

		$names  = array( 'Alex', 'Jordan', 'Taylor', 'Morgan', 'Casey', 'Riley', 'Quinn', 'Avery', 'Dakota', 'Skyler', 'Reese', 'Finley', 'Sage', 'Blake', 'Rowan' );
		$cities = array( 'New York', 'Los Angeles', 'Chicago', 'Houston', 'Miami', 'Denver', 'Seattle', 'Atlanta', 'Dallas', 'Phoenix', 'Portland', 'Nashville', 'Boston', 'San Diego', 'Austin' );
		$times  = array( '2 minutes', '5 minutes', '8 minutes', '14 minutes', '23 minutes', '31 minutes', '42 minutes', '1 hour', '2 hours', '3 hours', '4 hours', '5 hours', '7 hours', '10 hours', '12 hours' );

		$notifications = array();

		foreach ( $products as $index => $product ) {
			$thumbnail = $this->get_product_thumbnail( $product );

			$notifications[] = array(
				'name'      => $names[ $index % count( $names ) ],
				'city'      => $cities[ $index % count( $cities ) ],
				'product'   => sanitize_text_field( $product->get_name() ),
				'thumbnail' => esc_url_raw( $thumbnail ),
				'url'       => esc_url_raw( (string) $product->get_permalink() ),
				'ago'       => $times[ $index % count( $times ) ],
			);
		}

		return $notifications;
	}

	/**
	 * Get a product thumbnail URL with fallback.
	 *
	 * @param \WC_Product $product Product instance.
	 * @return string Thumbnail URL or empty string.
	 */
	private function get_product_thumbnail( \WC_Product $product ): string {
		$image_id = $product->get_image_id();
		if ( $image_id ) {
			$url = wp_get_attachment_image_url( (int) $image_id, 'thumbnail' );
			if ( $url ) {
				return $url;
			}
		}

		$thumb_url = get_the_post_thumbnail_url( $product->get_id(), 'thumbnail' );
		if ( $thumb_url ) {
			return $thumb_url;
		}

		// WooCommerce placeholder image.
		if ( function_exists( 'wc_placeholder_img_src' ) ) {
			return wc_placeholder_img_src( 'thumbnail' );
		}

		return '';
	}

	/**
	 * Determine if social proof should display on this page.
	 *
	 * @return bool
	 */
	private function should_show(): bool {
		if ( ! function_exists( 'is_shop' ) ) {
			return false;
		}

		return is_shop() || is_product_category() || is_product_tag() || is_product();
	}
}
