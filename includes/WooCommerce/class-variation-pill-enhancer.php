<?php
/**
 * Variation Pill Enhancer Class
 *
 * Transforms WooCommerce's default variation option pills into animated
 * morphing pills matching the quick view / sticky cart pattern.
 *
 * Injects a checkmark SVG and wraps the label text in a span so CSS can
 * animate the wipe-fill, checkmark bounce-in, and text slide on selection.
 *
 * @package Aggressive_Apparel
 * @since 1.45.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Variation Pill Enhancer Class
 *
 * Hooks into WooCommerce block rendering to restructure non-color
 * attribute pill labels for the morphing pill animation.
 *
 * @since 1.45.0
 */
class Variation_Pill_Enhancer {

	/**
	 * Initialize the pill enhancer.
	 *
	 * @return void
	 */
	public function init(): void {
		// Priority 30: after Color_Block_Swatch_Manager (10) and Size_Option_Sorter (20).
		add_filter( 'render_block', array( $this, 'enhance_variation_pills' ), 30, 2 );
	}

	/**
	 * Enhance non-color variation pills with checkmark and text wrapper.
	 *
	 * @param string               $block_content The block content.
	 * @param array<string, mixed> $block         The block data.
	 * @return string Modified block content.
	 */
	public function enhance_variation_pills( string $block_content, array $block ): string {
		if ( ! Block_Pill_Helper::is_attribute_options_block( $block ) ) {
			return $block_content;
		}

		return $this->inject_pill_markup( $block_content );
	}

	/**
	 * Restructure pill labels using DOMDocument.
	 *
	 * For each non-color pill label:
	 * 1. Adds the `aa-variation-pill` class
	 * 2. Injects a checkmark span after the input
	 * 3. Wraps the text content in a name span
	 *
	 * @param string $block_content The block content to modify.
	 * @return string Modified block content.
	 */
	private function inject_pill_markup( string $block_content ): string {
		$dom = Block_Pill_Helper::load_dom( $block_content );
		if ( ! $dom ) {
			return $block_content;
		}

		$pills    = Block_Pill_Helper::get_pill_labels( $dom );
		$modified = false;

		foreach ( $pills as $label ) {
			// Skip color swatch pills — they have their own shrink-reveal animation.
			if ( Block_Pill_Helper::has_color_swatch( $label ) ) {
				continue;
			}

			// Find the radio input.
			$inputs = $label->getElementsByTagName( 'input' );
			if ( 0 === $inputs->length ) {
				continue;
			}

			$input_node = $inputs->item( 0 );
			if ( ! $input_node ) {
				continue;
			}

			// Extract the text content (option name like "M", "L", etc.).
			$label_text = trim( $label->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			if ( '' === $label_text ) {
				continue;
			}

			// Add the aa-variation-pill class.
			$class_attr = $label->getAttribute( 'class' );
			$label->setAttribute( 'class', $class_attr . ' aa-variation-pill' );

			// Clear the label and rebuild: input + checkmark + name span.
			$input_clone = $input_node->cloneNode( true );

			while ( $label->firstChild ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$label->removeChild( $label->firstChild ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			}

			// 1. Re-add the input.
			$label->appendChild( $input_clone );

			// 2. Add checkmark span with SVG.
			$check = $dom->createElement( 'span' );
			$check->setAttribute( 'class', 'aa-variation-pill__check' );
			$check->setAttribute( 'aria-hidden', 'true' );

			$svg = $dom->createElement( 'svg' );
			$svg->setAttribute( 'viewBox', '0 0 12 12' );
			$svg->setAttribute( 'fill', 'none' );
			$svg->setAttribute( 'aria-hidden', 'true' );
			$polyline = $dom->createElement( 'polyline' );
			$polyline->setAttribute( 'points', '2.5 6.5 5 9 9.5 3.5' );
			$svg->appendChild( $polyline );
			$check->appendChild( $svg );
			$label->appendChild( $check );

			// 3. Add name span.
			$name = $dom->createElement( 'span' );
			$name->setAttribute( 'class', 'aa-variation-pill__name' );
			$name->appendChild( $dom->createTextNode( $label_text ) );
			$label->appendChild( $name );

			$modified = true;
		}

		if ( ! $modified ) {
			return $block_content;
		}

		return Block_Pill_Helper::save_dom( $dom, $block_content );
	}
}
