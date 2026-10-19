<?php
/**
 * Attribute Breadcrumb
 *
 * On a product attribute archive (/collection/womens/), WooCommerce's
 * breadcrumb names the attribute by its taxonomy label, which WooCommerce
 * prefixes with "Product" ("Home / Product Collection / Womens"). This shows
 * the attribute's own name instead: "Home / Collection / Womens".
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
 * Attribute archive breadcrumb label.
 */
final class Attribute_Breadcrumb {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_filter( 'woocommerce_get_breadcrumb', array( self::class, 'crumbs' ) );
	}

	/**
	 * Swap the attribute's taxonomy label for its own name.
	 *
	 * @param mixed $crumbs Breadcrumb trail: list of [text, url].
	 * @return mixed
	 */
	public static function crumbs( $crumbs ) {
		$term = is_tax() ? get_queried_object() : null;
		if ( ! is_array( $crumbs ) || ! $term instanceof \WP_Term || ! function_exists( 'taxonomy_is_product_attribute' ) || ! taxonomy_is_product_attribute( $term->taxonomy ) ) {
			return $crumbs;
		}

		$taxonomy = get_taxonomy( $term->taxonomy );
		$label    = $taxonomy instanceof \WP_Taxonomy ? $taxonomy->labels->name : '';
		$name     = wc_attribute_label( $term->taxonomy );
		// No attribute name found: keep WooCommerce's label, never the slug.
		if ( '' === $label || $name === $term->taxonomy ) {
			return $crumbs;
		}
		foreach ( $crumbs as $i => $crumb ) {
			if ( is_array( $crumb ) && ( $crumb[0] ?? null ) === $label ) {
				$crumbs[ $i ][0] = $name;
			}
		}

		return $crumbs;
	}
}
