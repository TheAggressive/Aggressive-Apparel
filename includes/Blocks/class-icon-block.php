<?php
/**
 * REST API and editor helpers for the Aggressive Icon block.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Blocks;

use Aggressive_Apparel\Core\Cache_Helper;
use Aggressive_Apparel\Core\Icons;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Icon block REST routes for the block editor picker and preview.
 */
class Icon_Block {

	/**
	 * REST namespace.
	 */
	private const REST_NAMESPACE = 'aggressive-apparel/v1';

	/**
	 * Minimum icon size in pixels.
	 */
	private const MIN_SIZE = 16;

	/**
	 * Maximum icon size in pixels.
	 */
	private const MAX_SIZE = 128;

	/**
	 * Size (px) of the thumbnails shown beside each option in the editor picker.
	 */
	private const PREVIEW_SIZE = 24;

	/**
	 * Transient TTL for the editor icon list payload.
	 */
	private const LIST_CACHE_TTL = DAY_IN_SECONDS;

	/**
	 * Hook REST routes.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Register icon library REST routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/icons',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_icon_list' ),
				'permission_callback' => array( self::class, 'can_edit_content' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/icons/(?P<slug>[a-z0-9-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_icon_preview' ),
				'permission_callback' => array( self::class, 'can_edit_content' ),
				'args'                => array(
					'slug' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
					'size' => array(
						'default'           => 48,
						'sanitize_callback' => array( self::class, 'sanitize_size' ),
					),
				),
			)
		);
	}

	/**
	 * Whether the current user may load editor icon data.
	 */
	public static function can_edit_content(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Clamp preview size query param.
	 *
	 * @param mixed $value Raw size value.
	 * @return int
	 */
	public static function sanitize_size( mixed $value ): int {
		return max( self::MIN_SIZE, min( self::MAX_SIZE, absint( $value ) ) );
	}

	/**
	 * Build the icon SVG markup shared by the editor preview and the front-end
	 * render, so the two can never drift apart. Size is clamped to the block's
	 * allowed range.
	 *
	 * @param string $slug Icon slug.
	 * @param mixed  $size Icon size in pixels (clamped).
	 * @return string SVG markup, or empty string if the icon does not exist.
	 */
	public static function render_svg( string $slug, mixed $size ): string {
		$slug = sanitize_key( $slug );

		if ( '' === $slug || ! Icons::exists( $slug ) ) {
			return '';
		}

		$size = self::sanitize_size( $size );

		return Icons::get(
			$slug,
			array(
				'width'       => $size,
				'height'      => $size,
				'class'       => 'aggressive-apparel-icon__svg',
				'fill'        => 'currentColor',
				'aria-hidden' => 'true',
			)
		);
	}

	/**
	 * Render icon SVG inside a sized wrapper span.
	 *
	 * Shared by blocks that embed icons beside text (ticker label, free-shipping, etc.).
	 *
	 * @param string               $slug Icon slug.
	 * @param mixed                $size Icon size in pixels (clamped).
	 * @param array<string, mixed> $args {
	 *     Optional wrapper arguments.
	 *
	 *     @type string $class       Additional wrapper classes.
	 *     @type bool   $aria_hidden Whether to add aria-hidden. Default true.
	 * }
	 * @return string Wrapper HTML, or empty string if the icon does not exist.
	 */
	public static function render_wrapped_svg( string $slug, mixed $size, array $args = array() ): string {
		$svg = self::render_svg( $slug, $size );

		if ( '' === $svg ) {
			return '';
		}

		$args = wp_parse_args(
			$args,
			array(
				'class'       => '',
				'aria_hidden' => true,
			)
		);

		$size    = self::sanitize_size( $size );
		$classes = trim( 'aggressive-apparel-icon__svg-wrap ' . (string) $args['class'] );
		$style   = sprintf( 'width:%1$dpx;height:%1$dpx;', $size );

		$attributes = sprintf(
			'class="%1$s" style="%2$s"',
			esc_attr( $classes ),
			esc_attr( $style )
		);

		if ( ! empty( $args['aria_hidden'] ) ) {
			$attributes .= ' aria-hidden="true"';
		}

		return sprintf(
			'<span %1$s>%2$s</span>',
			$attributes,
			$svg
		);
	}

	/**
	 * Return sorted icons (slug + thumbnail SVG) for the block editor combobox.
	 *
	 * The full library render is expensive (every brand definition is loaded),
	 * so the payload is stored in a theme-versioned transient (see LIST_CACHE_TTL).
	 *
	 * @return WP_REST_Response
	 */
	public static function get_icon_list(): WP_REST_Response {
		$icons = Cache_Helper::remember(
			self::list_cache_key(),
			self::LIST_CACHE_TTL,
			static function (): array {
				$slugs = Icons::list();
				sort( $slugs );

				return array_map(
					static function ( string $slug ): array {
						return array(
							'slug' => $slug,
							'svg'  => self::render_svg( $slug, self::PREVIEW_SIZE ),
						);
					},
					$slugs
				);
			},
			static function ( $cached ): bool {
				return is_array( $cached );
			}
		);

		return new WP_REST_Response(
			array(
				'icons' => $icons,
			),
			200
		);
	}

	/**
	 * Transient key for the editor icon list (busts on theme version change).
	 */
	private static function list_cache_key(): string {
		$version = defined( 'AGGRESSIVE_APPAREL_VERSION' )
			? (string) AGGRESSIVE_APPAREL_VERSION
			: '0';

		return 'aa_icon_list_' . md5( $version );
	}

	/**
	 * Drop the cached editor icon list (tests / manual invalidation).
	 *
	 * @internal
	 */
	public static function flush_list_cache_for_tests(): void {
		delete_transient( self::list_cache_key() );
	}

	/**
	 * Return sanitized SVG markup for editor canvas preview.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public static function get_icon_preview( WP_REST_Request $request ): WP_REST_Response {
		$slug = sanitize_key( (string) $request->get_param( 'slug' ) );
		$svg  = self::render_svg( $slug, $request->get_param( 'size' ) );

		if ( '' === $svg ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Icon not found.', 'aggressive-apparel' ),
				),
				404
			);
		}

		return new WP_REST_Response(
			array(
				'slug' => $slug,
				'svg'  => $svg,
			),
			200
		);
	}
}
