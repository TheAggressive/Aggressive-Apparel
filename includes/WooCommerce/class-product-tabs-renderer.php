<?php
/**
 * Product Tabs frontend renderers.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend product info display renderers.
 *
 * @since 1.17.0
 */
class Product_Tabs_Renderer {

	/**
	 * Content helper service.
	 *
	 * @var Product_Tabs_Content
	 */
	private Product_Tabs_Content $content;

	/**
	 * Product tabs orchestrator.
	 *
	 * @var Product_Tabs
	 */
	private Product_Tabs $tabs;

	/**
	 * Constructor.
	 *
	 * @param Product_Tabs_Content $content Content helper service.
	 * @param Product_Tabs         $tabs    Product tabs orchestrator.
	 */
	public function __construct( Product_Tabs_Content $content, Product_Tabs $tabs ) {
		$this->content = $content;
		$this->tabs    = $tabs;
	}

	/**
	 * Render tabs using the configured display style.
	 *
	 * @param array  $tabs         Renderable tab data.
	 * @param string $fallback     Original Product Details block HTML.
	 * @param bool   $hide_titles  Whether to hide the section heading above each tab's content.
	 * @return string Rendered HTML.
	 */
	public function render_tabs_by_style( array $tabs, string $fallback, bool $hide_titles = false ): string {
		switch ( $this->tabs->get_display_style() ) {
			case 'accordion':
				return $this->render_accordion( $tabs );
			case 'inline':
				return $this->render_inline( $tabs, $hide_titles );
			case 'modern-tabs':
				return $this->render_modern_tabs( $tabs );
			case 'scrollspy':
				return $this->render_scrollspy( $tabs, $hide_titles );
			default:
				return $fallback;
		}
	}

	/**
	 * Render tabs as accordions.
	 *
	 * @param array $tabs Renderable tab data.
	 * @return string Rendered HTML.
	 */
	public function render_accordion( array $tabs ): string {
		$html = '<div class="woocommerce aa-product-info aa-product-info--accordion" data-wp-interactive="aggressive-apparel/product-tabs" data-wp-init="callbacks.initHashNav">';

		foreach ( $tabs as $index => $tab ) {
			$open  = 0 === $index ? ' open' : '';
			$html .= sprintf(
				'<details class="aa-product-info__section" id="pi-%s"%s>' .
				'<summary class="aa-product-info__heading" data-wp-on--click="actions.toggleAccordion">' .
				'<span>%s</span>' .
				'<svg class="aa-product-info__chevron" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>' .
				'</summary>' .
				'<div class="aa-product-info__content is-layout-flow">%s</div>' .
				'</details>',
				esc_attr( $tab['id'] ),
				$open,
				esc_html( $tab['title'] ),
				$this->content->kses_tab_content( $tab['content'] ),
			);
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * Render tabs inline.
	 *
	 * @param array $tabs         Renderable tab data.
	 * @param bool  $hide_titles  Whether to omit the heading above each section.
	 * @return string Rendered HTML.
	 */
	public function render_inline( array $tabs, bool $hide_titles = false ): string {
		$html = '<div class="woocommerce aa-product-info aa-product-info--inline">';

		foreach ( $tabs as $tab ) {
			$heading = $hide_titles ? '' : sprintf( '<h3 class="aa-product-info__heading">%s</h3>', esc_html( $tab['title'] ) );
			$html   .= sprintf(
				'<section class="aa-product-info__section" id="pi-%s">%s<div class="aa-product-info__content is-layout-flow">%s</div></section>',
				esc_attr( $tab['id'] ),
				$heading,
				$this->content->kses_tab_content( $tab['content'] ),
			);
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * Render tabs with tablist semantics.
	 *
	 * @param array $tabs Renderable tab data.
	 * @return string Rendered HTML.
	 */
	public function render_modern_tabs( array $tabs ): string {
		$context = (string) wp_json_encode( array( 'tabCount' => count( $tabs ) ) );

		$html  = '<div class="woocommerce aa-product-info aa-product-info--modern-tabs" data-wp-interactive="aggressive-apparel/product-tabs" data-wp-context=\'' . esc_attr( $context ) . '\' data-wp-init="callbacks.initHashNav">';
		$html .= '<nav class="aa-product-info__tab-nav" role="tablist" aria-label="' . esc_attr__( 'Product information', 'aggressive-apparel' ) . '">';

		foreach ( $tabs as $index => $tab ) {
			$tab_context = (string) wp_json_encode( array( 'tabIndex' => $index ) );
			$selected    = 0 === $index ? 'true' : 'false';
			$tabindex    = 0 === $index ? '0' : '-1';
			$html       .= sprintf(
				'<button role="tab" id="tab-%s" aria-selected="%s" aria-controls="pi-%s" tabindex="%s" ' .
				'data-wp-context=\'%s\' ' .
				'data-wp-on--click="actions.selectTab" ' .
				'data-wp-on--keydown="actions.handleTabKeydown" ' .
				'data-wp-class--is-active="state.isActiveTab" ' .
				'data-wp-bind--aria-selected="state.ariaSelected" ' .
				'data-wp-bind--tabindex="state.tabTabindex">' .
				'%s</button>',
				esc_attr( $tab['id'] ),
				$selected,
				esc_attr( $tab['id'] ),
				$tabindex,
				esc_attr( $tab_context ),
				esc_html( $tab['title'] ),
			);
		}

		$html .= '</nav>';

		foreach ( $tabs as $index => $tab ) {
			$panel_context = (string) wp_json_encode( array( 'tabIndex' => $index ) );
			$hidden        = 0 === $index ? '' : ' hidden';
			$html         .= sprintf(
				'<div role="tabpanel" id="pi-%s" aria-labelledby="tab-%s" tabindex="0" ' .
				'class="aa-product-info__tab-panel" ' .
				'data-wp-context=\'%s\' ' .
				'data-wp-bind--hidden="!state.isPanelVisible"%s>' .
				'<div class="aa-product-info__content is-layout-flow">%s</div>' .
				'</div>',
				esc_attr( $tab['id'] ),
				esc_attr( $tab['id'] ),
				esc_attr( $panel_context ),
				$hidden,
				$this->content->kses_tab_content( $tab['content'] ),
			);
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * Render tabs with a scrollspy navigation.
	 *
	 * @param array $tabs         Renderable tab data.
	 * @param bool  $hide_titles  Whether to omit the heading above each section.
	 * @return string Rendered HTML.
	 */
	public function render_scrollspy( array $tabs, bool $hide_titles = false ): string {
		$html = '<div class="woocommerce aa-product-info aa-product-info--scrollspy" data-wp-interactive="aggressive-apparel/product-tabs" data-wp-init="callbacks.initScrollspy">';

		// Sidebar nav.
		$html .= '<nav class="aa-product-info__sidebar" aria-label="' . esc_attr__( 'Product information', 'aggressive-apparel' ) . '">';

		foreach ( $tabs as $index => $tab ) {
			$nav_context = (string) wp_json_encode( array( 'sectionId' => 'pi-' . $tab['id'] ) );
			$is_first    = 0 === $index ? ' is-active' : '';
			$html       .= sprintf(
				'<a href="#pi-%s" class="aa-product-info__nav-link%s" ' .
				'data-wp-context=\'%s\' ' .
				'data-wp-on--click="actions.scrollToSection" ' .
				'data-wp-class--is-active="state.isActiveNav" ' .
				'data-wp-bind--aria-current="state.ariaCurrent">' .
				'%s</a>',
				esc_attr( $tab['id'] ),
				$is_first,
				esc_attr( $nav_context ),
				esc_html( $tab['title'] ),
			);
		}

		$html .= '</nav>';

		// Main content.
		$html .= '<div class="aa-product-info__main">';

		foreach ( $tabs as $tab ) {
			$heading = $hide_titles ? '' : sprintf( '<h3 class="aa-product-info__heading">%s</h3>', esc_html( $tab['title'] ) );
			$html   .= sprintf(
				'<section id="pi-%s" class="aa-product-info__section">%s<div class="aa-product-info__content is-layout-flow">%s</div></section>',
				esc_attr( $tab['id'] ),
				$heading,
				$this->content->kses_tab_content( $tab['content'] ),
			);
		}

		$html .= '</div>';
		$html .= '</div>';
		return $html;
	}

	/**
	 * Extract tabs from WooCommerce's rendered Product Details HTML.
	 *
	 * @param string $html Original Product Details block HTML.
	 * @return array<int, array<string, string>> Renderable tab data.
	 */
	public function extract_tabs_from_html( string $html ): array {
		if ( '' === trim( $html ) ) {
			return array();
		}

		$use_errors = libxml_use_internal_errors( true );
		$doc        = new \DOMDocument();
		$doc->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $use_errors );

		$xpath = new \DOMXPath( $doc );
		$tabs  = array();

		// Extract tab titles from the <ul class="wc-tabs"> list items.
		$tab_links = $xpath->query( '//ul[contains(@class,"wc-tabs")]//a' );
		if ( ! $tab_links || 0 === $tab_links->length ) {
			return array();
		}

		$titles = array();
		foreach ( $tab_links as $link ) {
			if ( ! $link instanceof \DOMElement ) {
				continue;
			}
			$href = $link->getAttribute( 'href' );
			$id   = ltrim( $href, '#' );
			$text = trim( $link->textContent );
			if ( '' !== $id && '' !== $text ) {
				$titles[] = array(
					'id'    => $id,
					'title' => $text,
				);
			}
		}

		// Extract tab content from panels.
		foreach ( $titles as $tab_info ) {
			// Sanitize the ID for safe use in XPath — strip anything outside [a-zA-Z0-9_-].
			$safe_id = (string) preg_replace( '/[^a-zA-Z0-9_-]/', '', $tab_info['id'] );
			$panel   = $xpath->query( '//*[@id="' . $safe_id . '"]' );
			if ( $panel && $panel->length > 0 ) {
				$panel_node = $panel->item( 0 );
				$inner_html = '';
				if ( $panel_node instanceof \DOMElement ) {
					// Remove <script> tags — DOMDocument mangles them on re-serialization.
					$scripts = $xpath->query( './/script', $panel_node );
					if ( $scripts ) {
						foreach ( $scripts as $script ) {
							if ( $script->parentNode ) {
								$script->parentNode->removeChild( $script );
							}
						}
					}
					foreach ( $panel_node->childNodes as $child ) {
						$inner_html .= $doc->saveHTML( $child );
					}
				}
				// Use WC's original tab slug (e.g. "tab-reviews") stripped of "tab-" prefix
				// for stable IDs that don't change with review count.
				$slug    = (string) preg_replace( '/^tab-/', '', $safe_id );
				$content = trim( $inner_html );

				if ( $this->is_tab_content_empty( $content, $tab_info['title'] ) ) {
					continue;
				}

				$tabs[] = array(
					'title'   => $tab_info['title'],
					'id'      => $slug,
					'content' => $content,
				);
			}
		}

		return $tabs;
	}

	/**
	 * Check whether a tab panel is empty or contains only its title.
	 *
	 * @param string $content Tab panel content.
	 * @param string $title   Tab title.
	 * @return bool True when content should be skipped.
	 */
	public function is_tab_content_empty( string $content, string $title ): bool {
		if ( '' === trim( $content ) ) {
			return true;
		}

		$text = html_entity_decode( wp_strip_all_tags( $content ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );
		$text = trim( (string) preg_replace( '/\s+/', ' ', str_replace( "\xc2\xa0", ' ', $text ) ) );

		if ( '' === $text ) {
			return true;
		}

		$title = trim( (string) preg_replace( '/\s+/', ' ', $title ) );

		return '' !== $title && 0 === strcasecmp( $text, $title );
	}

	/**
	 * Respect the Product Details block setting for title duplication.
	 *
	 * @param array $block Parsed block data.
	 * @return bool True when title headings should be removed from panels.
	 */
	public function should_hide_tab_title_in_content( array $block ): bool {
		return ! empty( $block['attrs']['hideTabTitle'] );
	}

	/**
	 * Remove duplicate leading headings from all tabs.
	 *
	 * @param array $tabs Renderable tab data.
	 * @return array Renderable tab data.
	 */
	public function remove_tab_title_headings( array $tabs ): array {
		$output = array();

		foreach ( $tabs as $tab ) {
			$content = $this->strip_leading_tab_title_heading( $tab['content'], $tab['title'] );

			if ( $this->is_tab_content_empty( $content, $tab['title'] ) ) {
				continue;
			}

			$tab['content'] = $content;
			$output[]       = $tab;
		}

		return $output;
	}

	/**
	 * Strip a leading heading when it matches the tab title.
	 *
	 * @param string $content Tab panel content.
	 * @param string $title   Tab title.
	 * @return string Updated tab panel content.
	 */
	public function strip_leading_tab_title_heading( string $content, string $title ): string {
		if ( '' === trim( $content ) || '' === trim( $title ) ) {
			return $content;
		}

		if ( 1 !== preg_match( '/^\s*(?:<!--.*?-->\s*)*(<h([1-6])\b[^>]*>.*?<\/h\2>)/is', $content, $matches ) ) {
			return $content;
		}

		$heading_text = $this->normalize_tab_heading_text( wp_strip_all_tags( $matches[1] ) );
		$title_text   = $this->normalize_tab_heading_text( $title );

		if ( '' === $heading_text || '' === $title_text ) {
			return $content;
		}

		if ( $heading_text !== $title_text ) {
			return $content;
		}

		return trim( substr( $content, strlen( $matches[0] ) ) );
	}

	/**
	 * Normalize heading text for duplicate-title comparisons.
	 *
	 * @param string $text Heading or title text.
	 * @return string Normalized text.
	 */
	public function normalize_tab_heading_text( string $text ): string {
		$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );
		$text = trim( (string) preg_replace( '/\s+/', ' ', str_replace( "\xc2\xa0", ' ', $text ) ) );
		$text = (string) preg_replace( '/\s+\(\d+\)$/', '', $text );

		return strtolower( $text );
	}

	/**
	 * Build renderable tab data from WooCommerce tab callbacks.
	 *
	 * @param array $block          Parsed block data.
	 * @param bool  $hide_tab_title Whether duplicate tab title headings should be removed.
	 * @param mixed $block_instance Rendered block instance.
	 * @return array<int, array<string, string>> Renderable tab data.
	 */
	public function get_renderable_woocommerce_tabs( array $block, bool $hide_tab_title, $block_instance = null ): array {
		$previous_product_id           = $this->tabs->render_product_id;
		$this->tabs->render_product_id = $this->tabs->get_current_product_id( $block, $block_instance );
		$product_id                    = $this->tabs->render_product_id;

		try {
			$product_tabs = apply_filters( 'woocommerce_product_tabs', array() );
		} finally {
			$this->tabs->render_product_id = $previous_product_id;
		}

		if ( empty( $product_tabs ) || ! is_array( $product_tabs ) ) {
			return array();
		}

		$product_tabs = $this->ensure_product_custom_tabs_registered( $product_tabs, $product_id );

		uasort(
			$product_tabs,
			static function ( array $a, array $b ): int {
				return ( (int) ( $a['priority'] ?? 10 ) ) <=> ( (int) ( $b['priority'] ?? 10 ) );
			}
		);

		$tabs = array();

		foreach ( $product_tabs as $key => $tab ) {
			if ( ! is_array( $tab ) || empty( $tab['title'] ) || empty( $tab['callback'] ) || ! is_callable( $tab['callback'] ) ) {
				continue;
			}

			$title   = (string) $tab['title'];
			$content = $this->render_woocommerce_tab_callback( (string) $key, $tab );

			if ( $hide_tab_title ) {
				$content = $this->strip_leading_tab_title_heading( $content, $title );
			}

			if ( $this->is_tab_content_empty( $content, $title ) ) {
				continue;
			}

			$id = (string) preg_replace( '/^tab-/', '', sanitize_key( (string) $key ) );

			if ( '' === $id ) {
				$id = 'product-info-' . count( $tabs );
			}

			$tabs[] = array(
				'title'   => $title,
				'id'      => $id,
				'content' => $content,
			);
		}

		return $tabs;
	}

	/**
	 * Ensure product-specific custom tabs are present when rendering from callbacks.
	 *
	 * @param array $tabs       WooCommerce tab registry.
	 * @param int   $product_id Product ID.
	 * @return array WooCommerce tab registry.
	 */
	public function ensure_product_custom_tabs_registered( array $tabs, int $product_id ): array {
		if ( $product_id <= 0 ) {
			return $tabs;
		}

		foreach ( $tabs as $key => $tab ) {
			if ( 0 === strpos( (string) $key, 'aa_product_' ) ) {
				return $tabs;
			}
		}

		$product_tabs = get_post_meta( $product_id, Product_Tabs_Config::PRODUCT_CUSTOM_TABS_META_KEY, true );
		$product_tabs = $this->tabs->get_sanitizer()->sanitize_custom_tabs( $product_tabs );

		if ( empty( $product_tabs ) ) {
			return $tabs;
		}

		return $this->tabs->append_custom_tabs( $tabs, $product_tabs, 'product', $product_id );
	}

	/**
	 * Capture a WooCommerce tab callback into a string.
	 *
	 * @param string $key Tab key.
	 * @param array  $tab Tab registry entry.
	 * @return string Rendered callback output.
	 */
	public function render_woocommerce_tab_callback( string $key, array $tab ): string {
		$buffer_level = ob_get_level();

		ob_start();

		try {
			call_user_func( $tab['callback'], $key, $tab );
			return trim( (string) ob_get_clean() );
		} catch ( \Throwable $exception ) {
			while ( ob_get_level() > $buffer_level ) {
				// A non-removable buffer makes ob_end_clean() return false
				// without lowering the level — bail instead of spinning.
				if ( ! ob_end_clean() ) {
					break;
				}
			}

			return '';
		}
	}
}
