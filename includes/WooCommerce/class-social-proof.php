<?php
/**
 * Social Proof Class
 *
 * Renders a single rotating toast in the bottom-left of the page that
 * surfaces signals shoppers find reassuring. The toast itself is a
 * dumb cycle — what populates it is decided here in PHP and pulled
 * from one of several pluggable sources:
 *
 *   - `trust`         → admin-edited brand trust messages
 *   - `purchases`     → real, anonymized recent orders
 *   - `engagement`    → catalog products with strong lifetime sales (WC totals)
 *   - `announcements` → admin-edited promotional / seasonal messages
 *
 * In addition, an admin-only `demo` source lets the store owner
 * preview the design without waiting for real data to land.
 *
 * Privacy model:
 *   - Customer first names are NEVER stored — only the final display
 *     string is cached, so transient deletion is the only step needed
 *     for GDPR right-to-be-forgotten compliance.
 *   - Orders younger than the configured `min_order_age` are excluded
 *     so unique product+city+timestamp tuples can't be cross-referenced
 *     to identify individual customers.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Core\Icons;

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
	 * Versioned so the cache invalidates cleanly when the data shape
	 * changes (e.g. the privacy refactor that introduced pre-built
	 * `message` strings instead of raw billing data).
	 *
	 * @var string
	 */
	private const TRANSIENT_KEY = 'aggressive_apparel_social_proof_v5';

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
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_indicator' ), 100 );
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
				AGGRESSIVE_APPAREL_URI . '/build/interactivity/social-proof.js',
				array( '@wordpress/interactivity' ),
				AGGRESSIVE_APPAREL_VERSION,
			);
			wp_enqueue_script_module( '@aggressive-apparel/social-proof' );
		}
	}

	/**
	 * Markup for thumbnail + badge + standalone decor icons.
	 *
	 * @param string $initial_thumbnail_url URL for hydration first paint.
	 * @return void
	 */
	private function render_visual_column( string $initial_thumbnail_url ): void {
		echo '<div class="aggressive-apparel-social-proof__visual" data-wp-bind--hidden="state.currentVisualHidden">';
		echo '<div class="aggressive-apparel-social-proof__decor aggressive-apparel-social-proof__decor--slot" aria-hidden="true" data-wp-watch="callbacks.syncDecorHtml" data-wp-bind--hidden="state.currentDecorHidden"></div>';
		echo '<div class="aggressive-apparel-social-proof__thumb" data-wp-bind--hidden="state.currentThumbnailWrapHidden">';
		echo '<img class="no-lazy aggressive-apparel-social-proof__thumb-img" src="' . esc_url( $initial_thumbnail_url ) . '" alt="" decoding="async" loading="lazy" width="48" height="48" data-wp-watch="callbacks.syncImage" />';
		echo '<span class="aggressive-apparel-social-proof__badge aggressive-apparel-social-proof__badge--slot" aria-hidden="true" data-wp-watch="callbacks.syncBadgeHtml" data-wp-bind--hidden="state.currentBadgeHidden"></span>';
		echo '</div>';
		echo '</div>';
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
		echo '<div class="aggressive-apparel-social-proof__toast" data-wp-class--is-visible="context.isVisible" data-wp-class--is-demo="state.currentIsDemo" data-wp-bind--hidden="context.isDismissed">';
		echo '<a class="aggressive-apparel-social-proof__link" data-wp-bind--href="state.currentUrl" data-wp-bind--hidden="state.currentHasNoLink">';
		$this->render_visual_column( $notifications[0]['thumbnail'] ?? '' );
		echo '<div class="aggressive-apparel-social-proof__body">';
		echo '<p class="aggressive-apparel-social-proof__message" data-wp-text="state.currentMessage"></p>';
		echo '<p class="aggressive-apparel-social-proof__time" data-wp-text="state.currentTime" data-wp-bind--hidden="state.currentHasNoTime"></p>';
		echo '</div>';
		echo '</a>';
		// Static (non-link) variant for trust messages / announcements that
		// have no destination — same body markup so layout is identical.
		echo '<div class="aggressive-apparel-social-proof__static" data-wp-bind--hidden="state.currentHasLink">';
		$this->render_visual_column( $notifications[0]['thumbnail'] ?? '' );
		echo '<div class="aggressive-apparel-social-proof__body">';
		echo '<p class="aggressive-apparel-social-proof__message" data-wp-text="state.currentMessage"></p>';
		echo '<p class="aggressive-apparel-social-proof__time" data-wp-text="state.currentTime" data-wp-bind--hidden="state.currentHasNoTime"></p>';
		echo '</div>';
		echo '</div>';
		echo '<button type="button" class="aggressive-apparel-social-proof__close" data-wp-on--click="actions.dismiss" aria-label="' . esc_attr__( 'Dismiss', 'aggressive-apparel' ) . '">&times;</button>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Add an admin-bar indicator while demo preview is active so the
	 * admin can't accidentally leave it on. Only rendered for users
	 * with `edit_theme_options` (the same gate as the demo source).
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 * @return void
	 */
	public function add_admin_bar_indicator( $wp_admin_bar ): void {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		if ( ! Feature_Settings::is_enabled( 'social_proof' ) ) {
			return;
		}

		if ( ! get_option( Feature_Settings::SOCIAL_PROOF_DEMO_OPTION, false ) ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'aa-social-proof-demo',
				'title' => '◉ ' . esc_html__( 'Social Proof preview: ON', 'aggressive-apparel' ),
				'href'  => admin_url( 'themes.php?page=aggressive-apparel-features#tab-engagement' ),
				'meta'  => array(
					'title' => esc_attr__( 'Click to manage Social Proof settings', 'aggressive-apparel' ),
				),
			),
		);
	}

	// -- Mixer --

	/**
	 * Build the final notification queue by drawing from each enabled
	 * source according to its weight, then shuffling.
	 *
	 * Demo notifications are gated separately and ALWAYS prepended to
	 * the queue (so the admin sees the preview immediately on page load
	 * rather than waiting for the random draw to land on it).
	 *
	 * @return array<int, array{message: string, time: string, url: string, thumbnail: string, decor_html: string, badge_html: string, kind: string}>
	 */
	private function get_notifications(): array {
		$queue = array();

		// Demo first, gated to admins only — never cached so toggle changes are immediate.
		if ( $this->should_show_demo() ) {
			foreach ( $this->build_demo_notifications() as $demo ) {
				$queue[] = $demo;
			}
		}

		// Cached mixed pool (the expensive part — wc_get_orders / etc.).
		$mixed = get_transient( self::TRANSIENT_KEY );
		if ( ! is_array( $mixed ) ) {
			$mixed = $this->build_mixed_pool();
			if ( ! empty( $mixed ) ) {
				set_transient( self::TRANSIENT_KEY, $mixed, self::CACHE_TTL );
			}
		}

		foreach ( $mixed as $item ) {
			$queue[] = $item;
		}

		return array_slice( $queue, 0, self::MAX_NOTIFICATIONS + 1 );
	}

	/**
	 * Build the weighted-random mixed pool from all enabled sources.
	 *
	 * Each source's items are added to a pool `weight` times, then the
	 * pool is shuffled. This produces a natural weighted-random rotation
	 * without the per-cycle arithmetic the JS would otherwise need.
	 *
	 * @return array<int, array{message: string, time: string, url: string, thumbnail: string, decor_html: string, badge_html: string, kind: string}>
	 */
	private function build_mixed_pool(): array {
		$sources = Feature_Settings::get_social_proof_sources();
		$pool    = array();

		foreach ( $sources as $key => $weight ) {
			$weight = (int) $weight;
			if ( $weight <= 0 ) {
				continue;
			}

			$items = array();
			if ( 'trust' === $key ) {
				$items = $this->build_trust_notifications();
			} elseif ( 'purchases' === $key ) {
				$items = $this->build_purchase_notifications();
			} elseif ( 'engagement' === $key ) {
				$items = $this->build_engagement_notifications();
			} elseif ( 'announcements' === $key ) {
				$items = $this->build_announcement_notifications();
			}

			if ( empty( $items ) ) {
				continue;
			}

			for ( $i = 0; $i < $weight; $i++ ) {
				foreach ( $items as $item ) {
					$pool[] = $item;
				}
			}
		}

		shuffle( $pool );

		// De-dupe consecutive identical messages so the same one doesn't
		// repeat back-to-back when the pool is small.
		$deduped = array();
		$last    = '';
		foreach ( $pool as $item ) {
			if ( $item['message'] === $last ) {
				continue;
			}
			$deduped[] = $item;
			$last      = $item['message'];
		}

		return array_slice( $deduped, 0, self::MAX_NOTIFICATIONS );
	}

	// -- Source: Trust Messages --

	/**
	 * Build trust-message notifications from the admin-edited list.
	 *
	 * @return array<int, array{message: string, time: string, url: string, thumbnail: string, decor_html: string, badge_html: string, kind: string}>
	 */
	private function build_trust_notifications(): array {
		// Frontend doesn't fire `admin_init` so `register_setting()`
		// defaults are unavailable here — use the public accessor that
		// applies the shipped default list explicitly.
		$raw   = Feature_Settings::get_social_proof_trust_messages();
		$lines = $this->parse_message_lines( $raw );

		$out = array();
		foreach ( $lines as $line ) {
			$parsed = $this->decode_decorated_proof_line( $line );
			$out[]  = array(
				'message'    => sanitize_text_field( $parsed['message'] ),
				'time'       => '',
				'url'        => '',
				'thumbnail'  => '',
				'decor_html' => $parsed['decor_html'],
				'badge_html' => '',
				'kind'       => 'trust',
			);
		}

		return $out;
	}

	// -- Source: Custom Announcements --

	/**
	 * Build announcement notifications from the admin-edited list.
	 *
	 * @return array<int, array{message: string, time: string, url: string, thumbnail: string, decor_html: string, badge_html: string, kind: string}>
	 */
	private function build_announcement_notifications(): array {
		// See note in build_trust_notifications() about why we use the
		// accessor instead of get_option() with a fallback.
		$raw   = Feature_Settings::get_social_proof_announcements();
		$lines = $this->parse_message_lines( $raw );

		$out = array();
		foreach ( $lines as $line ) {
			$parsed = $this->decode_decorated_proof_line( $line );
			$out[]  = array(
				'message'    => sanitize_text_field( $parsed['message'] ),
				'time'       => '',
				'url'        => '',
				'thumbnail'  => '',
				'decor_html' => $parsed['decor_html'],
				'badge_html' => '',
				'kind'       => 'announcement',
			);
		}

		return $out;
	}

	/**
	 * Parses PREFIX|MESSAGE into visible copy + optional LEFT-COLUMN decor.
	 *
	 * When PREFIX is omitted the entire line is plain text with no badge.
	 * When PREFIX resolves to neither a recognised theme icon slug nor an
	 * HTTPS/HTTP asset URL we fall back to treating the FULL line as the
	 * message so stray pipe characters remain readable.
	 *
	 * PREFIX may be explicit none|, -|, 0| to deliberately hide an icon while
	 * still using structured lines.
	 *
	 * @param string $single_line Trimmed textarea line contents.
	 * @return array{message: string, decor_html: string}
	 */
	private function decode_decorated_proof_line( string $single_line ): array {
		if ( '' === $single_line ) {
			return array(
				'message'    => '',
				'decor_html' => '',
			);
		}

		if ( ! str_contains( $single_line, '|' ) ) {
			return array(
				'message'    => sanitize_text_field( $single_line ),
				'decor_html' => '',
			);
		}

		$parts      = explode( '|', $single_line, 2 );
		$left_token = trim( (string) ( $parts[0] ?? '' ) );
		$right_text = trim( (string) ( $parts[1] ?? '' ) );

		if ( '' === $right_text ) {
			return array(
				'message'    => sanitize_text_field( $single_line ),
				'decor_html' => '',
			);
		}

		if ( $this->decor_token_is_explicitly_disabled( $left_token ) ) {
			return array(
				'message'    => sanitize_text_field( $right_text ),
				'decor_html' => '',
			);
		}

		$decor_markup = $this->resolve_decor_token_markup( $left_token );

		if ( '' === $decor_markup ) {
			return array(
				'message'    => sanitize_text_field( $single_line ),
				'decor_html' => '',
			);
		}

		return array(
			'message'    => sanitize_text_field( $right_text ),
			'decor_html' => $decor_markup,
		);
	}

	/**
	 * User-facing tokens that deliberately suppress the PREFIX column.
	 *
	 * @param string $token Left-hand PREFIX trimmed.
	 * @return bool
	 */
	private function decor_token_is_explicitly_disabled( string $token ): bool {
		return in_array(
			strtolower( $token ),
			array( 'none', '-', '0', 'hidden' ),
			true
		);
	}

	/**
	 * Build trusted SVG/HTML for PREFIX slot (theme Icons or HTTPS image URLs).
	 *
	 * Only http(s) URLs are accepted for raster/SVG uploads so admins cannot
	 * inject javascript:-style URIs via the PREFIX field.
	 *
	 * @param string $token Raw PREFIX substring.
	 * @return string Safe HTML string or empty if PREFIX is unsupported.
	 */
	private function resolve_decor_token_markup( string $token ): string {
		if ( preg_match( '#^https?://#i', $token ) ) {
			$url = esc_url_raw( $token );
			if ( '' === $url ) {
				return '';
			}

			$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
			if ( ! is_string( $scheme )
				|| ! in_array( strtolower( $scheme ), array( 'http', 'https' ), true )
			) {
				return '';
			}

			return $this->build_decor_image_markup( $url );
		}

		$key = sanitize_key( strtolower( str_replace( ' ', '-', $token ) ) );
		if ( '' === $key || ! Icons::exists( $key ) ) {
			return '';
		}

		return Icons::get(
			$key,
			array(
				'width'       => 24,
				'height'      => 24,
				'class'       => 'aggressive-apparel-social-proof__decor-svg',
				'aria-hidden' => 'true',
			)
		);
	}

	/**
	 * Build a sanitized <img /> tag for PREFIX image URLs (custom icons).
	 *
	 * @param string $validated_url Absolute URL validated by PREFIX branch.
	 * @return string
	 */
	private function build_decor_image_markup( string $validated_url ): string {
		return wp_kses(
			sprintf(
				'<img src="%1$s" alt="" width="24" height="24" decoding="async" loading="lazy" class="aggressive-apparel-social-proof__decor-img" />',
				esc_url( $validated_url )
			),
			array(
				'img' => array(
					'src'      => array(),
					'alt'      => array(),
					'width'    => array(),
					'height'   => array(),
					'decoding' => array(),
					'loading'  => array(),
					'class'    => array(),
				),
			),
		);
	}

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

		$min_age_minutes = (int) get_option( Feature_Settings::SOCIAL_PROOF_MIN_ORDER_AGE_OPTION, 5 );
		$min_age_seconds = $min_age_minutes * MINUTE_IN_SECONDS;
		$cutoff_ts       = time() - $min_age_seconds;

		$display_mode = (string) get_option( Feature_Settings::SOCIAL_PROOF_DISPLAY_MODE_OPTION, 'anonymous' );
		$location_key = (string) get_option( Feature_Settings::SOCIAL_PROOF_LOCATION_GRANULARITY_OPTION, 'city' );

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
				'time'       => sanitize_text_field( human_time_diff( $date->getTimestamp(), time() ) . ' ago' ),
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
			if ( function_exists( 'WC' ) ) {
				$states = WC()->countries->get_states( $country );
				if ( is_array( $states ) && isset( $states[ $state_code ] ) ) {
					return (string) $states[ $state_code ];
				}
			}
			return $state_code;
		}

		if ( 'country' === $granularity ) {
			$country = (string) $order->get_billing_country();
			if ( '' === $country ) {
				return '';
			}
			if ( function_exists( 'WC' ) ) {
				$countries = WC()->countries->get_countries();
				if ( is_array( $countries ) && isset( $countries[ $country ] ) ) {
					return (string) $countries[ $country ];
				}
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
		if ( ! get_option( Feature_Settings::SOCIAL_PROOF_DEMO_OPTION, false ) ) {
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
		$display_mode = (string) get_option( Feature_Settings::SOCIAL_PROOF_DISPLAY_MODE_OPTION, 'anonymous' );
		$location_key = (string) get_option( Feature_Settings::SOCIAL_PROOF_LOCATION_GRANULARITY_OPTION, 'city' );

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
