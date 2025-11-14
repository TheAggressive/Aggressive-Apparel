<?php
/**
 * Block Categories Registration
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Core;

/**
 * Block Categories class for registering custom pattern categories
 */
class Block_Categories {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_categories' ), 5 );
	}

	/**
	 * Register block pattern categories
	 */
	public function register_categories(): void {
		$categories = $this->get_categories();

		foreach ( $categories as $category ) {
			if ( function_exists( 'register_block_pattern_category' ) ) {
				register_block_pattern_category( $category['slug'], $category );
			}
		}
	}

	/**
	 * Get categories configuration
	 *
	 * @return array Array of category configurations
	 */
	private function get_categories(): array {
		return array(
			array(
				'slug'        => 'aggressive',
				'label'       => __( 'Aggressive', 'aggressive-apparel' ),
				'description' => __( 'Premium apparel patterns for the Aggressive brand.', 'aggressive-apparel' ),
			),

			/*
			 * Future categories can be easily added here.
			 *
			 * array(
			 *     'slug'        => 'aggressive-products',
			 *     'label'       => __( 'Product Displays', 'aggressive-apparel' ),
			 *     'description' => __( 'Showcase products and collections.', 'aggressive-apparel' ),
			 * ),
			 */
		);
	}
}
