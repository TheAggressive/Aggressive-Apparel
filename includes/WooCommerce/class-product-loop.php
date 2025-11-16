<?php
/**
 * Product Loop Class
 *
 * Handles product loop customizations
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product Loop Class
 *
 * Customizes the WooCommerce product loop display.
 *
 * @since 1.0.0
 */
class Product_Loop {

	/**
	 * Number of columns
	 *
	 * @var int
	 */
	private $columns = 3;

	/**
	 * Products per page
	 *
	 * @var int
	 */
	private $per_page = 12;

	/**
	 * Constructor
	 *
	 * @param int $columns  Optional. Number of columns.
	 * @param int $per_page Optional. Products per page.
	 */
	public function __construct( $columns = 3, $per_page = 12 ) {
		$this->columns  = $columns;
		$this->per_page = $per_page;
	}

	/**
	 * Initialize product loop customizations
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'loop_shop_columns', array( $this, 'set_loop_columns' ), 10 );
		add_filter( 'loop_shop_per_page', array( $this, 'set_products_per_page' ), 20 );
	}

	/**
	 * Set number of columns in product loop
	 *
	 * @param int $columns Current column count.
	 * @return int Modified column count.
	 */
	public function set_loop_columns( $columns ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return apply_filters( 'aggressive_apparel_product_columns', $this->columns );
	}

	/**
	 * Set products per page
	 *
	 * @param int $per_page Current products per page.
	 * @return int Modified products per page.
	 */
	public function set_products_per_page( $per_page ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return apply_filters( 'aggressive_apparel_products_per_page', $this->per_page );
	}
}
