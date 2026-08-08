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
		$badge = array();

		foreach ( Badge_Field_Registry::fields() as $key => $spec ) {
			$field = (string) $spec['field'];

			// An absent field means "unspecified", which must fall through to the
			// registry default rather than be coerced from an empty string —
			// absint('') is 0, which would silently zero every unsent number.
			if ( ! array_key_exists( $field, $fields ) ) {
				$badge[ $key ] = $spec['default'];
				continue;
			}

			$badge[ $key ] = Badge_Field_Registry::sanitize( $key, $fields[ $field ] );
		}

		return $badge;
	}
}
