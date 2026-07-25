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

use Aggressive_Apparel\Assets\Asset_Loader;
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
	use Social_Proof_Decoration;
	use Social_Proof_Purchase;


	/**
	 * Transient key.
	 *
	 * Versioned so the cache invalidates cleanly when the data shape
	 * changes (e.g. the privacy refactor that introduced pre-built
	 * `message` strings instead of raw billing data).
	 *
	 * @var string
	 */
	private const TRANSIENT_KEY = 'aggressive_apparel_social_proof_v7';

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

		// Fresh purchases and status changes must not wait for TTL.
		add_action( 'woocommerce_new_order', array( self::class, 'flush_cache' ) );
		add_action( 'woocommerce_order_status_changed', array( self::class, 'flush_cache' ) );
		add_action( 'woocommerce_order_status_completed', array( self::class, 'flush_cache' ) );

		// Admin copy / source / display settings bake into the cached pool.
		add_action( 'updated_option', array( self::class, 'maybe_flush_on_option_change' ), 10, 1 );
		add_action( 'added_option', array( self::class, 'maybe_flush_on_option_change' ), 10, 1 );
		add_action( 'deleted_option', array( self::class, 'maybe_flush_on_option_change' ), 10, 1 );
	}

	/**
	 * Drop the social-proof notification pool transient.
	 *
	 * @return void
	 */
	public static function flush_cache(): void {
		delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * Flush when a social-proof related option (or the features bag) changes.
	 *
	 * @param string $option Option name.
	 * @return void
	 */
	public static function maybe_flush_on_option_change( string $option ): void {
		if ( Feature_Settings::OPTION_KEY === $option
			|| str_starts_with( $option, 'aggressive_apparel_social_proof' ) ) {
			self::flush_cache();
		}
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

		Asset_Loader::enqueue_feature_style(
			'aggressive-apparel-social-proof',
			'build/styles/woocommerce/social-proof'
		);

		Asset_Loader::enqueue_interactivity_module(
			'@aggressive-apparel/social-proof',
			'build/interactivity/social-proof'
		);
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

		// Decorative marketing surface: hidden from the accessibility tree so a
		// toast that re-cycles every ~20s doesn't spam polite announcements. Its
		// interactive children (link, dismiss) carry tabindex="-1" so there are
		// no focusable descendants inside aria-hidden (an ARIA violation) — mouse
		// users keep full interaction; keyboard/SR users simply don't get it.
		echo '<div class="aggressive-apparel-social-proof" data-wp-interactive="aggressive-apparel/social-proof" data-wp-context=\'' . esc_attr( $context ) . '\' data-wp-init="callbacks.startCycle" aria-hidden="true">';
		echo '<div class="aggressive-apparel-social-proof__toast" data-wp-class--is-visible="context.isVisible" data-wp-class--is-demo="state.currentIsDemo" data-wp-bind--hidden="context.isDismissed">';
		echo '<a class="aggressive-apparel-social-proof__link" tabindex="-1" data-wp-bind--href="state.currentUrl" data-wp-bind--hidden="state.currentHasNoLink">';
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
		echo '<button type="button" class="aggressive-apparel-social-proof__close" tabindex="-1" data-wp-on--click="actions.dismiss" aria-label="' . esc_attr__( 'Dismiss', 'aggressive-apparel' ) . '">&times;</button>';
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

		if ( ! Feature_Settings::is_social_proof_demo_enabled() ) {
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

		return array_slice( $queue, 0, self::MAX_NOTIFICATIONS );
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

		// Purchases lead: real orders are the strongest proof signal, so
		// when the shuffle didn't land one first, promote the earliest
		// purchase to the front of the queue.
		foreach ( $deduped as $index => $item ) {
			if ( 'purchase' !== $item['kind'] ) {
				continue;
			}
			if ( $index > 0 ) {
				array_splice( $deduped, $index, 1 );
				array_unshift( $deduped, $item );
			}
			break;
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
				'url'        => $parsed['url'],
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
				'url'        => $parsed['url'],
				'thumbnail'  => '',
				'decor_html' => $parsed['decor_html'],
				'badge_html' => '',
				'kind'       => 'announcement',
			);
		}

		return $out;
	}

	/**
	 * Determine if social proof should display on this page.
	 *
	 * @return bool
	 */
	private function should_show(): bool {
		return Product_Context::is_product_archive() || Product_Context::is_single_product();
	}
}
