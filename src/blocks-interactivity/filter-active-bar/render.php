<?php
/**
 * Active Filter Bar Block — Server Render
 *
 * Emits the active-filter bar (removable pills + Clear All link) wired to the
 * existing Interactivity API store registered by Product_Filters. The pills
 * container is populated by JS on filter change. SSR starts with `hidden` so
 * Clear All does not flash before hydration; `data-wp-bind--hidden` then
 * reveals the bar once at least one filter is active. No separate view
 * script module is required.
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

Product_Filters::ensure_assets();
?>
<div
	<?php
	echo get_block_wrapper_attributes(
		array(
			'class'                => 'aa-filter-active-bar',
			'data-wp-interactive'  => 'aggressive-apparel/product-filters',
			'data-wp-bind--hidden' => 'state.hasNoActiveFilters',
		)
	);
	?>
	hidden
>
	<div class="aa-filter-active-bar__pills"></div>
	<button
		type="button"
		class="aa-filter-active-bar__clear-all"
		data-wp-on--click="actions.clearAllFilters"
	>
		<?php esc_html_e( 'Clear All', 'aggressive-apparel' ); ?>
	</button>
</div>
