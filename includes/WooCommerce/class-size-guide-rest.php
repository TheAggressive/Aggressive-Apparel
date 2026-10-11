<?php
/**
 * Size Guide REST
 *
 * The routes behind the Size Guides editor (Products → Size Guides): list,
 * read, create, update, duplicate, and trash guides. One save writes the
 * whole guide — chart, notes, diagram, spec values, and where it applies.
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
 * Size guide REST controller.
 *
 * @phpstan-import-type Chart from Size_Chart
 * @phpstan-import-type Usage from Size_Guide_Assignments
 * @phpstan-type Summary array{id: int, title: string, sizes: int, columns: int, specs: int, usage: Usage, legacy: bool}
 * @phpstan-type Guide array{id: int, title: string, chart: Chart, notes: string, diagram: array{id: int, url: string}|null, values: array<string, string>, categories: list<int>, isDefault: bool, usage: Usage, legacyHtml: string, legacyEditUrl: string}
 */
final class Size_Guide_Rest {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	public const NAMESPACE = 'aggressive-apparel/v1';

	/**
	 * Collection route.
	 *
	 * @var string
	 */
	public const ROUTE = '/size-guides';

	/**
	 * Longest notes text.
	 *
	 * @var int
	 */
	private const NOTES_MAX = 2000;

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
		$manage = array( $this, 'can_manage' );
		$edit   = array( $this, 'can_edit' );

		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => fn(): \WP_REST_Response => rest_ensure_response( self::summaries() ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create' ),
					'permission_callback' => $manage,
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			self::ROUTE . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => fn( \WP_REST_Request $request ): \WP_REST_Response => rest_ensure_response( self::guide( (int) $request['id'] ) ),
					'permission_callback' => $edit,
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update' ),
					'permission_callback' => $edit,
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'trash' ),
					'permission_callback' => $edit,
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			self::ROUTE . '/(?P<id>\d+)/convert',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'convert' ),
				'permission_callback' => $edit,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			self::ROUTE . '/(?P<id>\d+)/duplicate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'duplicate' ),
				'permission_callback' => $edit,
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
	 * Store managers who can edit this guide.
	 *
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function can_edit( \WP_REST_Request $request ) {
		$forbidden = new \WP_Error( 'rest_forbidden', __( 'You cannot edit this size guide.', 'aggressive-apparel' ), array( 'status' => rest_authorization_required_code() ) );
		// Permission first, so the response never tells outsiders which IDs exist.
		if ( ! $this->can_manage() ) {
			return $forbidden;
		}

		$id = (int) $request['id'];
		if ( Size_Guide_Post_Type::POST_TYPE !== get_post_type( $id ) ) {
			return new \WP_Error( 'aa_size_guide_missing', __( 'That size guide no longer exists.', 'aggressive-apparel' ), array( 'status' => 404 ) );
		}

		return current_user_can( 'edit_post', $id ) ? true : $forbidden;
	}

	/**
	 * Create a guide from the submitted fields.
	 *
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create( \WP_REST_Request $request ) {
		$id = wp_insert_post(
			array(
				'post_type'   => Size_Guide_Post_Type::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => self::title( $request->get_param( 'title' ) ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		self::save( $id, self::body( $request ) );

		return rest_ensure_response( self::guide( $id ) );
	}

	/**
	 * Update a guide from the submitted fields.
	 *
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update( \WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$result = wp_update_post(
			array(
				'ID'         => $id,
				'post_title' => self::title( $request->get_param( 'title' ) ),
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		self::save( $id, self::body( $request ) );

		return rest_ensure_response( self::guide( $id ) );
	}

	/**
	 * A chart and notes read from a guide's old layout, unsaved, so the editor
	 * can show them before the user saves.
	 *
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function convert( \WP_REST_Request $request ) {
		$imported = Size_Chart_Import::from_html( (string) do_blocks( self::content( (int) $request['id'] ) ) );
		if ( null === $imported ) {
			return new \WP_Error(
				'aa_size_guide_no_table',
				__( 'This guide has no table to convert. Start a chart from a template instead.', 'aggressive-apparel' ),
				array( 'status' => 422 )
			);
		}

		$image = $imported['diagram'] > 0 ? wp_get_attachment_image_url( $imported['diagram'], 'medium' ) : false;

		return rest_ensure_response(
			array(
				'chart'    => $imported['chart'],
				'notes'    => $imported['notes'],
				'diagram'  => false !== $image ? array(
					'id'  => $imported['diagram'],
					'url' => $image,
				) : null,
				// False when something (extra images, embeds, other tables)
				// could not be carried over; the editor says so.
				'lossless' => $imported['lossless'],
			)
		);
	}

	/**
	 * Copy a guide's chart, notes, diagram, and spec values (not where it
	 * applies) into a new guide.
	 *
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function duplicate( \WP_REST_Request $request ) {
		$source = self::guide( (int) $request['id'] );
		$id     = wp_insert_post(
			array(
				'post_type'    => Size_Guide_Post_Type::POST_TYPE,
				'post_status'  => 'publish',
				/* translators: %s: size guide name. */
				'post_title'   => sprintf( __( '%s (copy)', 'aggressive-apparel' ), $source['title'] ),
				'post_content' => self::content( $source['id'] ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		self::save(
			$id,
			array(
				'chart'   => $source['chart'],
				'notes'   => $source['notes'],
				'diagram' => null !== $source['diagram'] ? $source['diagram']['id'] : 0,
				'values'  => $source['values'],
			)
		);

		return rest_ensure_response( self::guide( $id ) );
	}

	/**
	 * Trash a guide and release its category and store-wide assignments.
	 *
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @param \WP_REST_Request $request Request.
	 */
	public function trash( \WP_REST_Request $request ): \WP_REST_Response {
		$id = (int) $request['id'];
		Size_Guide_Assignments::release( $id );
		wp_trash_post( $id );
		( new Size_Guide() )->flush_all_caches();

		return rest_ensure_response( array( 'trashed' => true ) );
	}

	/**
	 * Every guide, summarized for the list.
	 *
	 * @return list<Summary>
	 */
	public static function summaries(): array {
		$posts = get_posts(
			array(
				'post_type'      => Size_Guide_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$usage = Size_Guide_Assignments::usage_map( array_values( array_map( static fn( \WP_Post $post ): int => $post->ID, $posts ) ) );
		$list  = array();
		foreach ( $posts as $post ) {
			$chart  = Size_Chart::get( $post->ID );
			$list[] = array(
				'id'      => $post->ID,
				'title'   => self::display_title( $post ),
				'sizes'   => null !== $chart ? count( $chart['rows'] ) : 0,
				'columns' => null !== $chart ? count( $chart['columns'] ) : 0,
				'specs'   => count( array_filter( Product_Specs_Values::stored( $post->ID ), static fn( string $value ): bool => '' !== $value ) ),
				'usage'   => $usage[ $post->ID ],
				'legacy'  => null === $chart && '' !== trim( $post->post_content ),
			);
		}

		return $list;
	}

	/**
	 * One guide with everything the editor needs.
	 *
	 * @param int $id Size guide post ID.
	 * @return Guide
	 */
	public static function guide( int $id ): array {
		$post    = get_post( $id );
		$chart   = Size_Chart::get( $id );
		$diagram = (int) get_post_meta( $id, Size_Chart::DIAGRAM, true );
		$image   = $diagram > 0 ? wp_get_attachment_image_url( $diagram, 'medium' ) : false;
		$legacy  = null === $chart && $post instanceof \WP_Post && '' !== trim( $post->post_content )
			? wp_kses_post( (string) do_blocks( $post->post_content ) )
			: '';

		return array(
			'id'            => $id,
			'title'         => $post instanceof \WP_Post ? self::display_title( $post ) : '',
			'chart'         => $chart ?? Size_Chart::sanitize( array() ),
			'notes'         => (string) get_post_meta( $id, Size_Chart::NOTES, true ),
			'diagram'       => false !== $image ? array(
				'id'  => $diagram,
				'url' => $image,
			) : null,
			'values'        => Product_Specs_Values::stored( $id ),
			'categories'    => Size_Guide_Assignments::categories( $id ),
			'isDefault'     => Size_Guide_Assignments::is_default( $id ),
			'usage'         => Size_Guide_Assignments::usage( $id ),
			'legacyHtml'    => $legacy,
			'legacyEditUrl' => '' !== $legacy ? (string) get_edit_post_link( $id, 'raw' ) : '',
		);
	}

	/**
	 * Write a guide's fields. Missing fields are left as they are.
	 *
	 * @param int                  $id   Size guide post ID.
	 * @param array<string, mixed> $data Submitted fields.
	 */
	private static function save( int $id, array $data ): void {
		if ( array_key_exists( 'chart', $data ) ) {
			$chart = Size_Chart::sanitize( $data['chart'] );
			if ( array() === $chart['rows'] ) {
				delete_post_meta( $id, Size_Chart::META );
			} else {
				update_post_meta( $id, Size_Chart::META, $chart );
			}
		}

		if ( array_key_exists( 'notes', $data ) ) {
			$notes = is_scalar( $data['notes'] ) ? mb_substr( sanitize_textarea_field( (string) $data['notes'] ), 0, self::NOTES_MAX ) : '';
			if ( '' === trim( $notes ) ) {
				delete_post_meta( $id, Size_Chart::NOTES );
			} else {
				update_post_meta( $id, Size_Chart::NOTES, $notes );
			}
		}

		if ( array_key_exists( 'diagram', $data ) ) {
			$diagram = absint( $data['diagram'] );
			if ( $diagram > 0 && wp_attachment_is_image( $diagram ) ) {
				update_post_meta( $id, Size_Chart::DIAGRAM, $diagram );
			} else {
				delete_post_meta( $id, Size_Chart::DIAGRAM );
			}
		}

		if ( isset( $data['values'] ) && is_array( $data['values'] ) ) {
			Product_Specs_Values::save( $id, $data['values'] );
		}

		if ( isset( $data['categories'] ) && is_array( $data['categories'] ) ) {
			Size_Guide_Assignments::set_categories( $id, array_values( array_filter( array_map( 'absint', $data['categories'] ) ) ) );
		}

		if ( array_key_exists( 'isDefault', $data ) ) {
			Size_Guide_Assignments::set_default( $id, (bool) $data['isDefault'] );
		}

		( new Size_Guide() )->flush_all_caches();
	}

	/**
	 * A guide's raw post content (its old free-form layout, if any).
	 *
	 * @param int $id Size guide post ID.
	 */
	private static function content( int $id ): string {
		$post = get_post( $id );

		return $post instanceof \WP_Post ? $post->post_content : '';
	}

	/**
	 * The submitted fields as an array.
	 *
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @param \WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	private static function body( \WP_REST_Request $request ): array {
		$body = $request->get_json_params();

		return is_array( $body ) ? $body : array();
	}

	/**
	 * A clean title, never empty.
	 *
	 * @param mixed $raw Raw title.
	 */
	private static function title( $raw ): string {
		$title = is_scalar( $raw ) ? trim( sanitize_text_field( (string) $raw ) ) : '';

		return '' !== $title ? mb_substr( $title, 0, 120 ) : __( 'Untitled size guide', 'aggressive-apparel' );
	}

	/**
	 * A guide's title for display.
	 *
	 * @param \WP_Post $post Size guide.
	 */
	private static function display_title( \WP_Post $post ): string {
		return '' !== $post->post_title ? $post->post_title : __( '(no title)', 'aggressive-apparel' );
	}
}
