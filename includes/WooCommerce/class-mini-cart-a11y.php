<?php
/**
 * WooCommerce mini-cart drawer accessibility.
 *
 * Adds inert to the closed mini-cart drawer so aria-hidden subtrees cannot
 * receive focus (PageSpeed Agent Accessibility / WCAG).
 *
 * @package Aggressive_Apparel
 * @since 1.16.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Assets\Asset_Loader;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mini-cart drawer accessibility integration.
 *
 * @since 1.16.0
 */
class Mini_Cart_A11y {

	/**
	 * Script handle.
	 */
	private const SCRIPT_HANDLE = 'aggressive-apparel-mini-cart-a11y';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'render_block', array( $this, 'inject_drawer_inert' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_script' ), 10 );
		add_action( 'wp_footer', array( $this, 'print_footer_bootstrap' ), 1 );
	}

	/**
	 * Add inert to the drawer in SSR HTML before Interactivity API hydrates.
	 *
	 * @param string               $content Rendered block HTML.
	 * @param array<string, mixed> $block   Block data.
	 * @return string
	 */
	public function inject_drawer_inert( string $content, array $block ): string {
		if ( 'woocommerce/mini-cart' !== ( $block['blockName'] ?? '' ) ) {
			return $content;
		}

		if ( false === strpos( $content, 'wc-block-mini-cart__drawer' ) ) {
			return $content;
		}

		if ( preg_match( '/\bwc-block-mini-cart__drawer\b[^>]*\binert\b/', $content ) ) {
			return $content;
		}

		$updated = preg_replace(
			'/(<div\b(?=[^>]*\bwc-block-mini-cart__drawer\b)(?![^>]*\binert\b)[^>]*)(>)/',
			'$1 inert$2',
			$content,
			1
		);

		return is_string( $updated ) ? $updated : $content;
	}

	/**
	 * Enqueue the drawer inert sync script on the frontend.
	 *
	 * @return void
	 */
	public function enqueue_script(): void {
		if ( is_admin() || ! function_exists( 'WC' ) ) {
			return;
		}

		Asset_Loader::enqueue_script(
			self::SCRIPT_HANDLE,
			'build/scripts/mini-cart-a11y'
		);
	}

	/**
	 * Re-sync drawer inert after WooCommerce Interactivity hydrates.
	 *
	 * @return void
	 */
	public function print_footer_bootstrap(): void {
		if ( is_admin() || ! function_exists( 'WC' ) ) {
			return;
		}

		echo '<script>(function(){var s=".wc-block-mini-cart__drawer";function c(d){if(d.getAttribute("aria-hidden")!=="true"){return}if("inert" in HTMLElement.prototype){d.inert=true}else{d.setAttribute("inert","")}d.querySelectorAll("a,button,input,select,textarea,[tabindex]:not([tabindex=\'-1\'])").forEach(function(e){if(!e.hasAttribute("data-aa-mini-cart-tabindex")){e.setAttribute("data-aa-mini-cart-tabindex",e.getAttribute("tabindex")||"")}e.tabIndex=-1})}function i(){document.querySelectorAll(s).forEach(c)}i();document.addEventListener("DOMContentLoaded",i);window.addEventListener("load",function(){i();setTimeout(i,250)})})();</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static inline bootstrap.
	}
}
