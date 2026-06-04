<?php
/**
 * Product Tabs Class
 *
 * Adds custom global and per-product tabs to single product pages,
 * and replaces the default WooCommerce tab UI with one of four
 * selectable display styles: accordion, inline, modern-tabs, scrollspy.
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
 * Product Tabs Manager
 *
 * @since 1.17.0
 */
class Product_Tabs {

	/**
	 * Option key for global tab content and display settings.
	 *
	 * @var string
	 */
	private const OPTION_KEY = 'aggressive_apparel_product_tabs';

	/**
	 * Product meta key for per-product custom tabs.
	 *
	 * @var string
	 */
	private const PRODUCT_CUSTOM_TABS_META_KEY = '_aggressive_apparel_custom_tabs';

	/**
	 * Product meta key for per-product global tab overrides.
	 *
	 * @var string
	 */
	private const PRODUCT_TAB_OVERRIDES_META_KEY = '_aggressive_apparel_tab_overrides';

	/**
	 * Valid display style values.
	 *
	 * @var string[]
	 */
	private const VALID_STYLES = array( 'accordion', 'inline', 'modern-tabs', 'scrollspy' );

	/**
	 * Valid tab content source values.
	 *
	 * @var string[]
	 */
	private const VALID_TAB_SOURCES = array( 'manual', 'product_meta', 'product_attribute' );

	/**
	 * Valid no-code custom tab layout values.
	 *
	 * @var string[]
	 */
	private const VALID_TAB_LAYOUTS = array(
		'rich_text',
		'feature_cards',
		'care_grid',
		'specs_table',
		'faq',
		'shipping_timeline',
		'icon_list',
		'custom_html',
	);

	/**
	 * Valid per-product override modes for global tabs.
	 *
	 * @var string[]
	 */
	private const VALID_OVERRIDE_MODES = array( 'inherit', 'override', 'append', 'hide' );

	/**
	 * Product ID currently being rendered by the Product Details block.
	 *
	 * @var int
	 */
	private int $render_product_id = 0;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'woocommerce_product_tabs', array( $this, 'add_custom_tabs' ), 20 );
		add_filter( 'render_block', array( $this, 'transform_product_details_block' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_product', array( $this, 'save_meta' ) );
		add_action( 'admin_init', array( $this, 'register_global_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Get the current display style.
	 *
	 * @return string One of: accordion, inline, modern-tabs, scrollspy.
	 */
	public function get_display_style(): string {
		$options = get_option( self::OPTION_KEY, array() );
		$style   = is_array( $options ) && isset( $options['display_style'] ) ? $options['display_style'] : 'accordion';

		return in_array( $style, self::VALID_STYLES, true ) ? $style : 'accordion';
	}

	/**
	 * Enqueue styles and Interactivity API script module on single product pages.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! $this->is_product_page() ) {
			return;
		}

		$css_file = AGGRESSIVE_APPAREL_DIR . '/build/styles/woocommerce/product-tabs.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'aggressive-apparel-product-tabs',
				AGGRESSIVE_APPAREL_URI . '/build/styles/woocommerce/product-tabs.css',
				array( \Aggressive_Apparel\Assets\Asset_Loader::TOKENS_HANDLE ),
				(string) filemtime( $css_file ),
			);
		}

		$style = $this->get_display_style();

		// Only accordion, modern-tabs, and scrollspy need JS.
		if ( 'inline' !== $style && function_exists( 'wp_register_script_module' ) ) {
			wp_register_script_module(
				'@aggressive-apparel/product-tabs',
				AGGRESSIVE_APPAREL_URI . '/build/interactivity/product-tabs.js',
				array( '@wordpress/interactivity' ),
				AGGRESSIVE_APPAREL_VERSION,
			);
			wp_enqueue_script_module( '@aggressive-apparel/product-tabs' );
		}

		if ( function_exists( 'wp_interactivity_state' ) ) {
			wp_interactivity_state(
				'aggressive-apparel/product-tabs',
				array(
					'style'         => $style,
					'activeTab'     => 0,
					'activeSection' => '',
				),
			);
		}
	}

		/**
		 * Enqueue lightweight admin styles for the custom tab builder.
		 *
		 * @return void
		 */
	public function enqueue_admin_assets(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || ( 'product' !== $screen->post_type && false === strpos( (string) $screen->id, 'aggressive-apparel' ) ) ) {
			return;
		}

		wp_register_style( 'aggressive-apparel-product-tabs-admin', false, array(), AGGRESSIVE_APPAREL_VERSION );
		wp_enqueue_style( 'aggressive-apparel-product-tabs-admin' );
		wp_add_inline_style(
			'aggressive-apparel-product-tabs-admin',
			'
				.aa-custom-tabs-repeater { max-width: 960px; }
				.aa-custom-tabs-repeater__row,
				.aa-tab-override {
					margin: 0 0 1rem;
					padding: 1rem;
					border: 1px solid #dcdcde;
					border-radius: 8px;
					background: #fff;
					box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
				}
				.aa-custom-tabs-repeater__toolbar {
					display: flex;
					gap: 1rem;
					align-items: center;
					flex-wrap: wrap;
					margin-top: 0;
					padding-bottom: 0.75rem;
					border-bottom: 1px solid #f0f0f1;
				}
				.aa-custom-tabs-repeater__grid {
					display: grid;
					grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
					gap: 1rem;
				}
					.aa-custom-tabs-repeater label,
					.aa-tab-override label {
						font-weight: 600;
					}
					.aa-tab-override__title {
						margin-top: 0;
					}
					.aa-custom-tabs-repeater input[type="text"],
					.aa-custom-tabs-repeater select,
					.aa-custom-tabs-repeater textarea {
						margin-top: 0.25rem;
					}
					.aa-custom-tabs-repeater .regular-text,
					.aa-custom-tab-items .regular-text {
						max-width: 100%;
					}
					.aa-custom-tabs-repeater__priority-input,
					.aa-custom-tab-items__icon-input {
						width: 5rem;
					}
					.aa-custom-tabs-source-fields {
						display: grid;
						grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
						gap: 1rem;
					}
				.aa-custom-tabs-repeater__row[data-source="manual"] [data-source-field],
				.aa-custom-tabs-repeater__row[data-source="product_meta"] [data-source-field="attribute"],
				.aa-custom-tabs-repeater__row[data-source="product_attribute"] [data-source-field="meta"],
				.aa-custom-tabs-repeater__row[data-layout="rich_text"] .aa-custom-tab-items,
				.aa-custom-tabs-repeater__row[data-layout="custom_html"] .aa-custom-tab-items {
					display: none;
				}
				.aa-custom-tab-items {
					margin-top: 1rem;
					padding: 1rem;
					border: 1px dashed #c3c4c7;
					border-radius: 8px;
					background: #f6f7f7;
				}
				.aa-custom-tab-items__header {
					margin-top: 0;
				}
				.aa-custom-tab-items__row {
					margin: 0 0 0.75rem;
					padding: 0.75rem;
					border: 1px solid #dcdcde;
					border-radius: 6px;
					background: #fff;
				}
				.aa-custom-tab-items__row-fields {
					display: grid;
					grid-template-columns: 5rem repeat(2, minmax(180px, 1fr)) auto;
					gap: 0.75rem;
					align-items: end;
					margin-top: 0;
				}
					.aa-custom-tabs-layout-help {
						display: block;
						margin-top: 0.35rem;
					}
					.aa-custom-tabs-preview {
						margin-top: 1rem;
						padding: 1rem;
						border: 1px solid #dcdcde;
						border-radius: 8px;
						background: #fbfbfc;
					}
					.aa-custom-tabs-preview__label {
						margin-top: 0;
					}
					.aa-custom-tabs-preview__surface {
						padding: 1rem;
						border: 1px solid #dcdcde;
						border-radius: 8px;
						background: #fff;
					}
					.aa-custom-tabs-preview__title {
						margin: 0 0 0.75rem;
						font-size: 0.875rem;
						letter-spacing: 0.04em;
						text-transform: uppercase;
					}
					.aa-custom-tabs-preview .aa-product-info__content {
						padding: 0;
						font-size: 1rem;
						line-height: 1.6;
					}
					.aa-custom-tabs-preview .aa-product-info__content > *:first-child {
						margin-top: 0;
					}
					.aa-custom-tabs-preview .aa-product-info__content > *:last-child {
						margin-bottom: 0;
					}
					.aa-custom-tabs-preview .aa-product-tab-layout {
						margin-block: 1rem 0;
						padding: 0;
						list-style: none;
					}
					.aa-custom-tabs-preview .aa-product-tab-layout--feature-cards,
					.aa-custom-tabs-preview .aa-product-tab-layout--care-grid {
						display: grid;
						grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
						gap: 0.875rem;
					}
					.aa-custom-tabs-preview .aa-product-tab-layout--icon-list,
					.aa-custom-tabs-preview .aa-product-tab-layout--shipping-timeline {
						display: grid;
						gap: 0.75rem;
					}
					.aa-custom-tabs-preview .aa-product-tab-layout__item {
						display: flex;
						gap: 0.875rem;
						padding: 1rem;
						border: 1px solid #dcdcde;
						border-radius: 8px;
					}
					.aa-custom-tabs-preview .aa-product-tab-layout--icon-list .aa-product-tab-layout__item,
					.aa-custom-tabs-preview .aa-product-tab-layout--shipping-timeline .aa-product-tab-layout__item {
						padding: 0;
						border: 0;
					}
					.aa-custom-tabs-preview .aa-product-tab-layout__icon {
						display: inline-flex;
						width: 2rem;
						height: 2rem;
						flex: 0 0 2rem;
						align-items: center;
						justify-content: center;
						border-radius: 999px;
						background: #f0f0f1;
						font-weight: 700;
						line-height: 1;
					}
					.aa-custom-tabs-preview .aa-product-tab-layout__meta {
						margin: 0 0 0.25rem;
						color: #646970;
						font-size: 0.75rem;
						font-weight: 700;
						letter-spacing: 0.08em;
						text-transform: uppercase;
					}
					.aa-custom-tabs-preview .aa-product-tab-layout__title {
						margin: 0 0 0.25rem;
						font-size: 0.95rem;
						font-weight: 700;
						line-height: 1.35;
					}
					.aa-custom-tabs-preview .aa-product-tab-layout--specs-table {
						width: 100%;
						border-collapse: collapse;
						border-top: 1px solid #dcdcde;
					}
					.aa-custom-tabs-preview .aa-product-tab-layout--specs-table th,
					.aa-custom-tabs-preview .aa-product-tab-layout--specs-table td {
						padding: 0.75rem 0;
						border-bottom: 1px solid #dcdcde;
						text-align: left;
						vertical-align: top;
					}
					.aa-custom-tabs-preview .aa-product-tab-layout--faq {
						border-top: 1px solid #dcdcde;
					}
					.aa-custom-tabs-preview .aa-product-tab-layout__faq-item {
						border-bottom: 1px solid #dcdcde;
					}
					.aa-custom-tabs-preview .aa-product-tab-layout__faq-question {
						padding: 0.875rem 0;
						font-weight: 700;
						cursor: pointer;
					}
					@media (max-width: 782px) {
						.aa-custom-tab-items__row-fields {
							grid-template-columns: 1fr;
					}
				}
				'
		);
	}

		/**
		 * Intercept the product-details block and replace with chosen style.
		 *
		 * @param string $block_content Block HTML.
		 * @param array  $block         Block data.
		 * @return string Modified or original HTML.
		 */
	public function transform_product_details_block( string $block_content, array $block ): string {
		if ( ! isset( $block['blockName'] ) || 'woocommerce/product-details' !== $block['blockName'] ) {
			return $block_content;
		}

		if ( ! $this->is_product_page() ) {
			return $block_content;
		}

		$hide_tab_title = $this->should_hide_tab_title_in_content( $block );
		$tabs           = $this->get_renderable_woocommerce_tabs( $block, $hide_tab_title );
		if ( empty( $tabs ) ) {
			$tabs = $this->extract_tabs_from_html( $block_content );

			if ( $hide_tab_title ) {
				$tabs = $this->remove_tab_title_headings( $tabs );
			}
		}

		if ( empty( $tabs ) ) {
			return $block_content;
		}

		return $this->render_tabs_by_style( $tabs, $block_content );
	}

	/**
	 * Render tabs using the configured display style.
	 *
	 * @param array<int, array{title: string, id: string, content: string}> $tabs     Tab data.
	 * @param string                                                        $fallback Original block content.
	 * @return string Rendered tab HTML.
	 */
	private function render_tabs_by_style( array $tabs, string $fallback ): string {
		switch ( $this->get_display_style() ) {
			case 'accordion':
				return $this->render_accordion( $tabs );
			case 'inline':
				return $this->render_inline( $tabs );
			case 'modern-tabs':
				return $this->render_modern_tabs( $tabs );
			case 'scrollspy':
				return $this->render_scrollspy( $tabs );
			default:
				return $fallback;
		}
	}

	/**
	 * Get WooCommerce tabs by rendering the registered tab callbacks.
	 *
	 * The Product Details block can render markup that is hard to parse after
	 * custom tabs are added. Reading WooCommerce's canonical tab registry keeps
	 * default, global, and per-product tabs on the same path.
	 *
	 * @param array $block          Block data from render_block.
	 * @param bool  $hide_tab_title Whether tab title headings should be hidden in content.
	 * @return array<int, array{title: string, id: string, content: string}> Renderable tabs.
	 */
	private function get_renderable_woocommerce_tabs( array $block, bool $hide_tab_title ): array {
		$previous_product_id     = $this->render_product_id;
		$this->render_product_id = $this->get_current_product_id( $block );

		try {
			$product_tabs = apply_filters( 'woocommerce_product_tabs', array() );
		} finally {
			$this->render_product_id = $previous_product_id;
		}

		if ( empty( $product_tabs ) || ! is_array( $product_tabs ) ) {
			return array();
		}

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
	 * Capture the HTML output from a WooCommerce tab callback.
	 *
	 * @param string $key Tab key.
	 * @param array  $tab Tab data.
	 * @return string Rendered callback content.
	 */
	private function render_woocommerce_tab_callback( string $key, array $tab ): string {
		$buffer_level = ob_get_level();

		ob_start();

		try {
			call_user_func( $tab['callback'], $key, $tab );
			return trim( (string) ob_get_clean() );
		} catch ( \Throwable $exception ) {
			while ( ob_get_level() > $buffer_level ) {
				ob_end_clean();
			}

			return '';
		}
	}

	/**
	 * Extract tab titles and content from WooCommerce tab HTML.
	 *
	 * @param string $html The rendered product-details block HTML.
	 * @return array<int, array{title: string, id: string, content: string}> Extracted tabs.
	 */
	private function extract_tabs_from_html( string $html ): array {
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
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMElement property.
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
							// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMNode property.
							if ( $script->parentNode ) {
								// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMNode property.
								$script->parentNode->removeChild( $script );
							}
						}
					}
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMElement property.
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
	 * Determine whether a tab panel has meaningful content.
	 *
	 * WooCommerce can render wrapper markup or a lone tab heading even when a
	 * product has no body content. Those panels should not become empty
	 * accordion sections or tabs in the custom product info layouts.
	 *
	 * @param string $content Raw tab panel HTML.
	 * @param string $title   Tab title.
	 * @return bool True when the panel should be hidden.
	 */
	private function is_tab_content_empty( string $content, string $title ): bool {
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
	 * Determine whether the Product Details block should hide tab titles.
	 *
	 * WooCommerce stores the inverse of the editor label as hideTabTitle.
	 *
	 * @param array $block Block data from render_block.
	 * @return bool True when tab titles should be removed from panel content.
	 */
	private function should_hide_tab_title_in_content( array $block ): bool {
		return ! empty( $block['attrs']['hideTabTitle'] );
	}

	/**
	 * Remove leading title headings from rendered tab content.
	 *
	 * @param array<int, array{title: string, id: string, content: string}> $tabs Tab data.
	 * @return array<int, array{title: string, id: string, content: string}> Tab data without leading title headings.
	 */
	private function remove_tab_title_headings( array $tabs ): array {
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
	 * Strip the first heading when it is the tab's generated title.
	 *
	 * @param string $content Tab panel content.
	 * @param string $title   Tab title.
	 * @return string Content without the leading generated heading.
	 */
	private function strip_leading_tab_title_heading( string $content, string $title ): string {
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
	 * Normalize heading text before comparing rendered titles.
	 *
	 * @param string $text Heading or title text.
	 * @return string Normalized text.
	 */
	private function normalize_tab_heading_text( string $text ): string {
		$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );
		$text = trim( (string) preg_replace( '/\s+/', ' ', str_replace( "\xc2\xa0", ' ', $text ) ) );
		$text = (string) preg_replace( '/\s+\(\d+\)$/', '', $text );

		return strtolower( $text );
	}

	/**
	 * Render accordion style using native <details> elements.
	 *
	 * @param array<int, array{title: string, id: string, content: string}> $tabs Tab data.
	 * @return string HTML.
	 */
	private function render_accordion( array $tabs ): string {
		$html = '<div class="woocommerce aa-product-info aa-product-info--accordion" data-wp-interactive="aggressive-apparel/product-tabs" data-wp-init="callbacks.initHashNav">';

		foreach ( $tabs as $index => $tab ) {
			$open  = 0 === $index ? ' open' : '';
			$html .= sprintf(
				'<details class="aa-product-info__section" id="pi-%s"%s>' .
				'<summary class="aa-product-info__heading" data-wp-on--click="actions.toggleAccordion">' .
				'<span>%s</span>' .
				'<svg class="aa-product-info__chevron" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>' .
				'</summary>' .
				'<div class="aa-product-info__content">%s</div>' .
				'</details>',
				esc_attr( $tab['id'] ),
				$open,
				esc_html( $tab['title'] ),
				$this->kses_tab_content( $tab['content'] ),
			);
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * Render inline sections — all content visible, no interaction.
	 *
	 * @param array<int, array{title: string, id: string, content: string}> $tabs Tab data.
	 * @return string HTML.
	 */
	private function render_inline( array $tabs ): string {
		$html = '<div class="woocommerce aa-product-info aa-product-info--inline">';

		foreach ( $tabs as $tab ) {
			$html .= sprintf(
				'<section class="aa-product-info__section" id="pi-%s">' .
				'<h3 class="aa-product-info__heading">%s</h3>' .
				'<div class="aa-product-info__content">%s</div>' .
				'</section>',
				esc_attr( $tab['id'] ),
				esc_html( $tab['title'] ),
				$this->kses_tab_content( $tab['content'] ),
			);
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * Render modern tabs with ARIA tablist pattern.
	 *
	 * @param array<int, array{title: string, id: string, content: string}> $tabs Tab data.
	 * @return string HTML.
	 */
	private function render_modern_tabs( array $tabs ): string {
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
				'<div class="aa-product-info__content">%s</div>' .
				'</div>',
				esc_attr( $tab['id'] ),
				esc_attr( $tab['id'] ),
				esc_attr( $panel_context ),
				$hidden,
				$this->kses_tab_content( $tab['content'] ),
			);
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * Render scrollspy style with sticky sidebar nav.
	 *
	 * @param array<int, array{title: string, id: string, content: string}> $tabs Tab data.
	 * @return string HTML.
	 */
	private function render_scrollspy( array $tabs ): string {
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
			$html .= sprintf(
				'<section id="pi-%s" class="aa-product-info__section">' .
				'<h3 class="aa-product-info__heading">%s</h3>' .
				'<div class="aa-product-info__content">%s</div>' .
				'</section>',
				esc_attr( $tab['id'] ),
				esc_html( $tab['title'] ),
				$this->kses_tab_content( $tab['content'] ),
			);
		}

		$html .= '</div>';
		$html .= '</div>';
		return $html;
	}

	/**
	 * Sanitize tab content with an extended allowlist.
	 *
	 * The default wp_kses_post() strips form elements (input, select, button,
	 * textarea, form) and the style attribute on spans. The Reviews tab needs
	 * all of these. Content originates from WooCommerce's own render_block
	 * pipeline, so we extend the allowlist rather than bypassing kses entirely.
	 *
	 * @param string $content Raw tab panel HTML.
	 * @return string Sanitized HTML.
	 */
	private function kses_tab_content( string $content ): string {
		$allowed = wp_kses_allowed_html( 'post' );

		// Form elements needed by the review form.
		$allowed['form']     = array(
			'action'     => true,
			'method'     => true,
			'class'      => true,
			'id'         => true,
			'novalidate' => true,
			'enctype'    => true,
		);
		$allowed['input']    = array(
			'type'          => true,
			'name'          => true,
			'value'         => true,
			'id'            => true,
			'class'         => true,
			'placeholder'   => true,
			'required'      => true,
			'checked'       => true,
			'aria-required' => true,
			'size'          => true,
		);
		$allowed['select']   = array(
			'name'          => true,
			'id'            => true,
			'class'         => true,
			'required'      => true,
			'aria-required' => true,
		);
		$allowed['option']   = array(
			'value'    => true,
			'selected' => true,
		);
		$allowed['textarea'] = array(
			'name'          => true,
			'id'            => true,
			'class'         => true,
			'rows'          => true,
			'cols'          => true,
			'required'      => true,
			'aria-required' => true,
			'placeholder'   => true,
		);
		$allowed['button']   = array(
			'type'     => true,
			'name'     => true,
			'value'    => true,
			'id'       => true,
			'class'    => true,
			'disabled' => true,
		);
		$allowed['label']    = array(
			'for'   => true,
			'class' => true,
		);

		// Ensure style attribute is allowed on span (star rating width).
		if ( isset( $allowed['span'] ) ) {
			$allowed['span']['style'] = true;
		} else {
			$allowed['span'] = array(
				'style' => true,
				'class' => true,
			);
		}

		// Allow style on div too (some WC markup uses it).
		if ( isset( $allowed['div'] ) ) {
			$allowed['div']['style'] = true;
		}

		return wp_kses( $content, $allowed );
	}

	/**
	 * Register global tab-content settings and display style under Appearance > Store Enhancements.
	 *
	 * @return void
	 */
	public function register_global_settings(): void {
		register_setting(
			'aggressive_apparel_features_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_global_tabs' ),
				'default'           => array(),
			),
		);

		add_settings_field(
			'product_tabs_display_style',
			__( 'Product Info Display Style', 'aggressive-apparel' ),
			array( $this, 'render_style_setting' ),
			'aggressive-apparel-features',
			'aggressive_apparel_features_product',
			array( 'label_for' => 'aa_product_tabs_style' ),
		);

		$fields = array(
			'care_instructions' => array(
				'label'       => __( 'Default Care Instructions', 'aggressive-apparel' ),
				'description' => __( 'Shown when a product does not have its own Care Instructions value.', 'aggressive-apparel' ),
				'rows'        => 5,
			),
			'shipping_returns'  => array(
				'label'       => __( 'Shipping & Returns Tab', 'aggressive-apparel' ),
				'description' => __( 'Leave empty to hide this tab from Product Details.', 'aggressive-apparel' ),
				'rows'        => 6,
			),
			'sustainability'    => array(
				'label'       => __( 'Sustainability Tab', 'aggressive-apparel' ),
				'description' => __( 'Leave empty to hide this tab from Product Details.', 'aggressive-apparel' ),
				'rows'        => 6,
			),
		);

		foreach ( $fields as $key => $field ) {
			add_settings_field(
				'product_tabs_' . $key,
				$field['label'],
				array( $this, 'render_textarea_setting' ),
				'aggressive-apparel-features',
				'aggressive_apparel_features_product',
				array(
					'key'         => $key,
					'label_for'   => 'aa_product_tabs_' . $key,
					'description' => $field['description'],
					'rows'        => $field['rows'],
				),
			);
		}

		add_settings_field(
			'product_tabs_custom_tabs',
			__( 'Global Custom Tabs', 'aggressive-apparel' ),
			array( $this, 'render_global_custom_tabs_setting' ),
			'aggressive-apparel-features',
			'aggressive_apparel_features_product'
		);
	}

	/**
	 * Render the display style dropdown on the settings page.
	 *
	 * @return void
	 */
	public function render_style_setting(): void {
		$style = $this->get_display_style();

		$styles = array(
			'accordion'   => __( 'Accordion — collapsible sections (recommended)', 'aggressive-apparel' ),
			'inline'      => __( 'Inline — all sections visible, no interaction', 'aggressive-apparel' ),
			'modern-tabs' => __( 'Modern Tabs — pill-style tab navigation', 'aggressive-apparel' ),
			'scrollspy'   => __( 'Scrollspy — sticky sidebar with scroll tracking', 'aggressive-apparel' ),
		);

		echo '<select id="aa_product_tabs_style" name="' . esc_attr( self::OPTION_KEY ) . '[display_style]">';
		foreach ( $styles as $value => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $value ),
				selected( $style, $value, false ),
				esc_html( $label ),
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Choose how product information is displayed on single product pages. Requires the Product Tabs Manager toggle to be enabled.', 'aggressive-apparel' ) . '</p>';
	}

	/**
	 * Render a global product-info textarea setting.
	 *
	 * @param array{key: string, label_for: string, description: string, rows: int} $args Field args.
	 * @return void
	 */
	public function render_textarea_setting( array $args ): void {
		$options = get_option( self::OPTION_KEY, array() );
		$key     = $args['key'];
		$value   = is_array( $options ) ? (string) ( $options[ $key ] ?? '' ) : '';
		$rows    = max( 3, (int) $args['rows'] );

		printf(
			'<textarea id="%1$s" name="%2$s[%3$s]" rows="%4$s" class="large-text">%5$s</textarea>',
			esc_attr( $args['label_for'] ),
			esc_attr( self::OPTION_KEY ),
			esc_attr( $key ),
			esc_attr( (string) $rows ),
			esc_textarea( $value ),
		);

		echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
	}

	/**
	 * Render repeatable global custom tabs in Store Enhancements.
	 *
	 * @return void
	 */
	public function render_global_custom_tabs_setting(): void {
		$options = get_option( self::OPTION_KEY, array() );
		$tabs    = is_array( $options ) && isset( $options['custom_tabs'] ) && is_array( $options['custom_tabs'] )
			? $this->sanitize_custom_tabs( $options['custom_tabs'] )
			: array();

		$this->render_custom_tabs_repeater(
			$tabs,
			self::OPTION_KEY . '[custom_tabs]',
			'aa_global_custom_tabs',
			__( 'Add store-wide tabs that appear in Product Details for every product. Product-specific custom tabs can be added on each product edit screen.', 'aggressive-apparel' )
		);
	}

	/**
	 * Sanitize global tab content and display settings.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, mixed>
	 */
	public function sanitize_global_tabs( $input ): array {
		$output = array();
		if ( ! is_array( $input ) ) {
			return $output;
		}

		foreach ( $input as $key => $value ) {
			if ( 'display_style' === $key ) {
				$output['display_style'] = in_array( $value, self::VALID_STYLES, true ) ? $value : 'accordion';
			} elseif ( 'custom_tabs' === $key ) {
				$output['custom_tabs'] = $this->sanitize_custom_tabs( $value );
			} else {
				$output[ sanitize_key( $key ) ] = wp_kses_post( (string) $value );
			}
		}

		return $output;
	}

	/**
	 * Sanitize repeatable custom tab rows.
	 *
	 * @param mixed $tabs Raw tab rows.
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_custom_tabs( $tabs ): array {
		if ( ! is_array( $tabs ) ) {
			return array();
		}

		$output = array();

		foreach ( $tabs as $index => $tab ) {
			if ( ! is_array( $tab ) ) {
				continue;
			}

			$title      = sanitize_text_field( (string) ( $tab['title'] ?? '' ) );
			$content    = wp_kses_post( (string) ( $tab['content'] ?? '' ) );
			$source     = sanitize_key( (string) ( $tab['source'] ?? 'manual' ) );
			$layout     = sanitize_key( (string) ( $tab['layout'] ?? 'rich_text' ) );
			$meta_field = sanitize_key( (string) ( $tab['metaField'] ?? $tab['meta_key'] ?? '' ) );
			$attribute  = sanitize_key( (string) ( $tab['attribute'] ?? '' ) );
			$items      = $this->sanitize_tab_items( $tab['items'] ?? array() );

			if ( ! in_array( $source, self::VALID_TAB_SOURCES, true ) ) {
				$source = 'manual';
			}

			if ( ! in_array( $layout, self::VALID_TAB_LAYOUTS, true ) ) {
				$layout = 'rich_text';
			}

			$key = sanitize_key( (string) ( $tab['key'] ?? '' ) );
			if ( '' === $key ) {
				$key = sanitize_key( $title );
			}
			if ( '' === $key ) {
				$key = 'custom_tab_' . absint( $index );
			}

			if (
					'' === $title &&
					'' === trim( wp_strip_all_tags( $content ) ) &&
					'' === $meta_field &&
					'' === $attribute &&
					empty( $items )
				) {
				continue;
			}

			$output[] = array(
				'key'       => $key,
				'enabled'   => isset( $tab['enabled'] ) && '0' !== (string) $tab['enabled'],
				'title'     => $title,
				'priority'  => max( 1, min( 999, absint( $tab['priority'] ?? 40 ) ) ),
				'source'    => $source,
				'layout'    => $layout,
				'metaField' => $meta_field,
				'attribute' => $attribute,
				'items'     => $items,
				'content'   => $content,
			);
		}

		return $output;
	}

	/**
	 * Sanitize no-code layout item rows.
	 *
	 * @param mixed $items Raw item rows.
	 * @return array<int, array{icon: string, title: string, text: string, meta: string}>
	 */
	private function sanitize_tab_items( $items ): array {
		if ( ! is_array( $items ) ) {
			return array();
		}

		$output = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$icon  = sanitize_text_field( (string) ( $item['icon'] ?? '' ) );
			$title = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
			$text  = wp_kses_post( (string) ( $item['text'] ?? '' ) );
			$meta  = sanitize_text_field( (string) ( $item['meta'] ?? '' ) );

			if ( '' === $icon && '' === $title && '' === trim( wp_strip_all_tags( $text ) ) && '' === $meta ) {
				continue;
			}

			$output[] = array(
				'icon'  => $icon,
				'title' => $title,
				'text'  => $text,
				'meta'  => $meta,
			);
		}

		return $output;
	}

	/**
	 * Add custom tabs to the product page.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array Modified tabs.
	 */
	public function add_custom_tabs( array $tabs ): array {
		$global  = get_option( self::OPTION_KEY, array() );
		$post_id = $this->get_current_product_id();

		// Care Instructions (per-product meta or global fallback).
		$care = '';
		if ( $post_id ) {
			$care = (string) get_post_meta( $post_id, '_aggressive_apparel_care_instructions', true );
		}
		if ( '' === $care && ! empty( $global['care_instructions'] ) ) {
			$care = $global['care_instructions'];
		}
		if ( '' !== $care ) {
			$tabs['care_instructions'] = array(
				'title'    => __( 'Care Instructions', 'aggressive-apparel' ),
				'priority' => 25,
				'callback' => function () use ( $care ) {
					$this->render_product_info_content( $care );
				},
			);
		}

		// Shipping & Returns (global only).
		if ( ! empty( $global['shipping_returns'] ) ) {
			$tabs['shipping_returns'] = array(
				'title'    => __( 'Shipping & Returns', 'aggressive-apparel' ),
				'priority' => 30,
				'callback' => function () use ( $global ) {
					$this->render_product_info_content( (string) $global['shipping_returns'] );
				},
			);
		}

		// Sustainability (global only).
		if ( ! empty( $global['sustainability'] ) ) {
			$tabs['sustainability'] = array(
				'title'    => __( 'Sustainability', 'aggressive-apparel' ),
				'priority' => 35,
				'callback' => function () use ( $global ) {
					$this->render_product_info_content( (string) $global['sustainability'] );
				},
			);
		}

		if ( is_array( $global ) && ! empty( $global['custom_tabs'] ) && is_array( $global['custom_tabs'] ) ) {
			$overrides = $post_id ? $this->get_product_tab_overrides( $post_id ) : array();
			$tabs      = $this->append_custom_tabs(
				$tabs,
				$this->sanitize_custom_tabs( $global['custom_tabs'] ),
				'global',
				$post_id ? (int) $post_id : 0,
				$overrides
			);
		}

		if ( $post_id ) {
			$product_tabs = get_post_meta( $post_id, self::PRODUCT_CUSTOM_TABS_META_KEY, true );
			$tabs         = $this->append_custom_tabs(
				$tabs,
				$this->sanitize_custom_tabs( $product_tabs ),
				'product',
				(int) $post_id
			);
		}

		return $tabs;
	}

	/**
	 * Append custom tab rows to WooCommerce's tab array.
	 *
	 * @param array  $tabs        Existing tabs.
	 * @param array  $custom_tabs Custom tabs.
	 * @param string $scope       Tab scope, used for unique keys.
	 * @param int    $product_id  Product ID used by source-driven tabs.
	 * @param array  $overrides   Per-product global tab overrides keyed by tab key.
	 * @return array<string, array<string, mixed>> Modified tabs.
	 */
	private function append_custom_tabs(
		array $tabs,
		array $custom_tabs,
		string $scope,
		int $product_id = 0,
		array $overrides = array()
	): array {
		foreach ( $custom_tabs as $index => $tab ) {
			if ( empty( $tab['enabled'] ) || empty( $tab['title'] ) ) {
				continue;
			}

			$tab_key  = (string) ( $tab['key'] ?? $index );
			$override = ( 'global' === $scope && isset( $overrides[ $tab_key ] ) && is_array( $overrides[ $tab_key ] ) )
				? $overrides[ $tab_key ]
				: array();

			if ( 'hide' === ( $override['mode'] ?? '' ) ) {
				continue;
			}

			$content = $this->resolve_custom_tab_content( $tab, $product_id );
			$extra   = (string) ( $override['content'] ?? '' );

			if ( 'override' === ( $override['mode'] ?? '' ) ) {
				$content = $extra;
			} elseif ( 'append' === ( $override['mode'] ?? '' ) && '' !== trim( wp_strip_all_tags( $extra ) ) ) {
				$content .= "\n\n" . $extra;
			}

			if ( ! $this->has_custom_tab_content( $tab, $content ) ) {
				continue;
			}

			$key          = 'aa_' . sanitize_key( $scope . '_' . $index . '_' . $tab_key );
			$title        = (string) $tab['title'];
			$tabs[ $key ] = array(
				'title'    => $title,
				'priority' => $tab['priority'],
				'callback' => function () use ( $tab, $content ) {
					$this->render_custom_tab_content( $tab, $content );
				},
			);
		}

		return $tabs;
	}

	/**
	 * Resolve a custom tab's content from its configured source.
	 *
	 * @param array $tab        Tab data.
	 * @param int   $product_id Product ID.
	 * @return string Resolved tab content.
	 */
	private function resolve_custom_tab_content( array $tab, int $product_id ): string {
		$fallback = (string) ( $tab['content'] ?? '' );
		$source   = (string) ( $tab['source'] ?? 'manual' );

		if ( 'product_meta' === $source && $product_id && ! empty( $tab['metaField'] ) ) {
			$value = get_post_meta( $product_id, (string) $tab['metaField'], true );
			$value = is_scalar( $value ) ? trim( (string) $value ) : '';

			return '' !== $value ? $value : $fallback;
		}

		if ( 'product_attribute' === $source && $product_id && ! empty( $tab['attribute'] ) && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			$value   = $product ? trim( wp_strip_all_tags( $product->get_attribute( (string) $tab['attribute'] ) ) ) : '';

			return '' !== $value ? $value : $fallback;
		}

		return $fallback;
	}

	/**
	 * Determine whether a custom tab has content to render.
	 *
	 * @param array  $tab     Tab data.
	 * @param string $content Resolved content.
	 * @return bool True when the tab should render.
	 */
	private function has_custom_tab_content( array $tab, string $content ): bool {
		if ( '' !== trim( wp_strip_all_tags( $content ) ) ) {
			return true;
		}

		return ! empty( $tab['items'] ) && is_array( $tab['items'] );
	}

	/**
	 * Render a custom tab using its selected no-code layout.
	 *
	 * @param array  $tab     Tab data.
	 * @param string $content Resolved content.
	 * @return void
	 */
	private function render_custom_tab_content( array $tab, string $content ): void {
		$layout = (string) ( $tab['layout'] ?? 'rich_text' );
		$items  = isset( $tab['items'] ) && is_array( $tab['items'] ) ? $tab['items'] : array();

		if ( ! in_array( $layout, self::VALID_TAB_LAYOUTS, true ) ) {
			$layout = 'rich_text';
		}

		if ( 'custom_html' === $layout || 'rich_text' === $layout || empty( $items ) ) {
			$this->render_product_info_content( $content );
			return;
		}

		$this->render_product_info_content( $content );
		$this->render_tab_layout_items( $layout, $items );
	}

	/**
	 * Render structured no-code layout items.
	 *
	 * @param string $layout Layout key.
	 * @param array  $items  Sanitized item rows.
	 * @return void
	 */
	private function render_tab_layout_items( string $layout, array $items ): void {
		if ( empty( $items ) ) {
			return;
		}

		if ( 'specs_table' === $layout ) {
			$this->render_specs_table_items( $items );
			return;
		}

		if ( 'faq' === $layout ) {
			$this->render_faq_items( $items );
			return;
		}

		$class = 'aa-product-tab-layout aa-product-tab-layout--' . sanitize_html_class( str_replace( '_', '-', $layout ) );
		$list  = 'shipping_timeline' === $layout ? 'ol' : 'ul';

		echo '<' . esc_html( $list ) . ' class="' . esc_attr( $class ) . '">';

		foreach ( $items as $item ) {
			$this->render_card_like_item( $layout, $item );
		}

		echo '</' . esc_html( $list ) . '>';
	}

	/**
	 * Render feature-card, care-grid, icon-list, and timeline items.
	 *
	 * @param string $layout Layout key.
	 * @param array  $item   Sanitized item row.
	 * @return void
	 */
	private function render_card_like_item( string $layout, array $item ): void {
		$item_class = 'aa-product-tab-layout__item';

		if ( 'shipping_timeline' === $layout ) {
			$item_class .= ' aa-product-tab-layout__item--timeline';
		}

		echo '<li class="' . esc_attr( $item_class ) . '">';

		if ( ! empty( $item['icon'] ) ) {
			echo '<span class="aa-product-tab-layout__icon" aria-hidden="true">' . esc_html( (string) $item['icon'] ) . '</span>';
		}

		echo '<div class="aa-product-tab-layout__body">';

		if ( ! empty( $item['meta'] ) ) {
			echo '<p class="aa-product-tab-layout__meta">' . esc_html( (string) $item['meta'] ) . '</p>';
		}

		if ( ! empty( $item['title'] ) ) {
			echo '<h4 class="aa-product-tab-layout__title">' . esc_html( (string) $item['title'] ) . '</h4>';
		}

		if ( ! empty( $item['text'] ) ) {
			echo '<div class="aa-product-tab-layout__text">';
			$this->render_product_info_content( (string) $item['text'] );
			echo '</div>';
		}

		echo '</div>';
		echo '</li>';
	}

	/**
	 * Render specs table rows.
	 *
	 * @param array $items Sanitized item rows.
	 * @return void
	 */
	private function render_specs_table_items( array $items ): void {
		echo '<table class="aa-product-tab-layout aa-product-tab-layout--specs-table"><tbody>';

		foreach ( $items as $item ) {
			if ( empty( $item['title'] ) && empty( $item['text'] ) ) {
				continue;
			}

			echo '<tr>';
			echo '<th scope="row">' . esc_html( (string) ( $item['title'] ?? '' ) ) . '</th>';
			echo '<td>' . wp_kses_post( (string) ( $item['text'] ?? '' ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Render FAQ rows.
	 *
	 * @param array $items Sanitized item rows.
	 * @return void
	 */
	private function render_faq_items( array $items ): void {
		echo '<div class="aa-product-tab-layout aa-product-tab-layout--faq">';

		foreach ( $items as $item ) {
			if ( empty( $item['title'] ) && empty( $item['text'] ) ) {
				continue;
			}

			echo '<details class="aa-product-tab-layout__faq-item">';
			echo '<summary class="aa-product-tab-layout__faq-question">' . esc_html( (string) ( $item['title'] ?? '' ) ) . '</summary>';
			echo '<div class="aa-product-tab-layout__faq-answer">';
			$this->render_product_info_content( (string) ( $item['text'] ?? '' ) );
			echo '</div>';
			echo '</details>';
		}

		echo '</div>';
	}

	/**
	 * Render tab content while preserving intentional custom markup.
	 *
	 * Plain text receives automatic paragraphs. Content that already includes
	 * block-level HTML is treated as authored markup and rendered as-is after
	 * WordPress post-content sanitization.
	 *
	 * @param string $content Tab content.
	 * @return void
	 */
	private function render_product_info_content( string $content ): void {
		$content = trim( $content );

		if ( '' === $content ) {
			return;
		}

		if ( $this->has_block_markup( $content ) ) {
			echo wp_kses_post( $content );
			return;
		}

		echo wp_kses_post( wpautop( $content ) );
	}

	/**
	 * Determine whether authored content already contains block-level markup.
	 *
	 * @param string $content Tab content.
	 * @return bool True when the content should not receive automatic paragraphs.
	 */
	private function has_block_markup( string $content ): bool {
		return 1 === preg_match(
			'/<\/?(address|article|aside|blockquote|details|div|dl|dt|dd|figure|figcaption|footer|form|h[1-6]|header|hr|li|main|nav|ol|p|pre|section|summary|table|tbody|td|tfoot|th|thead|tr|ul)\b/i',
			$content
		);
	}

	/**
	 * Resolve the current product ID across WooCommerce and block contexts.
	 *
	 * @param array $block Optional block data from render_block.
	 * @return int Product ID, or 0 when no product is available.
	 */
	private function get_current_product_id( array $block = array() ): int {
		if ( $this->render_product_id > 0 ) {
			return $this->render_product_id;
		}

		if ( isset( $block['context']['postId'] ) ) {
			$block_product_id = absint( $block['context']['postId'] );

			if ( $block_product_id > 0 && 'product' === get_post_type( $block_product_id ) ) {
				return $block_product_id;
			}
		}

		global $product;

		if ( $product instanceof \WC_Product ) {
			return (int) $product->get_id();
		}

		$queried_product_id = get_queried_object_id();
		if ( $queried_product_id > 0 && 'product' === get_post_type( $queried_product_id ) ) {
			return (int) $queried_product_id;
		}

		global $post;

		if ( $post instanceof \WP_Post && 'product' === $post->post_type ) {
			return (int) $post->ID;
		}

		$loop_product_id = get_the_ID();
		if ( $loop_product_id > 0 && 'product' === get_post_type( $loop_product_id ) ) {
			return (int) $loop_product_id;
		}

		return 0;
	}

	/**
	 * Get sanitized per-product overrides for global tabs.
	 *
	 * @param int $post_id Product post ID.
	 * @return array<string, array{mode: string, content: string}>
	 */
	private function get_product_tab_overrides( int $post_id ): array {
		$overrides = get_post_meta( $post_id, self::PRODUCT_TAB_OVERRIDES_META_KEY, true );

		return $this->sanitize_tab_overrides( $overrides );
	}

	/**
	 * Sanitize global tab override rows.
	 *
	 * @param mixed $overrides Raw overrides.
	 * @return array<string, array{mode: string, content: string}>
	 */
	private function sanitize_tab_overrides( $overrides ): array {
		if ( ! is_array( $overrides ) ) {
			return array();
		}

		$output = array();

		foreach ( $overrides as $key => $override ) {
			if ( ! is_array( $override ) ) {
				continue;
			}

			$mode = sanitize_key( (string) ( $override['mode'] ?? 'inherit' ) );
			if ( ! in_array( $mode, self::VALID_OVERRIDE_MODES, true ) ) {
				$mode = 'inherit';
			}

			$content = wp_kses_post( (string) ( $override['content'] ?? '' ) );

			if ( 'inherit' === $mode && '' === trim( wp_strip_all_tags( $content ) ) ) {
				continue;
			}

			$output[ sanitize_key( (string) $key ) ] = array(
				'mode'    => $mode,
				'content' => $content,
			);
		}

		return $output;
	}

	/**
	 * Add meta box for per-product tab content.
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		add_meta_box(
			'aggressive_apparel_product_tabs',
			__( 'Extra Product Tabs', 'aggressive-apparel' ),
			array( $this, 'render_meta_box' ),
			'product',
			'normal',
			'default',
		);
	}

	/**
	 * Render the meta box.
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'aggressive_apparel_tabs_nonce', '_aa_tabs_nonce' );

		$care = (string) get_post_meta( $post->ID, '_aggressive_apparel_care_instructions', true );
		$tabs = get_post_meta( $post->ID, self::PRODUCT_CUSTOM_TABS_META_KEY, true );
		$tabs = $this->sanitize_custom_tabs( $tabs );

		echo '<p><label for="aa_care_instructions"><strong>' . esc_html__( 'Care Instructions', 'aggressive-apparel' ) . '</strong></label></p>';
		echo '<textarea id="aa_care_instructions" name="aa_care_instructions" rows="4" class="widefat">' . esc_textarea( $care ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Leave empty to use the global default from Appearance → Store Enhancements.', 'aggressive-apparel' ) . '</p>';

		$this->render_global_tab_overrides( $post->ID );

		echo '<hr>';
		echo '<p><strong>' . esc_html__( 'Product-Specific Custom Tabs', 'aggressive-apparel' ) . '</strong></p>';
		$this->render_custom_tabs_repeater(
			$tabs,
			'aa_custom_tabs',
			'aa_product_custom_tabs_' . (string) $post->ID,
			__( 'These tabs appear only on this product and are added to the Product Details block.', 'aggressive-apparel' )
		);
	}

	/**
	 * Save meta box data.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_meta( int $post_id ): void {
		if ( ! isset( $_POST['_aa_tabs_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_aa_tabs_nonce'] ) ), 'aggressive_apparel_tabs_nonce' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$care = isset( $_POST['aa_care_instructions'] )
			? wp_kses_post( wp_unslash( $_POST['aa_care_instructions'] ) )
			: '';

		update_post_meta( $post_id, '_aggressive_apparel_care_instructions', $care );

		$raw_overrides = array();
		if ( isset( $_POST['aa_tab_overrides'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by sanitize_tab_overrides().
			$raw_overrides = wp_unslash( $_POST['aa_tab_overrides'] );
		}

		$overrides = $this->sanitize_tab_overrides( $raw_overrides );

		if ( empty( $overrides ) ) {
			delete_post_meta( $post_id, self::PRODUCT_TAB_OVERRIDES_META_KEY );
		} else {
			update_post_meta( $post_id, self::PRODUCT_TAB_OVERRIDES_META_KEY, $overrides );
		}

		$raw_custom_tabs = array();
		if ( isset( $_POST['aa_custom_tabs'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by sanitize_custom_tabs().
			$raw_custom_tabs = wp_unslash( $_POST['aa_custom_tabs'] );
		}

		$custom_tabs = $this->sanitize_custom_tabs( $raw_custom_tabs );

		if ( empty( $custom_tabs ) ) {
			delete_post_meta( $post_id, self::PRODUCT_CUSTOM_TABS_META_KEY );
		} else {
			update_post_meta( $post_id, self::PRODUCT_CUSTOM_TABS_META_KEY, $custom_tabs );
		}
	}

	/**
	 * Render per-product override controls for global tabs.
	 *
	 * @param int $post_id Product post ID.
	 * @return void
	 */
	private function render_global_tab_overrides( int $post_id ): void {
		$options     = get_option( self::OPTION_KEY, array() );
		$global_tabs = is_array( $options ) && isset( $options['custom_tabs'] ) && is_array( $options['custom_tabs'] )
			? $this->sanitize_custom_tabs( $options['custom_tabs'] )
			: array();

		echo '<hr>';
		echo '<p><strong>' . esc_html__( 'Global Tab Overrides', 'aggressive-apparel' ) . '</strong></p>';

		if ( empty( $global_tabs ) ) {
			echo '<p class="description">' . esc_html__( 'No global custom tabs are configured yet. Add them in Appearance → Store Enhancements → Product Page.', 'aggressive-apparel' ) . '</p>';
			return;
		}

		$overrides = $this->get_product_tab_overrides( $post_id );
		$modes     = array(
			'inherit'  => __( 'Use global behavior', 'aggressive-apparel' ),
			'override' => __( 'Override content', 'aggressive-apparel' ),
			'append'   => __( 'Append product content', 'aggressive-apparel' ),
			'hide'     => __( 'Hide on this product', 'aggressive-apparel' ),
		);

		foreach ( $global_tabs as $tab ) {
			$key      = (string) $tab['key'];
			$override = $overrides[ $key ] ?? array();
			$mode     = (string) ( $override['mode'] ?? 'inherit' );
			$content  = (string) ( $override['content'] ?? '' );

			echo '<div class="aa-tab-override">';
			echo '<p class="aa-tab-override__title"><strong>' . esc_html( (string) $tab['title'] ) . '</strong></p>';
			echo '<p><label>' . esc_html__( 'Mode', 'aggressive-apparel' ) . '<br><select name="' . esc_attr( 'aa_tab_overrides[' . $key . '][mode]' ) . '">';
			foreach ( $modes as $value => $label ) {
				printf(
					'<option value="%s"%s>%s</option>',
					esc_attr( $value ),
					selected( $mode, $value, false ),
					esc_html( $label )
				);
			}
			echo '</select></label></p>';
			echo '<p><label>' . esc_html__( 'Override / Append Content', 'aggressive-apparel' ) . '<br><textarea name="' . esc_attr( 'aa_tab_overrides[' . $key . '][content]' ) . '" rows="4" class="large-text">' . esc_textarea( $content ) . '</textarea></label></p>';
			echo '</div>';
		}
	}

	/**
	 * Render a repeatable custom tabs control.
	 *
	 * @param array  $tabs        Existing tabs.
	 * @param string $name_prefix Input name prefix.
	 * @param string $id          Unique control ID.
	 * @param string $description Helper text.
	 * @return void
	 */
	private function render_custom_tabs_repeater( array $tabs, string $name_prefix, string $id, string $description ): void {
		$rows = ! empty( $tabs )
			? array_values( $tabs )
			: array(
				array(
					'enabled'  => true,
					'key'      => '',
					'title'    => '',
					'priority' => 40,
					'source'   => 'manual',
					'layout'   => 'rich_text',
					'items'    => array(),
					'content'  => '',
				),
			);

		echo '<div class="aa-custom-tabs-repeater" id="' . esc_attr( $id ) . '" data-name-prefix="' . esc_attr( $name_prefix ) . '" data-next-index="' . esc_attr( (string) count( $rows ) ) . '">';
		echo '<div class="aa-custom-tabs-repeater__rows">';

		foreach ( $rows as $index => $tab ) {
			$this->render_custom_tab_row( $name_prefix, $index, $tab );
		}

		echo '</div>';
		echo '<button type="button" class="button aa-custom-tabs-repeater__add">' . esc_html__( 'Add Tab', 'aggressive-apparel' ) . '</button>';
		echo '<p class="description">' . esc_html( $description ) . '</p>';
		echo '</div>';
		$this->render_custom_tabs_repeater_script( $id );
	}

	/**
	 * Render one custom tab row.
	 *
	 * @param string $name_prefix Input name prefix.
	 * @param int    $index       Row index.
	 * @param array  $tab         Tab data.
	 * @return void
	 */
	private function render_custom_tab_row( string $name_prefix, int $index, array $tab ): void {
		$enabled_name  = sprintf( '%s[%d][enabled]', $name_prefix, $index );
		$key_name      = sprintf( '%s[%d][key]', $name_prefix, $index );
		$title_name    = sprintf( '%s[%d][title]', $name_prefix, $index );
		$priority_name = sprintf( '%s[%d][priority]', $name_prefix, $index );
		$source_name   = sprintf( '%s[%d][source]', $name_prefix, $index );
		$layout_name   = sprintf( '%s[%d][layout]', $name_prefix, $index );
		$meta_key_name = sprintf( '%s[%d][metaField]', $name_prefix, $index );
		$attr_name     = sprintf( '%s[%d][attribute]', $name_prefix, $index );
		$content_name  = sprintf( '%s[%d][content]', $name_prefix, $index );
		$source        = (string) ( $tab['source'] ?? 'manual' );
		$layout        = (string) ( $tab['layout'] ?? 'rich_text' );
		$sources       = $this->get_tab_source_labels();
		$layouts       = $this->get_tab_layout_labels();

			echo '<div class="aa-custom-tabs-repeater__row" data-tab-index="' . esc_attr( (string) $index ) . '" data-layout="' . esc_attr( $layout ) . '" data-source="' . esc_attr( $source ) . '">';
			echo '<input type="hidden" name="' . esc_attr( $key_name ) . '" value="' . esc_attr( (string) ( $tab['key'] ?? '' ) ) . '">';
			echo '<p class="aa-custom-tabs-repeater__toolbar">';
			echo '<label><input type="hidden" name="' . esc_attr( $enabled_name ) . '" value="0"><input type="checkbox" name="' . esc_attr( $enabled_name ) . '" value="1"' . checked( ! empty( $tab['enabled'] ), true, false ) . '> ' . esc_html__( 'Enabled', 'aggressive-apparel' ) . '</label>';
				echo '<label>' . esc_html__( 'Priority', 'aggressive-apparel' ) . ' <input type="number" min="1" max="999" step="1" name="' . esc_attr( $priority_name ) . '" value="' . esc_attr( (string) $tab['priority'] ) . '" class="aa-custom-tabs-repeater__priority-input"></label>';
			echo '<button type="button" class="button-link-delete aa-custom-tabs-repeater__remove">' . esc_html__( 'Remove', 'aggressive-apparel' ) . '</button>';
			echo '</p>';
			echo '<div class="aa-custom-tabs-repeater__grid">';
				echo '<p><label>' . esc_html__( 'Tab Title', 'aggressive-apparel' ) . '<br><input type="text" name="' . esc_attr( $title_name ) . '" value="' . esc_attr( $tab['title'] ) . '" class="regular-text" placeholder="' . esc_attr__( 'Size & Fit', 'aggressive-apparel' ) . '"></label></p>';
			echo '<p><label>' . esc_html__( 'Content Layout', 'aggressive-apparel' ) . '<br><select class="aa-custom-tabs-layout-select" name="' . esc_attr( $layout_name ) . '">';
		foreach ( $layouts as $value => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $value ),
				selected( $layout, $value, false ),
				esc_html( $label )
			);
		}
			echo '</select></label><span class="description aa-custom-tabs-layout-help" aria-live="polite"></span></p>';
			echo '<p><label>' . esc_html__( 'Content Source', 'aggressive-apparel' ) . '<br><select class="aa-custom-tabs-source-select" name="' . esc_attr( $source_name ) . '">';
		foreach ( $sources as $value => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $value ),
				selected( $source, $value, false ),
				esc_html( $label )
			);
		}
			echo '</select></label></p>';
			echo '</div>';
			echo '<div class="aa-custom-tabs-source-fields">';
				echo '<p data-source-field="meta"><label>' . esc_html__( 'Product Meta Field', 'aggressive-apparel' ) . '<br><input type="text" name="' . esc_attr( $meta_key_name ) . '" value="' . esc_attr( (string) ( $tab['metaField'] ?? $tab['meta_key'] ?? '' ) ) . '" class="regular-text" placeholder="_my_meta_key"></label><span class="description">' . esc_html__( 'Used only when Content Source is Product meta field.', 'aggressive-apparel' ) . '</span></p>';
				echo '<p data-source-field="attribute"><label>' . esc_html__( 'Product Attribute', 'aggressive-apparel' ) . '<br><input type="text" name="' . esc_attr( $attr_name ) . '" value="' . esc_attr( (string) ( $tab['attribute'] ?? '' ) ) . '" class="regular-text" placeholder="pa_material"></label><span class="description">' . esc_html__( 'Used only when Content Source is Product attribute.', 'aggressive-apparel' ) . '</span></p>';
			echo '</div>';
			echo '<p><label class="aa-custom-tabs-content-label">' . esc_html__( 'Intro / Fallback Content', 'aggressive-apparel' ) . '<br><textarea name="' . esc_attr( $content_name ) . '" rows="5" class="large-text">' . esc_textarea( (string) $tab['content'] ) . '</textarea></label><span class="description aa-custom-tabs-content-help">' . esc_html__( 'For structured layouts, this appears above the generated layout. For rich text, this is the full tab content.', 'aggressive-apparel' ) . '</span></p>';
				$this->render_custom_tab_items( $name_prefix, $index, isset( $tab['items'] ) && is_array( $tab['items'] ) ? $tab['items'] : array() );
				$this->render_custom_tab_preview();
				echo '</div>';
	}

	/**
	 * Get labels for custom tab content sources.
	 *
	 * @return array<string, string>
	 */
	private function get_tab_source_labels(): array {
		return array(
			'manual'            => __( 'Manual / fallback content', 'aggressive-apparel' ),
			'product_meta'      => __( 'Product meta field', 'aggressive-apparel' ),
			'product_attribute' => __( 'Product attribute', 'aggressive-apparel' ),
		);
	}

	/**
	 * Get labels for no-code custom tab layouts.
	 *
	 * @return array<string, string>
	 */
	private function get_tab_layout_labels(): array {
		return array(
			'rich_text'         => __( 'Rich Text', 'aggressive-apparel' ),
			'feature_cards'     => __( 'Feature Cards', 'aggressive-apparel' ),
			'care_grid'         => __( 'Care Grid', 'aggressive-apparel' ),
			'specs_table'       => __( 'Specs Table', 'aggressive-apparel' ),
			'faq'               => __( 'FAQ / Accordion', 'aggressive-apparel' ),
			'shipping_timeline' => __( 'Shipping Timeline', 'aggressive-apparel' ),
			'icon_list'         => __( 'Icon List', 'aggressive-apparel' ),
			'custom_html'       => __( 'Custom HTML', 'aggressive-apparel' ),
		);
	}

	/**
	 * Render no-code layout item fields for one custom tab.
	 *
	 * @param string $name_prefix Input name prefix.
	 * @param int    $tab_index   Parent tab index.
	 * @param array  $items       Item rows.
	 * @return void
	 */
	private function render_custom_tab_items( string $name_prefix, int $tab_index, array $items ): void {
		$rows = ! empty( $items )
			? array_values( $items )
			: array(
				array(
					'icon'  => '',
					'title' => '',
					'text'  => '',
					'meta'  => '',
				),
			);

		echo '<div class="aa-custom-tab-items" data-next-item-index="' . esc_attr( (string) count( $rows ) ) . '">';
		echo '<p class="aa-custom-tab-items__header"><strong>' . esc_html__( 'No-Code Layout Items', 'aggressive-apparel' ) . '</strong><br><span class="description">' . esc_html__( 'Add the rows this layout will render. The theme handles the frontend markup and styling.', 'aggressive-apparel' ) . '</span></p>';
		echo '<div class="aa-custom-tab-items__rows">';

		foreach ( $rows as $item_index => $item ) {
			$this->render_custom_tab_item_row( $name_prefix, $tab_index, $item_index, $item );
		}

		echo '</div>';
		echo '<button type="button" class="button aa-custom-tab-items__add">' . esc_html__( 'Add Layout Item', 'aggressive-apparel' ) . '</button>';
		echo '<p class="description">' . esc_html__( 'Use these rows for cards, specs tables, FAQs, timelines, care grids, and icon lists. Leave them empty for rich text or custom HTML tabs.', 'aggressive-apparel' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Render one no-code layout item row.
	 *
	 * @param string $name_prefix Input name prefix.
	 * @param int    $tab_index   Parent tab index.
	 * @param int    $item_index  Item index.
	 * @param array  $item        Item data.
	 * @return void
	 */
	private function render_custom_tab_item_row( string $name_prefix, int $tab_index, int $item_index, array $item ): void {
		$base = sprintf( '%s[%d][items][%d]', $name_prefix, $tab_index, $item_index );

		echo '<div class="aa-custom-tab-items__row">';
		echo '<p class="aa-custom-tab-items__row-fields">';
		echo '<label>' . esc_html__( 'Icon', 'aggressive-apparel' ) . '<br><input type="text" name="' . esc_attr( $base . '[icon]' ) . '" value="' . esc_attr( (string) ( $item['icon'] ?? '' ) ) . '" class="aa-custom-tab-items__icon-input" placeholder="✓"></label>';
		echo '<label>' . esc_html__( 'Label / Question / Step', 'aggressive-apparel' ) . '<br><input type="text" name="' . esc_attr( $base . '[title]' ) . '" value="' . esc_attr( (string) ( $item['title'] ?? '' ) ) . '" class="regular-text"></label>';
		echo '<label>' . esc_html__( 'Detail / Eyebrow', 'aggressive-apparel' ) . '<br><input type="text" name="' . esc_attr( $base . '[meta]' ) . '" value="' . esc_attr( (string) ( $item['meta'] ?? '' ) ) . '" class="regular-text"></label>';
		echo '<button type="button" class="button-link-delete aa-custom-tab-items__remove">' . esc_html__( 'Remove Item', 'aggressive-apparel' ) . '</button>';
		echo '</p>';
		echo '<p><label>' . esc_html__( 'Text / Answer / Value', 'aggressive-apparel' ) . '<br><textarea name="' . esc_attr( $base . '[text]' ) . '" rows="3" class="large-text">' . esc_textarea( (string) ( $item['text'] ?? '' ) ) . '</textarea></label></p>';
		echo '</div>';
	}

		/**
		 * Render the live frontend preview shell for one custom tab row.
		 *
		 * @return void
		 */
	private function render_custom_tab_preview(): void {
		echo '<div class="aa-custom-tabs-preview">';
		echo '<p class="aa-custom-tabs-preview__label"><strong>' . esc_html__( 'Frontend Preview', 'aggressive-apparel' ) . '</strong><br><span class="description">' . esc_html__( 'Updates as you edit. Custom HTML is shown as plain text in the preview for admin safety.', 'aggressive-apparel' ) . '</span></p>';
		echo '<div class="aa-custom-tabs-preview__surface">';
		echo '<h4 class="aa-custom-tabs-preview__title"></h4>';
		echo '<div class="aa-product-info__content aa-custom-tabs-preview__content"></div>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render the small repeatable-tabs script for one control.
	 *
	 * @param string $id Repeater ID.
	 * @return void
	 */
	private function render_custom_tabs_repeater_script( string $id ): void {
		?>
		<script>
		(function() {
			var root = document.getElementById(<?php echo wp_json_encode( $id ); ?>);
			if (!root) {
				return;
			}

			var rows = root.querySelector('.aa-custom-tabs-repeater__rows');
				var add = root.querySelector('.aa-custom-tabs-repeater__add');
				var prefix = root.dataset.namePrefix || 'custom_tabs';
				var nextIndex = parseInt(root.dataset.nextIndex || '0', 10);
					var layoutHelp = {
						rich_text: '<?php echo esc_js( __( 'Best for normal formatted content. Layout item rows are hidden.', 'aggressive-apparel' ) ); ?>',
						feature_cards: '<?php echo esc_js( __( 'Creates responsive cards from the no-code item rows.', 'aggressive-apparel' ) ); ?>',
					care_grid: '<?php echo esc_js( __( 'Creates compact care or instruction cards from item rows.', 'aggressive-apparel' ) ); ?>',
					specs_table: '<?php echo esc_js( __( 'Uses Label as the spec name and Text as the value.', 'aggressive-apparel' ) ); ?>',
					faq: '<?php echo esc_js( __( 'Uses Label as the question and Text as the answer.', 'aggressive-apparel' ) ); ?>',
					shipping_timeline: '<?php echo esc_js( __( 'Uses Detail as timing, Label as the step, and Text as the explanation.', 'aggressive-apparel' ) ); ?>',
					icon_list: '<?php echo esc_js( __( 'Creates a simple icon-supported list from item rows.', 'aggressive-apparel' ) ); ?>',
						custom_html: '<?php echo esc_js( __( 'For advanced markup. Layout item rows are hidden.', 'aggressive-apparel' ) ); ?>'
					};

					function escapeHtml(value) {
						return String(value || '')
							.replace(/&/g, '&amp;')
							.replace(/</g, '&lt;')
							.replace(/>/g, '&gt;')
							.replace(/"/g, '&quot;')
							.replace(/'/g, '&#039;');
					}

					function getFieldValue(scope, field) {
						var input = scope.querySelector('[name$="[' + field + ']"]');

						return input ? input.value : '';
					}

					function renderParagraphs(value) {
						var text = String(value || '').trim();

						if (!text) {
							return '';
						}

						return text.split(/\n{2,}/).map(function(part) {
							return '<p>' + escapeHtml(part).replace(/\n/g, '<br>') + '</p>';
						}).join('');
					}

					function getItems(row) {
						return Array.prototype.slice.call(row.querySelectorAll('.aa-custom-tab-items__row')).map(function(itemRow) {
							return {
								icon: getFieldValue(itemRow, 'icon'),
								title: getFieldValue(itemRow, 'title'),
								meta: getFieldValue(itemRow, 'meta'),
								text: getFieldValue(itemRow, 'text')
							};
						}).filter(function(item) {
							return item.icon || item.title || item.meta || item.text;
						});
					}

					function renderCardLikeItems(layout, items) {
						var className = 'aa-product-tab-layout aa-product-tab-layout--' + layout.replace(/_/g, '-');
						var list = 'shipping_timeline' === layout ? 'ol' : 'ul';

						return '<' + list + ' class="' + className + '">' + items.map(function(item) {
							var icon = item.icon ? '<span class="aa-product-tab-layout__icon" aria-hidden="true">' + escapeHtml(item.icon) + '</span>' : '';
							var meta = item.meta ? '<p class="aa-product-tab-layout__meta">' + escapeHtml(item.meta) + '</p>' : '';
							var title = item.title ? '<h4 class="aa-product-tab-layout__title">' + escapeHtml(item.title) + '</h4>' : '';
							var text = item.text ? '<div class="aa-product-tab-layout__text">' + renderParagraphs(item.text) + '</div>' : '';

							return '<li class="aa-product-tab-layout__item">' + icon + '<div class="aa-product-tab-layout__body">' + meta + title + text + '</div></li>';
						}).join('') + '</' + list + '>';
					}

					function renderSpecsTable(items) {
						return '<table class="aa-product-tab-layout aa-product-tab-layout--specs-table"><tbody>' + items.map(function(item) {
							return '<tr><th scope="row">' + escapeHtml(item.title || item.meta || '') + '</th><td>' + escapeHtml(item.text || '') + '</td></tr>';
						}).join('') + '</tbody></table>';
					}

					function renderFaq(items) {
						return '<div class="aa-product-tab-layout aa-product-tab-layout--faq">' + items.map(function(item, index) {
							return '<details class="aa-product-tab-layout__faq-item"' + (0 === index ? ' open' : '') + '><summary class="aa-product-tab-layout__faq-question">' + escapeHtml(item.title || '') + '</summary><div class="aa-product-tab-layout__faq-answer">' + renderParagraphs(item.text || '') + '</div></details>';
						}).join('') + '</div>';
					}

					function renderLayout(layout, items) {
						if (!items.length || 'rich_text' === layout || 'custom_html' === layout) {
							return '';
						}

						if ('specs_table' === layout) {
							return renderSpecsTable(items);
						}

						if ('faq' === layout) {
							return renderFaq(items);
						}

						return renderCardLikeItems(layout, items);
					}

					function renderPreview(row) {
						var titleInput = row.querySelector('[name$="[title]"]');
						var previewTitle = row.querySelector('.aa-custom-tabs-preview__title');
						var previewContent = row.querySelector('.aa-custom-tabs-preview__content');
						var layout = row.dataset.layout || 'rich_text';
						var intro = getFieldValue(row, 'content');
						var title = titleInput && titleInput.value ? titleInput.value : '<?php echo esc_js( __( 'Product Details', 'aggressive-apparel' ) ); ?>';
						var content = renderParagraphs(intro) + renderLayout(layout, getItems(row));

						if (!previewTitle || !previewContent) {
							return;
						}

						previewTitle.textContent = title;
						previewContent.innerHTML = content || '<p class="description"><?php echo esc_js( __( 'Add content or layout items to preview the frontend output.', 'aggressive-apparel' ) ); ?></p>';
					}

					function syncRow(row) {
						var layout = row.querySelector('.aa-custom-tabs-layout-select');
						var source = row.querySelector('.aa-custom-tabs-source-select');
						var help = row.querySelector('.aa-custom-tabs-layout-help');

					if (layout) {
						row.dataset.layout = layout.value;
						if (help) {
							help.textContent = layoutHelp[layout.value] || '';
						}
					}

						if (source) {
							row.dataset.source = source.value;
						}

						renderPreview(row);
					}

				function itemRow(tabIndex, itemIndex) {
					var base = prefix + '[' + tabIndex + '][items][' + itemIndex + ']';

					return '<div class="aa-custom-tab-items__row">' +
						'<p class="aa-custom-tab-items__row-fields">' +
							'<label><?php echo esc_js( __( 'Icon', 'aggressive-apparel' ) ); ?><br><input type="text" name="' + base + '[icon]" value="" class="aa-custom-tab-items__icon-input" placeholder="✓"></label>' +
							'<label><?php echo esc_js( __( 'Label / Question / Step', 'aggressive-apparel' ) ); ?><br><input type="text" name="' + base + '[title]" value="" class="regular-text"></label>' +
							'<label><?php echo esc_js( __( 'Detail / Eyebrow', 'aggressive-apparel' ) ); ?><br><input type="text" name="' + base + '[meta]" value="" class="regular-text"></label>' +
						'<button type="button" class="button-link-delete aa-custom-tab-items__remove"><?php echo esc_js( __( 'Remove Item', 'aggressive-apparel' ) ); ?></button>' +
					'</p>' +
					'<p><label><?php echo esc_js( __( 'Text / Answer / Value', 'aggressive-apparel' ) ); ?><br><textarea name="' + base + '[text]" rows="3" class="large-text"></textarea></label></p>' +
					'</div>';
			}

				function row(index) {
				var enabled = prefix + '[' + index + '][enabled]';
				var key = prefix + '[' + index + '][key]';
				var title = prefix + '[' + index + '][title]';
				var priority = prefix + '[' + index + '][priority]';
				var source = prefix + '[' + index + '][source]';
				var layout = prefix + '[' + index + '][layout]';
				var metaField = prefix + '[' + index + '][metaField]';
				var attribute = prefix + '[' + index + '][attribute]';
				var content = prefix + '[' + index + '][content]';

					return '<div class="aa-custom-tabs-repeater__row" data-tab-index="' + index + '" data-layout="rich_text" data-source="manual">' +
						'<input type="hidden" name="' + key + '" value="">' +
						'<p class="aa-custom-tabs-repeater__toolbar">' +
						'<label><input type="hidden" name="' + enabled + '" value="0"><input type="checkbox" name="' + enabled + '" value="1" checked> <?php echo esc_js( __( 'Enabled', 'aggressive-apparel' ) ); ?></label>' +
							'<label><?php echo esc_js( __( 'Priority', 'aggressive-apparel' ) ); ?> <input type="number" min="1" max="999" step="1" name="' + priority + '" value="40" class="aa-custom-tabs-repeater__priority-input"></label>' +
						'<button type="button" class="button-link-delete aa-custom-tabs-repeater__remove"><?php echo esc_js( __( 'Remove', 'aggressive-apparel' ) ); ?></button>' +
						'</p>' +
						'<div class="aa-custom-tabs-repeater__grid">' +
							'<p><label><?php echo esc_js( __( 'Tab Title', 'aggressive-apparel' ) ); ?><br><input type="text" name="' + title + '" value="" class="regular-text" placeholder="<?php echo esc_js( __( 'Size & Fit', 'aggressive-apparel' ) ); ?>"></label></p>' +
						'<p><label><?php echo esc_js( __( 'Content Layout', 'aggressive-apparel' ) ); ?><br><select class="aa-custom-tabs-layout-select" name="' + layout + '">' +
						'<option value="rich_text"><?php echo esc_js( __( 'Rich Text', 'aggressive-apparel' ) ); ?></option>' +
						'<option value="feature_cards"><?php echo esc_js( __( 'Feature Cards', 'aggressive-apparel' ) ); ?></option>' +
						'<option value="care_grid"><?php echo esc_js( __( 'Care Grid', 'aggressive-apparel' ) ); ?></option>' +
					'<option value="specs_table"><?php echo esc_js( __( 'Specs Table', 'aggressive-apparel' ) ); ?></option>' +
					'<option value="faq"><?php echo esc_js( __( 'FAQ / Accordion', 'aggressive-apparel' ) ); ?></option>' +
						'<option value="shipping_timeline"><?php echo esc_js( __( 'Shipping Timeline', 'aggressive-apparel' ) ); ?></option>' +
						'<option value="icon_list"><?php echo esc_js( __( 'Icon List', 'aggressive-apparel' ) ); ?></option>' +
						'<option value="custom_html"><?php echo esc_js( __( 'Custom HTML', 'aggressive-apparel' ) ); ?></option>' +
						'</select></label><span class="description aa-custom-tabs-layout-help" aria-live="polite"></span></p>' +
						'<p><label><?php echo esc_js( __( 'Content Source', 'aggressive-apparel' ) ); ?><br><select class="aa-custom-tabs-source-select" name="' + source + '">' +
						'<option value="manual"><?php echo esc_js( __( 'Manual / fallback content', 'aggressive-apparel' ) ); ?></option>' +
						'<option value="product_meta"><?php echo esc_js( __( 'Product meta field', 'aggressive-apparel' ) ); ?></option>' +
						'<option value="product_attribute"><?php echo esc_js( __( 'Product attribute', 'aggressive-apparel' ) ); ?></option>' +
						'</select></label></p>' +
						'</div>' +
						'<div class="aa-custom-tabs-source-fields">' +
							'<p data-source-field="meta"><label><?php echo esc_js( __( 'Product Meta Field', 'aggressive-apparel' ) ); ?><br><input type="text" name="' + metaField + '" value="" class="regular-text" placeholder="_my_meta_key"></label><span class="description"><?php echo esc_js( __( 'Used only when Content Source is Product meta field.', 'aggressive-apparel' ) ); ?></span></p>' +
							'<p data-source-field="attribute"><label><?php echo esc_js( __( 'Product Attribute', 'aggressive-apparel' ) ); ?><br><input type="text" name="' + attribute + '" value="" class="regular-text" placeholder="pa_material"></label><span class="description"><?php echo esc_js( __( 'Used only when Content Source is Product attribute.', 'aggressive-apparel' ) ); ?></span></p>' +
						'</div>' +
						'<p><label class="aa-custom-tabs-content-label"><?php echo esc_js( __( 'Intro / Fallback Content', 'aggressive-apparel' ) ); ?><br><textarea name="' + content + '" rows="5" class="large-text"></textarea></label><span class="description aa-custom-tabs-content-help"><?php echo esc_js( __( 'For structured layouts, this appears above the generated layout. For rich text, this is the full tab content.', 'aggressive-apparel' ) ); ?></span></p>' +
						'<div class="aa-custom-tab-items" data-next-item-index="1">' +
						'<p class="aa-custom-tab-items__header"><strong><?php echo esc_js( __( 'No-Code Layout Items', 'aggressive-apparel' ) ); ?></strong><br><span class="description"><?php echo esc_js( __( 'Add the rows this layout will render. The theme handles the frontend markup and styling.', 'aggressive-apparel' ) ); ?></span></p>' +
						'<div class="aa-custom-tab-items__rows">' + itemRow(index, 0) + '</div>' +
						'<button type="button" class="button aa-custom-tab-items__add"><?php echo esc_js( __( 'Add Layout Item', 'aggressive-apparel' ) ); ?></button>' +
							'<p class="description"><?php echo esc_js( __( 'Use these rows for cards, specs tables, FAQs, timelines, care grids, and icon lists. Leave them empty for rich text or custom HTML tabs.', 'aggressive-apparel' ) ); ?></p>' +
						'</div>' +
						'<div class="aa-custom-tabs-preview">' +
						'<p class="aa-custom-tabs-preview__label"><strong><?php echo esc_js( __( 'Frontend Preview', 'aggressive-apparel' ) ); ?></strong><br><span class="description"><?php echo esc_js( __( 'Updates as you edit. Custom HTML is shown as plain text in the preview for admin safety.', 'aggressive-apparel' ) ); ?></span></p>' +
						'<div class="aa-custom-tabs-preview__surface">' +
						'<h4 class="aa-custom-tabs-preview__title"></h4>' +
						'<div class="aa-product-info__content aa-custom-tabs-preview__content"></div>' +
						'</div>' +
						'</div>' +
						'</div>';
				}

				if (add) {
					add.addEventListener('click', function() {
						rows.insertAdjacentHTML('beforeend', row(nextIndex));
						syncRow(rows.lastElementChild);
						nextIndex += 1;
						root.dataset.nextIndex = String(nextIndex);
					});
				}

				root.querySelectorAll('.aa-custom-tabs-repeater__row').forEach(syncRow);

					root.addEventListener('change', function(event) {
						if (
							!event.target.classList.contains('aa-custom-tabs-layout-select') &&
							!event.target.classList.contains('aa-custom-tabs-source-select')
					) {
						return;
					}

						syncRow(event.target.closest('.aa-custom-tabs-repeater__row'));
					});

					root.addEventListener('input', function(event) {
						var row = event.target.closest('.aa-custom-tabs-repeater__row');

						if (!row) {
							return;
						}

						renderPreview(row);
					});

				root.addEventListener('click', function(event) {
					if (!event.target.classList.contains('aa-custom-tabs-repeater__remove')) {
						return;
				}

				event.preventDefault();
				var currentRows = rows.querySelectorAll('.aa-custom-tabs-repeater__row');
				if (currentRows.length <= 1) {
					currentRows[0].querySelectorAll('input[type="text"], textarea').forEach(function(field) {
						field.value = '';
					});
						currentRows[0].querySelector('input[type="number"]').value = '40';
						currentRows[0].querySelector('input[type="checkbox"]').checked = true;
						syncRow(currentRows[0]);
						return;
					}

				event.target.closest('.aa-custom-tabs-repeater__row').remove();
			});

			root.addEventListener('click', function(event) {
				if (event.target.classList.contains('aa-custom-tab-items__add')) {
					event.preventDefault();
					var itemRoot = event.target.closest('.aa-custom-tab-items');
					var tabRow = event.target.closest('.aa-custom-tabs-repeater__row');
					var tabIndex = tabRow.dataset.tabIndex || '0';
					var itemRows = itemRoot.querySelector('.aa-custom-tab-items__rows');
					var nextItemIndex = parseInt(itemRoot.dataset.nextItemIndex || '0', 10);

						itemRows.insertAdjacentHTML('beforeend', itemRow(tabIndex, nextItemIndex));
						nextItemIndex += 1;
						itemRoot.dataset.nextItemIndex = String(nextItemIndex);
						renderPreview(tabRow);
						return;
					}

				if (!event.target.classList.contains('aa-custom-tab-items__remove')) {
					return;
				}

				event.preventDefault();
				var currentItemRows = event.target.closest('.aa-custom-tab-items__rows').querySelectorAll('.aa-custom-tab-items__row');

				if (currentItemRows.length <= 1) {
					currentItemRows[0].querySelectorAll('input[type="text"], textarea').forEach(function(field) {
						field.value = '';
					});
					renderPreview(event.target.closest('.aa-custom-tabs-repeater__row'));
					return;
				}

				var parentRow = event.target.closest('.aa-custom-tabs-repeater__row');
				event.target.closest('.aa-custom-tab-items__row').remove();
				renderPreview(parentRow);
			});
		})();
		</script>
		<?php
	}

	/**
	 * Check if the current page is a single product page.
	 *
	 * @return bool
	 */
	private function is_product_page(): bool {
		return function_exists( 'is_product' ) && is_product();
	}
}
