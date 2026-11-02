<?php
/**
 * Printful Size Guide Handoff
 *
 * The Printful plugin has a size guide of its own: Printful sends a chart for
 * each product, and the plugin shows it as a "Size Chart" tab, a "Size Guide"
 * link, or both. Next to this theme's guide, shoppers would get two charts that
 * can disagree.
 *
 * So on the product page of a product whose guide has something to open, the
 * plugin finds no chart and adds nothing, not even its script. Its data isn't
 * touched: Printful and the product editor still see it, and a product with no
 * guide here keeps the plugin's chart rather than having none.
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
 * Makes way for this theme's guide over the Printful plugin's.
 */
final class Printful_Size_Guide_Handoff {

	/**
	 * Meta the plugin keeps its charts in.
	 *
	 * @var list<string>
	 */
	private const CHART_META = array( 'pf_size_chart', 'pf_advanced_size_chart' );

	/**
	 * Register hooks.
	 */
	public function init(): void {
		add_filter( 'get_post_metadata', array( self::class, 'hide_chart' ), 10, 4 );
	}

	/**
	 * Read the plugin's chart as empty where this theme's guide takes over.
	 *
	 * @param mixed  $value     Short-circuit value.
	 * @param int    $object_id Post ID.
	 * @param string $meta_key  Meta key.
	 * @param bool   $single    Whether one value was asked for.
	 * @return mixed
	 */
	public static function hide_chart( $value, $object_id, $meta_key, $single ) {
		if ( ! in_array( $meta_key, self::CHART_META, true ) || is_admin() || ! did_action( 'wp' ) || ! function_exists( 'is_product' ) || ! is_product() ) {
			return $value;
		}
		if ( 'product' !== get_post_type( (int) $object_id ) || ! Size_Guide::resolved( (int) $object_id )['dialog'] ) {
			return $value;
		}

		return $single ? array( '' ) : array();
	}
}
