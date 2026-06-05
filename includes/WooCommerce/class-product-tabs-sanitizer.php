<?php
/**
 * Product Tabs sanitization helpers.
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
 * Sanitization and override helpers for Product Tabs.
 *
 * @since 1.17.0
 */
class Product_Tabs_Sanitizer {

	/**
	 * Sanitize global Product Tabs settings.
	 *
	 * @param mixed $input Raw option input.
	 * @return array Sanitized option data.
	 */
	public function sanitize_global_tabs( $input ): array {
		$output = array();
		if ( ! is_array( $input ) ) {
			return $output;
		}

		foreach ( $input as $key => $value ) {
			if ( 'display_style' === $key ) {
				$output['display_style'] = in_array( $value, Product_Tabs_Config::VALID_STYLES, true ) ? $value : 'accordion';
			} elseif ( 'custom_tabs' === $key ) {
				$output['custom_tabs'] = $this->sanitize_custom_tabs( $value );
			} else {
				$output[ sanitize_key( $key ) ] = wp_kses_post( (string) $value );
			}
		}

		return $output;
	}

	/**
	 * Sanitize custom tab rows.
	 *
	 * @param mixed $tabs Raw custom tab rows.
	 * @return array<int, array<string, mixed>> Sanitized custom tab rows.
	 */
	public function sanitize_custom_tabs( $tabs ): array {
		if ( ! is_array( $tabs ) ) {
			return array();
		}

		$output = array();

		foreach ( $tabs as $index => $tab ) {
			if ( ! is_array( $tab ) ) {
				continue;
			}

			$title      = sanitize_text_field( (string) ( $tab['title'] ?? '' ) );
			$content    = wp_kses_post( (string) ( $tab['content'] ?? '' ) );
			$source     = sanitize_key( (string) ( $tab['source'] ?? 'manual' ) );
			$layout     = sanitize_key( (string) ( $tab['layout'] ?? 'rich_text' ) );
			$meta_field = sanitize_key( (string) ( $tab['metaField'] ?? $tab['meta_key'] ?? '' ) );
			$attribute  = sanitize_key( (string) ( $tab['attribute'] ?? '' ) );
			$items      = $this->sanitize_tab_items( $tab['items'] ?? array() );

			if ( ! in_array( $source, Product_Tabs_Config::VALID_TAB_SOURCES, true ) ) {
				$source = 'manual';
			}

			if ( ! in_array( $layout, Product_Tabs_Config::VALID_TAB_LAYOUTS, true ) ) {
				$layout = 'rich_text';
			}

			$key = sanitize_key( (string) ( $tab['key'] ?? '' ) );
			if ( '' === $key ) {
				$key = sanitize_key( $title );
			}
			if ( '' === $key ) {
				$key = 'custom_tab_' . absint( $index );
			}

			if (
					'' === $title &&
					'' === trim( wp_strip_all_tags( $content ) ) &&
					'' === $meta_field &&
					'' === $attribute &&
					empty( $items )
				) {
				continue;
			}

			$output[] = array(
				'key'       => $key,
				'enabled'   => isset( $tab['enabled'] ) && '0' !== (string) $tab['enabled'],
				'title'     => $title,
				'priority'  => max( 1, min( 999, absint( $tab['priority'] ?? 40 ) ) ),
				'source'    => $source,
				'layout'    => $layout,
				'metaField' => $meta_field,
				'attribute' => $attribute,
				'items'     => $items,
				'content'   => $content,
			);
		}

		return $output;
	}

	/**
	 * Sanitize no-code layout item rows.
	 *
	 * @param mixed $items Raw layout item rows.
	 * @return array<int, array<string, string>> Sanitized layout item rows.
	 */
	public function sanitize_tab_items( $items ): array {
		if ( ! is_array( $items ) ) {
			return array();
		}

		$output = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$icon  = sanitize_text_field( (string) ( $item['icon'] ?? '' ) );
			$title = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
			$text  = wp_kses_post( (string) ( $item['text'] ?? '' ) );
			$meta  = sanitize_text_field( (string) ( $item['meta'] ?? '' ) );

			if ( '' === $icon && '' === $title && '' === trim( wp_strip_all_tags( $text ) ) && '' === $meta ) {
				continue;
			}

			$output[] = array(
				'icon'  => $icon,
				'title' => $title,
				'text'  => $text,
				'meta'  => $meta,
			);
		}

		return $output;
	}

	/**
	 * Sanitize per-product global tab overrides.
	 *
	 * @param mixed $overrides Raw override rows.
	 * @return array<string, array<string, string>> Sanitized override rows.
	 */
	public function sanitize_tab_overrides( $overrides ): array {
		if ( ! is_array( $overrides ) ) {
			return array();
		}

		$output = array();

		foreach ( $overrides as $key => $override ) {
			if ( ! is_array( $override ) ) {
				continue;
			}

			$mode = sanitize_key( (string) ( $override['mode'] ?? 'inherit' ) );
			if ( ! in_array( $mode, Product_Tabs_Config::VALID_OVERRIDE_MODES, true ) ) {
				$mode = 'inherit';
			}

			$content = wp_kses_post( (string) ( $override['content'] ?? '' ) );

			if ( 'inherit' === $mode && '' === trim( wp_strip_all_tags( $content ) ) ) {
				continue;
			}

			$output[ sanitize_key( (string) $key ) ] = array(
				'mode'    => $mode,
				'content' => $content,
			);
		}

		return $output;
	}

	/**
	 * Get sanitized tab overrides for a product.
	 *
	 * @param int $post_id Product post ID.
	 * @return array<string, array<string, string>> Sanitized override rows.
	 */
	public function get_product_tab_overrides( int $post_id ): array {
		$overrides = get_post_meta( $post_id, Product_Tabs_Config::PRODUCT_TAB_OVERRIDES_META_KEY, true );

		return $this->sanitize_tab_overrides( $overrides );
	}

	/**
	 * Apply a per-product override to global tab content.
	 *
	 * @param string $key       Tab key.
	 * @param string $content   Global tab content.
	 * @param array  $overrides Sanitized override rows.
	 * @return string Updated content, or an empty string when hidden.
	 */
	public function apply_tab_override_content( string $key, string $content, array $overrides ): string {
		$override = $overrides[ $key ] ?? array();

		if ( ! is_array( $override ) ) {
			return $content;
		}

		$mode  = (string) ( $override['mode'] ?? 'inherit' );
		$extra = (string) ( $override['content'] ?? '' );

		if ( 'hide' === $mode ) {
			return '';
		}

		if ( 'override' === $mode ) {
			return $extra;
		}

		if ( 'append' === $mode && '' !== trim( wp_strip_all_tags( $extra ) ) ) {
			return $content . "\n\n" . $extra;
		}

		return $content;
	}
}
