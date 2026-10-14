<?php
/**
 * Product Specs Admin Fields
 *
 * Markup builders for the product Specs fields: one input per
 * spec item with a "where this value comes from" line, wording overrides
 * behind a disclosure, and the shared admin CSS. Every builder returns
 * escaped HTML.
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
 * Product Specs admin field markup.
 *
 * @phpstan-import-type ResolvedItem from Product_Specs
 * @phpstan-import-type Wording from Product_Specs
 * @phpstan-type LabelSet array{key: string, type: string, title: string, start: string, mid: string, end: string, steps: list<string>}
 */
final class Product_Specs_Admin_Fields {

	/**
	 * Form field name prefix.
	 *
	 * @var string
	 */
	public const FIELD = 'aa_specs';

	/**
	 * One product row per spec item: input, source line, hide toggle.
	 *
	 * @param array $item Resolved item.
	 * @phpstan-param ResolvedItem $item
	 */
	public static function product_row( array $item ): string {
		$key  = $item['key'];
		$id   = 'aa-specs-' . $key;
		$name = self::FIELD . '[values][' . $key . ']';

		$inherited = self::describe( $item, $item['inherited'] );

		if ( Product_Specs_Registry::TYPE_PICK === $item['type'] ) {
			$empty = '' !== $inherited
				/* translators: %s: inherited value, e.g. "Regular". */
				? sprintf( __( 'Inherit (%s)', 'aggressive-apparel' ), $inherited )
				: __( 'Not set', 'aggressive-apparel' );

			$options = '<option value="">' . esc_html( $empty ) . '</option>';
			foreach ( $item['steps'] as $i => $step ) {
				$options .= sprintf( '<option value="%1$d"%2$s>%3$s</option>', $i, selected( $item['own'], (string) $i, false ), esc_html( $step ) );
			}
			$control = '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . $options . '</select>';
		} elseif ( Product_Specs_Registry::TYPE_NUMBER === $item['type'] ) {
			$control = sprintf(
				'<input type="number" id="%1$s" name="%2$s" value="%3$s" placeholder="%4$s" min="0" step="any">',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( $item['own'] ),
				esc_attr( $item['inherited']['number'] > 0 ? (string) $item['inherited']['number'] : '' )
			);
		} else {
			$control = sprintf(
				'<input type="text" id="%1$s" name="%2$s" value="%3$s" placeholder="%4$s">',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( $item['own'] ),
				esc_attr( $item['inherited']['text'] )
			);
		}

		$label = Product_Specs_Registry::TYPE_NUMBER === $item['type'] && '' !== $item['unit']
			? sprintf( '%1$s (%2$s)', $item['title'], $item['unit'] )
			: $item['title'];

		$hide = sprintf(
			'<label class="aa-specs-hide"><input type="checkbox" name="%1$s[hidden][]" value="%2$s"%3$s> %4$s</label>',
			esc_attr( self::FIELD ),
			esc_attr( $key ),
			checked( $item['hidden'], true, false ),
			esc_html__( 'Hide on this product', 'aggressive-apparel' )
		);

		return '<p class="form-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>' . $control . $hide . self::status( $item ) . '</p>';
	}

	/**
	 * Wording override inputs, grouped per item, inside a disclosure.
	 *
	 * @param array  $sets    Label sets (current wording is the placeholder).
	 * @param array  $wording Stored overrides.
	 * @param string $intro   Help text.
	 * @phpstan-param list<LabelSet> $sets
	 * @phpstan-param Wording        $wording
	 */
	public static function wording( array $sets, array $wording, string $intro ): string {
		if ( array() === $sets ) {
			return '';
		}

		$changed = 0;
		foreach ( $sets as $set ) {
			$changed += count( $wording[ $set['key'] ] ?? array() );
		}

		$summary = 0 === $changed
			? __( 'Customize wording', 'aggressive-apparel' )
			: sprintf(
				/* translators: %d: number of wording fields changed. */
				_n( 'Customize wording (%d changed)', 'Customize wording (%d changed)', $changed, 'aggressive-apparel' ),
				$changed
			);

		$html  = '<details class="aa-specs-wording"' . ( $changed > 0 ? ' open' : '' ) . '><summary>' . esc_html( $summary ) . '</summary>';
		$html .= '<p class="aa-specs-help">' . esc_html( $intro ) . '</p>';

		foreach ( $sets as $set ) {
			$fields = array( array( 'title', __( 'Name', 'aggressive-apparel' ), $set['title'] ) );
			if ( Product_Specs_Registry::TYPE_TEXT !== $set['type'] ) {
				$fields[] = array( 'start', __( 'Start label', 'aggressive-apparel' ), $set['start'] );
				$fields[] = array( 'mid', __( 'Middle label', 'aggressive-apparel' ), $set['mid'] );
				$fields[] = array( 'end', __( 'End label', 'aggressive-apparel' ), $set['end'] );
				foreach ( $set['steps'] as $n => $step ) {
					$fields[] = array(
						'step' . $n,
						/* translators: %d: step number. */
						sprintf( __( 'Step %d', 'aggressive-apparel' ), $n + 1 ),
						$step,
					);
				}
			}

			$html .= '<fieldset class="aa-specs-wording__item"><legend>' . esc_html( $set['title'] ) . '</legend><div class="aa-specs-grid">';
			foreach ( $fields as list( $field, $label, $placeholder ) ) {
				$html .= sprintf(
					'<label for="%1$s">%2$s<input type="text" id="%1$s" name="%3$s[wording][%4$s][%5$s]" value="%6$s" placeholder="%7$s" maxlength="60"></label>',
					esc_attr( 'aa-specs-' . $set['key'] . '-' . $field ),
					esc_html( $label ),
					esc_attr( self::FIELD ),
					esc_attr( $set['key'] ),
					esc_attr( $field ),
					esc_attr( $wording[ $set['key'] ][ $field ] ?? '' ),
					esc_attr( $placeholder )
				);
			}
			$html .= '</div></fieldset>';
		}

		return $html . '</details>';
	}

	/**
	 * Label set from a resolved product item.
	 *
	 * @param array $item Resolved item.
	 * @phpstan-param ResolvedItem $item
	 * @return LabelSet
	 */
	public static function set_from_resolved( array $item ): array {
		return array(
			'key'   => $item['key'],
			'type'  => $item['type'],
			'title' => $item['title'],
			'start' => $item['start'],
			'mid'   => $item['mid'],
			'end'   => $item['end'],
			'steps' => $item['steps'],
		);
	}

	/**
	 * Shared admin CSS, printed once per request.
	 */
	public static function styles(): string {
		static $printed = false;
		if ( $printed ) {
			return '';
		}
		$printed = true;

		// Scoped under .woocommerce_options_panel to beat WooCommerce's panel
		// rules (floated labels, halved inputs, padded paragraphs).
		$panel = '.woocommerce_options_panel .aa-specs';

		return '<style>'
			. $panel . ' .aa-specs__title{margin:20px 12px 4px;padding:0;font-size:14px;font-weight:600}'
			. $panel . ' p.aa-specs-help{margin:0 12px 12px;padding:0;color:#646970;font-size:12px;line-height:1.5}'
			. $panel . ' p.form-field{display:flex;flex-wrap:wrap;align-items:center;row-gap:6px}'
			. $panel . ' p.form-field>label:first-child{float:none;flex:none;width:150px;margin:0 0 0 -150px;padding:0}'
			. $panel . ' p.form-field select,' . $panel . ' p.form-field input[type=text],' . $panel . ' p.form-field input[type=number]{float:none;flex:none;width:260px;max-width:100%;min-height:36px;margin:0}'
			. $panel . ' .aa-specs-hide{display:inline-flex;flex:none;gap:6px;align-items:center;float:none;width:auto;margin:0 0 0 16px;padding:0;color:#50575e;font-size:12px}'
			. $panel . ' .aa-specs-hide input{float:none;width:auto;margin:0}'
			. $panel . ' .aa-specs-status{flex-basis:100%;margin:0;color:#646970;font-size:12px;line-height:1.5}'
			. $panel . ' .aa-specs-status.is-empty{color:#996800}'
			. $panel . ' .aa-specs-preview{margin:8px 12px 16px;padding:16px 20px;border:1px solid #dcdcde;border-radius:4px;background:#fff;--aa-product-specs-fill:var(--wp-admin-theme-color,#2271b1)}'
			. $panel . ' .aa-specs-preview p{margin:0;padding:0;font-size:13px;line-height:1.5}'
			. $panel . ' .aa-specs-preview p.aa-specs-preview__label{margin:0 0 12px;color:#646970;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase}'
			. $panel . ' .aa-specs-wording{margin:4px 12px 16px}'
			. $panel . ' .aa-specs-wording summary{padding:6px 0;font-weight:600;cursor:pointer}'
			. $panel . ' .aa-specs-wording p.aa-specs-help{margin:4px 0 12px}'
			. $panel . ' .aa-specs-wording__item{margin:0 0 16px;padding:0;border:0}'
			. $panel . ' .aa-specs-wording__item legend{float:none;width:auto;margin:0 0 8px;padding:0;font-weight:600}'
			. $panel . ' .aa-specs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px 12px}'
			. $panel . ' .aa-specs-grid label{display:flex;flex-direction:column;gap:4px;float:none;width:auto;margin:0;padding:0;color:#50575e;font-size:12px}'
			. $panel . ' .aa-specs-grid input[type=text]{float:none;width:100%;margin:0}'
			. '</style>';
	}

	/**
	 * Status line: what the product page shows for an item, and from where.
	 *
	 * @param array $item Resolved item.
	 * @phpstan-param ResolvedItem $item
	 */
	private static function status( array $item ): string {
		if ( $item['hidden'] ) {
			return self::status_markup( __( 'Hidden on this product.', 'aggressive-apparel' ), false );
		}

		$shown = self::describe( $item, $item );
		if ( '' === $shown ) {
			return self::status_markup( __( 'Not set anywhere, so it is left out.', 'aggressive-apparel' ), true );
		}

		switch ( $item['source'] ) {
			case Product_Specs::SOURCE_ATTRIBUTE:
				/* translators: 1: value shown on the product page, 2: product attribute name. */
				$message = sprintf( __( 'Showing “%1$s” from the “%2$s” attribute.', 'aggressive-apparel' ), $shown, $item['label'] );
				break;
			case Product_Specs::SOURCE_GUIDE:
				/* translators: 1: value shown on the product page, 2: size guide title. */
				$message = sprintf( __( 'Showing “%1$s” from the size guide “%2$s”.', 'aggressive-apparel' ), $shown, $item['label'] );
				break;
			default:
				/* translators: %s: value shown on the product page. */
				$message = sprintf( __( 'Showing “%s”, set on this product.', 'aggressive-apparel' ), $shown );
		}

		return self::status_markup( $message, false );
	}

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
			return $value['text'];
		}
		if ( $value['step'] < 0 ) {
			return '';
		}

		$step = $item['steps'][ $value['step'] ] ?? '';

		return Product_Specs_Registry::TYPE_NUMBER === $item['type']
			? sprintf( '%1$s, %2$s', Product_Specs::format_number( $value['number'], $item['unit'] ), $step )
			: $step;
	}

	/**
	 * Status line markup.
	 *
	 * @param string $message  Message.
	 * @param bool   $is_empty Whether nothing will show.
	 */
	private static function status_markup( string $message, bool $is_empty ): string {
		return '<span class="aa-specs-status' . ( $is_empty ? ' is-empty' : '' ) . '">' . esc_html( $message ) . '</span>';
	}
}
