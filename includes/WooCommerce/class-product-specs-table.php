<?php
/**
 * Product Specs Table
 *
 * Adds spec items to WooCommerce's Additional information table, so specs set
 * once as a spec default or on a product appear there without adding attributes by
 * hand. Items whose value already comes from a visible attribute are skipped
 * (the attribute row is already there). The tab is added for products that
 * have specs but no attributes, and the shipping weight/dimension rows can be
 * hidden store-wide.
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
 * Additional information integration.
 */
final class Product_Specs_Table {

	/**
	 * Rows per product for this request.
	 *
	 * @var array<int, array<string, array{label: string, value: string}>>
	 */
	private array $rows = array();

	/**
	 * Register hooks.
	 */
	public function init(): void {
		add_filter( 'woocommerce_display_product_attributes', array( $this, 'add_rows' ), 20, 2 );
		add_filter( 'woocommerce_product_tabs', array( $this, 'ensure_tab' ), 15 );
		add_filter( 'wc_product_enable_dimensions_display', array( $this, 'dimensions_display' ), 20 );
	}

	/**
	 * Append spec rows to the Additional information table.
	 *
	 * @param array<string, array<string, mixed>> $attributes Table rows.
	 * @param mixed                               $product    Product.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_rows( $attributes, $product ): array {
		$attributes = is_array( $attributes ) ? $attributes : array();
		if ( ! $product instanceof \WC_Product ) {
			return $attributes;
		}

		return array_merge( $attributes, $this->rows_for( $product->get_id() ) );
	}

	/**
	 * Add the Additional information tab when a product has spec rows but
	 * nothing else to put there.
	 *
	 * @param array<string, array<string, mixed>> $tabs Product tabs.
	 * @return array<string, array<string, mixed>>
	 */
	public function ensure_tab( $tabs ): array {
		$tabs = is_array( $tabs ) ? $tabs : array();
		if ( isset( $tabs['additional_information'] ) || ! function_exists( 'woocommerce_product_additional_information_tab' ) ) {
			return $tabs;
		}

		$product_id = get_the_ID();
		if ( false === $product_id || 'product' !== get_post_type( $product_id ) || array() === $this->rows_for( $product_id ) ) {
			return $tabs;
		}

		$tabs['additional_information'] = array(
			'title'    => __( 'Additional information', 'aggressive-apparel' ),
			'priority' => 20,
			'callback' => 'woocommerce_product_additional_information_tab',
		);

		return $tabs;
	}

	/**
	 * Hide shipping weight and dimensions when the store chose to.
	 *
	 * @param mixed $enabled Whether WooCommerce shows them.
	 */
	public function dimensions_display( $enabled ): bool {
		return Product_Specs_Registry::get()['hide_dimensions'] ? false : (bool) $enabled;
	}

	/**
	 * Spec rows for a product, keyed like WooCommerce attribute rows.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, array{label: string, value: string}>
	 */
	public function rows_for( int $product_id ): array {
		if ( isset( $this->rows[ $product_id ] ) ) {
			return $this->rows[ $product_id ];
		}

		$rows = array();
		foreach ( Product_Specs::for_product( $product_id )['items'] as $item ) {
			if ( $item['hidden'] || ! $item['in_table'] || '' === $item['display'] || $item['attribute_visible'] ) {
				continue;
			}

			$value = $item['display'];
			if ( Product_Specs_Registry::TYPE_NUMBER === $item['type'] ) {
				$value = ( $item['steps'][ $item['step'] ] ?? '' ) . ' · ' . $item['display'];
			}

			$rows[ 'aa-spec-' . $item['key'] ] = array(
				'label' => esc_html( $item['title'] ),
				'value' => esc_html( $value ),
			);
		}

		$this->rows[ $product_id ] = $rows;

		return $rows;
	}
}
