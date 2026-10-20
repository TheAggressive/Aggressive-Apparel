<?php
/**
 * Size Guide Label Link
 *
 * Adds a size guide link beside the size attribute's name in the add to
 * cart form ("(Guide)"; "Size guide" to screen readers),
 * opening the product's size guide.
 *
 * The name itself stays plain text: WooCommerce uses it as the accessible
 * name of the size options (aria-labelledby), so turning it into a button
 * would rename the options or leave a button just called "Size".
 *
 * The link starts hidden (by class, which survives kses) and the size guide
 * script reveals it when the page has a size guide to open
 * (src/interactivity/size-guide/open.ts).
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
 * "Size guide" link beside the size attribute name.
 */
final class Size_Guide_Label_Link {

	/**
	 * Attribute names that mean "size", in the store's likely languages.
	 *
	 * @var string
	 */
	private const SIZE_NAME = '/\b(size|sizes|taille|talla|tallas|größe|grösse|groesse|taglia|maat|tamanho|rozmiar|storlek|størrelse|koko)\b/iu';

	/**
	 * Register hooks.
	 */
	public function init(): void {
		add_filter( 'render_block_woocommerce/add-to-cart-with-options-variation-selector-attribute-name', array( $this, 'render' ), 10, 3 );
	}

	/**
	 * Wrap a size attribute's name with the link.
	 *
	 * @param string    $content Block markup.
	 * @param array     $block   Parsed block.
	 * @param \WP_Block $instance Block instance.
	 * @phpstan-param array<string, mixed> $block
	 */
	public function render( string $content, array $block, \WP_Block $instance ): string {
		unset( $block );

		// The attribute's name from block context ("pa_size" or "Size"),
		// else the label text.
		$name = isset( $instance->context['woocommerce/attributeName'] ) && is_string( $instance->context['woocommerce/attributeName'] )
			? $instance->context['woocommerce/attributeName']
			: wp_strip_all_tags( $content );
		if ( ! self::is_size( (string) preg_replace( '/^pa_/', '', $name ) ) && ! self::is_size( wp_strip_all_tags( $content ) ) ) {
			return $content;
		}

		// The block has no postId context; it renders inside the product's loop.
		$product_id = absint( get_the_ID() );
		if ( $product_id <= 0 || ! Size_Guide::resolved( $product_id )['dialog'] ) {
			return $content;
		}

		// Shows "(Guide)"; screen readers hear "Size guide" (the parentheses
		// are hidden, and the visible word is part of the name, WCAG 2.5.3).
		return sprintf(
			'<div class="aa-size-label">%1$s<button type="button" class="aa-size-label__link is-pending" aria-haspopup="dialog"><span class="screen-reader-text">%2$s </span><span aria-hidden="true">(</span>%3$s<span aria-hidden="true">)</span></button></div>',
			$content,
			esc_html_x( 'Size', 'start of the "Size guide" link name, read only by screen readers', 'aggressive-apparel' ),
			esc_html_x( 'Guide', 'visible text of the size guide link beside the size options', 'aggressive-apparel' )
		);
	}

	/**
	 * Whether an attribute name means size.
	 *
	 * @param string $name Attribute name.
	 */
	public static function is_size( string $name ): bool {
		return 1 === preg_match( self::SIZE_NAME, trim( $name ) );
	}
}
