<?php
/**
 * Page Transitions Class
 *
 * Enables smooth cross-page navigation via the View Transitions API.
 * Adds view-transition-name to product images for shared element
 * morphing between archive and single product pages.
 *
 * Progressive enhancement — unsupported browsers get normal page loads.
 *
 * @package Aggressive_Apparel
 * @since 1.18.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Assets\Asset_Loader;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page Transitions
 *
 * @since 1.18.0
 */
class Page_Transitions {

	/**
	 * Commerce routes that must never be speculatively requested.
	 *
	 * @var string[]
	 */
	private const SPECULATION_EXCLUDE_PATHS = array(
		'/cart',
		'/cart/*',
		'/checkout',
		'/checkout/*',
		'/my-account',
		'/my-account/*',
	);

	/**
	 * Track used view-transition-names to prevent duplicates.
	 *
	 * View transition names must be unique per page. A product can appear
	 * both as the main content and in related/recommended sections — only
	 * the first occurrence gets a transition name.
	 *
	 * @var array<string, true>
	 */
	private array $used_names = array();

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_script' ) );
		add_action( 'wp_head', array( $this, 'output_direction_script' ) );
		add_filter( 'wp_speculation_rules_configuration', array( $this, 'configure_speculative_loading' ) );
		add_filter( 'wp_speculation_rules_href_exclude_paths', array( $this, 'exclude_sensitive_paths' ), 10, 2 );

		Block_Filter_Hooks::add_featured_image( array( $this, 'inject_archive_transition_name' ) );
		Block_Filter_Hooks::add(
			'woocommerce/product-image-gallery',
			array( $this, 'inject_single_gallery_transition_name' )
		);
	}

	/**
	 * Enqueue page transition styles on all frontend pages except checkout.
	 *
	 * @return void
	 */
	public function enqueue_styles(): void {
		if ( ! $this->should_load_assets() ) {
			return;
		}

		Asset_Loader::enqueue_feature_style(
			'aggressive-apparel-page-transitions',
			'build/styles/woocommerce/page-transitions'
		);
	}

	/**
	 * Register and enqueue the page transitions script module.
	 *
	 * Provides a navigation progress bar and a pointerdown prefetch fallback for
	 * browsers without native speculation-rules support.
	 *
	 * @return void
	 */
	public function enqueue_script(): void {
		if ( ! $this->should_load_assets() ) {
			return;
		}

		// No Interactivity API dependency — this module is a standalone
		// navigation progress bar + prefetch helper.
		Asset_Loader::enqueue_interactivity_module(
			'@aggressive-apparel/page-transitions',
			'build/interactivity/page-transitions',
			array(),
			false
		);
	}

	/**
	 * Configure WordPress core speculative loading on product display routes.
	 *
	 * Core owns validation, URL prefixing, logged-in behavior, query-string
	 * exclusions, and final script output. The theme only makes eligible links
	 * hover-driven and explicitly disables the more expensive prerender mode.
	 *
	 * @param array<string, string>|null $configuration Core configuration.
	 * @return array<string, string>|null Filtered configuration.
	 */
	public function configure_speculative_loading( ?array $configuration ): ?array {
		if ( null === $configuration || ! $this->should_load_assets() ) {
			return $configuration;
		}

		return array(
			'mode'      => 'prefetch',
			'eagerness' => 'moderate',
		);
	}

	/**
	 * Add commerce routes that must not be prefetched.
	 *
	 * WordPress core already excludes admin, login, asset, nonce, query-string,
	 * nofollow, and `.no-prefetch` URLs. These additions cover personalized and
	 * transaction-oriented WooCommerce routes.
	 *
	 * @param string[] $paths Existing excluded path patterns.
	 * @param string   $mode  Active speculative loading mode.
	 * @return string[] Filtered path patterns.
	 */
	public function exclude_sensitive_paths( array $paths, string $mode ): array {
		if ( 'prefetch' !== $mode && 'prerender' !== $mode ) {
			return $paths;
		}

		return array_values( array_unique( array_merge( $paths, self::SPECULATION_EXCLUDE_PATHS ) ) );
	}

	/**
	 * Output inline script to detect view-transition navigations.
	 *
	 * Must be parser-blocking in <head> (not deferred) because the
	 * pagereveal event fires before first render. Adds 'vt-navigated'
	 * class to <html> so CSS can scope stagger animations to view
	 * transition navigations only (not direct loads/refreshes).
	 *
	 * @return void
	 */
	public function output_direction_script(): void {
		if ( ! $this->should_load_assets() ) {
			return;
		}

		?>
		<script>
		addEventListener('pagereveal',function(e){
			if(e.viewTransition)document.documentElement.classList.add('vt-navigated');
		});
		</script>
		<?php
	}

	/**
	 * Inject view-transition-name on archive product card featured images.
	 *
	 * @param string               $block_content Rendered block HTML.
	 * @param array<string, mixed> $block         Block data.
	 * @return string Modified block HTML.
	 */
	public function inject_archive_transition_name( string $block_content, array $block ): string {
		unset( $block );

		return $this->handle_archive_image( $block_content );
	}

	/**
	 * Inject view-transition-name on the single-product image gallery block.
	 *
	 * @param string               $block_content Rendered block HTML.
	 * @param array<string, mixed> $block         Block data.
	 * @return string Modified block HTML.
	 */
	public function inject_single_gallery_transition_name( string $block_content, array $block ): string {
		unset( $block );

		return $this->handle_single_gallery( $block_content );
	}

	/**
	 * Whether page-transition assets and speculation rules should load.
	 *
	 * Limited to product display routes so blog posts and static pages do not
	 * load transition assets for unrelated links.
	 *
	 * @return bool
	 */
	private function should_load_assets(): bool {
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return false;
		}

		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return false;
		}

		return Product_Context::is_product_display_page();
	}

	/**
	 * Add transition name to product featured images on archive/listing pages.
	 *
	 * Targets the <img> element for a cleaner morph between the grid
	 * thumbnail and the single product hero image.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @return string Modified HTML.
	 */
	private function handle_archive_image( string $block_content ): string {
		if ( ! $this->is_listing_page() ) {
			return $block_content;
		}

		$product_id = (int) get_the_ID();
		if ( $product_id <= 0 ) {
			return $block_content;
		}

		$name = sprintf( 'product-img-%d', $product_id );
		if ( ! $this->claim_name( $name ) ) {
			return $block_content;
		}

		$style = sprintf( 'view-transition-name:%s;view-transition-class:product-img', $name );

		return $this->inject_style_on_img( $block_content, $style );
	}

	/**
	 * Add transition name to the product image gallery on single product pages.
	 *
	 * Targets the main <img> element inside the gallery wrapper.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @return string Modified HTML.
	 */
	private function handle_single_gallery( string $block_content ): string {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return $block_content;
		}

		$product_id = (int) get_the_ID();
		if ( $product_id <= 0 ) {
			return $block_content;
		}

		$name = sprintf( 'product-img-%d', $product_id );
		if ( ! $this->claim_name( $name ) ) {
			return $block_content;
		}

		$style = sprintf( 'view-transition-name:%s;view-transition-class:product-img', $name );

		return $this->inject_style_on_img( $block_content, $style );
	}

	/**
	 * Inject an inline style onto the first <img> element found in the HTML.
	 *
	 * Falls back to the first element if no <img> is present.
	 *
	 * @param string $html  Block HTML.
	 * @param string $style CSS declarations to inject.
	 * @return string Modified HTML.
	 */
	private function inject_style_on_img( string $html, string $style ): string {
		// Try to find an <img> tag and inject the style onto it.
		if ( str_contains( $html, '<img' ) ) {
			$injected = false;
			$result   = (string) preg_replace_callback(
				'/<img\b([^>]*?)(\s*\/?>)/i',
				function ( $matches ) use ( $style, &$injected ) {
					if ( $injected ) {
						return $matches[0];
					}
					$injected   = true;
					$attributes = $matches[1];
					$closing    = $matches[2];

					if ( preg_match( '/\bstyle="([^"]*)"/i', $attributes ) ) {
						$attributes = (string) preg_replace(
							'/\bstyle="([^"]*)"/i',
							'style="$1;' . esc_attr( $style ) . '"',
							$attributes,
							1
						);
						return '<img' . $attributes . $closing;
					}

					return '<img' . $attributes . ' style="' . esc_attr( $style ) . '"' . $closing;
				},
				$html
			);
			return $result;
		}

		// Fallback: inject on the first element.
		return $this->inject_inline_style( $html, $style );
	}

	/**
	 * Inject an inline style into the first HTML element of block content.
	 *
	 * Appends to existing style attribute or creates a new one.
	 *
	 * @param string $html  Block HTML.
	 * @param string $style CSS declarations to inject.
	 * @return string Modified HTML.
	 */
	private function inject_inline_style( string $html, string $style ): string {
		// If there's an existing style attribute, append to it.
		if ( preg_match( '/^(<[a-z][^>]*)\bstyle="([^"]*)"/i', $html ) ) {
			return (string) preg_replace(
				'/^(<[a-z][^>]*)\bstyle="([^"]*)"/i',
				'$1style="$2;' . esc_attr( $style ) . '"',
				$html,
				1
			);
		}

		// No existing style — add one to the first element.
		return (string) preg_replace(
			'/^(<[a-z][^>]*?)(\s*\/?>)/i',
			'$1 style="' . esc_attr( $style ) . '"$2',
			$html,
			1
		);
	}

	/**
	 * Check if the current page is a product listing (archive, category, tag, shop).
	 *
	 * @return bool
	 */
	private function is_listing_page(): bool {
		return Product_Context::is_product_listing();
	}

	/**
	 * Claim a view-transition-name, returning false if already used.
	 *
	 * View transition names must be unique per page. This prevents
	 * duplicates when a product appears in both the main content and
	 * a related/recommended products section.
	 *
	 * @param string $name The transition name to claim.
	 * @return bool True if claimed, false if already taken.
	 */
	private function claim_name( string $name ): bool {
		if ( isset( $this->used_names[ $name ] ) ) {
			return false;
		}

		$this->used_names[ $name ] = true;

		return true;
	}
}
