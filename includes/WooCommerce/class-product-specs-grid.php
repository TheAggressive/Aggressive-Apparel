<?php
/**
 * Product Specs Grid
 *
 * REST routes behind the Products tab on Products → Size Guides: a searchable,
 * paginated product list with each spec's own value and what the product
 * would show without it, and a bulk save. Every write goes through
 * Product_Specs_Product_Writer, as product attributes, the same path as the
 * product's own Size & specs tab.
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
 * Products grid REST routes.
 *
 * @phpstan-type Cell array{applies: bool, own: string, shown: string, inherited: string, hidden: bool}
 * @phpstan-type Row array{id: int, title: string, editUrl: string, thumb: string, categories: list<string>, guide: int, specs: array<string, Cell>}
 */
final class Product_Specs_Grid {

	/**
	 * Most products per page.
	 *
	 * @var int
	 */
	private const MAX_PER_PAGE = 50;

	/**
	 * Most products saved per request.
	 *
	 * @var int
	 */
	private const MAX_SAVE = 200;

	/**
	 * Register hooks.
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the products routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			'aggressive-apparel/v1',
			'/product-specs/products',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_products' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 25,
							'minimum' => 1,
							'maximum' => self::MAX_PER_PAGE,
						),
						'search'   => array(
							'type'    => 'string',
							'default' => '',
						),
						'category' => array(
							'type'    => 'integer',
							'default' => 0,
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_products' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	/**
	 * Store managers only.
	 */
	public function can_manage(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * One page of products with their spec values.
	 *
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @param \WP_REST_Request $request Request.
	 */
	public function list_products( \WP_REST_Request $request ): \WP_REST_Response {
		$args = array(
			'post_type'      => 'product',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => min( self::MAX_PER_PAGE, max( 1, (int) $request->get_param( 'per_page' ) ) ),
			'paged'          => max( 1, (int) $request->get_param( 'page' ) ),
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$category = absint( $request->get_param( 'category' ) );
		if ( $category > 0 ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- admin-only, paginated.
			$args['tax_query'] = array(
				array(
					'taxonomy'         => 'product_cat',
					'terms'            => array( $category ),
					'include_children' => true,
				),
			);
		}

		$query = new \WP_Query( $args );
		$rows  = array();
		foreach ( $query->posts as $post ) {
			if ( $post instanceof \WP_Post ) {
				$rows[] = self::row( $post );
			}
		}

		return rest_ensure_response(
			array(
				'total'    => (int) $query->found_posts,
				'pages'    => (int) $query->max_num_pages,
				'products' => $rows,
			)
		);
	}

	/**
	 * Save edited values. Body: { products: { <id>: { <item key>: <value> } } }.
	 *
	 * Products the user cannot edit are skipped and counted.
	 *
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @param \WP_REST_Request $request Request.
	 */
	public function save_products( \WP_REST_Request $request ): \WP_REST_Response {
		$body     = $request->get_json_params();
		$products = is_array( $body ) && isset( $body['products'] ) && is_array( $body['products'] ) ? $body['products'] : array();

		$saved   = 0;
		$skipped = 0;
		$failed  = 0;
		foreach ( array_slice( $products, 0, self::MAX_SAVE, true ) as $product_id => $values ) {
			$product_id = absint( $product_id );
			$product    = 'product' === get_post_type( $product_id ) ? wc_get_product( $product_id ) : null;
			if ( ! is_array( $values ) || ! $product instanceof \WC_Product || ! current_user_can( 'edit_post', $product_id ) ) {
				++$skipped;
				continue;
			}
			// Only specs that apply to this product, as the grid shows them.
			$values = array_intersect_key( $values, array_flip( array_column( Product_Specs::for_product( $product_id )['items'], 'key' ) ) );
			// Values that couldn't be stored are counted, so the grid never
			// reports them as saved.
			$failed += count( Product_Specs_Product_Writer::save( $product, $values )['failed'] );
			++$saved;
		}

		return rest_ensure_response(
			array(
				'saved'   => $saved,
				'skipped' => $skipped,
				'failed'  => $failed,
			)
		);
	}

	/**
	 * One product row: each enabled spec's own value, what shows, and what
	 * would show without the product's own value.
	 *
	 * @param \WP_Post $post Product.
	 * @return Row
	 */
	private static function row( \WP_Post $post ): array {
		$resolved = array();
		foreach ( Product_Specs::for_product( $post->ID )['items'] as $item ) {
			$resolved[ $item['key'] ] = $item;
		}

		$specs = array();
		foreach ( Product_Specs_Registry::items() as $item ) {
			$found = $resolved[ $item['key'] ] ?? null;
			if ( null === $found ) {
				$specs[ $item['key'] ] = array(
					'applies'   => false,
					'own'       => '',
					'shown'     => '',
					'inherited' => '',
					'hidden'    => false,
				);
				continue;
			}

			$specs[ $item['key'] ] = array(
				'applies'   => true,
				'own'       => $found['own'],
				'shown'     => Product_Specs_Admin_Fields::describe( $found, $found ),
				'inherited' => Product_Specs_Admin_Fields::describe( $found, $found['inherited'] ),
				'hidden'    => $found['hidden'],
			);
		}

		$terms = get_the_terms( $post->ID, 'product_cat' );

		return array(
			'id'         => $post->ID,
			'title'      => '' !== $post->post_title ? $post->post_title : __( '(no title)', 'aggressive-apparel' ),
			'editUrl'    => (string) get_edit_post_link( $post->ID, 'raw' ),
			'thumb'      => (string) get_the_post_thumbnail_url( $post->ID, 'thumbnail' ),
			'categories' => is_array( $terms ) ? array_values( array_map( static fn( \WP_Term $term ): string => $term->name, $terms ) ) : array(),
			// For showing a guide's own spec columns only where they apply.
			'guide'      => Size_Guide::resolved( $post->ID )['id'],
			'specs'      => $specs,
		);
	}
}
