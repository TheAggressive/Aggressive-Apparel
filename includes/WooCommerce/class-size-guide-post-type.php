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
				'show_in_menu'        => 'edit.php?post_type=product',
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
