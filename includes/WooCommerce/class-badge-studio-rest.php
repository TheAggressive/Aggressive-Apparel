<?php
/**
 * Badge studio REST — compile preview from style JSON.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST routes for the React badge studio.
 */
class Badge_Studio_Rest {

	private const NAMESPACE = 'aggressive-apparel/v1';
	private const ROUTE     = '/badge-studio/compile';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'compile' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	/**
	 * Taxonomy managers only.
	 *
	 * @return bool
	 */
	public function can_manage(): bool {
		return current_user_can( 'manage_categories' );
	}

	/**
	 * Compile badge HTML + classes from a flat field map or badge data array.
	 *
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function compile( \WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		$fields = isset( $payload['fields'] ) && is_array( $payload['fields'] )
			? $payload['fields']
			: $payload;

		$label = isset( $payload['label'] ) ? (string) $payload['label'] : '';

		return rest_ensure_response( self::compile_payload( $fields, $label ) );
	}

	/**
	 * Compile the studio preview payload from a flat `badge_*` field map.
	 *
	 * The single render path for the badge studio: the taxonomy screen seeds
	 * the first paint with this (so the preview is correct before any request
	 * lands), and every subsequent edit re-enters here over REST. The client
	 * has no compiler of its own, so the preview cannot drift from the
	 * storefront.
	 *
	 * @param array<string, mixed> $fields Flat `badge_*` field map.
	 * @param string               $label  Badge label text.
	 * @return array{html: string, classes: string[], style: string, position: string}
	 */
	public static function compile_payload( array $fields, string $label = '' ): array {
		$badge = self::fields_to_badge_data( $fields );
		$label = sanitize_text_field( $label );
		if ( '' === $label ) {
			$label = __( 'Badge', 'aggressive-apparel' );
		}

		$icon_position = Badge_Style_Schema::sanitize_enum(
			$badge['icon_position'] ?? 'start',
			Badge_Style_Schema::ICON_POSITIONS,
			'start'
		);
		$icon_html     = Custom_Badge_Taxonomy::build_badge_icon_html(
			(string) $badge['svg_icon'],
			(string) $badge['library_icon'],
			(string) $badge['icon'],
			(string) $badge['icon_color'],
			(int) $badge['icon_size']
		);

		$aria_label = '';
		if ( 'only' === $icon_position && '' !== $icon_html ) {
			$inner      = $icon_html;
			$aria_label = $label;
		} elseif ( 'only' === $icon_position ) {
			// No icon configured — fall back to the label so the badge is never blank.
			$inner = esc_html( $label );
		} elseif ( 'end' === $icon_position ) {
			$inner = esc_html( $label ) . $icon_html;
		} else {
			$inner = $icon_html . esc_html( $label );
		}

		return array(
			'html'     => Badge_Style_Schema::compile_badge_span( $badge, $inner, 'admin', 'aa-badge-preview-el', $aria_label ),
			'classes'  => Badge_Style_Schema::emit_class_names( $badge ),
			'style'    => Badge_Style_Schema::emit_style_attribute( $badge, 'admin' ),
			// Enum-checked: the client interpolates this into a slot class name.
			'position' => Badge_Style_Schema::sanitize_enum(
				$badge['position'] ?? 'top-left',
				Badge_Style_Schema::POSITIONS,
				'top-left'
			),
		);
	}

	/**
	 * Map `badge_*` POST-style fields into schema badge data.
	 *
	 * @param array<string, mixed> $fields Field map.
	 * @return array<string, mixed>
	 */
	public static function fields_to_badge_data( array $fields ): array {
		$get = static function ( string $key, $fallback = '' ) use ( $fields ) {
			return array_key_exists( $key, $fields ) ? $fields[ $key ] : $fallback;
		};

		$glass_raw = $get( 'badge_glass', '0' );
		$glass     = in_array( (string) $glass_raw, array( '1', 'on', 'true' ), true ) ? 1 : 0;

		return Badge_Style_Schema::with_defaults(
			array(
				'bg_color'           => (string) $get( 'badge_bg_color', '#000000' ),
				'text_color'         => (string) $get( 'badge_text_color', '#ffffff' ),
				'icon'               => (string) $get( 'badge_icon', '' ),
				'library_icon'       => (string) $get( 'badge_library_icon', '' ),
				'svg_icon'           => (string) $get( 'badge_svg_icon', '' ),
				'icon_color'         => (string) $get( 'badge_icon_color', '' ),
				'icon_size'          => (int) $get( 'badge_icon_size', 0 ),
				'icon_gap'           => (int) $get( 'badge_icon_gap', 0 ),
				'priority'           => (int) $get( 'badge_priority', 10 ),
				'border_color'       => (string) $get( 'badge_border_color', '' ),
				'border_width'       => (int) $get( 'badge_border_width', 0 ),
				'border_style'       => (string) $get( 'badge_border_style', 'none' ),
				'radius_tl'          => (int) $get( 'badge_radius_tl', 4 ),
				'radius_tr'          => (int) $get( 'badge_radius_tr', 4 ),
				'radius_br'          => (int) $get( 'badge_radius_br', 4 ),
				'radius_bl'          => (int) $get( 'badge_radius_bl', 4 ),
				'padding_x'          => (int) $get( 'badge_padding_x', 8 ),
				'padding_y'          => (int) $get( 'badge_padding_y', 3 ),
				'position'           => (string) $get( 'badge_position', 'top-left' ),
				'badge_type'         => (string) $get( 'badge_type', 'custom' ),
				'border_mode'        => (string) $get( 'badge_border_mode', 'none' ),
				'inner_border_color' => (string) $get( 'badge_inner_border_color', '' ),
				'inner_border_width' => (int) $get( 'badge_inner_border_width', 0 ),
				'border_gap'         => (int) $get( 'badge_border_gap', 0 ),
				'font_size'          => (string) $get( 'badge_font_size', 'x-small' ),
				'font_size_px'       => (int) $get( 'badge_font_size_px', 0 ),
				'font_weight'        => (int) $get( 'badge_font_weight', 700 ),
				'text_transform'     => (string) $get( 'badge_text_transform', 'uppercase' ),
				'letter_spacing'     => (string) $get( 'badge_letter_spacing', 'wide' ),
				'line_height'        => (string) $get( 'badge_line_height', 'snug' ),
				'icon_position'      => (string) $get( 'badge_icon_position', 'start' ),
				'offset_x'           => (int) $get( 'badge_offset_x', 0 ),
				'offset_y'           => (int) $get( 'badge_offset_y', 0 ),
				'rotation'           => (int) $get( 'badge_rotation', 0 ),
				'shadow_blur'        => (int) $get( 'badge_shadow_blur', 0 ),
				'shadow_spread'      => (int) $get( 'badge_shadow_spread', 0 ),
				'shadow_color'       => (string) $get( 'badge_shadow_color', '' ),
				'glass'              => $glass,
				'fill_mode'          => (string) $get( 'badge_fill_mode', 'solid' ),
				'gradient_angle'     => (int) $get( 'badge_gradient_angle', 135 ),
				'gradient_from'      => (string) $get( 'badge_gradient_from', '#000000' ),
				'gradient_to'        => (string) $get( 'badge_gradient_to', '#444444' ),
				'shape'              => (string) $get( 'badge_shape', 'rect' ),
				'shape_mode'         => (string) $get( 'badge_shape_mode', 'mask' ),
				'shape_svg'          => (string) $get( 'badge_shape_svg', '' ),
				'frame_color'        => (string) $get( 'badge_frame_color', '' ),
				'frame_width'        => (int) $get( 'badge_frame_width', 2 ),
			)
		);
	}
}
