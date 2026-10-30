<?php
/**
 * Color Block Swatch Manager Class
 *
 * Styles WooCommerce's native colour swatches in the variation selector.
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Assets\Asset_Loader;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Color Block Swatch Manager Class
 *
 * WooCommerce draws the swatches itself once `pa_color` is a "Color / image"
 * attribute: each option is a chip button holding a
 * `wc-block-product-filter-chips__swatch` span painted from the term's
 * `color` / `image` meta. This only adds attributes to that markup — never
 * elements — so the theme's color-swatches.css styles it: the chip and swatch
 * classes, the colour name the hover tooltip shows (`data-tooltip` when
 * Swatch_Tooltips adds fabric details), and `--swatch-color` for the selected
 * ring.
 *
 * WooCommerce's chip script re-applies the chip's `title` and the swatch's
 * `style` / `--no-color` class when it hydrates, so nothing here relies on
 * those: the ring colour sits on the button (the swatch inherits it), terms
 * without a stored swatch get one through Color_Data_Manager's
 * `default_term_metadata` fallback, and color-swatch-chips.ts drops the
 * `title` the styled tooltip replaces.
 *
 * WooCommerce server-renders only the first 30 options; the rest render in
 * the browser on "+N more". Both are decorated from one swatch map: here for
 * the server's chips, and in color-swatch-chips.ts, from the list's
 * `data-aa-color-swatches` copy, for the browser's.
 *
 * @since 1.0.0
 */
class Color_Block_Swatch_Manager {

	/**
	 * The variation selector's input for the colour attribute.
	 */
	private const COLOR_INPUT = 'name="attribute_pa_color"';

	/**
	 * Prefix of WooCommerce's id for a colour option (`attribute_pa_color-<slug>`).
	 */
	private const OPTION_ID_PREFIX = 'attribute_pa_color-';

	/**
	 * Class on WooCommerce's chip list.
	 */
	private const LIST_CLASS = 'wc-block-product-filter-chips';

	/**
	 * Class on WooCommerce's chip button.
	 */
	private const CHIP_CLASS = 'wc-block-product-filter-chips__item';

	/**
	 * Class on WooCommerce's swatch span inside the chip.
	 */
	private const SWATCH_CLASS = 'wc-block-product-filter-chips__swatch';

	/**
	 * Theme class marking a decorated chip.
	 */
	private const THEME_CHIP_CLASS = 'aggressive-apparel-color-chip';

	/**
	 * Ring colour mixed into a pattern swatch, which has no single colour.
	 */
	private const PATTERN_RING = 'rgb(0 0 0 / 40%)';

	/**
	 * Script that keeps chips decorated in the browser.
	 */
	private const SCRIPT_HANDLE = 'aggressive-apparel-color-swatch-chips';

	/**
	 * Initialize the block swatch manager
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'render_block_' . Block_Pill_Helper::BLOCK_NAME, array( $this, 'decorate_color_swatches' ), 10, 2 );
	}

	/**
	 * Decorate the swatches in the colour attribute's variation selector.
	 *
	 * @param string $block_content Block HTML.
	 * @param array  $block         Parsed block.
	 * @return string
	 */
	public function decorate_color_swatches( string $block_content, array $block ): string {
		// Only pa_color: another "Color / image" attribute's terms aren't in
		// this swatch map, even where a slug happens to match.
		if ( ! Block_Pill_Helper::is_attribute_options_block( $block ) || ! str_contains( $block_content, self::COLOR_INPUT ) || ! str_contains( $block_content, self::SWATCH_CLASS ) ) {
			return $block_content;
		}

		$swatches  = Color_Data_Manager::get_safe_swatch_data();
		$processor = new \WP_HTML_Tag_Processor( $block_content );
		$map       = null;
		$current   = null;
		$decorated = false;

		// One pass. The block renders every attribute, each in a wrapper whose
		// context lists its options, so $map is the colour options' swatch map
		// inside the colour wrapper and null in the others. A chip button
		// selects its term's entry, which the next swatch span (always inside
		// that button) receives.
		while ( $processor->next_tag() ) {
			$tag = $processor->get_tag();

			$options = self::context_option_slugs( $processor->get_attribute( 'data-wp-context' ) );
			if ( null !== $options ) {
				$map = array() !== $options ? self::swatch_map( $options, $swatches ) : null;
			}

			if ( null === $map ) {
				continue;
			}

			if ( 'DIV' === $tag && $processor->has_class( self::LIST_CLASS ) ) {
				// Always present on a colour list: it is also how the script
				// finds the lists whose chip titles to drop.
				$processor->set_attribute( 'data-aa-color-swatches', (string) wp_json_encode( (object) $map ) );
				$decorated = true;
				continue;
			}

			if ( 'BUTTON' === $tag ) {
				$current = null;
				// Skip a chip already decorated (the filter can run again).
				$slug = $processor->has_class( self::CHIP_CLASS ) && ! $processor->has_class( self::THEME_CHIP_CLASS ) && str_starts_with( (string) $processor->get_attribute( 'id' ), self::OPTION_ID_PREFIX )
					? (string) $processor->get_attribute( 'value' )
					: '';
				if ( '' !== $slug && is_array( $map[ $slug ] ?? null ) ) {
					$current   = $map[ $slug ];
					$decorated = true;
					$processor->add_class( self::THEME_CHIP_CLASS );
					$processor->remove_attribute( 'title' );
					$style = rtrim( trim( (string) $processor->get_attribute( 'style' ) ), ';' );
					$processor->set_attribute( 'style', ltrim( $style . '; --swatch-color: ' . (string) ( $current['ring'] ?? '' ) . ';', '; ' ) );
				}
				continue;
			}

			if ( null !== $current && 'SPAN' === $tag && $processor->has_class( self::SWATCH_CLASS ) ) {
				self::decorate_swatch( $processor, $current );
				$current = null;
			}
		}

		if ( $decorated ) {
			Asset_Loader::enqueue_script( self::SCRIPT_HANDLE, 'build/scripts/color-swatch-chips' );
		}

		return $processor->get_updated_html();
	}

	/**
	 * The colour option slugs in an attribute wrapper's context
	 * (`variationAttributeOptions`): empty for another attribute, null when
	 * this isn't an attribute wrapper.
	 *
	 * @param mixed $context A `data-wp-context` value.
	 * @return list<string>|null
	 */
	private static function context_option_slugs( $context ): ?array {
		if ( ! is_string( $context ) || ! str_contains( $context, '"variationAttributeOptions"' ) ) {
			return null;
		}

		// A context may carry a "namespace::" prefix before its JSON.
		$data  = json_decode( (string) substr( $context, (int) strpos( $context, '{' ) ), true );
		$items = is_array( $data ) && is_array( $data['variationAttributeOptions'] ?? null ) ? $data['variationAttributeOptions'] : array();

		return array_values(
			array_filter(
				array_map(
					static fn( $item ) => is_array( $item ) && is_scalar( $item['value'] ?? null ) && str_starts_with( (string) ( $item['id'] ?? '' ), self::OPTION_ID_PREFIX ) ? (string) $item['value'] : '',
					$items
				)
			)
		);
	}

	/**
	 * The swatch map for a colour attribute's options: each entry has `name`,
	 * `ring` and `pattern`, and the filter may add `tooltip`. It decorates the
	 * server's chips, and is sent with the list for the browser's.
	 *
	 * @param array               $slugs    This attribute's option slugs.
	 * @param array<string,array> $swatches Swatch data keyed by slug.
	 * @phpstan-param list<string> $slugs
	 * @return array<string, array<string, mixed>>
	 */
	private static function swatch_map( array $slugs, array $swatches ): array {
		$map = array();
		foreach ( $slugs as $slug ) {
			if ( ! isset( $swatches[ $slug ] ) ) {
				continue;
			}
			$is_pattern   = 'pattern' === ( $swatches[ $slug ]['type'] ?? '' );
			$map[ $slug ] = array(
				'name'    => (string) ( $swatches[ $slug ]['name'] ?? '' ),
				'ring'    => $is_pattern ? self::PATTERN_RING : (string) ( $swatches[ $slug ]['value'] ?? '' ),
				'pattern' => $is_pattern,
			);
		}

		/**
		 * Filter a colour selector's swatch map (Swatch_Tooltips adds each
		 * `tooltip`).
		 *
		 * @param array<string, array> $map Swatch data keyed by term slug.
		 */
		return (array) apply_filters( 'aggressive_apparel_color_swatch_map', $map );
	}

	/**
	 * Decorate one swatch span, as color-swatch-chips.ts's decorateChip() does.
	 *
	 * @param \WP_HTML_Tag_Processor $processor Processor positioned on the span.
	 * @param array                  $entry     The term's swatch map entry.
	 * @return void
	 */
	private static function decorate_swatch( \WP_HTML_Tag_Processor $processor, array $entry ): void {
		$processor->add_class( 'aggressive-apparel-color-swatch' );
		$processor->add_class( 'aggressive-apparel-color-swatch__circle' );
		if ( ! empty( $entry['pattern'] ) ) {
			$processor->add_class( 'aggressive-apparel-color-swatch--pattern' );
		}

		$processor->set_attribute( 'data-color-name', (string) ( $entry['name'] ?? '' ) );
		$tooltip = is_string( $entry['tooltip'] ?? null ) ? $entry['tooltip'] : '';
		if ( '' !== $tooltip ) {
			$processor->set_attribute( 'data-tooltip', $tooltip );
		}
	}
}
