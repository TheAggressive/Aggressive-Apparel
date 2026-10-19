<?php
/**
 * Product Specs Settings
 *
 * Products → Size & Specs: the one screen for size guides (chart, notes,
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
	 * Add Products → Size & Specs.
	 */
	public function add_page(): void {
		$hook = add_submenu_page(
			'edit.php?post_type=product',
			__( 'Size & Specs', 'aggressive-apparel' ),
			__( 'Size & Specs', 'aggressive-apparel' ),
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
			'registry'   => self::registry(),
			'defaults'   => Product_Specs_Registry::defaults(),
			'categories' => self::categories(),
			'restPath'   => '/' . self::REST_NAMESPACE . self::REST_ROUTE,
			'gridPath'   => '/' . self::REST_NAMESPACE . self::REST_ROUTE . '/products',
			'guidesPath' => '/' . Size_Guide_Rest::NAMESPACE . Size_Guide_Rest::ROUTE,
			'guides'     => Size_Guide_Rest::summaries(),
			'suggest'    => Product_Specs_Suggestions::all(),
			'attributes' => Product_Specs_Suggestions::names(),
			// Global attributes (taxonomy => label), for the attribute a spec uses.
			'taxonomies' => Product_Specs_Taxonomy::attributes(),
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
		echo '<h1>' . esc_html__( 'Size & Specs', 'aggressive-apparel' ) . '</h1>';
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
			self::REST_ROUTE . '/attribute',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_attribute' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'taxonomy' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

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
	 * One global attribute for the editor: its label, whether its values
	 * link to archive pages, and its values with how many products use each
	 * (so a new spec can link it and pick values before it is saved).
	 *
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_attribute( \WP_REST_Request $request ) {
		$taxonomy   = (string) $request->get_param( 'taxonomy' );
		$attributes = Product_Specs_Taxonomy::attributes();
		if ( ! isset( $attributes[ $taxonomy ] ) ) {
			return new \WP_Error( 'aa_product_specs_no_attribute', __( 'That attribute no longer exists.', 'aggressive-apparel' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'taxonomy' => $taxonomy,
				'label'    => $attributes[ $taxonomy ],
				'archives' => Product_Specs_Taxonomy::has_archives( $taxonomy ),
				'terms'    => Product_Specs_Taxonomy::usage( $taxonomy ),
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
		return rest_ensure_response( self::registry() );
	}

	/**
	 * Validate and save spec definitions.
	 *
	 * The editor sends its whole list plus the revision it loaded. When the
	 * registry moved on since (a size guide saved its own specs, or another
	 * tab saved), only the items the editor changed or deleted are applied
	 * on top of the current list, so neither save undoes the other.
	 *
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_registry( \WP_REST_Request $request ) {
		$body   = $request->get_json_params();
		$body   = is_array( $body ) ? $body : array();
		$result = Product_Specs_Registry::validate(
			array(
				'items'           => self::merged( $body ),
				'hide_dimensions' => ! empty( $body['hide_dimensions'] ),
			)
		);

		$errors = array() !== $result['errors']
			? $result['errors']
			: Product_Specs_Registry::save_connected(
				array(
					'items'           => $result['items'],
					'hide_dimensions' => $result['hide_dimensions'],
				)
			);
		if ( array() !== $errors ) {
			return new \WP_Error(
				'aa_product_specs_invalid',
				__( 'Some specs need fixing before they can be saved.', 'aggressive-apparel' ),
				array(
					'status' => 400,
					'errors' => $errors,
				)
			);
		}

		return rest_ensure_response( self::registry() );
	}

	/**
	 * The registry for the editor: pick steps named as their terms are now,
	 * each text spec's existing values (its terms) to pick from, and the
	 * global attributes as they are after this save (a save can create one).
	 *
	 * @return array<string, mixed>
	 */
	public static function registry(): array {
		$registry = Product_Specs_Registry::get();
		$items    = array();
		foreach ( $registry['items'] as $item ) {
			$item['steps'] = Product_Specs_Taxonomy::step_names( $item );
			// WooCommerce's own archive setting, so the editor shows the truth.
			if ( '' !== $item['taxonomy'] ) {
				$item['archives'] = Product_Specs_Taxonomy::has_archives( $item['taxonomy'] );
			}
			$items[] = $item + array(
				'terms' => Product_Specs_Registry::TYPE_TEXT === $item['type'] ? Product_Specs_Taxonomy::usage( $item['taxonomy'] ) : array(),
			);
		}

		return array(
			'items'      => $items,
			'taxonomies' => Product_Specs_Taxonomy::attributes(),
		) + $registry;
	}

	/**
	 * The raw item list to save: the editor's list when it saw the latest
	 * revision, else the current list with the editor's changes applied.
	 *
	 * @param array<string, mixed> $body Request body.
	 * @return list<mixed>
	 */
	private static function merged( array $body ): array {
		$sent    = isset( $body['items'] ) && is_array( $body['items'] ) ? array_values( $body['items'] ) : array();
		$current = Product_Specs_Registry::get();
		if ( absint( $body['revision'] ?? 0 ) === $current['revision'] ) {
			return $sent;
		}

		$keys    = static fn( $raw ): array => array_flip( array_map( static fn( $key ): string => sanitize_key( (string) $key ), is_array( $raw ) ? array_filter( $raw, 'is_scalar' ) : array() ) );
		$changed = $keys( $body['changed'] ?? array() );
		$deleted = $keys( $body['deleted'] ?? array() );
		$saved   = array_column( $current['items'], null, 'key' );

		$items = array();
		$seen  = array();
		foreach ( $sent as $entry ) {
			$key = is_array( $entry ) ? sanitize_key( (string) ( $entry['key'] ?? '' ) ) : '';
			if ( '' === $key || isset( $changed[ $key ] ) ) {
				$items[] = $entry;
			} elseif ( isset( $saved[ $key ] ) ) {
				// Unchanged here: keep whatever was saved since.
				$items[] = $saved[ $key ];
			}
			$seen[ $key ] = true;
		}
		foreach ( $current['items'] as $item ) {
			// Added elsewhere since this editor loaded.
			if ( ! isset( $seen[ $item['key'] ] ) && ! isset( $deleted[ $item['key'] ] ) ) {
				$items[] = $item;
			}
		}

		return $items;
	}

	/**
	 * Product categories for pickers, with their path for clarity.
	 *
	 * @return list<array{id: int, name: string}>
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
				'id'   => $id,
				'name' => implode( ' › ', $path ),
			);
		}

		usort( $list, static fn( array $a, array $b ): int => strcasecmp( $a['name'], $b['name'] ) );

		return $list;
	}
}
