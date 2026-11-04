<?php
/**
 * Product Specs Admin Fields
 *
 * What the product admin and the Products grid share about a spec's fields:
 * the form field name their values post under, and a value written out as
 * people read it.
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
 * Product Specs admin field helpers.
 *
 * @phpstan-import-type ResolvedItem from Product_Specs
 */
final class Product_Specs_Admin_Fields {

	/**
	 * Form field name prefix.
	 *
	 * @var string
	 */
	public const FIELD = 'aa_specs';

	/**
	 * Human text for a value of an item ('' when unset).
	 *
	 * @param array $item  Resolved item (for steps and unit).
	 * @param array $value Value to describe.
	 * @phpstan-param ResolvedItem $item
	 * @phpstan-param array{step: int, number: float, text: string} $value
	 */
	public static function describe( array $item, array $value ): string {
		if ( Product_Specs_Registry::TYPE_TEXT === $item['type'] ) {
			return $item['multiple'] ? implode( ', ', Product_Specs_Taxonomy::split( $value['text'] ) ) : $value['text'];
		}
		if ( $value['step'] < 0 ) {
			return '';
		}

		$step = $item['steps'][ $value['step'] ] ?? '';

		return Product_Specs_Registry::TYPE_NUMBER === $item['type']
			? sprintf( '%1$s, %2$s', Product_Specs::format_number( $value['number'], $item['unit'] ), $step )
			: $step;
	}
}
