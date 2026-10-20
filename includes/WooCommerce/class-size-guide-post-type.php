<?php
/**
 * Size Guide Post Type
 *
 * Registers the aa_size_guide custom post type so size guides
 * can be managed as standalone posts and assigned to products.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Size Guide Post Type
 *
 * @since 1.17.0
 */
class Size_Guide_Post_Type {

	/**
	 * Post type slug.
	 *
	 * @var string
	 */
	public const POST_TYPE = 'aa_size_guide';

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register' ) );
		add_filter( 'rest_pre_dispatch', array( $this, 'staff_only_rest' ), 10, 3 );
	}

	/**
	 * Keep guides out of the public REST API. They stay in REST for the
	 * block editor (older free-form guides), but only staff can read them,
	 * so visitors can't list guide names or where they were imported from.
	 *
	 * @param mixed            $result  Response so far.
	 * @param \WP_REST_Server  $server  Server.
	 * @param \WP_REST_Request $request Request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @return mixed
	 */
	public function staff_only_rest( $result, $server, $request ) {
		unset( $server );
		$route = untrailingslashit( $request->get_route() );
		$base  = '/wp/v2/' . self::POST_TYPE;
		if ( null !== $result || ( $base !== $route && ! str_starts_with( $route, $base . '/' ) ) || current_user_can( 'edit_posts' ) ) {
			return $result;
		}

		return new \WP_Error(
			'rest_forbidden',
			__( 'Sorry, you are not allowed to do that.', 'aggressive-apparel' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Register the custom post type.
	 *
	 * @return void
	 */
	public function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => self::get_labels(),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => false, // Managed on Products → Size & Specs (Product_Specs_Settings).
				'show_in_rest'        => true,
				'supports'            => array( 'title', 'editor', 'revisions' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'exclude_from_search' => true,
			),
		);
	}

	/**
	 * Get post type labels.
	 *
	 * @return array<string, string>
	 */
	private static function get_labels(): array {
		return array(
			'name'               => __( 'Size Guides', 'aggressive-apparel' ),
			'singular_name'      => __( 'Size Guide', 'aggressive-apparel' ),
			'menu_name'          => __( 'Size Guides', 'aggressive-apparel' ),
			'add_new'            => __( 'Add New', 'aggressive-apparel' ),
			'add_new_item'       => __( 'Add New Size Guide', 'aggressive-apparel' ),
			'edit_item'          => __( 'Edit Size Guide', 'aggressive-apparel' ),
			'new_item'           => __( 'New Size Guide', 'aggressive-apparel' ),
			'view_item'          => __( 'View Size Guide', 'aggressive-apparel' ),
			'search_items'       => __( 'Search Size Guides', 'aggressive-apparel' ),
			'not_found'          => __( 'No size guides found.', 'aggressive-apparel' ),
			'not_found_in_trash' => __( 'No size guides found in Trash.', 'aggressive-apparel' ),
			'all_items'          => __( 'Size Guides', 'aggressive-apparel' ),
		);
	}
}
