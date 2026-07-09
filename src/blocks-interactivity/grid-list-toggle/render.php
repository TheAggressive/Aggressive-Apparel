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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$show_labels = isset( $attributes['showLabels'] ) && (bool) $attributes['showLabels'];

$icon_attrs = array(
	'width'       => 18,
	'height'      => 18,
	'aria-hidden' => 'true',
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
	get_block_wrapper_attributes(
		array(
			'class'               => 'aa-grid-list-toggle',
			'data-wp-interactive' => 'aggressive-apparel/grid-list-toggle',
			'data-wp-init'        => 'callbacks.init',
		)
	),
	esc_attr__( 'Grid view', 'aggressive-apparel' ),
	aggressive_apparel_get_icon( 'grid-view', $icon_attrs ),
	wp_kses_post( $grid_label ),
	esc_attr__( 'List view', 'aggressive-apparel' ),
	aggressive_apparel_get_icon( 'list-view', $icon_attrs ),
	wp_kses_post( $list_label )
);
