<?php
/**
 * Product Tabs content helpers.
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
 * Tab content rendering and kses helpers.
 *
 * @since 1.17.0
 */
class Product_Tabs_Content {

	/**
	 * Sanitize rendered WooCommerce tab HTML while preserving form markup.
	 *
	 * @param string $content Tab HTML.
	 * @return string Sanitized tab HTML.
	 */
	public function kses_tab_content( string $content ): string {
		// wp_kses() strips <script>/<style> tags but keeps their inner text, so
		// inline JS/CSS (e.g. WooCommerce's comment-form unfiltered-html script)
		// would render as visible source. Remove those blocks — tag and contents
		// — before sanitizing. We never want to allow them, so this is dropped
		// entirely rather than escaped.
		$stripped = preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', '', $content );
		$content  = is_string( $stripped ) ? $stripped : $content;

		$allowed = wp_kses_allowed_html( 'post' );

		// Form elements needed by the review form.
		$allowed['form']     = array(
			'action'     => true,
			'method'     => true,
			'class'      => true,
			'id'         => true,
			'novalidate' => true,
			'enctype'    => true,
		);
		$allowed['input']    = array(
			'type'          => true,
			'name'          => true,
			'value'         => true,
			'id'            => true,
			'class'         => true,
			'placeholder'   => true,
			'required'      => true,
			'checked'       => true,
			'aria-required' => true,
			'size'          => true,
		);
		$allowed['select']   = array(
			'name'          => true,
			'id'            => true,
			'class'         => true,
			'required'      => true,
			'aria-required' => true,
		);
		$allowed['option']   = array(
			'value'    => true,
			'selected' => true,
		);
		$allowed['textarea'] = array(
			'name'          => true,
			'id'            => true,
			'class'         => true,
			'rows'          => true,
			'cols'          => true,
			'required'      => true,
			'aria-required' => true,
			'placeholder'   => true,
		);
		$allowed['button']   = array(
			'type'     => true,
			'name'     => true,
			'value'    => true,
			'id'       => true,
			'class'    => true,
			'disabled' => true,
		);
		$allowed['label']    = array(
			'for'   => true,
			'class' => true,
		);

		// Ensure style attribute is allowed on span (star rating width).
		if ( isset( $allowed['span'] ) ) {
			$allowed['span']['style'] = true;
		} else {
			$allowed['span'] = array(
				'style' => true,
				'class' => true,
			);
		}

		// Allow style on div too (some WC markup uses it).
		if ( isset( $allowed['div'] ) ) {
			$allowed['div']['style'] = true;
		}

		return wp_kses( $content, $allowed );
	}

	/**
	 * Render rich text or block-like tab content.
	 *
	 * @param string $content Tab content.
	 * @return void
	 */
	public function render_product_info_content( string $content ): void {
		$content = trim( $content );

		if ( '' === $content ) {
			return;
		}

		if ( $this->has_block_markup( $content ) ) {
			echo wp_kses_post( $content );
			return;
		}

		echo wp_kses_post( wpautop( $content ) );
	}

	/**
	 * Check whether content already contains block-level HTML.
	 *
	 * @param string $content Tab content.
	 * @return bool True when block-level markup exists.
	 */
	public function has_block_markup( string $content ): bool {
		return 1 === preg_match(
			'/<\/?(address|article|aside|blockquote|details|div|dl|dt|dd|figure|figcaption|footer|form|h[1-6]|header|hr|li|main|nav|ol|p|pre|section|summary|table|tbody|td|tfoot|th|thead|tr|ul)\b/i',
			$content
		);
	}

	/**
	 * Resolve custom tab content from the selected source.
	 *
	 * @param array $tab        Sanitized custom tab config.
	 * @param int   $product_id Product ID.
	 * @return string Resolved content.
	 */
	public function resolve_custom_tab_content( array $tab, int $product_id ): string {
		$fallback = (string) ( $tab['content'] ?? '' );
		$source   = (string) ( $tab['source'] ?? 'manual' );

		if ( 'product_meta' === $source && $product_id && ! empty( $tab['metaField'] ) ) {
			$value = get_post_meta( $product_id, (string) $tab['metaField'], true );
			$value = is_scalar( $value ) ? trim( (string) $value ) : '';

			return '' !== $value ? $value : $fallback;
		}

		if ( 'product_attribute' === $source && $product_id && ! empty( $tab['attribute'] ) && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			$value   = $product ? trim( wp_strip_all_tags( $product->get_attribute( (string) $tab['attribute'] ) ) ) : '';

			return '' !== $value ? $value : $fallback;
		}

		return $fallback;
	}

	/**
	 * Determine whether a custom tab has renderable content.
	 *
	 * @param array  $tab     Sanitized custom tab config.
	 * @param string $content Resolved tab content.
	 * @return bool True when the tab can render meaningful content.
	 */
	public function has_custom_tab_content( array $tab, string $content ): bool {
		if ( '' !== trim( wp_strip_all_tags( $content ) ) ) {
			return true;
		}

		return ! empty( $tab['items'] ) && is_array( $tab['items'] );
	}
}
