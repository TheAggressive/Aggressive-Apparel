<?php
/**
 * Active Filter Bar Block — Server Render
 *
 * Emits the active-filter bar (removable pills + Clear All link) wired to the
 * existing Interactivity API store registered by Product_Filters. The pills
 * container is populated by JS on filter change; the wrapper's
 * `data-wp-bind--hidden` keeps the bar hidden until at least one filter is
 * active. No separate view script module is required.
 *
 * Available variables:
 *   $attributes (array)
 *   $content    (string)
 *   $block      (WP_Block)
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Product_Filters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (
	! class_exists( Product_Filters::class ) ||
	! class_exists( Feature_Settings::class ) ||
	! Feature_Settings::is_enabled( 'product_filters' )
) {
	return;
}

if ( ! Product_Filters::is_filterable_archive() ) {
	return;
}

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'                => 'aa-filter-active-bar',
		'data-wp-interactive'  => 'aggressive-apparel/product-filters',
		'data-wp-bind--hidden' => 'state.hasNoActiveFilters',
	)
);

$inner_html  = '<div class="aa-filter-active-bar__pills"></div>';
$inner_html .= '<button type="button" class="aa-filter-active-bar__clear-all" data-wp-on--click="actions.clearAllFilters">';
$inner_html .= esc_html__( 'Clear All', 'aggressive-apparel' );
$inner_html .= '</button>';

printf(
	'<div %1$s>%2$s</div>',
	wp_kses_post( $wrapper_attributes ),
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static markup; pill content is escaped at hydration time.
	$inner_html
);
