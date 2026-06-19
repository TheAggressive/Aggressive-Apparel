<?php
/**
 * Product Loop Class
 *
 * Handles product loop customizations
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

declare(strict_types=1);

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
	 * Registers filters regardless of WooCommerce status for theme flexibility.
	 *
	 * @return void
	 */
	public function init() {
		// Register filters regardless of WooCommerce status for theme flexibility.
		add_filter( 'loop_shop_columns', array( $this, 'set_loop_columns' ), 10 );
		add_filter( 'loop_shop_per_page', array( $this, 'set_products_per_page' ), 20 );
		add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'set_product_button_text' ), 10, 2 );
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

	/**
	 * Customize the product-loop button text.
	 *
	 * @param string $text    Current button text.
	 * @param mixed  $product Product object.
	 * @return string Modified button text.
	 */
	public function set_product_button_text( $text, $product = null ) {
		if ( ! is_object( $product ) ) {
			return $text;
		}

		if ( method_exists( $product, 'is_in_stock' ) && ! $product->is_in_stock() ) {
			return Feature_Settings::get_out_of_stock_button_text();
		}

		if ( method_exists( $product, 'is_purchasable' ) && ! $product->is_purchasable() ) {
			return $text;
		}

		if ( method_exists( $product, 'is_type' ) && $product->is_type( 'variable' ) ) {
			return Feature_Settings::get_variable_product_button_text();
		}

		if ( method_exists( $product, 'is_type' ) && $product->is_type( 'simple' ) ) {
			return Feature_Settings::get_simple_product_button_text();
		}

		return $text;
	}
}
