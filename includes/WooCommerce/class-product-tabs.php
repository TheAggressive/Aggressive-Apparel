<?php
/**
 * Product Tabs orchestrator.
 *
 * Coordinates tab registration, block transformation, and delegated services.
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
 * Product Tabs Manager.
 *
 * @since 1.17.0
 */
class Product_Tabs {

	/**
	 * Product ID currently being rendered by the Product Details block.
	 *
	 * @var int
	 */
	public int $render_product_id = 0;

	/**
	 * Sanitization service.
	 *
	 * @var Product_Tabs_Sanitizer
	 */
	private Product_Tabs_Sanitizer $sanitizer;

	/**
	 * Content helpers.
	 *
	 * @var Product_Tabs_Content
	 */
	private Product_Tabs_Content $content;

	/**
	 * Layout renderers.
	 *
	 * @var Product_Tabs_Layouts
	 */
	private Product_Tabs_Layouts $layouts;

	/**
	 * Frontend display renderer.
	 *
	 * @var Product_Tabs_Renderer
	 */
	private Product_Tabs_Renderer $renderer;

	/**
	 * Admin UI.
	 *
	 * @var Product_Tabs_Admin
	 */
	private Product_Tabs_Admin $admin;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->sanitizer = new Product_Tabs_Sanitizer();
		$this->content   = new Product_Tabs_Content();
		$this->layouts   = new Product_Tabs_Layouts( $this->content );
		$this->renderer  = new Product_Tabs_Renderer( $this->content, $this );
		$this->admin     = new Product_Tabs_Admin( $this->sanitizer, $this );
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'woocommerce_product_tabs', array( $this, 'add_custom_tabs' ), 20 );
		add_filter( 'render_block', array( $this, 'transform_product_details_block' ), 10, 3 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'add_meta_boxes', array( $this->admin, 'add_meta_box' ) );
		add_action( 'save_post_product', array( $this->admin, 'save_meta' ) );
		add_action( 'woocommerce_process_product_meta', array( $this->admin, 'save_meta' ) );
		add_action( 'admin_init', array( $this->admin, 'register_global_settings' ) );
		add_action( 'admin_menu', array( $this->admin, 'add_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_admin_assets' ) );
	}

	/**
	 * Get the sanitizer service.
	 *
	 * @return Product_Tabs_Sanitizer
	 */
	public function get_sanitizer(): Product_Tabs_Sanitizer {
		return $this->sanitizer;
	}

	/**
	 * Get the current display style.
	 *
	 * @return string One of: accordion, inline, modern-tabs, scrollspy.
	 */
	public function get_display_style(): string {
		$options = get_option( Product_Tabs_Config::OPTION_KEY, array() );
		$style   = is_array( $options ) && isset( $options['display_style'] ) ? $options['display_style'] : 'accordion';

		return in_array( $style, Product_Tabs_Config::VALID_STYLES, true ) ? $style : 'accordion';
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
	 * Intercept the product-details block and replace with chosen style.
	 *
	 * @param string $block_content  Block HTML.
	 * @param array  $block          Block data.
	 * @param mixed  $block_instance Rendered block instance.
	 * @return string Modified or original HTML.
	 */
	public function transform_product_details_block( string $block_content, array $block, $block_instance = null ): string {
		if ( ! isset( $block['blockName'] ) || 'woocommerce/product-details' !== $block['blockName'] ) {
			return $block_content;
		}

		if ( ! $this->is_product_page() ) {
			return $block_content;
		}

		$hide_tab_title = $this->renderer->should_hide_tab_title_in_content( $block );
		$tabs           = $this->renderer->get_renderable_woocommerce_tabs( $block, $hide_tab_title, $block_instance );

		if ( empty( $tabs ) ) {
			$tabs = $this->renderer->extract_tabs_from_html( $block_content );

			if ( $hide_tab_title ) {
				$tabs = $this->renderer->remove_tab_title_headings( $tabs );
			}
		}

		if ( empty( $tabs ) ) {
			return $block_content;
		}

		return $this->renderer->render_tabs_by_style( $tabs, $block_content );
	}

	/**
	 * Add custom tabs to the product page.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array Modified tabs.
	 */
	public function add_custom_tabs( array $tabs ): array {
		$global    = get_option( Product_Tabs_Config::OPTION_KEY, array() );
		$post_id   = $this->get_current_product_id();
		$overrides = $post_id ? $this->sanitizer->get_product_tab_overrides( $post_id ) : array();

		$care = '';
		if ( $post_id ) {
			$care = (string) get_post_meta( $post_id, '_aggressive_apparel_care_instructions', true );
		}
		if ( '' === $care && is_array( $global ) && ! empty( $global['care_instructions'] ) ) {
			$care = (string) $global['care_instructions'];
		}
		$care = $this->sanitizer->apply_tab_override_content( 'care_instructions', $care, $overrides );

		if ( '' !== $care ) {
			$tabs['care_instructions'] = array(
				'title'    => __( 'Care Instructions', 'aggressive-apparel' ),
				'priority' => 25,
				'callback' => function () use ( $care ): void {
					$this->content->render_product_info_content( $care );
				},
			);
		}

		if ( is_array( $global ) && ! empty( $global['shipping_returns'] ) ) {
			$shipping_returns = $this->sanitizer->apply_tab_override_content( 'shipping_returns', (string) $global['shipping_returns'], $overrides );

			if ( '' === $shipping_returns ) {
				unset( $tabs['shipping_returns'] );
			} else {
				$tabs['shipping_returns'] = array(
					'title'    => __( 'Shipping & Returns', 'aggressive-apparel' ),
					'priority' => 30,
					'callback' => function () use ( $shipping_returns ): void {
						$this->content->render_product_info_content( $shipping_returns );
					},
				);
			}
		}

		if ( is_array( $global ) && ! empty( $global['sustainability'] ) ) {
			$sustainability = $this->sanitizer->apply_tab_override_content( 'sustainability', (string) $global['sustainability'], $overrides );

			if ( '' === $sustainability ) {
				unset( $tabs['sustainability'] );
			} else {
				$tabs['sustainability'] = array(
					'title'    => __( 'Sustainability', 'aggressive-apparel' ),
					'priority' => 35,
					'callback' => function () use ( $sustainability ): void {
						$this->content->render_product_info_content( $sustainability );
					},
				);
			}
		}

		if ( is_array( $global ) && ! empty( $global['custom_tabs'] ) && is_array( $global['custom_tabs'] ) ) {
			$tabs = $this->append_custom_tabs(
				$tabs,
				$this->sanitizer->sanitize_custom_tabs( $global['custom_tabs'] ),
				'global',
				$post_id ? (int) $post_id : 0,
				$overrides
			);
		}

		if ( $post_id ) {
			$product_tabs = get_post_meta( $post_id, Product_Tabs_Config::PRODUCT_CUSTOM_TABS_META_KEY, true );
			$tabs         = $this->append_custom_tabs(
				$tabs,
				$this->sanitizer->sanitize_custom_tabs( $product_tabs ),
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
	public function append_custom_tabs(
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

			$content = $this->content->resolve_custom_tab_content( $tab, $product_id );
			$extra   = (string) ( $override['content'] ?? '' );

			if ( 'override' === ( $override['mode'] ?? '' ) ) {
				$content = $extra;
			} elseif ( 'append' === ( $override['mode'] ?? '' ) && '' !== trim( wp_strip_all_tags( $extra ) ) ) {
				$content .= "\n\n" . $extra;
			}

			if ( ! $this->content->has_custom_tab_content( $tab, $content ) ) {
				continue;
			}

			$key          = 'aa_' . sanitize_key( $scope . '_' . $index . '_' . $tab_key );
			$title        = (string) $tab['title'];
			$tabs[ $key ] = array(
				'title'    => $title,
				'priority' => $tab['priority'],
				'callback' => function () use ( $tab, $content ): void {
					$this->layouts->render_custom_tab_content( $tab, $content );
				},
			);
		}

		return $tabs;
	}

	/**
	 * Resolve the current product ID across WooCommerce and block contexts.
	 *
	 * @param array $block          Optional block data from render_block.
	 * @param mixed $block_instance Optional rendered block instance.
	 * @return int Product ID, or 0 when no product is available.
	 */
	public function get_current_product_id( array $block = array(), $block_instance = null ): int {
		if ( $this->render_product_id > 0 ) {
			return $this->render_product_id;
		}

		if ( $block_instance instanceof \WP_Block && isset( $block_instance->context['postId'] ) ) {
			$context_product_id = absint( $block_instance->context['postId'] );

			if ( $context_product_id > 0 && 'product' === get_post_type( $context_product_id ) ) {
				return $context_product_id;
			}
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
	 * Check if the current page is a single product page.
	 *
	 * @return bool
	 */
	private function is_product_page(): bool {
		return function_exists( 'is_product' ) && is_product();
	}
}
