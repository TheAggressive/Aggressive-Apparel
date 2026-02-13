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
	 * Valid display style values.
	 *
	 * @var string[]
	 */
	private const VALID_STYLES = array( 'accordion', 'inline', 'modern-tabs', 'scrollspy' );

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
				array(),
				(string) filemtime( $css_file ),
			);
		}

		$style = $this->get_display_style();

		// Only accordion, modern-tabs, and scrollspy need JS.
		if ( 'inline' !== $style && function_exists( 'wp_register_script_module' ) ) {
			wp_register_script_module(
				'@aggressive-apparel/product-tabs',
				AGGRESSIVE_APPAREL_URI . '/assets/interactivity/product-tabs.js',
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

		$tabs = $this->extract_tabs_from_html( $block_content );
		if ( empty( $tabs ) ) {
			return $block_content;
		}

		$style = $this->get_display_style();

		switch ( $style ) {
			case 'accordion':
				return $this->render_accordion( $tabs );
			case 'inline':
				return $this->render_inline( $tabs );
			case 'modern-tabs':
				return $this->render_modern_tabs( $tabs );
			case 'scrollspy':
				return $this->render_scrollspy( $tabs );
			default:
				return $block_content;
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
				$slug   = (string) preg_replace( '/^tab-/', '', $safe_id );
				$tabs[] = array(
					'title'   => $tab_info['title'],
					'id'      => $slug,
					'content' => trim( $inner_html ),
				);
			}
		}

		return $tabs;
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
			'aggressive_apparel_features_server',
			array( 'label_for' => 'aa_product_tabs_style' ),
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
	 * Sanitize global tab content and display settings.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, string>
	 */
	public function sanitize_global_tabs( $input ): array {
		$output = array();
		if ( ! is_array( $input ) ) {
			return $output;
		}

		foreach ( $input as $key => $value ) {
			if ( 'display_style' === $key ) {
				$output['display_style'] = in_array( $value, self::VALID_STYLES, true ) ? $value : 'accordion';
			} else {
				$output[ sanitize_key( $key ) ] = wp_kses_post( $value );
			}
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
		$post_id = get_the_ID();

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
					echo wp_kses_post( wpautop( $care ) );
				},
			);
		}

		// Shipping & Returns (global only).
		if ( ! empty( $global['shipping_returns'] ) ) {
			$tabs['shipping_returns'] = array(
				'title'    => __( 'Shipping & Returns', 'aggressive-apparel' ),
				'priority' => 30,
				'callback' => function () use ( $global ) {
					echo wp_kses_post( wpautop( $global['shipping_returns'] ) );
				},
			);
		}

		// Sustainability (global only).
		if ( ! empty( $global['sustainability'] ) ) {
			$tabs['sustainability'] = array(
				'title'    => __( 'Sustainability', 'aggressive-apparel' ),
				'priority' => 35,
				'callback' => function () use ( $global ) {
					echo wp_kses_post( wpautop( $global['sustainability'] ) );
				},
			);
		}

		return $tabs;
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

		echo '<p><label for="aa_care_instructions"><strong>' . esc_html__( 'Care Instructions', 'aggressive-apparel' ) . '</strong></label></p>';
		echo '<textarea id="aa_care_instructions" name="aa_care_instructions" rows="4" class="widefat">' . esc_textarea( $care ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Leave empty to use the global default from Appearance → Store Enhancements.', 'aggressive-apparel' ) . '</p>';
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
