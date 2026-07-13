<?php
/**
 * Theme Support Class
 *
 * Handles WordPress theme support features registration
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme Support Class
 *
 * Registers all WordPress theme supports following WordPress best practices.
 *
 * @since 1.0.0
 */
class Theme_Support {

	/**
	 * Initialize theme support
	 *
	 * @return void
	 */
	public function init() {
		$this->register_theme_support();
		$this->register_block_styles();
		$this->remove_emoji_scripts();
	}

	/**
	 * Remove the WordPress emoji detection script and styles.
	 *
	 * Modern browsers render emoji natively; the polyfill costs ~10 KB of
	 * inline script plus potential twemoji image fetches on every page.
	 *
	 * @return void
	 */
	private function remove_emoji_scripts() {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

		// Drop the s.w.org DNS prefetch emitted for the twemoji CDN.
		add_filter(
			'emoji_svg_url',
			'__return_false'
		);
	}

	/**
	 * Register theme support features
	 *
	 * @return void
	 */
	public function register_theme_support() {
		// Add default posts and comments RSS feed links to head.
		add_theme_support( 'automatic-feed-links' );

		// Let WordPress manage the document title.
		add_theme_support( 'title-tag' );

		// Enable support for Post Thumbnails on posts and pages.
		add_theme_support( 'post-thumbnails' );

		// Add support for responsive embedded content.
		add_theme_support( 'responsive-embeds' );

		// Add support for experimental link color control.
		add_theme_support( 'experimental-link-color' );

		// Add support for Block Styles.
		add_theme_support( 'wp-block-styles' );

		// Load the design-token layer into the block editor canvas so --aa-*
		// custom properties resolve there (matching the front end). WordPress
		// injects this into the editor iframe and scopes :root to
		// .editor-styles-wrapper — the reliable channel that enqueue_block_assets
		// does not guarantee. Only tokens.css is loaded this way; the heavier
		// main.css/Tailwind stays on enqueue_block_assets to avoid the editor's
		// CSS selector-prefixer choking on modern syntax.
		add_theme_support( 'editor-styles' );
		add_editor_style( 'build/styles/base/tokens.css' );

		// Add support for full and wide align images.
		add_theme_support( 'align-wide' );

		// Add support for custom line height controls.
		add_theme_support( 'custom-line-height' );

		// Add support for custom units.
		add_theme_support( 'custom-units' );

		// Add support for custom spacing.
		add_theme_support( 'custom-spacing' );

		// Add support for HTML5 markup.
		add_theme_support(
			'html5',
			array(
				'comment-list',
				'comment-form',
				'search-form',
				'gallery',
				'caption',
				'style',
				'script',
			)
		);

		// Remove core block patterns.
		remove_theme_support( 'core-block-patterns' );

		/**
		 * Hook: After theme support registration
		 *
		 * @since 1.0.0
		 */
		do_action( 'aggressive_apparel_after_theme_support' );
	}

	/**
	 * Register design system block style variations.
	 *
	 * @return void
	 */
	private function register_block_styles(): void {
		register_block_style(
			'core/button',
			array(
				'name'       => 'ghost',
				'label'      => __( 'Ghost', 'aggressive-apparel' ),
				'style_data' => array(
					'border' => array(
						'color' => 'var:preset|color|foreground',
						'style' => 'solid',
						'width' => '2px',
					),
					'color'  => array(
						'background' => 'transparent',
						'text'       => 'var:preset|color|foreground',
					),
					':hover' => array(
						'color' => array(
							'background' => 'var:preset|color|foreground',
							'text'       => 'var:preset|color|surface',
						),
					),
				),
			)
		);

		register_block_style(
			'core/button',
			array(
				'name'       => 'text',
				'label'      => __( 'Text', 'aggressive-apparel' ),
				'style_data' => array(
					'border'     => array(
						'width' => '0',
					),
					'color'      => array(
						'background' => 'transparent',
						'text'       => 'var:preset|color|accent',
					),
					'spacing'    => array(
						'padding' => array(
							'left'  => '0',
							'right' => '0',
						),
					),
					'typography' => array(
						'textDecoration' => 'underline',
					),
					':hover'     => array(
						'color' => array(
							'background' => 'transparent',
							'text'       => 'var:preset|color|foreground',
						),
					),
				),
			)
		);

		register_block_style(
			'core/button',
			array(
				'name'  => 'small',
				'label' => __( 'Small', 'aggressive-apparel' ),
			)
		);

		register_block_style(
			'core/button',
			array(
				'name'  => 'cta',
				'label' => __( 'CTA', 'aggressive-apparel' ),
			)
		);

		register_block_style(
			'core/button',
			array(
				'name'  => 'cta-small',
				'label' => __( 'CTA Small', 'aggressive-apparel' ),
			)
		);

		register_block_style(
			'core/button',
			array(
				'name'       => 'outline-on-dark',
				'label'      => __( 'Outline on Dark', 'aggressive-apparel' ),
				'style_data' => array(
					'border' => array(
						'color' => 'var:preset|color|white',
						'style' => 'solid',
						'width' => '2px',
					),
					'color'  => array(
						'background' => 'transparent',
						'text'       => 'var:preset|color|white',
					),
					':hover' => array(
						'color' => array(
							'background' => 'var:preset|color|white',
							'text'       => 'var:preset|color|black',
						),
					),
				),
			)
		);

		register_block_style(
			'core/group',
			array(
				'name'  => 'frosted',
				'label' => __( 'Frosted Glass', 'aggressive-apparel' ),
			)
		);

		register_block_style(
			'core/group',
			array(
				'name'  => 'surface-card',
				'label' => __( 'Surface Card', 'aggressive-apparel' ),
			)
		);

		register_block_style(
			'core/group',
			array(
				'name'  => 'bordered',
				'label' => __( 'Bordered', 'aggressive-apparel' ),
			)
		);

		register_block_style(
			'core/heading',
			array(
				'name'  => 'display',
				'label' => __( 'Display', 'aggressive-apparel' ),
			)
		);

		register_block_style(
			'core/image',
			array(
				'name'  => 'editorial',
				'label' => __( 'Editorial', 'aggressive-apparel' ),
			)
		);

		register_block_style(
			'core/separator',
			array(
				'name'  => 'brand-stripe',
				'label' => __( 'Brand Stripe', 'aggressive-apparel' ),
			)
		);

		register_block_style(
			'core/paragraph',
			array(
				'name'  => 'badge',
				'label' => __( 'Badge', 'aggressive-apparel' ),
			)
		);

		register_block_style(
			'core/paragraph',
			array(
				'name'  => 'badge-muted',
				'label' => __( 'Badge Muted', 'aggressive-apparel' ),
			)
		);

		register_block_style(
			'core/paragraph',
			array(
				'name'  => 'eyebrow',
				'label' => __( 'Eyebrow', 'aggressive-apparel' ),
			)
		);

		register_block_style(
			'core/paragraph',
			array(
				'name'  => 'caption',
				'label' => __( 'Caption', 'aggressive-apparel' ),
			)
		);

		register_block_style(
			'core/paragraph',
			array(
				'name'  => 'meta',
				'label' => __( 'Meta', 'aggressive-apparel' ),
			)
		);

		register_block_style(
			'core/paragraph',
			array(
				'name'  => 'legal',
				'label' => __( 'Legal', 'aggressive-apparel' ),
			)
		);

		register_block_style(
			'core/paragraph',
			array(
				'name'  => 'price',
				'label' => __( 'Price', 'aggressive-apparel' ),
			)
		);

		register_block_style(
			'core/separator',
			array(
				'name'  => 'subtle',
				'label' => __( 'Subtle', 'aggressive-apparel' ),
			)
		);

		if ( class_exists( 'WooCommerce' ) ) {
			register_block_style(
				'woocommerce/product-collection',
				array(
					'name'  => 'commerce-grid',
					'label' => __( 'Commerce Grid', 'aggressive-apparel' ),
				)
			);

			register_block_style(
				'woocommerce/product-template',
				array(
					'name'  => 'commerce-cards',
					'label' => __( 'Commerce Cards', 'aggressive-apparel' ),
				)
			);

			register_block_style(
				'woocommerce/product-image',
				array(
					'name'  => 'product-frame',
					'label' => __( 'Product Frame', 'aggressive-apparel' ),
				)
			);

			register_block_style(
				'woocommerce/product-price',
				array(
					'name'  => 'commerce-price',
					'label' => __( 'Commerce Price', 'aggressive-apparel' ),
				)
			);
		}

		// ── Display / editorial styles ───────────────────────────────────.
		register_block_style(
			'core/heading',
			array(
				'name'  => 'overflow',
				'label' => __( 'Overflow', 'aggressive-apparel' ),
			)
		);

		register_block_style(
			'core/heading',
			array(
				'name'  => 'text-mask',
				'label' => __( 'Text Mask', 'aggressive-apparel' ),
			)
		);

		register_block_style(
			'core/cover',
			array(
				'name'  => 'cinematic',
				'label' => __( 'Cinematic', 'aggressive-apparel' ),
			)
		);

		register_block_style(
			'core/group',
			array(
				'name'  => 'frosted-dark',
				'label' => __( 'Frosted Dark', 'aggressive-apparel' ),
			)
		);
	}
}
