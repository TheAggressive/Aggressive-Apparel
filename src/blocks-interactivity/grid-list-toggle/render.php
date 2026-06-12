<?php
/**
 * Grid / List Toggle Block — Server Render
 *
 * Renders the grid/list view toggle buttons. The Interactivity API
 * store reads the visitor's saved preference from localStorage on init
 * and applies the correct view mode class to the document body.
 *
 * Available variables:
 *   $attributes (array)
 *   $content    (string)
 *   $block      (WP_Block)
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

use Aggressive_Apparel\Core\Icons;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$show_labels = isset( $attributes['showLabels'] ) && (bool) $attributes['showLabels'];

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'               => 'aa-grid-list-toggle',
		'data-wp-interactive' => 'aggressive-apparel/grid-list-toggle',
		'data-wp-init'        => 'callbacks.init',
	)
);

$grid_icon = Icons::get(
	'grid-view',
	array(
		'width'       => 18,
		'height'      => 18,
		'aria-hidden' => 'true',
	)
);

$list_icon = Icons::get(
	'list-view',
	array(
		'width'       => 18,
		'height'      => 18,
		'aria-hidden' => 'true',
	)
);

$grid_label = $show_labels
	? '<span class="aa-grid-list-toggle__label">' . esc_html__( 'Grid', 'aggressive-apparel' ) . '</span>'
	: '';

$list_label = $show_labels
	? '<span class="aa-grid-list-toggle__label">' . esc_html__( 'List', 'aggressive-apparel' ) . '</span>'
	: '';

printf(
	'<div %1$s>'
	. '<button class="aa-grid-list-toggle__btn aa-grid-list-toggle__btn--grid"'
	. ' type="button"'
	. ' data-wp-on--click="actions.setGrid"'
	. ' data-wp-class--is-active="state.isGridView"'
	. ' data-wp-bind--aria-pressed="state.isGridView"'
	. ' aria-pressed="true"'
	. ' aria-label="%2$s">'
	. '%3$s%4$s'
	. '</button>'
	. '<button class="aa-grid-list-toggle__btn aa-grid-list-toggle__btn--list"'
	. ' type="button"'
	. ' data-wp-on--click="actions.setList"'
	. ' data-wp-class--is-active="state.isListView"'
	. ' data-wp-bind--aria-pressed="state.isListView"'
	. ' aria-pressed="false"'
	. ' aria-label="%5$s">'
	. '%6$s%7$s'
	. '</button>'
	. '</div>',
	$wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() is trusted.
	esc_attr__( 'Grid view', 'aggressive-apparel' ),
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::get() returns trusted SVG.
	$grid_icon,
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped above.
	$grid_label,
	esc_attr__( 'List view', 'aggressive-apparel' ),
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::get() returns trusted SVG.
	$list_icon,
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped above.
	$list_label
);
