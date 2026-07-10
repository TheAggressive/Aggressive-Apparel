<?php
/**
 * Block Categories Registration
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

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
	public function register_block_categories( array $categories, \WP_Block_Editor_Context $context ): array {
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
			array(
				'slug'        => 'aggressive-homepage',
				'label'       => __( 'Homepage', 'aggressive-apparel' ),
				'description' => __( 'Full homepage compositions and hero sections.', 'aggressive-apparel' ),
			),
			array(
				'slug'        => 'aggressive-products',
				'label'       => __( 'Shop & Products', 'aggressive-apparel' ),
				'description' => __( 'Product grids, collections, and shop-the-look layouts.', 'aggressive-apparel' ),
			),
			array(
				'slug'        => 'aggressive-shop',
				'label'       => __( 'Shop Archive', 'aggressive-apparel' ),
				'description' => __( 'PLP headers, filters, and browse recovery states.', 'aggressive-apparel' ),
			),
			array(
				'slug'        => 'aggressive-pdp',
				'label'       => __( 'Product Page', 'aggressive-apparel' ),
				'description' => __( 'PDP support modules: fit, shipping, cross-sells.', 'aggressive-apparel' ),
			),
			array(
				'slug'        => 'aggressive-drops',
				'label'       => __( 'Drops & Culture', 'aggressive-apparel' ),
				'description' => __( 'Drop launches, collabs, creators, and events.', 'aggressive-apparel' ),
			),
			array(
				'slug'        => 'aggressive-social-proof',
				'label'       => __( 'Social Proof', 'aggressive-apparel' ),
				'description' => __( 'UGC, reviews, press, and community.', 'aggressive-apparel' ),
			),
			array(
				'slug'        => 'aggressive-conversion',
				'label'       => __( 'Conversion', 'aggressive-apparel' ),
				'description' => __( 'Email capture, promos, loyalty, and urgency.', 'aggressive-apparel' ),
			),
			array(
				'slug'        => 'aggressive-informational',
				'label'       => __( 'Brand & Content', 'aggressive-apparel' ),
				'description' => __( 'About, journal, FAQ, and brand story sections.', 'aggressive-apparel' ),
			),
		);
	}
}
