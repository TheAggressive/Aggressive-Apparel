<?php
/**
 * Editorial Collection Band
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
 * The black collection band shared by the Featured Drop and Defiance
 * Division sections.
 *
 * With a portrait photo of the collection it is an edge-to-edge spread: the
 * photo runs the full height of the band on the left, the title, line, CTA
 * and a row of two products on the right. Without one it is the title band above a
 * grid sized to what the collection has in stock. It never renders a
 * placeholder frame: those read as missing content.
 *
 * The pattern files pass their copy in already translated, so strings stay
 * in the patterns for extraction.
 */
final class Editorial_Collection_Band {

	/**
	 * Block markup for the band, or '' when the collection has nothing in stock.
	 *
	 * @param array<string, mixed> $args Band options.
	 * @phpstan-param array{term: \WP_Term, query_id: int, name: string, eyebrow: string, title: string, line: string, cta: string, grid_label: string} $args
	 */
	public static function markup( array $args ): string {
		$term  = $args['term'];
		$count = Editorial_Catalog::visible_count( $term );
		if ( 0 === $count ) {
			return '';
		}
		$lead = Editorial_Imagery::leads( 1, $term )[0] ?? null;
		$url  = Editorial_Catalog::url( $term );

		if ( null !== $lead ) {
			$text = self::text( $args, $url );
			// Beside the photo: one row of two, never one lone card. A 2 x 2
			// made the copy column so tall the photo stretched to ~1600px.
			$per_page = $count >= 2 ? 2 : 0;
			$grid     = $per_page > 0 ? self::grid( $args, $per_page, 2 ) : '';
			$wide     = 'max(var(--wp--preset--spacing--gutter), calc((100% - var(--wp--style--global--wide-size)) / 2))';
			$attrs    = array(
				'verticalAlignment' => 'center',
				'width'             => '55%',
				'style'             => array(
					'spacing' => array(
						'padding'  => array(
							'top'    => 'var:preset|spacing|section-standard',
							'right'  => $wide,
							'bottom' => 'var:preset|spacing|section-standard',
							'left'   => 'var:preset|spacing|gutter',
						),
						'blockGap' => 'var:preset|spacing|fluid-12',
					),
				),
			);
			$style    = 'padding-top:var(--wp--preset--spacing--section-standard);padding-right:' . $wide . ';padding-bottom:var(--wp--preset--spacing--section-standard);padding-left:var(--wp--preset--spacing--gutter);flex-basis:55%';
			// Photo first: on phones it sits above the copy, so the next
			// section (Women's, copy first) alternates rather than stacking
			// two photos.
			$inner   = '<!-- wp:columns {"className":"aa-tablet-split","align":"full","style":{"spacing":{"blockGap":{"top":"0","left":"0"},"margin":{"top":"0","bottom":"0"}}}} -->' . "\n"
				. '<div class="wp-block-columns aa-tablet-split alignfull" style="margin-top:0;margin-bottom:0"><!-- wp:column {"width":"45%","className":"aa-bleed-photo"} -->' . "\n"
				. '<div class="wp-block-column aa-bleed-photo" style="flex-basis:45%">' . Editorial_Imagery::image_markup( $lead, '4/5' ) . '</div>' . "\n"
				. '<!-- /wp:column -->' . "\n\n"
				. '<!-- wp:column ' . serialize_block_attributes( $attrs ) . ' -->' . "\n"
				. '<div class="wp-block-column is-vertically-aligned-center" style="' . esc_attr( $style ) . '">' . $text . $grid . '</div>' . "\n"
				. '<!-- /wp:column --></div>' . "\n"
				. '<!-- /wp:columns -->';
			$padding = array(
				'top'    => '0',
				'bottom' => '0',
				'left'   => '0',
				'right'  => '0',
			);
		} else {
			$columns = max( 2, min( 4, $count ) );
			$inner   = '<!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|fluid-12"}},"layout":{"type":"default"}} -->' . "\n"
				. '<div class="wp-block-group alignwide">' . self::header( $args, $url ) . self::grid( $args, $columns, $columns ) . '</div>' . "\n"
				. '<!-- /wp:group -->';
			$padding = array(
				'top'    => 'var:preset|spacing|section-standard',
				'bottom' => 'var:preset|spacing|section-standard',
				'left'   => 'var:preset|spacing|gutter',
				'right'  => 'var:preset|spacing|gutter',
			);
		}

		$band = array(
			'metadata'        => array( 'name' => $args['name'] ),
			'align'           => 'full',
			'style'           => array(
				'spacing'  => array(
					'padding' => $padding,
					'margin'  => array(
						'top'    => '0',
						'bottom' => '0',
					),
				),
				'elements' => array( 'link' => array( 'color' => array( 'text' => 'var:preset|color|white' ) ) ),
			),
			'backgroundColor' => 'black',
			'textColor'       => 'white',
			'layout'          => array( 'type' => null !== $lead ? 'default' : 'constrained' ),
		);
		$css  = 'margin-top:0;margin-bottom:0;'
			. 'padding-top:' . self::css_value( $padding['top'] ) . ';padding-right:' . self::css_value( $padding['right'] )
			. ';padding-bottom:' . self::css_value( $padding['bottom'] ) . ';padding-left:' . self::css_value( $padding['left'] );

		return '<!-- wp:group ' . serialize_block_attributes( $band ) . ' -->' . "\n"
			. '<div class="wp-block-group alignfull has-white-color has-black-background-color has-text-color has-background has-link-color" style="' . esc_attr( $css ) . '">' . $inner . '</div>' . "\n"
			. '<!-- /wp:group -->';
	}

	/**
	 * Eyebrow, title, line and CTA.
	 *
	 * @param array<string, mixed> $args Band options.
	 * @param string               $url  Collection archive URL.
	 */
	private static function text( array $args, string $url ): string {
		// Eyebrow, title and line sit 12px apart like every section header;
		// the CTA keeps the group's larger gap below them.
		return '<!-- wp:group {"layout":{"type":"constrained","contentSize":"720px","justifyContent":"left"}} -->' . "\n"
			. '<div class="wp-block-group"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|3"}},"layout":{"type":"default"}} -->' . "\n"
			. '<div class="wp-block-group"><!-- wp:paragraph {"className":"is-style-meta","textColor":"white"} -->' . "\n"
			. '<p class="is-style-meta has-white-color has-text-color">' . esc_html( (string) $args['eyebrow'] ) . '</p>' . "\n"
			. '<!-- /wp:paragraph -->' . "\n\n"
			. '<!-- wp:heading {"className":"is-style-campaign","textColor":"white"} -->' . "\n"
			. '<h2 class="wp-block-heading is-style-campaign has-white-color has-text-color">' . esc_html( (string) $args['title'] ) . '</h2>' . "\n"
			. '<!-- /wp:heading -->' . "\n\n"
			. '<!-- wp:paragraph {"className":"is-style-lead","textColor":"white"} -->' . "\n"
			. '<p class="is-style-lead has-white-color has-text-color">' . esc_html( (string) $args['line'] ) . '</p>' . "\n"
			. '<!-- /wp:paragraph --></div>' . "\n"
			. '<!-- /wp:group -->' . "\n\n"
			. '<!-- wp:buttons -->' . "\n"
			. '<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-outline-on-dark"} -->' . "\n"
			. '<div class="wp-block-button is-style-outline-on-dark"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $url ) . '">' . esc_html( (string) $args['cta'] ) . '</a></div>' . "\n"
			. '<!-- /wp:button --></div>' . "\n"
			. '<!-- /wp:buttons --></div>' . "\n"
			. '<!-- /wp:group -->';
	}

	/**
	 * Without a photo: one header row, the title on the left and the line and
	 * CTA on the right, so the products start one screen-height sooner.
	 *
	 * @param array<string, mixed> $args Band options.
	 * @param string               $url  Collection archive URL.
	 */
	private static function header( array $args, string $url ): string {
		return '<!-- wp:columns {"verticalAlignment":"bottom","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|8","left":"var:preset|spacing|fluid-16"}}}} -->' . "\n"
			. '<div class="wp-block-columns are-vertically-aligned-bottom"><!-- wp:column {"verticalAlignment":"bottom","width":"60%"} -->' . "\n"
			. '<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:60%"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|3"}},"layout":{"type":"default"}} -->' . "\n"
			. '<div class="wp-block-group"><!-- wp:paragraph {"className":"is-style-meta","textColor":"white"} -->' . "\n"
			. '<p class="is-style-meta has-white-color has-text-color">' . esc_html( (string) $args['eyebrow'] ) . '</p>' . "\n"
			. '<!-- /wp:paragraph -->' . "\n\n"
			. '<!-- wp:heading {"className":"is-style-campaign","textColor":"white"} -->' . "\n"
			. '<h2 class="wp-block-heading is-style-campaign has-white-color has-text-color">' . esc_html( (string) $args['title'] ) . '</h2>' . "\n"
			. '<!-- /wp:heading --></div>' . "\n"
			. '<!-- /wp:group --></div>' . "\n"
			. '<!-- /wp:column -->' . "\n\n"
			. '<!-- wp:column {"verticalAlignment":"bottom","width":"40%"} -->' . "\n"
			. '<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:40%"><!-- wp:paragraph {"className":"is-style-lead","textColor":"white"} -->' . "\n"
			. '<p class="is-style-lead has-white-color has-text-color">' . esc_html( (string) $args['line'] ) . '</p>' . "\n"
			. '<!-- /wp:paragraph -->' . "\n\n"
			. '<!-- wp:buttons -->' . "\n"
			. '<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-outline-on-dark"} -->' . "\n"
			. '<div class="wp-block-button is-style-outline-on-dark"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $url ) . '">' . esc_html( (string) $args['cta'] ) . '</a></div>' . "\n"
			. '<!-- /wp:button --></div>' . "\n"
			. '<!-- /wp:buttons --></div>' . "\n"
			. '<!-- /wp:column --></div>' . "\n"
			. '<!-- /wp:columns -->';
	}

	/**
	 * The collection's product grid.
	 *
	 * @param array<string, mixed> $args     Band options.
	 * @param int                  $per_page Products shown.
	 * @param int                  $columns  Columns.
	 */
	private static function grid( array $args, int $per_page, int $columns ): string {
		/** The term is checked by markup(). @var \WP_Term $term */
		$term = $args['term'];

		return Editorial_Product_Grid::markup(
			array(
				'query_id' => (int) $args['query_id'],
				'per_page' => $per_page,
				'columns'  => $columns,
				'term'     => $term,
				'align'    => '',
				'label'    => (string) $args['grid_label'],
			)
		);
	}

	/**
	 * A spacing value as CSS: preset references become custom properties.
	 *
	 * @param string $value Block spacing value.
	 */
	private static function css_value( string $value ): string {
		return str_starts_with( $value, 'var:preset|spacing|' )
			? 'var(--wp--preset--spacing--' . substr( $value, strlen( 'var:preset|spacing|' ) ) . ')'
			: $value;
	}
}
