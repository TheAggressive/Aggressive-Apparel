<?php
/**
 * Product Specs Settings
 *
 * Products → Size Guides: the one screen for size guides (chart, notes,
 * specs, where each applies), spec definitions, and per-product values. It
 * hosts the React app in src/scripts/admin/product-specs/ and owns the REST
 * route for spec definitions; guides and products save through
 * Size_Guide_Rest and Product_Specs_Grid.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Assets\Asset_Loader;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Size guides & specs admin page and spec definitions route.
 */
final class Product_Specs_Settings {

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'aa-product-specs';

	/**
	 * Tabs, first is the default.
	 *
	 * @var list<string>
	 */
	public const TABS = array( 'guides', 'specs', 'products' );

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private const REST_NAMESPACE = 'aggressive-apparel/v1';

	/**
	 * Spec definitions route.
	 *
	 * @var string
	 */
	private const REST_ROUTE = '/product-specs';

	/**
	 * Script handle.
	 *
	 * @var string
	 */
	private const HANDLE = 'aggressive-apparel-product-specs-settings';

	/**
	 * Capability to manage size guides and specs.
	 *
	 * @var string
	 */
	private const CAPABILITY = 'manage_woocommerce';

	/**
	 * Register hooks.
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		// Attribute suggestions are cached; any product or attribute change
		// can alter them.
		add_action( 'save_post_product', array( Product_Specs_Suggestions::class, 'flush' ) );
		foreach ( array( 'woocommerce_attribute_added', 'woocommerce_attribute_updated', 'woocommerce_attribute_deleted' ) as $hook ) {
			add_action( $hook, array( Product_Specs_Suggestions::class, 'flush' ) );
		}
		foreach ( array( 'created_term', 'edited_term', 'delete_term' ) as $hook ) {
			add_action( $hook, array( Product_Specs_Suggestions::class, 'flush_for_term' ), 10, 3 );
		}
	}

	/**
	 * URL of the page, optionally on a tab, with extra query args.
	 *
	 * @param string               $tab  Tab slug.
	 * @param array<string, mixed> $args Extra query args (e.g. guide => ID).
	 */
	public static function url( string $tab = '', array $args = array() ): string {
		$query = array(
			'post_type' => 'product',
			'page'      => self::PAGE_SLUG,
		);
		if ( '' !== $tab ) {
			$query['tab'] = $tab;
		}

		return add_query_arg( array_merge( $query, $args ), admin_url( 'edit.php' ) );
	}

	/**
	 * Add Products → Size Guides.
	 */
	public function add_page(): void {
		$hook = add_submenu_page(
			'edit.php?post_type=product',
			__( 'Size Guides & Specs', 'aggressive-apparel' ),
			__( 'Size Guides', 'aggressive-apparel' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);

		if ( false !== $hook ) {
			add_action( 'load-' . $hook, array( $this, 'enqueue_assets' ) );
		}
	}

	/**
	 * Enqueue the app and the media library (measuring diagrams).
	 */
	public function enqueue_assets(): void {
		wp_enqueue_media();
		Asset_Loader::enqueue_script(
			self::HANDLE,
			'build/scripts/admin/product-specs/index',
			array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-dom-ready', 'wp-url' )
		);
		Asset_Loader::enqueue_style( self::HANDLE, 'build/scripts/admin/product-specs/index', array( 'wp-components' ) );
	}

	/**
	 * Render the page shell and mount node.
	 */
	public function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view selection.
		$tab   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$guide = isset( $_GET['guide'] ) ? sanitize_key( wp_unslash( $_GET['guide'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$config = array(
			'registry'   => Product_Specs_Registry::get(),
			'defaults'   => Product_Specs_Registry::defaults(),
			'categories' => self::categories(),
			'restPath'   => '/' . self::REST_NAMESPACE . self::REST_ROUTE,
			'gridPath'   => '/' . self::REST_NAMESPACE . self::REST_ROUTE . '/products',
			'guidesPath' => '/' . Size_Guide_Rest::NAMESPACE . Size_Guide_Rest::ROUTE,
			'guides'     => Size_Guide_Rest::summaries(),
			'suggest'    => Product_Specs_Suggestions::all(),
			'attributes' => Product_Specs_Suggestions::names(),
			'tab'        => in_array( $tab, self::TABS, true ) ? $tab : self::TABS[0],
			// "new" opens a blank guide; a number opens that guide.
			'guide'      => 'new' === $guide ? 'new' : absint( $guide ),
			'limits'     => array(
				'items'    => Product_Specs_Registry::MAX_ITEMS,
				'minSteps' => Product_Specs_Registry::MIN_STEPS,
				'maxSteps' => Product_Specs_Registry::MAX_STEPS,
				'columns'  => Size_Chart::MAX_COLUMNS,
				'rows'     => Size_Chart::MAX_ROWS,
			),
		);

		echo '<div class="wrap aa-product-specs-settings">';
		echo '<h1>' . esc_html__( 'Size Guides & Specs', 'aggressive-apparel' ) . '</h1>';
		printf(
			'<div id="aa-product-specs-root" data-aa-product-specs="%s"><p>%s</p></div>',
			esc_attr( (string) wp_json_encode( $config ) ),
			esc_html__( 'Loading…', 'aggressive-apparel' )
		);
		echo '</div>';
	}

	/**
	 * Register the spec definitions route.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_registry' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_registry' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	/**
	 * Store managers only.
	 */
	public function can_manage(): bool {
		return current_user_can( self::CAPABILITY );
	}

	/**
	 * Current spec definitions.
	 */
	public function get_registry(): \WP_REST_Response {
		return rest_ensure_response( Product_Specs_Registry::get() );
	}

	/**
	 * Validate and save spec definitions.
	 *
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_registry( \WP_REST_Request $request ) {
		$result = Product_Specs_Registry::validate( $request->get_json_params() );

		if ( array() !== $result['errors'] ) {
			return new \WP_Error(
				'aa_product_specs_invalid',
				__( 'Some specs need fixing before they can be saved.', 'aggressive-apparel' ),
				array(
					'status' => 400,
					'errors' => $result['errors'],
				)
			);
		}

		Product_Specs_Registry::save(
			array(
				'items'           => $result['items'],
				'hide_dimensions' => $result['hide_dimensions'],
			)
		);

		return rest_ensure_response( Product_Specs_Registry::get() );
	}

	/**
	 * Product categories for pickers, with their path for clarity.
	 *
	 * @return list<array{id: int, name: string, parent: int}>
	 */
	private static function categories(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 500,
				'orderby'    => 'name',
			)
		);
		if ( ! is_array( $terms ) ) {
			return array();
		}

		$by_id = array();
		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term ) {
				$by_id[ $term->term_id ] = $term;
			}
		}

		$list = array();
		foreach ( $by_id as $id => $term ) {
			$path   = array( $term->name );
			$parent = $term->parent;
			$depth  = 0;
			while ( $parent > 0 && isset( $by_id[ $parent ] ) && $depth < 5 ) {
				array_unshift( $path, $by_id[ $parent ]->name );
				$parent = $by_id[ $parent ]->parent;
				++$depth;
			}
			$list[] = array(
				'id'     => $id,
				'name'   => implode( ' › ', $path ),
				'parent' => $term->parent,
			);
		}

		usort( $list, static fn( array $a, array $b ): int => strcasecmp( $a['name'], $b['name'] ) );

		return $list;
	}
}
