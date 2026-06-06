<?php
/**
 * WooCommerce Block Detector
 *
 * Determines whether the current request renders WooCommerce blocks anywhere
 * (routes, post content, block templates, or template parts).
 *
 * @package Aggressive_Apparel
 * @since 1.79.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Block Detector
 *
 * @since 1.79.0
 */
class WooCommerce_Block_Detector {

	/**
	 * Cached parse results keyed by a hash of the content string.
	 *
	 * @var array<string, bool>
	 */
	private static array $content_scan_cache = array();

	/**
	 * Reference keys currently being resolved to prevent infinite recursion.
	 *
	 * @var array<string, true>
	 */
	private static array $resolving_refs = array();

	/**
	 * Whether WooCommerce frontend assets should load on this request.
	 *
	 * @return bool
	 */
	public static function request_needs_assets(): bool {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return false;
		}

		$detected = self::detect();

		/**
		 * Filter whether WooCommerce frontend assets should load.
		 *
		 * @since 1.79.0
		 *
		 * @param bool $needed Whether assets are needed for the current request.
		 */
		return (bool) apply_filters( 'aggressive_apparel_needs_woocommerce_assets', $detected );
	}

	/**
	 * Run all detection strategies for the current request.
	 *
	 * @return bool
	 */
	private static function detect(): bool {
		if ( self::is_wc_route() ) {
			return true;
		}

		if ( Product_Context::is_product_display_page() ) {
			return true;
		}

		if ( self::queried_content_has_woocommerce_blocks() ) {
			return true;
		}

		if ( self::resolved_template_has_woocommerce_blocks() ) {
			return true;
		}

		if ( self::theme_template_parts_have_woocommerce_blocks() ) {
			return true;
		}

		if ( self::widget_areas_have_woocommerce_blocks() ) {
			return true;
		}

		if ( self::index_template_has_woocommerce_blocks() ) {
			return true;
		}

		return false;
	}

	/**
	 * Whether the request is on a native WooCommerce route.
	 *
	 * @return bool
	 */
	private static function is_wc_route(): bool {
		if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
			return true;
		}

		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return true;
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return true;
		}

		return function_exists( 'is_account_page' ) && is_account_page();
	}

	/**
	 * Whether the queried post (or posts page) content contains WooCommerce blocks.
	 *
	 * @return bool
	 */
	private static function queried_content_has_woocommerce_blocks(): bool {
		$post_id = get_queried_object_id();

		if ( $post_id > 0 ) {
			return self::post_has_woocommerce_blocks( $post_id );
		}

		if ( is_home() && ! is_front_page() ) {
			$posts_page_id = (int) get_option( 'page_for_posts' );

			if ( $posts_page_id > 0 ) {
				return self::post_has_woocommerce_blocks( $posts_page_id );
			}
		}

		return false;
	}

	/**
	 * Whether a post's stored content contains WooCommerce blocks.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function post_has_woocommerce_blocks( int $post_id ): bool {
		if ( $post_id <= 0 ) {
			return false;
		}

		$content = get_post_field( 'post_content', $post_id );

		if ( ! is_string( $content ) || '' === $content ) {
			return false;
		}

		return self::content_has_woocommerce_blocks( $content );
	}

	/**
	 * Whether a block markup string contains WooCommerce blocks.
	 *
	 * Recursively resolves reusable `core/block` references.
	 *
	 * @param string $content Block HTML or serialized block content.
	 * @return bool
	 */
	public static function content_has_woocommerce_blocks( string $content ): bool {
		if ( '' === $content ) {
			return false;
		}

		$cache_key = md5( $content );

		if ( isset( self::$content_scan_cache[ $cache_key ] ) ) {
			return self::$content_scan_cache[ $cache_key ];
		}

		if ( str_contains( $content, '<!-- wp:woocommerce/' ) ) {
			self::$content_scan_cache[ $cache_key ] = true;
			return true;
		}

		if ( ! str_contains( $content, '<!-- wp:' ) ) {
			self::$content_scan_cache[ $cache_key ] = false;
			return false;
		}

		$found = self::blocks_contain_woocommerce( parse_blocks( $content ) );

		self::$content_scan_cache[ $cache_key ] = $found;

		return $found;
	}

	/**
	 * Whether the block template for the current request contains WooCommerce blocks.
	 *
	 * @return bool
	 */
	private static function resolved_template_has_woocommerce_blocks(): bool {
		$slug = self::resolve_template_slug_for_request();

		if ( '' === $slug ) {
			return false;
		}

		return self::block_template_has_woocommerce_blocks( $slug, 'wp_template' );
	}

	/**
	 * Whether any theme template part contains WooCommerce blocks.
	 *
	 * Scans every `parts/*.html` file so custom parts (not only header/footer)
	 * cannot hide WooCommerce blocks from detection.
	 *
	 * @return bool
	 */
	private static function theme_template_parts_have_woocommerce_blocks(): bool {
		foreach ( self::get_theme_template_part_slugs() as $slug ) {
			if ( self::block_template_has_woocommerce_blocks( $slug, 'wp_template_part' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether block widgets in active sidebars contain WooCommerce blocks.
	 *
	 * @return bool
	 */
	private static function widget_areas_have_woocommerce_blocks(): bool {
		$sidebars_widgets = wp_get_sidebars_widgets();

		if ( ! is_array( $sidebars_widgets ) ) {
			return false;
		}

		$block_widgets = get_option( 'widget_block', array() );

		if ( ! is_array( $block_widgets ) ) {
			return false;
		}

		foreach ( $sidebars_widgets as $sidebar_id => $widgets ) {
			if ( ! is_array( $widgets ) || 'wp_inactive_widgets' === $sidebar_id ) {
				continue;
			}

			foreach ( $widgets as $widget_id ) {
				if ( ! is_string( $widget_id ) || ! str_starts_with( $widget_id, 'block-' ) ) {
					continue;
				}

				$instance_id = (int) substr( $widget_id, 6 );

				if ( $instance_id <= 0 || ! isset( $block_widgets[ $instance_id ]['content'] ) ) {
					continue;
				}

				$widget_content = $block_widgets[ $instance_id ]['content'];

				if ( is_string( $widget_content ) && self::content_has_woocommerce_blocks( $widget_content ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Whether the theme index template contains WooCommerce blocks.
	 *
	 * Index is the FSE fallback for many routes; treat it as a fail-open signal.
	 *
	 * @return bool
	 */
	private static function index_template_has_woocommerce_blocks(): bool {
		return self::block_template_has_woocommerce_blocks( 'index', 'wp_template' );
	}

	/**
	 * List template part slugs from the active theme's `parts/` directory.
	 *
	 * @return array<int, string>
	 */
	private static function get_theme_template_part_slugs(): array {
		static $slugs = null;

		if ( null !== $slugs ) {
			return $slugs;
		}

		$slugs = array();
		$files = glob( get_theme_file_path( 'parts' ) . '/*.html' );

		if ( ! is_array( $files ) ) {
			return $slugs;
		}

		foreach ( $files as $file ) {
			if ( ! is_string( $file ) ) {
				continue;
			}

			$slug = basename( $file, '.html' );

			if ( '' !== $slug ) {
				$slugs[] = $slug;
			}
		}

		return $slugs;
	}

	/**
	 * Whether a theme block template or template part contains WooCommerce blocks.
	 *
	 * Checks the customized database template first, then falls back to theme files.
	 *
	 * @param string $slug Template slug without extension.
	 * @param string $type `wp_template` or `wp_template_part`.
	 * @return bool
	 */
	private static function block_template_has_woocommerce_blocks( string $slug, string $type ): bool {
		if ( function_exists( 'get_block_template' ) ) {
			$template_id = get_stylesheet() . '//' . $slug;
			$template    = 'wp_template_part' === $type
				? get_block_template( $template_id, 'wp_template_part' )
				: get_block_template( $template_id, 'wp_template' );

			if ( $template && ! empty( $template->content ) && self::content_has_woocommerce_blocks( $template->content ) ) {
				return true;
			}
		}

		$relative_path = 'wp_template_part' === $type
			? 'parts/' . $slug . '.html'
			: 'templates/' . $slug . '.html';

		$file_path = get_theme_file_path( $relative_path );

		if ( ! is_readable( $file_path ) ) {
			return false;
		}

		$file_content = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		return is_string( $file_content ) && self::content_has_woocommerce_blocks( $file_content );
	}

	/**
	 * Resolve the block template slug WordPress would use for this request.
	 *
	 * @return string Empty string when no template mapping is available.
	 */
	private static function resolve_template_slug_for_request(): string {
		if ( is_front_page() ) {
			return 'page' === get_option( 'show_on_front' ) ? 'front-page' : 'home';
		}

		if ( is_home() ) {
			return 'home';
		}

		if ( is_search() ) {
			return 'search';
		}

		if ( is_404() ) {
			return '404';
		}

		if ( Product_Context::is_single_product() ) {
			return 'single-product';
		}

		if ( is_singular( 'post' ) ) {
			return 'single';
		}

		if ( is_singular() && ! is_page() ) {
			return 'singular';
		}

		if ( is_page() ) {
			$custom_slug = get_page_template_slug();

			if ( is_string( $custom_slug ) && '' !== $custom_slug ) {
				return self::normalize_template_slug( $custom_slug );
			}

			return 'page';
		}

		if ( function_exists( 'is_product_category' ) && is_product_category() ) {
			return 'taxonomy-product_cat';
		}

		if ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
			return 'taxonomy-product_tag';
		}

		if ( Product_Context::is_product_archive() ) {
			return 'archive-product';
		}

		return '';
	}

	/**
	 * Normalize a template slug from `get_page_template_slug()` for FSE lookup.
	 *
	 * @param string $slug Raw template slug.
	 * @return string
	 */
	private static function normalize_template_slug( string $slug ): string {
		$slug = str_replace( '\\', '/', $slug );
		$slug = preg_replace( '#^templates/#', '', $slug ) ?? $slug;
		$slug = preg_replace( '#\.html$#', '', $slug ) ?? $slug;

		return $slug;
	}

	/**
	 * Recursively detect WooCommerce blocks, including reusable block refs.
	 *
	 * WordPress `parse_blocks()` may include null values (e.g. `blockName`).
	 *
	 * @param array<int|string, array<string, mixed>> $blocks Parsed blocks.
	 * @return bool
	 */
	private static function blocks_contain_woocommerce( array $blocks ): bool {
		foreach ( $blocks as $block ) {
			$block_name = $block['blockName'] ?? '';

			if ( is_string( $block_name ) && str_starts_with( $block_name, 'woocommerce/' ) ) {
				return true;
			}

			if ( 'core/block' === $block_name && ! empty( $block['attrs']['ref'] ) ) {
				$ref_id = absint( $block['attrs']['ref'] );

				if ( $ref_id > 0 && self::post_has_woocommerce_blocks( $ref_id ) ) {
					return true;
				}
			}

			if ( 'core/template-part' === $block_name && ! empty( $block['attrs']['slug'] ) ) {
				$slug = $block['attrs']['slug'];

				if ( is_string( $slug ) && '' !== $slug && self::template_part_reference_has_woocommerce_blocks( $slug ) ) {
					return true;
				}
			}

			if ( 'core/pattern' === $block_name && ! empty( $block['attrs']['slug'] ) ) {
				$slug = $block['attrs']['slug'];

				if ( is_string( $slug ) && '' !== $slug && self::pattern_has_woocommerce_blocks( $slug ) ) {
					return true;
				}
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				if ( self::blocks_contain_woocommerce( $block['innerBlocks'] ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Whether a referenced template part contains WooCommerce blocks.
	 *
	 * @param string $slug Template part slug.
	 * @return bool
	 */
	private static function template_part_reference_has_woocommerce_blocks( string $slug ): bool {
		$ref_key = 'template-part:' . $slug;

		if ( isset( self::$resolving_refs[ $ref_key ] ) ) {
			return false;
		}

		self::$resolving_refs[ $ref_key ] = true;

		$found = self::block_template_has_woocommerce_blocks( $slug, 'wp_template_part' );

		unset( self::$resolving_refs[ $ref_key ] );

		return $found;
	}

	/**
	 * Whether a registered block pattern contains WooCommerce blocks.
	 *
	 * @param string $slug Pattern slug.
	 * @return bool
	 */
	private static function pattern_has_woocommerce_blocks( string $slug ): bool {
		$ref_key = 'pattern:' . $slug;

		if ( isset( self::$resolving_refs[ $ref_key ] ) ) {
			return false;
		}

		if ( ! class_exists( 'WP_Block_Patterns_Registry' ) ) {
			return false;
		}

		self::$resolving_refs[ $ref_key ] = true;

		$registry = \WP_Block_Patterns_Registry::get_instance();
		$pattern  = $registry->get_registered( $slug );
		$found    = false;

		if ( is_array( $pattern ) && ! empty( $pattern['content'] ) && is_string( $pattern['content'] ) ) {
			$found = self::content_has_woocommerce_blocks( $pattern['content'] );
		}

		unset( self::$resolving_refs[ $ref_key ] );

		return $found;
	}

	/**
	 * Whether a rendered block is a WooCommerce block.
	 *
	 * @param array<string, mixed> $block Parsed block data.
	 * @return bool
	 */
	public static function is_woocommerce_block( array $block ): bool {
		$block_name = $block['blockName'] ?? '';

		return is_string( $block_name ) && str_starts_with( $block_name, 'woocommerce/' );
	}
}
