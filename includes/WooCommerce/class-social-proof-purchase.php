<?php
/**
 * Social Proof — purchase/engagement notifications + resolution.
 *
 * Extracted from Social_Proof to keep each file under the length cap.
 * Composed via `use`; all callers are unchanged.
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

trait Social_Proof_Purchase {
	/**
	 * Optional overlay badge reused on purchase, engagement, and demo notifications.
	 *
	 * Memoised once per request because the markup never changes mid-request.
	 *
	 * @return string Trusted SVG markup or empty.
	 */
	private function resolve_global_purchase_thumbnail_badge_html(): string {
		static $evaluated = false;
		static $markup    = '';

		if ( $evaluated ) {
			return $markup;
		}

		$evaluated = true;

		$key = Feature_Settings::resolve_social_proof_purchase_badge_icon_slug();

		if ( '' === $key || ! Icons::exists( $key ) ) {
			return $markup;
		}

		$markup = Icons::get(
			$key,
			array(
				'width'       => 14,
				'height'      => 14,
				'class'       => 'aggressive-apparel-social-proof__badge-svg',
				'aria-hidden' => 'true',
			)
		);

		return $markup;
	}

	/**
	 * Parse a multiline messages string into a clean array.
	 *
	 * Strips empty lines, comment lines (starting with `#`), and trims
	 * whitespace. This is the shared parser used by both Trust Messages
	 * and Custom Announcements.
	 *
	 * @param string $raw Raw textarea value.
	 * @return array<int, string>
	 */
	private function parse_message_lines( string $raw ): array {
		if ( '' === trim( $raw ) ) {
			return array();
		}

		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		if ( ! is_array( $lines ) ) {
			return array();
		}

		$out = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || str_starts_with( $line, '#' ) ) {
				continue;
			}
			$out[] = $line;
		}

		return $out;
	}

	// -- Source: Real Purchases --

	/**
	 * Build purchase notifications from real orders.
	 *
	 * Only the final display string + thumbnail + url + relative time
	 * are stored. Raw billing data never leaves this method, so the
	 * cached transient is GDPR-safe (deleting the customer's WC orders
	 * removes their data; the transient holds no PII either way).
	 *
	 * @return array<int, array{message: string, time: string, url: string, thumbnail: string, decor_html: string, badge_html: string, kind: string}>
	 */
	private function build_purchase_notifications(): array {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$min_age_minutes = Feature_Settings::get_social_proof_min_order_age();
		$min_age_seconds = $min_age_minutes * MINUTE_IN_SECONDS;
		$cutoff_ts       = time() - $min_age_seconds;

		$display_mode = Feature_Settings::get_social_proof_display_mode();
		$location_key = Feature_Settings::get_social_proof_location_granularity();

		$orders = wc_get_orders(
			array(
				'status'  => array( 'wc-completed', 'wc-processing' ),
				'limit'   => 30,
				'orderby' => 'date',
				'order'   => 'DESC',
			),
		);

		if ( ! is_array( $orders ) ) {
			return array();
		}

		$badge = $this->resolve_global_purchase_thumbnail_badge_html();

		$out = array();

		foreach ( $orders as $order ) {
			if ( count( $out ) >= self::MAX_NOTIFICATIONS ) {
				break;
			}

			$date = $order->get_date_created();
			if ( ! $date ) {
				continue;
			}

			// Min-age floor: skip orders younger than the configured threshold.
			if ( $min_age_seconds > 0 && $date->getTimestamp() > $cutoff_ts ) {
				continue;
			}

			$items = $order->get_items();
			if ( empty( $items ) ) {
				continue;
			}

			$first_item   = reset( $items );
			$product_name = $first_item->get_name();
			if ( '' === $product_name ) {
				continue;
			}

			$product_id = $first_item instanceof \WC_Order_Item_Product ? $first_item->get_product_id() : 0;
			$thumbnail  = '';
			$permalink  = '';

			if ( $product_id && function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( $product_id );
				if ( $product ) {
					$thumbnail = $this->get_product_thumbnail( $product );
					$permalink = (string) $product->get_permalink();
				}
			}

			$identity = $this->resolve_identity( $order, $display_mode );
			$location = $this->resolve_location( $order, $location_key );

			$message = $this->format_purchase_message( $identity, $location, $product_name );

			$out[] = array(
				'message'    => sanitize_text_field( $message ),
				/* translators: %s: human-readable time difference, e.g. "5 minutes". */
				'time'       => sanitize_text_field( sprintf( __( '%s ago', 'aggressive-apparel' ), human_time_diff( $date->getTimestamp(), time() ) ) ),
				'url'        => esc_url_raw( $permalink ),
				'thumbnail'  => esc_url_raw( $thumbnail ),
				'decor_html' => '',
				'badge_html' => $badge,
				'kind'       => 'purchase',
			);
		}

		return $out;
	}

	// -- Source: Catalog engagement (sales totals) --

	/**
	 * Build engagement notifications from catalog sales (WooCommerce totals).
	 *
	 * Uses the same honest signal as bestseller-style badges: products must
	 * meet the admin minimum lifetime `total_sales` threshold.
	 *
	 * @return array<int, array{message: string, time: string, url: string, thumbnail: string, decor_html: string, badge_html: string, kind: string}>
	 */
	private function build_engagement_notifications(): array {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		$min_sales = Feature_Settings::get_social_proof_engagement_min_sales();
		$badge     = $this->resolve_global_purchase_thumbnail_badge_html();

		$products = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => 40,
				'orderby' => 'popularity',
				'order'   => 'DESC',
				'parent'  => 0,
			)
		);

		if ( ! is_array( $products ) ) {
			return array();
		}

		$out = array();

		foreach ( $products as $product ) {
			if ( count( $out ) >= self::MAX_NOTIFICATIONS ) {
				break;
			}

			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			if ( (int) $product->get_total_sales() < $min_sales ) {
				continue;
			}

			$name = $product->get_name();
			if ( '' === $name ) {
				continue;
			}

			$message = sprintf(
				/* translators: %s: product title. */
				__( 'Selling well — %s', 'aggressive-apparel' ),
				$name
			);

			$out[] = array(
				'message'    => sanitize_text_field( $message ),
				'time'       => '',
				'url'        => esc_url_raw( (string) $product->get_permalink() ),
				'thumbnail'  => esc_url_raw( $this->get_product_thumbnail( $product ) ),
				'decor_html' => '',
				'badge_html' => $badge,
				'kind'       => 'engagement',
			);
		}

		return $out;
	}

	/**
	 * Resolve the buyer identity string per the configured display mode.
	 *
	 * Returns an empty string for the `anonymous` mode so the message
	 * builder collapses cleanly to "Someone …".
	 *
	 * @param \WC_Order $order        Order instance.
	 * @param string    $display_mode One of `anonymous`, `initial`, `first_name`.
	 * @return string
	 */
	private function resolve_identity( \WC_Order $order, string $display_mode ): string {
		if ( 'anonymous' === $display_mode ) {
			return '';
		}

		$first = trim( (string) $order->get_billing_first_name() );
		if ( '' === $first ) {
			return '';
		}

		if ( 'initial' === $display_mode ) {
			return strtoupper( substr( $first, 0, 1 ) ) . '.';
		}

		return $first;
	}

	/**
	 * Resolve the location string per the configured granularity.
	 *
	 * Falls back gracefully when the requested field is missing on the
	 * order so we never produce strings like "Someone in  purchased X".
	 *
	 * @param \WC_Order $order        Order instance.
	 * @param string    $granularity  One of `city`, `state`, `country`, `hidden`.
	 * @return string
	 */
	private function resolve_location( \WC_Order $order, string $granularity ): string {
		if ( 'hidden' === $granularity ) {
			return '';
		}

		if ( 'city' === $granularity ) {
			return trim( (string) $order->get_billing_city() );
		}

		if ( 'state' === $granularity ) {
			$state_code = (string) $order->get_billing_state();
			$country    = (string) $order->get_billing_country();
			if ( '' === $state_code ) {
				return '';
			}
			// Try to expand state code to full name when WC provides the lookup.
			$states = WC()->countries->get_states( $country );
			if ( isset( $states[ $state_code ] ) ) {
				return (string) $states[ $state_code ];
			}
			return $state_code;
		}

		if ( 'country' === $granularity ) {
			$country = (string) $order->get_billing_country();
			if ( '' === $country ) {
				return '';
			}
			$countries = WC()->countries->get_countries();
			if ( isset( $countries[ $country ] ) ) {
				return (string) $countries[ $country ];
			}
			return $country;
		}

		return '';
	}

	/**
	 * Compose the final purchase display string from the resolved
	 * identity + location + product. Handles every empty-state combo
	 * so the output always reads naturally.
	 *
	 * @param string $identity Resolved identity ("", "S.", "Sarah").
	 * @param string $location Resolved location ("", "Portland", "Oregon").
	 * @param string $product  Product name.
	 * @return string
	 */
	private function format_purchase_message( string $identity, string $location, string $product ): string {
		// Identity prefix.
		if ( '' === $identity ) {
			$subject = __( 'Someone', 'aggressive-apparel' );
		} else {
			$subject = $identity;
		}

		// Location preposition: "from" reads better with names; "in"
		// reads better with anonymous / state / country granularity.
		if ( '' !== $location ) {
			if ( '' === $identity ) {
				/* translators: 1: subject, 2: location, 3: product. */
				return sprintf( __( '%1$s in %2$s purchased %3$s', 'aggressive-apparel' ), $subject, $location, $product );
			}
			/* translators: 1: subject, 2: location, 3: product. */
			return sprintf( __( '%1$s from %2$s purchased %3$s', 'aggressive-apparel' ), $subject, $location, $product );
		}

		/* translators: 1: subject, 2: product. */
		return sprintf( __( '%1$s purchased %2$s', 'aggressive-apparel' ), $subject, $product );
	}

	// -- Source: Demo Preview (admin-only) --

	/**
	 * Whether the demo preview source should render for the current viewer.
	 *
	 * BOTH gates must pass:
	 *   1. The admin enabled the toggle in settings.
	 *   2. The current viewer holds `edit_theme_options`.
	 *
	 * This guarantees customers never see a demo notification, even if
	 * the toggle is left on accidentally — the visibility check happens
	 * at render time, not just at the option toggle.
	 *
	 * @return bool
	 */
	private function should_show_demo(): bool {
		if ( ! Feature_Settings::is_social_proof_demo_enabled() ) {
			return false;
		}
		return current_user_can( 'edit_theme_options' );
	}

	/**
	 * Build a single demo notification using a real product from the
	 * store so the image, name and link are accurate to what shoppers
	 * would see — only the buyer + timing are fabricated, and only the
	 * admin will ever see them.
	 *
	 * @return array<int, array{message: string, time: string, url: string, thumbnail: string, decor_html: string, badge_html: string, kind: string}>
	 */
	private function build_demo_notifications(): array {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		$products = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => 1,
				'orderby' => 'date',
				'order'   => 'DESC',
			),
		);

		if ( ! is_array( $products ) || empty( $products ) ) {
			return array();
		}

		$product      = $products[0];
		$product_name = (string) $product->get_name();
		$thumbnail    = $this->get_product_thumbnail( $product );
		$permalink    = (string) $product->get_permalink();
		$display_mode = Feature_Settings::get_social_proof_display_mode();
		$location_key = Feature_Settings::get_social_proof_location_granularity();

		$identity = '';
		if ( 'initial' === $display_mode ) {
			$identity = 'A.';
		} elseif ( 'first_name' === $display_mode ) {
			$identity = 'Alex';
		}

		$location = '';
		if ( 'city' === $location_key ) {
			$location = 'Portland';
		} elseif ( 'state' === $location_key ) {
			$location = 'Oregon';
		} elseif ( 'country' === $location_key ) {
			$location = 'United States';
		}

		$message = $this->format_purchase_message( $identity, $location, $product_name );

		// Visible "(preview)" tag so the admin always knows what they're
		// looking at — the gating already prevents customers from seeing
		// it, and tagging keeps things unambiguous in screenshots / videos.
		$message = sprintf( '%s — %s', $message, __( 'preview', 'aggressive-apparel' ) );

		$badge = $this->resolve_global_purchase_thumbnail_badge_html();

		return array(
			array(
				'message'    => sanitize_text_field( $message ),
				'time'       => sanitize_text_field( __( 'just now', 'aggressive-apparel' ) ),
				'url'        => esc_url_raw( $permalink ),
				'thumbnail'  => esc_url_raw( $thumbnail ),
				'decor_html' => '',
				'badge_html' => $badge,
				'kind'       => 'demo',
			),
		);
	}

	// -- Helpers --

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
}
