<?php
/**
 * Add-to-cart button accessibility.
 *
 * Inside "Add to Cart with Options", WooCommerce's product button marks itself
 * unavailable until a valid variation is chosen with a `disabled` *class* only.
 * Sighted users see a faded button; assistive tech hears an active one, and
 * contrast checks can't apply the inactive-control exemption (WCAG 1.4.3).
 *
 * The button mirrors that class as `aria-disabled`, bound to the same store
 * state. `aria-disabled` (not `disabled`) keeps the button clickable, so
 * WooCommerce can still answer a press with its "select options" feedback.
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
 * Exposes the add-to-cart button's unavailable state to assistive tech.
 */
final class Add_To_Cart_Button_A11y {

	/**
	 * The directive WooCommerce uses to show the button as unavailable.
	 *
	 * @var string
	 */
	private const DISABLED_CLASS_DIRECTIVE = 'data-wp-class--disabled';

	/**
	 * The directive that mirrors it for assistive tech.
	 *
	 * @var string
	 */
	private const ARIA_DISABLED_DIRECTIVE = 'data-wp-bind--aria-disabled';

	/**
	 * Register hooks.
	 */
	public function init(): void {
		add_filter( 'render_block_woocommerce/product-button', array( $this, 'mirror_disabled_state' ), 99 );
	}

	/**
	 * Bind `aria-disabled` to whatever drives the button's `disabled` class.
	 *
	 * @param string $content Rendered product button.
	 */
	public function mirror_disabled_state( string $content ): string {
		$tags = new \WP_HTML_Tag_Processor( $content );
		while ( $tags->next_tag( array( 'tag_name' => 'button' ) ) ) {
			$state = $tags->get_attribute( self::DISABLED_CLASS_DIRECTIVE );
			if ( is_string( $state ) && '' !== $state && null === $tags->get_attribute( self::ARIA_DISABLED_DIRECTIVE ) ) {
				$tags->set_attribute( self::ARIA_DISABLED_DIRECTIVE, $state );
			}
		}

		return $tags->get_updated_html();
	}
}
