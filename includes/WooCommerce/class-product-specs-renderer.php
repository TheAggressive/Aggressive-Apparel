<?php
/**
 * Product Specs Renderer
 *
 * Builds the inner markup of the Product Specs block: segmented scales (or a
 * compact card per scale), per-step advice, a details list, and a footer with
 * the picked size's measurements and a link that opens the size guide.
 * Each scale is a `role="meter"` whose `aria-valuetext` carries the reading,
 * so the bars never have to be interpreted visually.
 *
 * The footer is product data only (every size's line, both units); the
 * script picks the line for the shopper's size and unit in the browser, so
 * the markup stays full-page-cache safe.
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
 * Product Specs block markup.
 *
 * @phpstan-import-type ResolvedItem from Product_Specs
 */
final class Product_Specs_Renderer {

	/**
	 * Block layout, sanitized.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public static function layout( array $attributes ): string {
		return 'compact' === ( $attributes['layout'] ?? '' ) ? 'compact' : 'full';
	}

	/**
	 * Inner block markup for a product, or '' when there is nothing to show.
	 *
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public static function render( int $product_id, array $attributes ): string {
		if ( ! Feature_Settings::is_enabled( 'size_guide' ) ) {
			return '';
		}

		$layout    = self::layout( $attributes );
		$requested = isset( $attributes['scales'] ) && is_array( $attributes['scales'] )
			? array_map( 'strval', $attributes['scales'] )
			: array();
		$labels    = ! isset( $attributes['showLabels'] ) || (bool) $attributes['showLabels'];
		$advice    = ! empty( $attributes['showNudge'] );
		$details   = 'full' === $layout && ! empty( $attributes['showDetails'] );

		$scales = array();
		$facts  = array();
		foreach ( Product_Specs::for_product( $product_id )['items'] as $item ) {
			if ( $item['hidden'] ) {
				continue;
			}
			// The block's scale picker only lists scales, so it never limits
			// the details list.
			$picked = array() === $requested || in_array( $item['key'], $requested, true );
			if ( $picked && Product_Specs_Registry::TYPE_TEXT !== $item['type'] && $item['step'] >= 0 ) {
				$scales[] = $item;
			}
			if ( Product_Specs_Registry::TYPE_PICK !== $item['type'] && '' !== $item['display'] ) {
				$facts[] = $item;
			}
		}

		$markup = '';
		if ( array() !== $scales ) {
			$rows = '';
			foreach ( $scales as $item ) {
				$rows .= 'compact' === $layout ? self::card( $item, $advice ) : self::scale( $item, $labels );
			}
			$markup .= sprintf( '<div class="aa-product-specs__%1$s">%2$s</div>', 'compact' === $layout ? 'cards' : 'scales', $rows );
		}

		// Compact cards carry their advice; the full layout lists it after.
		if ( $advice && 'full' === $layout ) {
			foreach ( $scales as $item ) {
				if ( '' !== $item['advice'] ) {
					$markup .= '<p class="aa-product-specs__nudge">' . esc_html( $item['advice'] ) . '</p>';
				}
			}
		}

		if ( $details && array() !== $facts ) {
			$rows = '';
			foreach ( $facts as $item ) {
				$rows .= '<div class="aa-product-specs__detail"><dt>' . esc_html( $item['title'] ) . '</dt><dd>' . esc_html( $item['display'] ) . '</dd></div>';
			}
			$markup .= '<dl class="aa-product-specs__details">' . $rows . '</dl>';
		}

		if ( '' === $markup ) {
			return '';
		}

		return $markup . self::footer( $product_id, $attributes );
	}

	/**
	 * One full scale: heading, segmented track, end labels.
	 *
	 * @param array $item   Resolved item.
	 * @param bool  $labels Whether to show the start/middle/end labels.
	 * @phpstan-param ResolvedItem $item
	 */
	private static function scale( array $item, bool $labels ): string {
		$label_markup = '';
		if ( $labels ) {
			$label_markup = sprintf(
				'<div class="aa-product-specs__labels" aria-hidden="true"><span>%1$s</span><span>%2$s</span><span>%3$s</span></div>',
				esc_html( $item['start'] ),
				esc_html( $item['mid'] ),
				esc_html( $item['end'] )
			);
		}

		return sprintf(
			'<div class="aa-product-specs__scale%1$s" %2$s><div class="aa-product-specs__head" aria-hidden="true"><span class="aa-product-specs__title">%3$s</span><span class="aa-product-specs__value">%4$s</span></div>%5$s%6$s</div>',
			$item['centered'] ? ' is-centered' : '',
			self::meter_attributes( $item ),
			esc_html( $item['title'] ),
			esc_html( $item['steps'][ $item['step'] ] ?? '' ),
			self::track( $item ),
			$label_markup
		);
	}

	/**
	 * One compact card: title and value over a full-width track, then advice.
	 *
	 * The advice sits outside the meter, whose children are presentational.
	 *
	 * @param array $item   Resolved item.
	 * @param bool  $advice Whether to show the step's advice.
	 * @phpstan-param ResolvedItem $item
	 */
	private static function card( array $item, bool $advice ): string {
		return sprintf(
			'<div class="aa-product-specs__card"><div class="aa-product-specs__line%1$s" %2$s><div class="aa-product-specs__head" aria-hidden="true"><span class="aa-product-specs__title">%3$s</span><span class="aa-product-specs__value">%4$s</span></div>%5$s</div>%6$s</div>',
			$item['centered'] ? ' is-centered' : '',
			self::meter_attributes( $item ),
			esc_html( $item['title'] ),
			esc_html( $item['steps'][ $item['step'] ] ?? '' ),
			self::track( $item ),
			$advice && '' !== $item['advice'] ? '<p class="aa-product-specs__nudge">' . esc_html( $item['advice'] ) . '</p>' : ''
		);
	}

	/**
	 * The picked size's measurements and a link that opens the size guide.
	 *
	 * Starts hidden; the size guide script reveals each part once it has
	 * something to show (a picked size in the chart, a size guide on the page).
	 * The block can sit inside kses-filtered content (product tabs), which
	 * strips `hidden` and `data-wp-on--*`, so pending parts are hidden by a
	 * class and the link's click is wired from the init callback.
	 *
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	private static function footer( int $product_id, array $attributes ): string {
		$measurements = ! isset( $attributes['showMeasurements'] ) || (bool) $attributes['showMeasurements'];
		$link         = ! isset( $attributes['showGuideLink'] ) || (bool) $attributes['showGuideLink'];

		$chart = $measurements ? Size_Chart::for_product( $product_id ) : null;
		$sizes = null !== $chart ? Size_Chart::size_lines( $chart ) : array();

		$inner = '';
		if ( array() !== $sizes ) {
			// Starts as a prompt (server text, so it isn't announced on load);
			// the script swaps in the picked size's line.
			$prompt = __( 'Pick a size to see its measurements.', 'aggressive-apparel' );
			$inner .= '<p class="aa-product-specs__fit" aria-live="polite" data-prompt="' . esc_attr( $prompt ) . '">' . esc_html( $prompt ) . '</p>';
		}
		if ( $link ) {
			$inner .= '<button type="button" class="aa-product-specs__guide-link is-pending">' . esc_html__( 'Size guide', 'aggressive-apparel' ) . '</button>';
		}
		if ( '' === $inner ) {
			return '';
		}

		return sprintf(
			'<div class="aa-product-specs__footer is-pending" data-wp-interactive="aggressive-apparel/product-specs" data-wp-init="callbacks.initFooter" data-sizes="%1$s">%2$s</div>',
			esc_attr( (string) wp_json_encode( $sizes ) ),
			$inner
		);
	}

	/**
	 * Segmented track with the active step marked.
	 *
	 * @param array $item Resolved item.
	 * @phpstan-param ResolvedItem $item
	 */
	private static function track( array $item ): string {
		$count    = count( $item['steps'] );
		$segments = '';
		for ( $i = 0; $i < $count; $i++ ) {
			$segments .= $i === $item['step']
				? '<span class="aa-product-specs__segment is-active"></span>'
				: '<span class="aa-product-specs__segment"></span>';
		}

		return sprintf( '<div class="aa-product-specs__track" style="--aa-product-specs-steps:%d" aria-hidden="true">%s</div>', $count, $segments );
	}

	/**
	 * Meter role and value attributes for a scale.
	 *
	 * @param array $item Resolved item.
	 * @phpstan-param ResolvedItem $item
	 */
	private static function meter_attributes( array $item ): string {
		$value_text = sprintf(
			/* translators: 1: current step (e.g. "Regular"), 2: lowest label (e.g. "Snug"), 3: highest label (e.g. "Oversized"). */
			__( '%1$s, on a scale from %2$s to %3$s', 'aggressive-apparel' ),
			$item['steps'][ $item['step'] ] ?? '',
			$item['start'],
			$item['end']
		);

		return sprintf(
			'role="meter" aria-label="%1$s" aria-valuemin="1" aria-valuemax="%2$d" aria-valuenow="%3$d" aria-valuetext="%4$s"',
			esc_attr( $item['title'] ),
			count( $item['steps'] ),
			$item['step'] + 1,
			esc_attr( $value_text )
		);
	}
}
