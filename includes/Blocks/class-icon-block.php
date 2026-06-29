<?php
/**
 * REST API and editor helpers for the Aggressive Icon block.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Blocks;

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
	 * Return sorted icon slugs for the block editor combobox.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_icon_list(): WP_REST_Response {
		$icons = Icons::list();
		sort( $icons );

		return new WP_REST_Response(
			array(
				'icons' => $icons,
			),
			200
		);
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
		$size = self::sanitize_size( $request->get_param( 'size' ) );

		if ( '' === $slug || ! Icons::exists( $slug ) ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Icon not found.', 'aggressive-apparel' ),
				),
				404
			);
		}

		$svg = Icons::get(
			$slug,
			array(
				'width'       => $size,
				'height'      => $size,
				'class'       => 'aggressive-apparel-icon__svg',
				'fill'        => 'currentColor',
				'aria-hidden' => 'true',
			)
		);

		if ( '' === $svg ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Icon could not be rendered.', 'aggressive-apparel' ),
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
