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
		add_action( 'wp_head', array( $this, 'output_speculation_rules' ) );
		add_action( 'wp_head', array( $this, 'output_direction_script' ) );
		add_filter( 'render_block', array( $this, 'inject_transition_names' ), 10, 2 );
	}

	/**
	 * Enqueue page transition styles on all frontend pages except checkout.
	 *
	 * @return void
	 */
	public function enqueue_styles(): void {
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return;
		}

		$css_file = AGGRESSIVE_APPAREL_DIR . '/build/styles/woocommerce/page-transitions.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'aggressive-apparel-page-transitions',
				AGGRESSIVE_APPAREL_URI . '/build/styles/woocommerce/page-transitions.css',
				array(),
				(string) filemtime( $css_file ),
			);
		}
	}

	/**
	 * Register and enqueue the page transitions script module.
	 *
	 * Provides a navigation progress bar and pointerdown prefetch
	 * for faster perceived navigation. No Interactivity API dependency.
	 *
	 * @return void
	 */
	public function enqueue_script(): void {
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return;
		}

		if ( ! function_exists( 'wp_register_script_module' ) ) {
			return;
		}

		$js_file = AGGRESSIVE_APPAREL_DIR . '/assets/interactivity/page-transitions.js';
		if ( ! file_exists( $js_file ) ) {
			return;
		}

		wp_register_script_module(
			'@aggressive-apparel/page-transitions',
			AGGRESSIVE_APPAREL_URI . '/assets/interactivity/page-transitions.js',
			array(),
			(string) filemtime( $js_file ),
		);
		wp_enqueue_script_module( '@aggressive-apparel/page-transitions' );
	}

	/**
	 * Output two-tier speculation rules for SPA-like navigation speed.
	 *
	 * Tier 1 (prefetch/eager): All in-viewport same-origin links get their
	 * HTML prefetched immediately — lightweight, just a document fetch.
	 *
	 * Tier 2 (prerender/moderate): Hovered links get fully prerendered in
	 * a hidden tab (~200ms delay) — heavier but near-instant navigation.
	 *
	 * Browsers that don't support speculation rules ignore the unknown
	 * script type — zero impact on unsupported browsers.
	 *
	 * @return void
	 */
	public function output_speculation_rules(): void {
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return;
		}

		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return;
		}

		$where = array(
			'and' => array(
				array( 'href_matches' => '/*' ),
				array( 'not' => array( 'href_matches' => '/checkout/*' ) ),
				array( 'not' => array( 'href_matches' => '/cart/*' ) ),
				array( 'not' => array( 'href_matches' => '/wp-admin/*' ) ),
				array( 'not' => array( 'href_matches' => '/wp-login.php' ) ),
				array( 'not' => array( 'selector_matches' => '[target=_blank]' ) ),
			),
		);

		$rules = array(
			'prefetch'  => array(
				array(
					'where'     => $where,
					'eagerness' => 'eager',
				),
			),
			'prerender' => array(
				array(
					'where'     => $where,
					'eagerness' => 'moderate',
				),
			),
		);

		printf(
			'<script type="speculationrules">%s</script>' . "\n",
			wp_json_encode( $rules, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG )
		);
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
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
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
	 * Inject view-transition-name on product images for shared element morphing.
	 *
	 * On archive pages: targets core/post-featured-image.
	 * On single product: targets woocommerce/product-image-gallery.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Block data including blockName.
	 * @return string Modified block HTML.
	 */
	public function inject_transition_names( string $block_content, array $block ): string {
		$block_name = $block['blockName'] ?? '';

		if ( 'core/post-featured-image' === $block_name ) {
			return $this->handle_archive_image( $block_content );
		}

		if ( 'woocommerce/product-image-gallery' === $block_name ) {
			return $this->handle_single_gallery( $block_content );
		}

		return $block_content;
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
		if ( ! function_exists( 'is_shop' ) ) {
			return false;
		}

		return is_shop() || is_product_category() || is_product_tag() || is_search();
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
