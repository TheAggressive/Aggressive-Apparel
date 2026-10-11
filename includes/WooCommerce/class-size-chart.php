<?php
/**
 * Size Chart
 *
 * A size guide's structured chart: sizes (rows) × measurements (columns),
 * entered in inches or centimetres, plus notes and an optional measuring
 * diagram. Renders as an accessible data table with both units in the markup
 * so the in/cm toggle never rewrites text, only switches which one shows.
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
 * Size chart storage, conversion, and rendering.
 *
 * @phpstan-type Column array{label: string, type: string}
 * @phpstan-type Row array{size: string, values: list<string>}
 * @phpstan-type Chart array{unit: string, columns: list<Column>, rows: list<Row>}
 */
final class Size_Chart {

	/**
	 * Chart meta key.
	 *
	 * @var string
	 */
	public const META = '_aa_size_chart';

	/**
	 * Notes meta key.
	 *
	 * @var string
	 */
	public const NOTES = '_aa_size_notes';

	/**
	 * Measuring diagram (attachment ID) meta key.
	 *
	 * @var string
	 */
	public const DIAGRAM = '_aa_size_diagram';

	/**
	 * A measurement column, converted between units.
	 *
	 * @var string
	 */
	public const TYPE_MEASURE = 'measure';

	/**
	 * A text column (like "US size"), never converted.
	 *
	 * @var string
	 */
	public const TYPE_TEXT = 'text';

	/**
	 * Most columns.
	 *
	 * @var int
	 */
	public const MAX_COLUMNS = 12;

	/**
	 * Most rows.
	 *
	 * @var int
	 */
	public const MAX_ROWS = 40;

	/**
	 * Centimetres per inch.
	 *
	 * @var float
	 */
	private const CM_PER_INCH = 2.54;

	/**
	 * Normalize a submitted or stored chart.
	 *
	 * Rows without a size are dropped; every row gets one value per column.
	 *
	 * @param mixed $raw Raw chart.
	 * @return Chart
	 */
	public static function sanitize( $raw ): array {
		$raw     = is_array( $raw ) ? $raw : array();
		$columns = array();
		foreach ( array_slice( is_array( $raw['columns'] ?? null ) ? array_values( $raw['columns'] ) : array(), 0, self::MAX_COLUMNS ) as $column ) {
			$column    = is_array( $column ) ? $column : array();
			$columns[] = array(
				'label' => self::text( $column['label'] ?? '', 40 ),
				'type'  => self::TYPE_TEXT === ( $column['type'] ?? '' ) ? self::TYPE_TEXT : self::TYPE_MEASURE,
			);
		}

		$rows = array();
		foreach ( array_slice( is_array( $raw['rows'] ?? null ) ? array_values( $raw['rows'] ) : array(), 0, self::MAX_ROWS ) as $row ) {
			$row  = is_array( $row ) ? $row : array();
			$size = self::text( $row['size'] ?? '', 30 );
			if ( '' === $size ) {
				continue;
			}
			$values = is_array( $row['values'] ?? null ) ? array_values( $row['values'] ) : array();
			$clean  = array();
			foreach ( array_keys( $columns ) as $index ) {
				$clean[] = self::text( $values[ $index ] ?? '', 30 );
			}
			$rows[] = array(
				'size'   => $size,
				'values' => $clean,
			);
		}

		return array(
			'unit'    => 'cm' === ( $raw['unit'] ?? '' ) ? 'cm' : 'in',
			'columns' => $columns,
			'rows'    => $rows,
		);
	}

	/**
	 * A guide's stored chart, or null when it has none.
	 *
	 * @param int $guide_id Size guide ID.
	 * @return Chart|null
	 */
	public static function get( int $guide_id ): ?array {
		$chart = self::sanitize( get_post_meta( $guide_id, self::META, true ) );

		return array() !== $chart['rows'] ? $chart : null;
	}

	/**
	 * Full size guide markup: chart, notes, diagram.
	 *
	 * The guide's name is for the admin only and never shown to shoppers.
	 *
	 * @param int $guide_id Size guide ID.
	 */
	public static function render( int $guide_id ): string {
		$chart = self::get( $guide_id );
		if ( null === $chart ) {
			return '';
		}

		$html  = '<div class="aa-size-chart" data-unit="' . esc_attr( $chart['unit'] ) . '" data-wp-init="callbacks.initChart" data-wp-watch="callbacks.highlightSize">';
		$html .= self::table( $chart );
		$html .= sprintf(
			'<p class="aa-size-chart__live screen-reader-text" aria-live="polite" data-in="%1$s" data-cm="%2$s"></p>',
			esc_attr__( 'Showing inches', 'aggressive-apparel' ),
			esc_attr__( 'Showing centimetres', 'aggressive-apparel' )
		);
		$html .= self::notes( $guide_id );
		$html .= '</div>';

		return $html;
	}

	/**
	 * A measurement in the other unit, keeping ranges and separators.
	 *
	 * "38-40" in inches → "96.5-101.5" in cm; values without numbers come back
	 * unchanged.
	 *
	 * @param string $value Value as entered.
	 * @param string $from  Entered unit ('in' or 'cm').
	 */
	public static function convert( string $value, string $from ): string {
		$numbers = self::numbers( $value );
		if ( array() === $numbers ) {
			return $value;
		}

		$parts = array();
		foreach ( $numbers as $number ) {
			$parts[] = 'in' === $from
				? self::format_cm( $number * self::CM_PER_INCH )
				: self::format_inches( $number / self::CM_PER_INCH );
		}

		return implode( '–', $parts );
	}

	/**
	 * Numbers in a value: "11 3/4", "11¾", "11.75", "38-40", "38 to 40".
	 *
	 * @param string $value Value.
	 * @return list<float>
	 */
	public static function numbers( string $value ): array {
		$value = strtr(
			$value,
			array(
				'¼' => ' 1/4',
				'½' => ' 1/2',
				'¾' => ' 3/4',
				'⅛' => ' 1/8',
				'⅜' => ' 3/8',
				'⅝' => ' 5/8',
				'⅞' => ' 7/8',
				',' => '.',
			)
		);

		// A bare fraction ("3/4") first, then a number with an optional
		// fraction ("11 3/4").
		if ( ! preg_match_all( '/(\d+)\/(\d+)|(\d+(?:\.\d+)?)(?:\s+(\d+)\/(\d+))?/', $value, $matches, PREG_SET_ORDER ) ) {
			return array();
		}

		$numbers = array();
		foreach ( $matches as $match ) {
			$numerator   = (int) ( $match[1] ?? 0 );
			$denominator = (int) ( $match[2] ?? 0 );
			if ( '' !== ( $match[1] ?? '' ) && $denominator > 0 ) {
				$numbers[] = $numerator / $denominator;
				continue;
			}
			if ( '' === ( $match[3] ?? '' ) ) {
				continue;
			}
			$number  = (float) $match[3];
			$part    = (int) ( $match[4] ?? 0 );
			$part_of = (int) ( $match[5] ?? 0 );
			if ( $part_of > 0 ) {
				$number += $part / $part_of;
			}
			$numbers[] = $number;
		}

		return array_slice( $numbers, 0, 2 );
	}

	/**
	 * Inches to the nearest quarter, with fraction glyphs ("11¾").
	 *
	 * @param float $inches Inches.
	 */
	public static function format_inches( float $inches ): string {
		$quarters = (int) round( $inches * 4 );
		$whole    = intdiv( $quarters, 4 );
		$glyph    = array( '', '¼', '½', '¾' )[ $quarters % 4 ];

		return ( 0 === $whole && '' !== $glyph ? '' : (string) $whole ) . $glyph;
	}

	/**
	 * Centimetres to the nearest half.
	 *
	 * @param float $cm Centimetres.
	 */
	public static function format_cm( float $cm ): string {
		$half = round( $cm * 2 ) / 2;

		return number_format_i18n( $half, floor( $half ) === $half ? 0 : 1 );
	}

	/**
	 * The chart a product's size guide dialog shows, or null when that guide
	 * is old free-form content (or there is none). Uses the dialog's own
	 * resolver, so the two always agree.
	 *
	 * @param int $product_id Product ID.
	 * @return Chart|null
	 */
	public static function for_product( int $product_id ): ?array {
		$guide = Size_Guide::resolved( $product_id );

		return $guide['chart'] ? self::get( $guide['id'] ) : null;
	}

	/**
	 * Whether a chart has measurement columns (so in/cm applies).
	 *
	 * @param array $chart Chart.
	 * @phpstan-param Chart $chart
	 */
	public static function has_measures( array $chart ): bool {
		foreach ( $chart['columns'] as $column ) {
			if ( self::TYPE_MEASURE === $column['type'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * One readable line per size, in both units, for showing the picked
	 * size's measurements outside the chart ("Size M: Chest 42 in, Length 29 in").
	 *
	 * @param array $chart Chart.
	 * @phpstan-param Chart $chart
	 * @return list<array{size: string, in: string, cm: string}>
	 */
	public static function size_lines( array $chart ): array {
		$other = 'in' === $chart['unit'] ? 'cm' : 'in';
		$lines = array();
		foreach ( $chart['rows'] as $row ) {
			$parts = array(
				'in' => array(),
				'cm' => array(),
			);
			foreach ( $chart['columns'] as $index => $column ) {
				$value = $row['values'][ $index ] ?? '';
				if ( '' === $value ) {
					continue;
				}
				if ( self::TYPE_MEASURE === $column['type'] ) {
					$parts[ $chart['unit'] ][] = trim( $column['label'] . ' ' . $value . ' ' . $chart['unit'] );
					$parts[ $other ][]         = trim( $column['label'] . ' ' . self::convert( $value, $chart['unit'] ) . ' ' . $other );
				} else {
					$parts['in'][] = trim( $column['label'] . ' ' . $value );
					$parts['cm'][] = trim( $column['label'] . ' ' . $value );
				}
			}
			if ( array() === $parts['in'] ) {
				continue;
			}

			$lines[] = array(
				'size' => $row['size'],
				'in'   => self::size_line( $row['size'], $parts['in'] ),
				'cm'   => self::size_line( $row['size'], $parts['cm'] ),
			);
		}

		return $lines;
	}

	/**
	 * "Size M: Chest 42 in, Length 29 in".
	 *
	 * @param string $size  Size label.
	 * @param array  $parts Measurements.
	 * @phpstan-param list<string> $parts
	 */
	private static function size_line( string $size, array $parts ): string {
		return sprintf(
			/* translators: 1: size (e.g. "M"), 2: its measurements (e.g. "Chest 42 in, Length 29 in"). */
			__( 'Size %1$s: %2$s', 'aggressive-apparel' ),
			$size,
			wp_sprintf( '%l', $parts )
		);
	}

	/**
	 * The in/cm switch the size guide dialog shows beside its heading.
	 *
	 * @param string $unit Unit the chart was entered in (pressed at first).
	 */
	public static function unit_switch( string $unit ): string {
		$buttons = '';
		foreach ( array(
			'in' => __( 'Inches', 'aggressive-apparel' ),
			'cm' => __( 'Centimetres', 'aggressive-apparel' ),
		) as $option => $label ) {
			$buttons .= sprintf(
				'<button type="button" class="aa-size-chart__unit-button" data-unit="%1$s" aria-pressed="%2$s" data-wp-on--click="actions.setUnit"><span aria-hidden="true">%3$s</span><span class="screen-reader-text">%4$s</span></button>',
				esc_attr( $option ),
				$option === $unit ? 'true' : 'false',
				esc_html( $option ),
				esc_html( $label )
			);
		}

		return '<div class="aa-size-chart__units" role="group" aria-label="' . esc_attr__( 'Measurement units', 'aggressive-apparel' ) . '">' . $buttons . '</div>';
	}

	/**
	 * The chart table.
	 *
	 * @param array $chart Chart.
	 * @phpstan-param Chart $chart
	 */
	private static function table( array $chart ): string {
		$other = 'in' === $chart['unit'] ? 'cm' : 'in';

		// Names the table for screen readers; the dialog title says it visually.
		$caption = sprintf(
			'%1$s <span class="aa-size-chart__caption-unit">(%2$s)</span>',
			esc_html__( 'Size chart', 'aggressive-apparel' ),
			self::dual(
				esc_html( self::unit_name( $chart['unit'] ) ),
				esc_html( self::unit_name( $other ) ),
				$chart['unit']
			)
		);

		$head = '<th scope="col">' . esc_html__( 'Size', 'aggressive-apparel' ) . '</th>';
		foreach ( $chart['columns'] as $column ) {
			$head .= '<th scope="col">' . esc_html( $column['label'] ) . '</th>';
		}

		// Revealed by the size guide script on the size picked in the form.
		$current = '<span class="screen-reader-text aa-size-chart__current" hidden>' . esc_html__( '(your selected size)', 'aggressive-apparel' ) . '</span>';

		$body = '';
		foreach ( $chart['rows'] as $row ) {
			$cells = '<th scope="row">' . esc_html( $row['size'] ) . ' ' . $current . '</th>';
			foreach ( $chart['columns'] as $index => $column ) {
				$value = $row['values'][ $index ] ?? '';
				if ( '' === $value ) {
					$cells .= '<td><span class="aa-size-chart__empty" aria-hidden="true">–</span><span class="screen-reader-text">' . esc_html__( 'Not available', 'aggressive-apparel' ) . '</span></td>';
				} elseif ( self::TYPE_MEASURE === $column['type'] ) {
					$cells .= '<td>' . self::dual( self::with_unit( $value, $chart['unit'] ), self::with_unit( self::convert( $value, $chart['unit'] ), $other ), $chart['unit'] ) . '</td>';
				} else {
					$cells .= '<td>' . esc_html( $value ) . '</td>';
				}
			}
			$body .= '<tr data-size="' . esc_attr( self::size_key( $row['size'] ) ) . '">' . $cells . '</tr>';
		}

		return sprintf(
			'<div class="aa-size-chart__scroll" role="region" tabindex="0" aria-label="%1$s"><table class="aa-size-chart__table"><caption class="screen-reader-text">%2$s</caption><thead><tr>%3$s</tr></thead><tbody>%4$s</tbody></table></div>',
			esc_attr__( 'Size chart, scrolls sideways', 'aggressive-apparel' ),
			$caption,
			$head,
			$body
		);
	}

	/**
	 * Notes and measuring diagram.
	 *
	 * @param int $guide_id Size guide ID.
	 */
	private static function notes( int $guide_id ): string {
		$html  = '';
		$notes = (string) get_post_meta( $guide_id, self::NOTES, true );
		if ( '' !== trim( $notes ) ) {
			$html .= '<div class="aa-size-chart__notes">' . wpautop( make_clickable( esc_html( $notes ) ) ) . '</div>';
		}

		$diagram = (int) get_post_meta( $guide_id, self::DIAGRAM, true );
		if ( $diagram > 0 && wp_attachment_is_image( $diagram ) ) {
			$alt   = (string) get_post_meta( $diagram, '_wp_attachment_image_alt', true );
			$html .= '<figure class="aa-size-chart__diagram">' . wp_get_attachment_image(
				$diagram,
				'large',
				false,
				array(
					'alt'     => '' !== $alt ? $alt : __( 'How to measure', 'aggressive-apparel' ),
					'loading' => 'lazy',
				)
			) . '</figure>';
		}

		return $html;
	}

	/**
	 * Both unit variants of a value; CSS shows the one matching data-unit.
	 *
	 * @param string $entered Escaped value in the entered unit.
	 * @param string $other   Escaped value in the other unit.
	 * @param string $unit    Entered unit.
	 */
	private static function dual( string $entered, string $other, string $unit ): string {
		$in = 'in' === $unit ? $entered : $other;
		$cm = 'in' === $unit ? $other : $entered;

		return '<span class="aa-size-chart__v" data-show="in">' . $in . '</span><span class="aa-size-chart__v" data-show="cm">' . $cm . '</span>';
	}

	/**
	 * A measurement with its unit beside it ("30 cm"), kept on one line.
	 *
	 * @param string $value Measurement.
	 * @param string $unit  Unit.
	 */
	private static function with_unit( string $value, string $unit ): string {
		return esc_html( $value ) . '&nbsp;<span class="aa-size-chart__unit">' . esc_html( $unit ) . '</span>';
	}

	/**
	 * Unit name for captions.
	 *
	 * @param string $unit Unit.
	 */
	private static function unit_name( string $unit ): string {
		return 'cm' === $unit ? __( 'centimetres', 'aggressive-apparel' ) : __( 'inches', 'aggressive-apparel' );
	}

	/**
	 * Lowercase key for matching the selected size ("2XL" → "2xl").
	 *
	 * @param string $size Size label.
	 */
	public static function size_key( string $size ): string {
		return strtolower( (string) preg_replace( '/[^a-z0-9]+/i', '', $size ) );
	}

	/**
	 * Sanitized short text.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $max   Maximum length.
	 */
	private static function text( $value, int $max ): string {
		return is_scalar( $value ) ? mb_substr( trim( sanitize_text_field( (string) $value ) ), 0, $max ) : '';
	}
}
