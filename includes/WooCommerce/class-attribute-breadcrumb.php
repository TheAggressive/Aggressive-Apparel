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
		// No attribute name found (WooCommerce falls back to the slug, with
		// or without "pa_"): keep its label rather than show a slug.
		if ( '' === $label || in_array( $name, array( $term->taxonomy, wc_attribute_taxonomy_slug( $term->taxonomy ) ), true ) ) {
			return $crumbs;
		}
		// Only the label crumb, which comes before any term crumbs; a term
		// that happens to share the label keeps its own title.
		foreach ( $crumbs as $i => $crumb ) {
			if ( is_array( $crumb ) && ( $crumb[0] ?? null ) === $label ) {
				$crumbs[ $i ][0] = $name;
				break;
			}
		}

		return $crumbs;
	}
}
