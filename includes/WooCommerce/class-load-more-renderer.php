<?php
/**
 * Load More Renderer
 *
 * REST endpoint that renders product cards through the full WordPress block
 * pipeline. Every render_block filter (hover image, quick-view, badges, etc.)
 * runs automatically, so infinite-scroll cards are byte-for-byte identical to
 * the initial server-rendered output regardless of block editor changes.
 *
 * @package Aggressive_Apparel
 * @since 1.65.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load More Renderer
 *
 * @since 1.65.0
 */
class Load_More_Renderer {

	/** REST namespace / route. */
	private const NAMESPACE = 'aggressive-apparel/v1';
	private const ROUTE     = '/products/rendered';

	/** Template slugs that contain a woocommerce/product-template block. */
	private const PRODUCT_TEMPLATES = array(
		'archive-product',
		'taxonomy-product_cat',
		'taxonomy-product_tag',
	);

	/**
	 * Parsed inner-block cache keyed by template slug (per-request).
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	private array $blocks_cache = array();

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	/**
	 * Register the REST route.
	 *
	 * @return void
	 */
	public function register_route(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'page'     => array(
						'default'           => 1,
						'type'              => 'integer',
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'default'           => 12,
						'type'              => 'integer',
						'minimum'           => 1,
						'maximum'           => 100,
						'sanitize_callback' => 'absint',
					),
					'orderby'  => array(
						'default'           => 'date',
						'type'              => 'string',
						'enum'              => array( 'date', 'popularity', 'rating', 'price', 'price-desc', 'title-asc', 'title-desc' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
					'category' => array(
						'default'           => '',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'template' => array(
						'default'           => 'archive-product',
						'type'              => 'string',
						'enum'              => self::PRODUCT_TEMPLATES,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Handle the REST request.
	 *
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		$page          = max( 1, (int) $request->get_param( 'page' ) );
		$per_page      = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$orderby       = (string) $request->get_param( 'orderby' );
		$category      = (string) $request->get_param( 'category' );
		$template_slug = (string) $request->get_param( 'template' );

		if ( ! in_array( $template_slug, self::PRODUCT_TEMPLATES, true ) ) {
			$template_slug = 'archive-product';
		}

		$inner_blocks = $this->get_template_inner_blocks( $template_slug );
		if ( empty( $inner_blocks ) ) {
			return new \WP_REST_Response( array( 'error' => 'Template not found' ), 404 );
		}

		$query = new \WP_Query( $this->build_query_args( $page, $per_page, $orderby, $category ) );

		if ( ! $query->have_posts() ) {
			return new \WP_REST_Response(
				array(
					'html'           => '',
					'total_products' => 0,
					'total_pages'    => 0,
				)
			);
		}

		// Signal to render_block guards that we are rendering product cards so
		// each class's is_listing_page() (and equivalents) returns true.
		add_filter( 'aggressive_apparel_is_listing_page', '__return_true' );

		$html = '';

		while ( $query->have_posts() ) {
			$query->the_post();
			$product_id = get_the_ID();

			if ( ! $product_id ) {
				continue;
			}

			$context = array(
				'postType' => 'product',
				'postId'   => $product_id,
			);

			$card_html = '';
			foreach ( $inner_blocks as $parsed_block ) {
				if ( empty( $parsed_block['blockName'] ) ) {
					continue;
				}
				$block_obj  = new \WP_Block( $parsed_block, $context );
				$card_html .= $block_obj->render();
			}

			$post_classes   = implode( ' ', get_post_class( 'wc-block-product' ) );
			$encoded        = wp_json_encode(
				array( 'productId' => $product_id ),
				JSON_NUMERIC_CHECK | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
			);
			$wp_context_str = false !== $encoded ? $encoded : '{"productId":' . $product_id . '}';

			$html .= sprintf(
				'<li class="%s" data-wp-interactive="woocommerce/product-collection" data-wp-context=\'%s\' data-wp-key="product-item-%d">%s</li>',
				esc_attr( $post_classes ),
				$wp_context_str,
				$product_id,
				$card_html
			);
		}

		wp_reset_postdata();
		remove_filter( 'aggressive_apparel_is_listing_page', '__return_true' );

		return new \WP_REST_Response(
			array(
				'html'           => $html,
				'total_products' => (int) $query->found_posts,
				'total_pages'    => (int) $query->max_num_pages,
			)
		);
	}

	/**
	 * Get the parsed innerBlocks of woocommerce/product-template from a template.
	 *
	 * Prefers a DB-customised template (Site Editor changes) over the theme file.
	 *
	 * @param string $template_slug Template slug.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_template_inner_blocks( string $template_slug ): array {
		if ( isset( $this->blocks_cache[ $template_slug ] ) ) {
			return $this->blocks_cache[ $template_slug ];
		}

		// Try DB template first (respects Site Editor customisations).
		$templates = get_block_templates( array( 'slug__in' => array( $template_slug ) ), 'wp_template' );

		if ( ! empty( $templates ) ) {
			$content = $templates[0]->content;
		} else {
			$file = AGGRESSIVE_APPAREL_DIR . '/templates/' . $template_slug . '.html';
			if ( ! file_exists( $file ) ) {
				return array();
			}
			global $wp_filesystem;
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			WP_Filesystem();
			if ( ! $wp_filesystem ) {
				return array();
			}
			$content = $wp_filesystem->get_contents( $file );
			if ( false === $content ) {
				return array();
			}
		}

		$blocks = parse_blocks( $content );
		$inner  = $this->find_product_template_inner_blocks( $blocks );

		$this->blocks_cache[ $template_slug ] = $inner;
		return $inner;
	}

	/**
	 * Recursively find woocommerce/product-template and return its innerBlocks.
	 *
	 * @param array<int|string, array<string, mixed>> $blocks Parsed blocks.
	 * @return array<int, array<string, mixed>>
	 */
	private function find_product_template_inner_blocks( array $blocks ): array {
		foreach ( $blocks as $block ) {
			if ( 'woocommerce/product-template' === ( $block['blockName'] ?? '' ) ) {
				return $block['innerBlocks'] ?? array();
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$result = $this->find_product_template_inner_blocks( $block['innerBlocks'] );
				if ( ! empty( $result ) ) {
					return $result;
				}
			}
		}
		return array();
	}

	/**
	 * Build WP_Query args for the given parameters.
	 *
	 * Maps WooCommerce catalogue sort values to WP_Query orderby args.
	 *
	 * @param int    $page     Page number.
	 * @param int    $per_page Posts per page.
	 * @param string $orderby  WooCommerce sort value.
	 * @param string $category Product category slug.
	 * @return array<string, mixed>
	 */
	private function build_query_args( int $page, int $per_page, string $orderby, string $category ): array {
		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'post_status'    => 'publish',
		);

		switch ( $orderby ) {
			case 'popularity':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = 'total_sales'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$args['order']    = 'DESC';
				break;

			case 'rating':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = '_wc_average_rating'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$args['order']    = 'DESC';
				break;

			case 'price':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = '_price'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$args['order']    = 'ASC';
				break;

			case 'price-desc':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = '_price'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$args['order']    = 'DESC';
				break;

			case 'title-asc':
				$args['orderby'] = 'title';
				$args['order']   = 'ASC';
				break;

			case 'title-desc':
				$args['orderby'] = 'title';
				$args['order']   = 'DESC';
				break;

			default:
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
		}

		if ( $category ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => $category,
				),
			);
		}

		return $args;
	}
}
