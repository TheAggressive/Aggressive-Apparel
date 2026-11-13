<?php
/**
 * Product Tabs — custom tabs repeater
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
 * The custom-tabs repeater on the global Product Tabs page and the product
 * meta box: tab rows, their attribute-driven items and the live preview.
 * Markup only; Product_Tabs_Admin owns saving and the screens.
 */
final class Product_Tabs_Repeater {

	/**
	 * Render a custom tabs repeater UI.
	 *
	 * @param array  $tabs        Sanitized custom tabs.
	 * @param string $name_prefix Form field prefix.
	 * @param string $id          Repeater element ID.
	 * @param string $description Repeater help text.
	 * @param int    $product_id  Product post ID for attribute options.
	 * @return void
	 */
	public function render_custom_tabs_repeater( array $tabs, string $name_prefix, string $id, string $description, int $product_id = 0 ): void {
		$rows = ! empty( $tabs )
			? array_values( $tabs )
			: array(
				array(
					'enabled'  => true,
					'key'      => '',
					'title'    => '',
					'priority' => 40,
					'source'   => 'manual',
					'layout'   => 'rich_text',
					'items'    => array(),
					'content'  => '',
				),
			);

		$attribute_options = $product_id > 0 ? $this->get_product_attribute_options( $product_id ) : array();

		$attribute_options_json = wp_json_encode( $attribute_options );
		if ( false === $attribute_options_json ) {
			$attribute_options_json = '{}';
		}

		echo '<div class="aa-custom-tabs-repeater" id="' . esc_attr( $id ) . '" data-name-prefix="' . esc_attr( $name_prefix ) . '" data-next-index="' . esc_attr( (string) count( $rows ) ) . '" data-attribute-options="' . esc_attr( $attribute_options_json ) . '">';
		echo '<div class="aa-custom-tabs-repeater__header">';
		echo '<p class="description">' . esc_html( $description ) . '</p>';
		echo '<div class="aa-custom-tabs-repeater__actions">';
		echo '<button type="button" class="button button-primary aa-custom-tabs-repeater__add" data-template="blank">' . esc_html__( 'Add Blank Tab', 'aggressive-apparel' ) . '</button>';
		echo '</div>';
		echo '</div>';
		echo '<div class="aa-custom-tabs-template-bar" aria-label="' . esc_attr__( 'Tab templates', 'aggressive-apparel' ) . '">';
		echo '<span class="aa-custom-tabs-template-bar__label">' . esc_html__( 'Quick templates', 'aggressive-apparel' ) . '</span>';
		echo '<button type="button" class="button aa-custom-tabs-template" data-template="size_fit">' . esc_html__( 'Size & Fit', 'aggressive-apparel' ) . '</button>';
		echo '<button type="button" class="button aa-custom-tabs-template" data-template="shipping_returns">' . esc_html__( 'Shipping & Returns', 'aggressive-apparel' ) . '</button>';
		echo '<button type="button" class="button aa-custom-tabs-template" data-template="faq">' . esc_html__( 'FAQ', 'aggressive-apparel' ) . '</button>';
		echo '<button type="button" class="button aa-custom-tabs-template" data-template="specs">' . esc_html__( 'Specs Table', 'aggressive-apparel' ) . '</button>';
		echo '<button type="button" class="button aa-custom-tabs-template" data-template="care">' . esc_html__( 'Care Grid', 'aggressive-apparel' ) . '</button>';
		echo '</div>';
		echo '<div class="aa-custom-tabs-manager">';
		echo '<aside class="aa-custom-tabs-manager__sidebar">';
		echo '<div class="aa-custom-tabs-manager__sidebar-header">';
		echo '<p class="aa-custom-tabs-manager__sidebar-title">' . esc_html__( 'Tabs', 'aggressive-apparel' ) . '</p>';
		echo '<span class="aa-custom-tabs-manager__count"></span>';
		echo '</div>';
		echo '<div class="aa-custom-tabs-manager__list" role="listbox" aria-label="' . esc_attr__( 'Custom tabs', 'aggressive-apparel' ) . '"></div>';
		echo '<p class="description">' . esc_html__( 'Select a tab to edit it. Drag or use arrows to reorder.', 'aggressive-apparel' ) . '</p>';
		echo '</aside>';
		echo '<div class="aa-custom-tabs-repeater__rows">';

		foreach ( $rows as $index => $tab ) {
			$this->render_custom_tab_row( $name_prefix, $index, $tab, $attribute_options );
		}

		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render one custom tab repeater row.
	 *
	 * @param string $name_prefix       Form field prefix.
	 * @param int    $index             Row index.
	 * @param array  $tab               Sanitized custom tab row.
	 * @param array  $attribute_options Product attribute options.
	 * @return void
	 */
	private function render_custom_tab_row( string $name_prefix, int $index, array $tab, array $attribute_options = array() ): void {
		$enabled_name  = sprintf( '%s[%d][enabled]', $name_prefix, $index );
		$key_name      = sprintf( '%s[%d][key]', $name_prefix, $index );
		$title_name    = sprintf( '%s[%d][title]', $name_prefix, $index );
		$priority_name = sprintf( '%s[%d][priority]', $name_prefix, $index );
		$source_name   = sprintf( '%s[%d][source]', $name_prefix, $index );
		$layout_name   = sprintf( '%s[%d][layout]', $name_prefix, $index );
		$meta_key_name = sprintf( '%s[%d][metaField]', $name_prefix, $index );
		$attr_name     = sprintf( '%s[%d][attribute]', $name_prefix, $index );
		$content_name  = sprintf( '%s[%d][content]', $name_prefix, $index );
		$source        = (string) ( $tab['source'] ?? 'manual' );
		$layout        = (string) ( $tab['layout'] ?? 'rich_text' );
		$sources       = Product_Tabs_Config::get_tab_source_labels();
		$layouts       = Product_Tabs_Config::get_tab_layout_labels();
		$title         = '' !== (string) ( $tab['title'] ?? '' ) ? (string) $tab['title'] : __( 'Untitled tab', 'aggressive-apparel' );
		$is_enabled    = ! empty( $tab['enabled'] );

			echo '<div class="aa-custom-tabs-repeater__row aa-custom-tabs-card" data-tab-index="' . esc_attr( (string) $index ) . '" data-layout="' . esc_attr( $layout ) . '" data-source="' . esc_attr( $source ) . '">';
			echo '<div class="aa-custom-tabs-card__header">';
			echo '<div>';
			echo '<p class="aa-custom-tabs-card__eyebrow">' . esc_html__( 'Selected tab', 'aggressive-apparel' ) . '</p>';
			echo '<h3 class="aa-custom-tabs-card__title">' . esc_html( $title ) . '</h3>';
			echo '</div>';
			echo '<span class="aa-custom-tabs-card__meta">';
			echo '<span class="aa-tabs-badge aa-tabs-badge--enabled">' . esc_html( $is_enabled ? __( 'Enabled', 'aggressive-apparel' ) : __( 'Disabled', 'aggressive-apparel' ) ) . '</span>';
			echo '<span class="aa-tabs-badge aa-tabs-badge--layout">' . esc_html( $layouts[ $layout ] ?? $layouts['rich_text'] ) . '</span>';
			echo '<span class="aa-tabs-badge aa-tabs-badge--render">' . esc_html__( 'Ready', 'aggressive-apparel' ) . '</span>';
			echo '</span>';
			echo '</div>';
			echo '<div class="aa-custom-tabs-card__body">';
			echo '<input type="hidden" name="' . esc_attr( $key_name ) . '" value="' . esc_attr( (string) ( $tab['key'] ?? '' ) ) . '">';
			echo '<p class="aa-custom-tabs-repeater__toolbar">';
			echo '<label><input type="hidden" name="' . esc_attr( $enabled_name ) . '" value="0"><input type="checkbox" name="' . esc_attr( $enabled_name ) . '" value="1"' . checked( ! empty( $tab['enabled'] ), true, false ) . '> ' . esc_html__( 'Enabled', 'aggressive-apparel' ) . '</label>';
				echo '<label>' . esc_html__( 'Priority', 'aggressive-apparel' ) . ' <input type="number" min="1" max="999" step="1" name="' . esc_attr( $priority_name ) . '" value="' . esc_attr( (string) $tab['priority'] ) . '" class="aa-custom-tabs-repeater__priority-input"></label>';
			echo '<button type="button" class="button button-secondary aa-custom-tabs-repeater__remove">' . esc_html__( 'Remove Tab', 'aggressive-apparel' ) . '</button>';
			echo '</p>';
			echo '<div class="aa-custom-tabs-repeater__grid">';
				echo '<p><label>' . esc_html__( 'Tab Title', 'aggressive-apparel' ) . '<br><input type="text" name="' . esc_attr( $title_name ) . '" value="' . esc_attr( $tab['title'] ) . '" class="regular-text" placeholder="' . esc_attr__( 'Size & Fit', 'aggressive-apparel' ) . '"></label></p>';
			echo '<p><label>' . esc_html__( 'Content Layout', 'aggressive-apparel' ) . '<br><select class="aa-custom-tabs-layout-select" name="' . esc_attr( $layout_name ) . '">';
		foreach ( $layouts as $value => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $value ),
				selected( $layout, $value, false ),
				esc_html( $label )
			);
		}
			echo '</select></label><span class="description aa-custom-tabs-layout-help" aria-live="polite"></span></p>';
			echo '<p><label>' . esc_html__( 'Content Source', 'aggressive-apparel' ) . '<br><select class="aa-custom-tabs-source-select" name="' . esc_attr( $source_name ) . '">';
		foreach ( $sources as $value => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $value ),
				selected( $source, $value, false ),
				esc_html( $label )
			);
		}
			echo '</select></label></p>';
			echo '</div>';
			echo '<div class="aa-custom-tabs-source-fields">';
				echo '<p data-source-field="meta"><label>' . esc_html__( 'Product Meta Field', 'aggressive-apparel' ) . '<br><input type="text" name="' . esc_attr( $meta_key_name ) . '" value="' . esc_attr( (string) ( $tab['metaField'] ?? $tab['meta_key'] ?? '' ) ) . '" class="regular-text" placeholder="_my_meta_key"></label><span class="description">' . esc_html__( 'Used only when Content Source is Product meta field.', 'aggressive-apparel' ) . '</span></p>';
				echo '<p data-source-field="attribute"><label>' . esc_html__( 'Product Attribute', 'aggressive-apparel' ) . '<br>';
				$this->render_attribute_field( $attr_name, (string) ( $tab['attribute'] ?? '' ), $attribute_options );
				echo '</label><span class="description">' . esc_html__( 'Used only when Content Source is Product attribute.', 'aggressive-apparel' ) . '</span></p>';
			echo '</div>';
			echo '<p><label class="aa-custom-tabs-content-label">' . esc_html__( 'Intro / Fallback Content', 'aggressive-apparel' ) . '<br><textarea name="' . esc_attr( $content_name ) . '" rows="5" class="large-text">' . esc_textarea( (string) $tab['content'] ) . '</textarea></label><span class="description aa-custom-tabs-content-help">' . esc_html__( 'For structured layouts, this appears above the generated layout. For rich text, this is the full tab content.', 'aggressive-apparel' ) . '</span></p>';
				echo '<p class="aa-custom-tabs-validation" aria-live="polite"></p>';
				$this->render_custom_tab_items( $name_prefix, $index, isset( $tab['items'] ) && is_array( $tab['items'] ) ? $tab['items'] : array() );
				$this->render_custom_tab_preview();
				echo '</div>';
				echo '</div>';
	}

	/**
	 * Get attribute slug options for a product edit screen.
	 *
	 * @param int $product_id Product post ID.
	 * @return array<string, string> Attribute slug => label.
	 */
	private function get_product_attribute_options( int $product_id ): array {
		if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
			return array();
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array();
		}

		$options = array();

		foreach ( $product->get_attributes() as $attribute ) {
			if ( ! $attribute instanceof \WC_Product_Attribute ) {
				continue;
			}

			$name = $attribute->get_name();
			if ( '' === $name ) {
				continue;
			}

			$label            = wc_attribute_label( $name, $product );
			$options[ $name ] = is_string( $label ) && '' !== $label ? $label : $name;
		}

		return $options;
	}

	/**
	 * Render the attribute source field as a select or text input.
	 *
	 * @param string               $name              Input name.
	 * @param string               $selected          Selected attribute slug.
	 * @param array<string,string> $attribute_options Available attributes.
	 * @return void
	 */
	private function render_attribute_field( string $name, string $selected, array $attribute_options ): void {
		if ( empty( $attribute_options ) ) {
			printf(
				'<input type="text" name="%1$s" value="%2$s" class="regular-text" placeholder="pa_material">',
				esc_attr( $name ),
				esc_attr( $selected )
			);
			return;
		}

		echo '<select name="' . esc_attr( $name ) . '" class="regular-text">';
		echo '<option value="">' . esc_html__( 'Select attribute…', 'aggressive-apparel' ) . '</option>';

		foreach ( $attribute_options as $slug => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( (string) $slug ),
				selected( $selected, (string) $slug, false ),
				esc_html( (string) $label )
			);
		}

		echo '</select>';
	}

	/**
	 * Render no-code layout item rows for a custom tab.
	 *
	 * @param string $name_prefix Form field prefix.
	 * @param int    $tab_index   Parent tab row index.
	 * @param array  $items       Sanitized layout item rows.
	 * @return void
	 */
	private function render_custom_tab_items( string $name_prefix, int $tab_index, array $items ): void {
		$rows = ! empty( $items )
			? array_values( $items )
			: array(
				array(
					'icon'  => '',
					'title' => '',
					'text'  => '',
					'meta'  => '',
				),
			);

		echo '<div class="aa-custom-tab-items" data-next-item-index="' . esc_attr( (string) count( $rows ) ) . '">';
		echo '<p class="aa-custom-tab-items__header"><strong>' . esc_html__( 'No-Code Layout Items', 'aggressive-apparel' ) . '</strong><br><span class="description">' . esc_html__( 'Add the rows this layout will render. The theme handles the frontend markup and styling.', 'aggressive-apparel' ) . '</span></p>';
		echo '<div class="aa-custom-tab-items__rows">';

		foreach ( $rows as $item_index => $item ) {
			$this->render_custom_tab_item_row( $name_prefix, $tab_index, $item_index, $item );
		}

		echo '</div>';
		echo '<button type="button" class="button aa-custom-tab-items__add">' . esc_html__( 'Add Layout Item', 'aggressive-apparel' ) . '</button>';
		echo '<p class="description">' . esc_html__( 'Use these rows for cards, specs tables, FAQs, timelines, care grids, and icon lists. Leave them empty for rich text or custom HTML tabs.', 'aggressive-apparel' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Render one no-code layout item row.
	 *
	 * @param string $name_prefix Form field prefix.
	 * @param int    $tab_index   Parent tab row index.
	 * @param int    $item_index  Item row index.
	 * @param array  $item        Sanitized item row.
	 * @return void
	 */
	private function render_custom_tab_item_row( string $name_prefix, int $tab_index, int $item_index, array $item ): void {
		$base = sprintf( '%s[%d][items][%d]', $name_prefix, $tab_index, $item_index );

		echo '<div class="aa-custom-tab-items__row">';
		echo '<p class="aa-custom-tab-items__row-fields">';
		echo '<label>' . esc_html__( 'Icon', 'aggressive-apparel' ) . '<br><input type="text" name="' . esc_attr( $base . '[icon]' ) . '" value="' . esc_attr( (string) ( $item['icon'] ?? '' ) ) . '" class="aa-custom-tab-items__icon-input" placeholder="✓"></label>';
		echo '<label>' . esc_html__( 'Label / Question / Step', 'aggressive-apparel' ) . '<br><input type="text" name="' . esc_attr( $base . '[title]' ) . '" value="' . esc_attr( (string) ( $item['title'] ?? '' ) ) . '" class="regular-text"></label>';
		echo '<label>' . esc_html__( 'Detail / Eyebrow', 'aggressive-apparel' ) . '<br><input type="text" name="' . esc_attr( $base . '[meta]' ) . '" value="' . esc_attr( (string) ( $item['meta'] ?? '' ) ) . '" class="regular-text"></label>';
		echo '<button type="button" class="button button-small aa-custom-tab-items__remove">' . esc_html__( 'Remove Item', 'aggressive-apparel' ) . '</button>';
		echo '</p>';
		echo '<p><label>' . esc_html__( 'Text / Answer / Value', 'aggressive-apparel' ) . '<br><textarea name="' . esc_attr( $base . '[text]' ) . '" rows="3" class="large-text">' . esc_textarea( (string) ( $item['text'] ?? '' ) ) . '</textarea></label></p>';
		echo '</div>';
	}

	/**
	 * Render the live frontend-style preview shell.
	 *
	 * @return void
	 */
	private function render_custom_tab_preview(): void {
		echo '<div class="aa-custom-tabs-preview">';
		echo '<p class="aa-custom-tabs-preview__label"><strong>' . esc_html__( 'Frontend Preview', 'aggressive-apparel' ) . '</strong><br><span class="description">' . esc_html__( 'Updates as you edit. Custom HTML is shown as plain text in the preview for admin safety.', 'aggressive-apparel' ) . '</span></p>';
		echo '<div class="aa-custom-tabs-preview__surface">';
		echo '<h4 class="aa-custom-tabs-preview__title"></h4>';
		echo '<div class="aa-product-info__content aa-custom-tabs-preview__content"></div>';
		echo '</div>';
		echo '</div>';
	}
}
