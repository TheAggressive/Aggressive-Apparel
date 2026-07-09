<?php
/**
 * Product Filter Renderer
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

defined( 'ABSPATH' ) || exit;

/** Renders reusable product-filter controls and category trees. */
final class Product_Filter_Renderer {

	/**
	 * Build the horizontal filter bar HTML.
	 *
	 * @param array<string, mixed> $data Filter source data.
	 * @return string Bar HTML.
	 */
	public function build_horizontal_bar( array $data ): string {
		$visible_categories = $this->get_visible_categories( $data['categories'] );

		$bar = '<div class="aa-product-filters__bar">';

		// Category dropdown.
		if ( ! empty( $visible_categories ) ) {
			$bar .= $this->build_bar_dropdown(
				'categories',
				__( 'Category', 'aggressive-apparel' ),
				'state.isCategoryDropdownOpen',
			);
		}

		// Fit dropdown.
		if ( ! empty( $data['fitTerms'] ) ) {
			$bar .= $this->build_bar_dropdown(
				'fit',
				__( 'Fit', 'aggressive-apparel' ),
				'state.isFitDropdownOpen',
			);
		}

		// Color dropdown.
		if ( ! empty( $data['colorTerms'] ) ) {
			$bar .= $this->build_bar_dropdown(
				'colors',
				__( 'Color', 'aggressive-apparel' ),
				'state.isColorDropdownOpen',
			);
		}

		// Size dropdown.
		if ( ! empty( $data['sizeTerms'] ) ) {
			$bar .= $this->build_bar_dropdown(
				'sizes',
				__( 'Size', 'aggressive-apparel' ),
				'state.isSizeDropdownOpen',
			);
		}

		// Price dropdown.
		$bar .= $this->build_bar_dropdown(
			'price',
			__( 'Price', 'aggressive-apparel' ),
			'state.isPriceDropdownOpen',
		);

		// Availability dropdown.
		$bar .= $this->build_bar_dropdown(
			'stock',
			__( 'Availability', 'aggressive-apparel' ),
			'state.isStockDropdownOpen',
		);

		$bar .= '</div>';

		return $bar;
	}

	/**
	 * Build a single horizontal bar dropdown trigger.
	 *
	 * @param string $id    Dropdown identifier.
	 * @param string $label Display label.
	 * @param string $state State getter for open/close.
	 * @return string Dropdown trigger HTML.
	 */
	private function build_bar_dropdown( string $id, string $label, string $state ): string {
		$html  = sprintf(
			'<div class="aa-product-filters__bar-item" data-wp-context=\'{"dropdownId":"%s"}\'>',
			esc_attr( $id ),
		);
		$html .= sprintf(
			'<button class="aa-product-filters__bar-trigger" data-wp-on--click="actions.toggleDropdown" aria-expanded="false" data-wp-bind--aria-expanded="%s">',
			esc_attr( $state ),
		);
		$html .= esc_html( $label );
		$html .= aggressive_apparel_get_icon(
			'chevron-down',
			array(
				'width'       => 16,
				'height'      => 16,
				'aria-hidden' => 'true',
			)
		);
		$html .= '</button>';
		$html .= sprintf(
			'<div data-wp-bind--hidden="!%s">',
			esc_attr( $state ),
		);
		$html .= '<div class="aa-product-filters__bar-dropdown">';
		$html .= '</div></div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Render shared filter sections HTML.
	 *
	 * @param array<string, mixed> $data Filter source data.
	 * @return void
	 */
	public function render_sections( array $data ): void {
		// Categories (hierarchical).
		$visible_categories = $this->get_visible_categories( $data['categories'] );
		if ( ! empty( $visible_categories ) ) {
			$tree = $this->build_category_tree( $visible_categories );
			if ( ! empty( $tree ) ) {
				$this->render_section_start( 'categories', __( 'Categories', 'aggressive-apparel' ) );
				$this->render_category_tree( $tree );
				$this->render_section_end();
			}
		}

		// Fit.
		if ( ! empty( $data['fitTerms'] ) ) {
			$this->render_section_start( 'fit', __( 'Fit', 'aggressive-apparel' ) );
			echo '<div class="aa-product-filters__fit-list" role="group" aria-label="' . esc_attr__( 'Filter by fit', 'aggressive-apparel' ) . '">';
			foreach ( $data['fitTerms'] as $fit ) {
				printf(
					'<button class="aa-product-filters__fit-chip" data-wp-on--click="actions.toggleFit" data-filter-value="%s" aria-pressed="false" aria-label="%s"><span class="aa-product-filters__fit-chip-check" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><polyline points="2.5 6.5 5 9 9.5 3.5"/></svg></span><span class="aa-product-filters__fit-chip-name">%s</span></button>',
					esc_attr( $fit['slug'] ),
					/* translators: %s: fit name */
					esc_attr( sprintf( __( 'Filter by %s', 'aggressive-apparel' ), $fit['name'] ) ),
					esc_html( $fit['name'] ),
				);
			}
			echo '</div>';
			$this->render_section_end();
		}

		// Colors.
		if ( ! empty( $data['colorTerms'] ) ) {
			$this->render_section_start( 'colors', __( 'Color', 'aggressive-apparel' ) );
			echo '<div class="aa-product-filters__color-list" role="group" aria-label="' . esc_attr__( 'Filter by color', 'aggressive-apparel' ) . '">';
			foreach ( $data['colorTerms'] as $color ) {
				if ( 'pattern' === $color['type'] ) {
					$style = sprintf( 'background-image:url(%s);background-size:cover;', esc_url( $color['value'] ) );
				} else {
					$style = sprintf(
						'background-color:%s;--swatch-color:%s;',
						esc_attr( $color['value'] ),
						esc_attr( $color['value'] ),
					);
				}

				printf(
					'<button class="aa-product-filters__color-swatch" data-wp-on--click="actions.toggleColor" data-filter-value="%s" style="%s" title="%s" aria-label="%s" aria-pressed="false"><span class="screen-reader-text">%s</span></button>',
					esc_attr( $color['slug'] ),
					esc_attr( $style ),
					esc_attr( $color['name'] ),
					/* translators: %s: color name */
					esc_attr( sprintf( __( 'Filter by %s', 'aggressive-apparel' ), $color['name'] ) ),
					esc_html( $color['name'] ),
				);
			}
			echo '</div>';
			$this->render_section_end();
		}

		// Sizes.
		if ( ! empty( $data['sizeTerms'] ) ) {
			$this->render_section_start( 'sizes', __( 'Size', 'aggressive-apparel' ) );
			echo '<p class="aa-product-filters__size-hint">' . esc_html__( 'Select a category to see sizing options.', 'aggressive-apparel' ) . '</p>';
			echo '<div class="aa-product-filters__size-list" hidden role="group" aria-label="' . esc_attr__( 'Filter by size', 'aggressive-apparel' ) . '">';
			foreach ( $data['sizeTerms'] as $size ) {
				printf(
					'<button class="aa-product-filters__size-chip" data-wp-on--click="actions.toggleSize" data-filter-value="%s" aria-pressed="false" aria-label="%s"><span class="aa-product-filters__size-chip-check" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><polyline points="2.5 6.5 5 9 9.5 3.5"/></svg></span><span class="aa-product-filters__size-chip-name">%s</span></button>',
					esc_attr( $size['slug'] ),
					/* translators: %s: size name */
					esc_attr( sprintf( __( 'Filter by size %s', 'aggressive-apparel' ), $size['name'] ) ),
					esc_html( $size['name'] ),
				);
			}
			echo '</div>';
			$this->render_section_end();
		}

		// Price range.
		$range = $data['priceRange'];
		if ( $range['max'] > $range['min'] ) {
			$this->render_section_start( 'price', __( 'Price', 'aggressive-apparel' ) );
			printf(
				'<div class="aa-product-filters__price-slider" data-min="%d" data-max="%d">',
				(int) $range['min'],
				(int) $range['max'],
			);
			echo '<div class="aa-product-filters__price-track"><div class="aa-product-filters__price-range"></div></div>';
			echo '<span class="aa-product-filters__price-tooltip aa-product-filters__price-tooltip--min" data-wp-text="state.priceMinDisplay" aria-hidden="true"></span>';
			echo '<span class="aa-product-filters__price-tooltip aa-product-filters__price-tooltip--max" data-wp-text="state.priceMaxDisplay" aria-hidden="true"></span>';
			printf(
				'<input type="range" class="aa-product-filters__price-thumb aa-product-filters__price-thumb--min" min="%d" max="%d" step="1" value="%d" data-wp-on--input="actions.setPriceMin" aria-label="%s" aria-valuemin="%d" aria-valuemax="%d" aria-valuenow="%d" />',
				(int) $range['min'],
				(int) $range['max'],
				(int) $range['min'],
				esc_attr__( 'Minimum price', 'aggressive-apparel' ),
				(int) $range['min'],
				(int) $range['max'],
				(int) $range['min'],
			);
			printf(
				'<input type="range" class="aa-product-filters__price-thumb aa-product-filters__price-thumb--max" min="%d" max="%d" step="1" value="%d" data-wp-on--input="actions.setPriceMax" aria-label="%s" aria-valuemin="%d" aria-valuemax="%d" aria-valuenow="%d" />',
				(int) $range['min'],
				(int) $range['max'],
				(int) $range['max'],
				esc_attr__( 'Maximum price', 'aggressive-apparel' ),
				(int) $range['min'],
				(int) $range['max'],
				(int) $range['max'],
			);
			echo '</div>';
			$this->render_section_end();
		}

		// Availability.
		$this->render_section_start( 'stock', __( 'Availability', 'aggressive-apparel' ) );
		echo '<label class="aa-product-filters__stock-toggle">';
		echo '<span class="aa-product-filters__stock-label">' . esc_html__( 'In stock', 'aggressive-apparel' ) . '</span>';
		echo '<input type="checkbox" class="aa-product-filters__stock-checkbox" data-wp-on--change="actions.toggleInStockOnly" role="switch" />';
		echo '<span class="aa-product-filters__stock-switch"></span>';
		echo '</label>';
		echo '<label class="aa-product-filters__stock-toggle">';
		echo '<span class="aa-product-filters__stock-label">' . esc_html__( 'On sale', 'aggressive-apparel' ) . '</span>';
		echo '<input type="checkbox" class="aa-product-filters__on-sale-checkbox" data-wp-on--change="actions.toggleOnSaleOnly" role="switch" />';
		echo '<span class="aa-product-filters__stock-switch"></span>';
		echo '</label>';
		$this->render_section_end();
	}

	/**
	 * Render a collapsible section start.
	 *
	 * @param string $id    Section identifier.
	 * @param string $title Section title.
	 * @return void
	 */
	private function render_section_start( string $id, string $title ): void {
		printf(
			'<div class="aa-product-filters__section" data-section="%s">',
			esc_attr( $id ),
		);
		printf(
			'<button class="aa-product-filters__section-toggle" data-wp-on--click="actions.toggleSection" aria-expanded="true"><span class="aa-product-filters__section-title">%s</span>%s</button>',
			esc_html( $title ),
			aggressive_apparel_get_icon(
				'chevron-down',
				array(
					'width'       => 16,
					'height'      => 16,
					'class'       => 'aa-product-filters__section-icon',
					'aria-hidden' => 'true',
				)
			),
		);
		echo '<div class="aa-product-filters__section-body"><div class="aa-product-filters__section-inner">';
	}

	/**
	 * Render a section end.
	 *
	 * @return void
	 */
	private function render_section_end(): void {
		echo '</div></div></div>';
	}

	/**
	 * Build a hierarchical category tree from a flat list.
	 *
	 * Groups child categories under their parents. Top-level parents
	 * that have only one child (and no products of their own) are
	 * skipped — the child is promoted to top level.
	 *
	 * @param array $categories Flat array with 'id', 'parent', etc.
	 * @return array Tree array with 'children' key on parents.
	 */
	private function build_category_tree( array $categories ): array {
		$by_id = array();
		foreach ( $categories as $cat ) {
			$cat['children']     = array();
			$by_id[ $cat['id'] ] = $cat;
		}

		$tree = array();
		foreach ( $by_id as &$cat ) {
			if ( $cat['parent'] && isset( $by_id[ $cat['parent'] ] ) ) {
				$by_id[ $cat['parent'] ]['children'][] = &$cat;
			} else {
				$tree[] = &$cat;
			}
		}
		unset( $cat );

		return $tree;
	}

	/**
	 * Remove system-managed catalogue categories from shopper filter choices.
	 *
	 * The full category data remains in interactivity state so canonical term
	 * links and Sales archive context continue to work. Only the duplicate chip
	 * is hidden from the filter UI.
	 *
	 * @param array $categories Product category data.
	 * @return array Shopper-selectable category data.
	 */
	private function get_visible_categories( array $categories ): array {
		return array_values(
			array_filter(
				$categories,
				static fn( array $category ): bool => Sale_Category::TERM_SLUG !== ( $category['slug'] ?? '' )
			)
		);
	}

	/**
	 * Render a category tree as nested lists.
	 *
	 * @param array $nodes   Category nodes (each may have 'children').
	 * @param bool  $is_root Whether this is the top-level list.
	 * @return void
	 */
	private function render_category_tree( array $nodes, bool $is_root = true ): void {
		$class = 'aa-product-filters__category-list';
		if ( ! $is_root ) {
			$class .= ' aa-product-filters__category-list--children';
		}

		if ( $is_root ) {
			printf(
				'<ul class="%s" role="group" aria-label="%s">',
				esc_attr( $class ),
				esc_attr__( 'Filter by category', 'aggressive-apparel' ),
			);
		} else {
			printf( '<ul class="%s">', esc_attr( $class ) );
		}

		foreach ( $nodes as $cat ) {
			echo '<li class="aa-product-filters__category-item">';
			printf(
				'<button class="aa-product-filters__category-chip" data-wp-on--click="actions.toggleCategory" data-filter-value="%s" data-filter-type="category" aria-pressed="false"><span class="aa-product-filters__category-chip-check" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><polyline points="2.5 6.5 5 9 9.5 3.5"/></svg></span><span class="aa-product-filters__category-chip-name">%s</span><span class="aa-product-filters__category-chip-count">%d</span></button>',
				esc_attr( $cat['slug'] ),
				esc_html( $cat['name'] ),
				(int) $cat['count'],
			);

			if ( ! empty( $cat['children'] ) ) {
				$this->render_category_tree( $cat['children'], false );
			}

			echo '</li>';
		}

		echo '</ul>';
	}
}
