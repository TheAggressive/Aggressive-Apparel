<?php
/**
 * Grid/List View Toggle Class
 *
 * Adds a toggle to switch between grid and list view on shop archive pages.
 *
 * @package Aggressive_Apparel
 * @since 1.51.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Assets\Asset_Loader;
use Aggressive_Apparel\Core\Icons;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Grid/List View Toggle
 *
 * Injects toggle buttons near the catalog sorting dropdown and enqueues
 * CSS/JS for switching between grid and list layouts. Preference is
 * persisted in localStorage.
 *
 * @since 1.51.0
 */
class Grid_List_Toggle {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'render_block', array( $this, 'inject_toggle' ), 10, 2 );
	}

	/**
	 * Enqueue CSS and register JS module.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		Asset_Loader::enqueue_feature_style(
			'aggressive-apparel-grid-list-toggle',
			'build/styles/woocommerce/grid-list-toggle'
		);

		Asset_Loader::enqueue_interactivity_module(
			'@aggressive-apparel/grid-list-toggle',
			'build/interactivity/grid-list-toggle'
		);
	}

	/**
	 * Inject grid/list toggle buttons after the catalog sorting block.
	 *
	 * @param string               $block_content Rendered block HTML.
	 * @param array<string, mixed> $block         Block attributes.
	 * @return string Modified block HTML.
	 */
	public function inject_toggle( string $block_content, array $block ): string {
		if ( 'woocommerce/catalog-sorting' !== ( $block['blockName'] ?? '' ) ) {
			return $block_content;
		}

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

		$toggle_html = sprintf(
			'<div class="aa-grid-list-toggle" data-wp-interactive="aggressive-apparel/grid-list-toggle" data-wp-init="callbacks.init">'
			. '<button class="aa-grid-list-toggle__btn aa-grid-list-toggle__btn--grid"'
			. ' data-wp-on--click="actions.setGrid"'
			. ' data-wp-class--is-active="state.isGridView"'
			. ' aria-label="%1$s"'
			. ' data-wp-bind--aria-pressed="state.isGridView">'
			. '%2$s'
			. '</button>'
			. '<button class="aa-grid-list-toggle__btn aa-grid-list-toggle__btn--list"'
			. ' data-wp-on--click="actions.setList"'
			. ' data-wp-class--is-active="state.isListView"'
			. ' aria-label="%3$s"'
			. ' data-wp-bind--aria-pressed="state.isListView">'
			. '%4$s'
			. '</button>'
			. '</div>',
			esc_attr__( 'Grid view', 'aggressive-apparel' ),
			$grid_icon,
			esc_attr__( 'List view', 'aggressive-apparel' ),
			$list_icon
		);

		return $block_content . $toggle_html;
	}
}
