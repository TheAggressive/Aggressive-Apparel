<?php
/**
 * Mini-Cart Style Defer
 *
 * The mini-cart block in the header enqueues WooCommerce's drawer stylesheet
 * (`mini-cart-contents.css`, ~72 KB) on every page as render-blocking CSS,
 * although the drawer stays closed until the shopper opens it. This loads it
 * without blocking first paint and inlines the few rules that keep the closed
 * drawer out of layout and invisible until the full file arrives.
 *
 * The deferred <link> keeps its position in <head>, so cascade order against
 * the theme's mini-cart overrides is unchanged.
 *
 * @package Aggressive_Apparel
 * @since 1.183.5
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mini-Cart Style Defer
 *
 * @since 1.183.5
 */
class Mini_Cart_Style_Defer {

	/**
	 * WooCommerce's mini-cart drawer style handle.
	 */
	public const HANDLE = 'wc-blocks-style-mini-cart-contents';

	/**
	 * Closed-drawer rules copied from mini-cart-contents.css. Zero specificity:
	 * WooCommerce's identical rules take over once the full file loads.
	 */
	private const CRITICAL_CSS = ':where(.wc-block-components-drawer__screen-overlay--is-hidden){position:fixed;inset:0;opacity:0;pointer-events:none}';

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Priority 100: after WooCommerce enqueues block styles and after
		// Legacy_Asset_Trim (99), so only a handle that will print is touched.
		add_action( 'wp_enqueue_scripts', array( $this, 'add_critical_css' ), 100 );
		add_filter( 'style_loader_tag', array( $this, 'defer_tag' ), 10, 4 );
	}

	/**
	 * Inline the closed-drawer rules next to the deferred stylesheet.
	 *
	 * Block styles can also be enqueued during render; when the handle is not
	 * queued yet it is registered, so the inline rules still travel with it.
	 *
	 * @return void
	 */
	public function add_critical_css(): void {
		if ( ! $this->should_defer() || ! wp_style_is( self::HANDLE, 'registered' ) ) {
			return;
		}

		wp_add_inline_style( self::HANDLE, self::CRITICAL_CSS );
	}

	/**
	 * Swap the stylesheet to the non-blocking print-media pattern.
	 *
	 * @param string $tag    The link tag.
	 * @param string $handle Style handle.
	 * @param string $href   Stylesheet URL.
	 * @param string $media  Media attribute value.
	 * @return string Filtered tag.
	 */
	public function defer_tag( string $tag, string $handle, string $href, string $media ): string {
		if ( self::HANDLE !== $handle || ! $this->should_defer() ) {
			return $tag;
		}

		if ( '' === $media || str_contains( $tag, ' onload=' ) ) {
			return $tag;
		}

		$media_attr = preg_quote( $media, '/' );
		$deferred   = preg_replace(
			'/\smedia=([\'"])' . $media_attr . '\1/',
			' media="print" onload="this.media=\'' . esc_attr( $media ) . '\'"',
			$tag,
			1
		);

		if ( ! is_string( $deferred ) || $deferred === $tag ) {
			return $tag;
		}

		return $deferred . '<noscript>' . trim( $tag ) . "</noscript>\n";
	}

	/**
	 * Whether this request should defer the drawer stylesheet.
	 *
	 * @return bool
	 */
	private function should_defer(): bool {
		if ( is_admin() ) {
			return false;
		}

		/**
		 * Filter whether the mini-cart drawer stylesheet loads without blocking
		 * first paint.
		 *
		 * @since 1.183.5
		 *
		 * @param bool $defer True to defer the stylesheet.
		 */
		return (bool) apply_filters( 'aggressive_apparel_defer_mini_cart_styles', true );
	}
}
