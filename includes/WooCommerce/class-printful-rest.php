<?php
/**
 * Printful REST
 *
 * The routes behind "Import from Printful" on Products → Size & Specs:
 * search the catalog, and load one product as a size guide draft to review.
 * Creating the guide is the ordinary size guide create route, which hands
 * the import's extras to Printful_Import::created().
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
 * Printful import routes.
 */
final class Printful_Rest {

	/**
	 * Route base, under the size guide routes.
	 *
	 * @var string
	 */
	public const ROUTE = '/size-guides/printful';

	/**
	 * Register hooks.
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		$manage = static fn(): bool => current_user_can( 'manage_woocommerce' );

		register_rest_route(
			Size_Guide_Rest::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search' ),
				'permission_callback' => $manage,
				'args'                => array(
					'search' => array(
						'type'    => 'string',
						'default' => '',
					),
					'group'  => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);

		register_rest_route(
			Size_Guide_Rest::NAMESPACE,
			self::ROUTE . '/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'draft' ),
				'permission_callback' => $manage,
			)
		);
	}

	/**
	 * Catalog products matching a search, each with the guide already made
	 * from it (if any).
	 *
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function search( \WP_REST_Request $request ) {
		$group = sanitize_text_field( (string) $request->get_param( 'group' ) );
		$found = Printful_Catalog::search(
			sanitize_text_field( (string) $request->get_param( 'search' ) ),
			in_array( $group, Printful_Catalog::GROUPS, true ) ? $group : ''
		);
		if ( is_wp_error( $found ) ) {
			return $found;
		}

		$imported = Printful_Import::imported( 'printful' );

		return rest_ensure_response(
			array(
				'groups'   => Printful_Catalog::GROUPS,
				'products' => array_map(
					static fn( array $product ): array => $product + array( 'guide' => $imported[ $product['id'] ] ?? 0 ),
					$found
				),
			)
		);
	}

	/**
	 * One product as a size guide draft.
	 *
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function draft( \WP_REST_Request $request ) {
		$draft = Printful_Import::draft( (int) $request['id'] );

		return is_wp_error( $draft ) ? $draft : rest_ensure_response( $draft );
	}
}
