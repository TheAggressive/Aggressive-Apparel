<?php
/**
 * Product Tabs no-code layout renderers.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render structured custom tab layouts from no-code admin rows.
 *
 * @since 1.17.0
 */
class Product_Tabs_Layouts {

	/**
	 * Content helper service.
	 *
	 * @var Product_Tabs_Content
	 */
	private Product_Tabs_Content $content;

	/**
	 * Constructor.
	 *
	 * @param Product_Tabs_Content $content Content helper service.
	 */
	public function __construct( Product_Tabs_Content $content ) {
		$this->content = $content;
	}

	/**
	 * Render custom tab content and optional structured layout rows.
	 *
	 * @param array  $tab     Sanitized custom tab config.
	 * @param string $content Resolved tab content.
	 * @return void
	 */
	public function render_custom_tab_content( array $tab, string $content ): void {
		$layout = (string) ( $tab['layout'] ?? 'rich_text' );
		$items  = isset( $tab['items'] ) && is_array( $tab['items'] ) ? $tab['items'] : array();

		if ( ! in_array( $layout, Product_Tabs_Config::VALID_TAB_LAYOUTS, true ) ) {
			$layout = 'rich_text';
		}

		if ( 'custom_html' === $layout || 'rich_text' === $layout || empty( $items ) ) {
			$this->content->render_product_info_content( $content );
			return;
		}

		$this->content->render_product_info_content( $content );
		$this->render_tab_layout_items( $layout, $items );
	}

	/**
	 * Render layout items for a supported no-code layout.
	 *
	 * @param string $layout Layout key.
	 * @param array  $items  Sanitized layout item rows.
	 * @return void
	 */
	public function render_tab_layout_items( string $layout, array $items ): void {
		if ( empty( $items ) ) {
			return;
		}

		if ( 'specs_table' === $layout ) {
			$this->render_specs_table_items( $items );
			return;
		}

		if ( 'faq' === $layout ) {
			$this->render_faq_items( $items );
			return;
		}

		$class = 'aa-product-tab-layout aa-product-tab-layout--' . sanitize_html_class( str_replace( '_', '-', $layout ) );
		$list  = 'shipping_timeline' === $layout ? 'ol' : 'ul';

		echo '<' . esc_html( $list ) . ' class="' . esc_attr( $class ) . '">';

		foreach ( $items as $item ) {
			$this->render_card_like_item( $layout, $item );
		}

		echo '</' . esc_html( $list ) . '>';
	}

	/**
	 * Render one list/card style layout item.
	 *
	 * @param string $layout Layout key.
	 * @param array  $item   Sanitized item row.
	 * @return void
	 */
	public function render_card_like_item( string $layout, array $item ): void {
		$item_class = 'aa-product-tab-layout__item';

		if ( 'shipping_timeline' === $layout ) {
			$item_class .= ' aa-product-tab-layout__item--timeline';
		}

		echo '<li class="' . esc_attr( $item_class ) . '">';

		if ( ! empty( $item['icon'] ) ) {
			echo '<span class="aa-product-tab-layout__icon" aria-hidden="true">' . esc_html( (string) $item['icon'] ) . '</span>';
		}

		echo '<div class="aa-product-tab-layout__body">';

		if ( ! empty( $item['meta'] ) ) {
			echo '<p class="aa-product-tab-layout__meta">' . esc_html( (string) $item['meta'] ) . '</p>';
		}

		if ( ! empty( $item['title'] ) ) {
			echo '<h4 class="aa-product-tab-layout__title">' . esc_html( (string) $item['title'] ) . '</h4>';
		}

		if ( ! empty( $item['text'] ) ) {
			echo '<div class="aa-product-tab-layout__text">';
			$this->content->render_product_info_content( (string) $item['text'] );
			echo '</div>';
		}

		echo '</div>';
		echo '</li>';
	}

	/**
	 * Render layout rows as a specification table.
	 *
	 * @param array $items Sanitized layout item rows.
	 * @return void
	 */
	public function render_specs_table_items( array $items ): void {
		echo '<table class="aa-product-tab-layout aa-product-tab-layout--specs-table"><tbody>';

		foreach ( $items as $item ) {
			if ( empty( $item['title'] ) && empty( $item['text'] ) ) {
				continue;
			}

			echo '<tr>';
			echo '<th scope="row">' . esc_html( (string) ( $item['title'] ?? '' ) ) . '</th>';
			echo '<td>' . wp_kses_post( (string) ( $item['text'] ?? '' ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Render layout rows as FAQ disclosure items.
	 *
	 * @param array $items Sanitized layout item rows.
	 * @return void
	 */
	public function render_faq_items( array $items ): void {
		echo '<div class="aa-product-tab-layout aa-product-tab-layout--faq">';

		foreach ( $items as $item ) {
			if ( empty( $item['title'] ) && empty( $item['text'] ) ) {
				continue;
			}

			echo '<details class="aa-product-tab-layout__faq-item">';
			echo '<summary class="aa-product-tab-layout__faq-question">' . esc_html( (string) ( $item['title'] ?? '' ) ) . '</summary>';
			echo '<div class="aa-product-tab-layout__faq-answer">';
			$this->content->render_product_info_content( (string) ( $item['text'] ?? '' ) );
			echo '</div>';
			echo '</details>';
		}

		echo '</div>';
	}
}
