<?php
/**
 * Card Enhancements Store API Extension
 *
 * Bridges PHP-only card-level enhancements (badges, countdown timer,
 * view-transition names) to AJAX-rendered cards by exposing them on the
 * WooCommerce Store API product response under a single namespace.
 *
 * This decouples the JS card builders (product-filters, load-more) from
 * the underlying PHP feature classes — they read whatever this extension
 * provides and render markup accordingly. Features the admin has disabled
 * simply omit their key from the payload.
 *
 * @package Aggressive_Apparel
 * @since 1.55.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Card Enhancements
 *
 * @since 1.55.0
 */
class Card_Enhancements {

	/**
	 * Store API namespace under which the data appears in product responses.
	 *
	 * Reachable client-side as
	 *   product.extensions['aggressive-apparel/card-enhancements']
	 *
	 * @var string
	 */
	private const NAMESPACE = 'aggressive-apparel/card-enhancements';

	/**
	 * Cached badge renderer instance to avoid rebuilding term lookups
	 * on every product in a paginated response.
	 *
	 * @var Product_Badges|null
	 */
	private ?Product_Badges $badges_renderer = null;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_extension' ) );
	}

	/**
	 * Register the Store API endpoint extension.
	 *
	 * @return void
	 */
	public function register_extension(): void {
		Store_Api_Extension::register_product_data(
			self::NAMESPACE,
			array( $this, 'build_payload' ),
			array(
				'cache' => true,
				'ttl'   => 15 * MINUTE_IN_SECONDS,
			)
		);
	}

	/**
	 * Build the per-product payload for the Store API extension.
	 *
	 * Each enhancement is conditional on its feature flag so we never
	 * pay the cost of rendering badges, computing countdowns, etc. for
	 * features the admin has disabled.
	 *
	 * @param \WC_Product $product Product object provided by Store API.
	 * @return array{badges_html?: string, view_transition_name?: string, countdown?: array<string, int>}
	 */
	public function build_payload( \WC_Product $product ): array {
		$payload = array();

		if ( Feature_Settings::is_enabled( 'product_badges' ) ) {
			$badges_html = $this->get_badges_renderer()->get_badges_html( $product );
			if ( '' !== $badges_html ) {
				$payload['badges_html'] = $badges_html;
			}
		}

		if ( Feature_Settings::is_enabled( 'page_transitions' ) ) {
			// Mirrors Page_Transitions::handle_archive_image() so SSR and
			// AJAX cards share the same per-product transition name and the
			// archive→single morph still works on dynamically loaded cards.
			$payload['view_transition_name'] = sprintf( 'product-img-%d', $product->get_id() );
		}

		if ( Feature_Settings::is_enabled( 'countdown_timer' ) ) {
			$countdown = Countdown_Timer::get_countdown_data( $product );
			if ( null !== $countdown ) {
				$payload['countdown'] = $countdown;
			}
		}

		return $payload;
	}

	/**
	 * Lazily build a badge renderer.
	 *
	 * Calls `apply_threshold_filters()` (not `init()`) so we get the
	 * same threshold values as the SSR injection without registering
	 * the `render_block` filter a second time.
	 *
	 * @return Product_Badges
	 */
	private function get_badges_renderer(): Product_Badges {
		if ( null === $this->badges_renderer ) {
			$this->badges_renderer = new Product_Badges();
			$this->badges_renderer->apply_threshold_filters();
		}
		return $this->badges_renderer;
	}
}
