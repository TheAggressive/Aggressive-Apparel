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

use Aggressive_Apparel\Core\Cache_Helper;

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
	 * Option key storing a global cache version for detector transients.
	 *
	 * @var string
	 */
	private const CACHE_VERSION_OPTION = 'aggressive_apparel_wc_block_detector_version';

	/**
	 * TTL for cached template, widget, and route detection results.
	 *
	 * @var int
	 */
	private const CACHE_TTL = DAY_IN_SECONDS;

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
	 * Memoized detect() result for the current request.
	 *
	 * @var bool|null
	 */
	private static ?bool $detect_result = null;

	/**
	 * Whether invalidation hooks have been registered.
	 *
	 * @var bool
	 */
	private static bool $hooks_registered = false;

	/**
	 * Whether WooCommerce frontend assets should load on this request.
	 *
	 * @return bool
	 */
	public static function request_needs_assets(): bool {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return false;
		}

		self::register_invalidation_hooks();

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
		if ( null !== self::$detect_result ) {
			return self::$detect_result;
		}

		if ( self::is_wc_route() ) {
			self::$detect_result = true;
			return self::$detect_result;
		}

		if ( Product_Context::is_product_display_page() ) {
			self::$detect_result = true;
			return self::$detect_result;
		}

		if ( self::queried_content_has_woocommerce_blocks() ) {
			self::$detect_result = true;
			return self::$detect_result;
		}

		if ( self::resolved_template_has_woocommerce_blocks() ) {
			self::$detect_result = true;
			return self::$detect_result;
		}

		if ( self::theme_template_parts_have_woocommerce_blocks() ) {
			self::$detect_result = true;
			return self::$detect_result;
		}

		if ( self::widget_areas_have_woocommerce_blocks() ) {
			self::$detect_result = true;
			return self::$detect_result;
		}

		if ( self::index_template_has_woocommerce_blocks() ) {
			self::$detect_result = true;
			return self::$detect_result;
		}

		self::$detect_result = false;

		return self::$detect_result;
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
		$cache_key = sprintf(
			'aa_wc_detector_parts_%d_%s',
			self::get_cache_version(),
			md5( get_stylesheet() . '|' . self::get_theme_parts_fingerprint() )
		);

		$cached = Cache_Helper::remember(
			$cache_key,
			self::CACHE_TTL,
			static function (): int {
				foreach ( self::get_theme_template_part_slugs() as $slug ) {
					if ( self::block_template_has_woocommerce_blocks( $slug, 'wp_template_part' ) ) {
						return 1;
					}
				}

				return 0;
			},
			static function ( $value ): bool {
				return is_int( $value );
			}
		);

		return 1 === (int) $cached;
	}

	/**
	 * Whether block widgets in active sidebars contain WooCommerce blocks.
	 *
	 * @return bool
	 */
	private static function widget_areas_have_woocommerce_blocks(): bool {
		$cache_key = sprintf(
			'aa_wc_detector_widgets_%d_%s',
			self::get_cache_version(),
			self::get_widgets_fingerprint()
		);

		$cached = Cache_Helper::remember(
			$cache_key,
			self::CACHE_TTL,
			static function (): int {
				$sidebars_widgets = wp_get_sidebars_widgets();

				if ( ! is_array( $sidebars_widgets ) ) {
					return 0;
				}

				$block_widgets = get_option( 'widget_block', array() );

				if ( ! is_array( $block_widgets ) ) {
					return 0;
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
							return 1;
						}
					}
				}

				return 0;
			},
			static function ( $value ): bool {
				return is_int( $value );
			}
		);

		return 1 === (int) $cached;
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
		$cache_key = sprintf(
			'aa_wc_btpl_%d_%s_%s_%s',
			self::get_cache_version(),
			sanitize_key( $type ),
			sanitize_key( $slug ),
			self::get_block_template_source_fingerprint( $slug, $type )
		);

		$cached = Cache_Helper::remember(
			$cache_key,
			self::CACHE_TTL,
			static function () use ( $slug, $type ): int {
				return self::scan_block_template_for_woocommerce_blocks( $slug, $type ) ? 1 : 0;
			},
			static function ( $value ): bool {
				return is_int( $value );
			}
		);

		return 1 === (int) $cached;
	}

	/**
	 * Scan a theme or customized template for WooCommerce blocks.
	 *
	 * @param string $slug Template slug without extension.
	 * @param string $type `wp_template` or `wp_template_part`.
	 * @return bool
	 */
	private static function scan_block_template_for_woocommerce_blocks( string $slug, string $type ): bool {
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

		$file_path    = get_theme_file_path( $relative_path );
		$file_content = aggressive_apparel_read_theme_file( $file_path );

		return false !== $file_content && self::content_has_woocommerce_blocks( $file_content );
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

	/**
	 * Bump the detector cache version so persisted scan results are invalidated.
	 *
	 * @return void
	 */
	public static function bump_cache_version(): void {
		$version = self::get_cache_version();
		update_option( self::CACHE_VERSION_OPTION, $version + 1, false );
		self::reset_runtime_state();
	}

	/**
	 * Clear per-request memoization without touching persisted transients.
	 *
	 * @return void
	 */
	public static function reset_runtime_state(): void {
		self::$detect_result      = null;
		self::$content_scan_cache = array();
		self::$resolving_refs     = array();
	}

	/**
	 * Reset all detector caches. Intended for PHPUnit only.
	 *
	 * @return void
	 */
	public static function reset_caches_for_tests(): void {
		self::reset_runtime_state();
		self::$hooks_registered = false;
		self::bump_cache_version();
	}

	/**
	 * Register hooks that invalidate cached template and widget scans.
	 *
	 * @return void
	 */
	private static function register_invalidation_hooks(): void {
		if ( self::$hooks_registered ) {
			return;
		}

		self::$hooks_registered = true;

		add_action( 'save_post_wp_template', array( self::class, 'handle_template_save' ) );
		add_action( 'save_post_wp_template_part', array( self::class, 'handle_template_save' ) );
		add_action( 'switch_theme', array( self::class, 'bump_cache_version' ) );
		add_action( 'updated_option', array( self::class, 'handle_option_update' ), 10, 3 );
	}

	/**
	 * Invalidate cached scans when a block template is saved.
	 *
	 * @param int $post_id Saved post ID.
	 * @return void
	 */
	public static function handle_template_save( int $post_id ): void {
		unset( $post_id );
		self::bump_cache_version();
	}

	/**
	 * Invalidate cached widget scans when sidebar widgets change.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Previous option value.
	 * @param mixed  $value     New option value.
	 * @return void
	 */
	public static function handle_option_update( string $option, $old_value, $value ): void {
		unset( $old_value, $value );

		if ( in_array( $option, array( 'widget_block', 'sidebars_widgets' ), true ) ) {
			self::bump_cache_version();
		}
	}

	/**
	 * Return the persisted detector cache version.
	 *
	 * @return int
	 */
	private static function get_cache_version(): int {
		return (int) get_option( self::CACHE_VERSION_OPTION, 1 );
	}

	/**
	 * Build a fingerprint for all theme template part files.
	 *
	 * @return string
	 */
	private static function get_theme_parts_fingerprint(): string {
		$parts = array();

		foreach ( self::get_theme_template_part_slugs() as $slug ) {
			$file_path = get_theme_file_path( 'parts/' . $slug . '.html' );

			if ( ! is_readable( $file_path ) ) {
				continue;
			}

			$parts[] = $slug . ':' . (string) filemtime( $file_path ) . ':' . (string) filesize( $file_path );
		}

		sort( $parts );

		return md5( implode( '|', $parts ) );
	}

	/**
	 * Build a fingerprint for block widget sidebars.
	 *
	 * @return string
	 */
	private static function get_widgets_fingerprint(): string {
		$sidebars_widgets = wp_get_sidebars_widgets();
		$block_widgets    = get_option( 'widget_block', array() );

		$encoded_widgets = wp_json_encode(
			array(
				'sidebars' => $sidebars_widgets,
				'widgets'  => is_array( $block_widgets ) ? $block_widgets : array(),
			)
		);

		if ( false === $encoded_widgets ) {
			return md5( '' );
		}

		return md5( $encoded_widgets );
	}

	/**
	 * Build a fingerprint for a template source without scanning its blocks.
	 *
	 * @param string $slug Template slug without extension.
	 * @param string $type `wp_template` or `wp_template_part`.
	 * @return string
	 */
	private static function get_block_template_source_fingerprint( string $slug, string $type ): string {
		if ( function_exists( 'get_block_template' ) ) {
			$template_id = get_stylesheet() . '//' . $slug;
			$template    = 'wp_template_part' === $type
				? get_block_template( $template_id, 'wp_template_part' )
				: get_block_template( $template_id, 'wp_template' );

			if ( $template && ! empty( $template->content ) ) {
				$modified = isset( $template->modified ) ? (string) $template->modified : '';

				return md5( $modified . '|' . $template->content );
			}
		}

		$relative_path = 'wp_template_part' === $type
			? 'parts/' . $slug . '.html'
			: 'templates/' . $slug . '.html';
		$file_path     = get_theme_file_path( $relative_path );

		if ( ! is_readable( $file_path ) ) {
			return 'missing';
		}

		return md5( (string) filemtime( $file_path ) . '|' . (string) filesize( $file_path ) );
	}
}
