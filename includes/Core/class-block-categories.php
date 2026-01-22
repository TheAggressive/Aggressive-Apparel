<?php
/**
 * Block Categories Registration
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Core;

/**
 * Block Categories class for registering custom block and pattern categories
 */
class Block_Categories {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_pattern_categories' ), 5 );
		add_filter( 'block_categories_all', array( $this, 'register_block_categories' ), 10, 2 );
	}

	/**
	 * Register custom block categories
	 *
	 * @param array                    $categories Array of block categories.
	 * @param \WP_Block_Editor_Context $context    Block editor context (unused).
	 * @return array Modified array of block categories.
	 */
	public function register_block_categories( array $categories, \WP_Block_Editor_Context $context ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// Add custom category at the beginning of the list.
		array_unshift(
			$categories,
			array(
				'slug'  => 'aggressive-apparel',
				'title' => __( 'Aggressive Apparel', 'aggressive-apparel' ),
				'icon'  => null,
			)
		);

		return $categories;
	}

	/**
	 * Register block pattern categories
	 */
	public function register_pattern_categories(): void {
		$categories = $this->get_pattern_categories();

		foreach ( $categories as $category ) {
			if ( function_exists( 'register_block_pattern_category' ) ) {
				register_block_pattern_category( $category['slug'], $category );
			}
		}
	}

	/**
	 * Get pattern categories configuration
	 *
	 * @return array Array of pattern category configurations
	 */
	private function get_pattern_categories(): array {
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
