<?php
/**
 * Size Chart Import
 *
 * Turns a size guide's old free-form content into a structured chart: the
 * first table becomes the grid (first column = sizes, header row = column
 * names), the remaining text becomes notes, and the unit is detected.
 *
 * A later table whose sizes match and whose numbers are the first table's
 * converted (×2.54 either way) is the same chart in the other unit and is
 * dropped (the chart converts on its own); any other table is kept, as
 * readable lines, in the notes. One image from the media library becomes the
 * measuring diagram; links keep their address in the notes.
 *
 * The automatic pass (first admin load after this ships) only converts guides
 * it can convert without losing anything shoppers see: tables, text, and at
 * most one library image. Anything richer (more images, embeds, other tables)
 * keeps rendering as it is until someone converts it from the editor, which
 * previews the result before saving. The old content always stays on the
 * post.
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
 * Old size guide content → structured chart.
 *
 * @phpstan-import-type Chart from Size_Chart
 * @phpstan-type Table array{header: list<string>, lines: list<list<string>>}
 */
final class Size_Chart_Import {

	/**
	 * Option recording that existing guides were converted.
	 *
	 * @var string
	 */
	public const MIGRATED_OPTION = 'aggressive_apparel_size_charts_migrated';

	/**
	 * Migration version; bump to re-run on existing stores.
	 *
	 * @var int
	 */
	private const VERSION = 1;

	/**
	 * Guides converted per query while migrating.
	 *
	 * @var int
	 */
	private const BATCH = 50;

	/**
	 * Median measurement above which a chart is in centimetres. Garment
	 * measurements in inches sit well below it (chest widths ~16–35, lengths
	 * ~25–34); the same garments in cm sit well above (~40–90).
	 *
	 * @var float
	 */
	private const CM_MEDIAN_THRESHOLD = 45.0;

	/**
	 * Ratio between the same measurement in cm and in inches.
	 *
	 * @var float
	 */
	private const CM_PER_INCH = 2.54;

	/**
	 * Elements a guide can hold and still convert without losing anything.
	 *
	 * @var list<string>
	 */
	private const LOSSLESS_TAGS = array( 'html', 'body', 'div', 'section', 'figure', 'figcaption', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'colgroup', 'col', 'p', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'b', 'em', 'i', 'u', 'span', 'a', 'br', 'small', 'sup', 'sub', 'mark', 'code', 'img' );

	/**
	 * Elements whose text becomes a note, in document order.
	 *
	 * @var string
	 */
	private const NOTE_XPATH = '//p[not(ancestor::table)] | //li[not(.//p) and not(ancestor::table)] | //h1 | //h2 | //h3 | //h4 | //h5 | //h6 | //figcaption[not(.//table) and not(ancestor::table)]';

	/**
	 * Column names that hold sizes in another system, never measurements.
	 * Mirrors SIZE_SYSTEM in src/scripts/admin/product-specs/_chart.ts.
	 *
	 * @var string
	 */
	private const SIZE_SYSTEM = '/^(us|uk|eu|it|fr|de|jp|au|cn|kr|mx|br|intl|international|alpha|numeric|size)\b/i';

	/**
	 * Register hooks.
	 */
	public function init(): void {
		add_action( 'admin_init', array( $this, 'maybe_migrate' ) );
	}

	/**
	 * Convert every old guide once.
	 */
	public function maybe_migrate(): void {
		if ( (int) get_option( self::MIGRATED_OPTION, 0 ) >= self::VERSION || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		self::migrate_all();

		// Product Specs folded into Size Guides & Specs; drop its old toggle.
		$features = get_option( Feature_Settings::OPTION_KEY, array() );
		if ( is_array( $features ) && array_key_exists( 'product_specs', $features ) ) {
			unset( $features['product_specs'] );
			update_option( Feature_Settings::OPTION_KEY, $features );
		}

		update_option( self::MIGRATED_OPTION, self::VERSION, false );
	}

	/**
	 * Convert every guide that has a table and no chart yet, in batches, so
	 * stores of any size are fully covered before the migration is marked done.
	 *
	 * @return int Guides converted.
	 */
	public static function migrate_all(): int {
		$converted = 0;
		$page      = 1;

		do {
			$ids = get_posts(
				array(
					'post_type'      => Size_Guide_Post_Type::POST_TYPE,
					'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future' ),
					'posts_per_page' => self::BATCH,
					'paged'          => $page,
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'fields'         => 'ids',
				)
			);
			foreach ( $ids as $id ) {
				if ( self::migrate( (int) $id ) ) {
					++$converted;
				}
			}
			++$page;
			$full = count( $ids ) === self::BATCH;
		} while ( $full );

		if ( $converted > 0 ) {
			( new Size_Guide() )->flush_all_caches();
		}

		return $converted;
	}

	/**
	 * Convert one guide automatically: only when it has no chart yet, has a
	 * table, and converts without losing anything shoppers see.
	 *
	 * @param int $guide_id Size guide post ID.
	 */
	public static function migrate( int $guide_id ): bool {
		if ( null !== Size_Chart::get( $guide_id ) ) {
			return false;
		}

		$post = get_post( $guide_id );
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		$imported = self::from_html( (string) do_blocks( $post->post_content ) );
		if ( null === $imported || ! $imported['lossless'] ) {
			return false;
		}

		update_post_meta( $guide_id, Size_Chart::META, $imported['chart'] );
		if ( '' !== $imported['notes'] && '' === (string) get_post_meta( $guide_id, Size_Chart::NOTES, true ) ) {
			update_post_meta( $guide_id, Size_Chart::NOTES, $imported['notes'] );
		}
		if ( $imported['diagram'] > 0 && 0 === (int) get_post_meta( $guide_id, Size_Chart::DIAGRAM, true ) ) {
			update_post_meta( $guide_id, Size_Chart::DIAGRAM, $imported['diagram'] );
		}

		return true;
	}

	/**
	 * A chart, notes, and diagram from free-form HTML, or null when it has no
	 * table. `lossless` says whether everything shoppers see was carried over.
	 *
	 * @param string $html Guide content (rendered blocks).
	 * @return array{chart: Chart, notes: string, diagram: int, lossless: bool}|null
	 */
	public static function from_html( string $html ): ?array {
		if ( '' === trim( $html ) || false === stripos( $html, '<table' ) ) {
			return null;
		}

		$doc      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$loaded   = $doc->loadHTML( '<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $loaded ) {
			return null;
		}

		$tables = array();
		foreach ( $doc->getElementsByTagName( 'table' ) as $element ) {
			$tables[] = array(
				'element' => $element,
				'data'    => self::read_table( $element ),
			);
		}
		if ( array() === $tables ) {
			return null;
		}

		$first  = $tables[0]['data'];
		$labels = array_slice( $first['header'], 1 );
		$width  = count( $labels );
		foreach ( $first['lines'] as $line ) {
			$width = max( $width, count( $line ) - 1 );
		}
		$width   = min( $width, Size_Chart::MAX_COLUMNS );
		$columns = array();
		for ( $i = 0; $i < $width; $i++ ) {
			$label     = $labels[ $i ] ?? '';
			$values    = array_map( static fn( array $line ): string => $line[ $i + 1 ] ?? '', $first['lines'] );
			$columns[] = array(
				'label' => $label,
				'type'  => self::is_measure( $values, $label ) ? Size_Chart::TYPE_MEASURE : Size_Chart::TYPE_TEXT,
			);
		}

		$rows = array();
		foreach ( $first['lines'] as $line ) {
			if ( '' === ( $line[0] ?? '' ) ) {
				continue;
			}
			$rows[] = array(
				'size'   => $line[0],
				'values' => array_map( static fn( int $i ): string => $line[ $i + 1 ] ?? '', range( 0, max( 0, $width - 1 ) ) ),
			);
		}
		if ( array() === $rows ) {
			return null;
		}

		$lossless = self::only_lossless_markup( $doc );

		// Later tables: the first one again in the other unit is dropped (the
		// chart converts); anything else stays readable in the notes.
		$extra = array();
		foreach ( array_slice( $tables, 1 ) as $table ) {
			if ( ! self::is_unit_copy( $first, $table['data'] ) ) {
				$extra[]  = self::table_notes( $table['data'] );
				$lossless = false;
			}
		}

		$diagram = self::diagram( $doc );
		if ( $doc->getElementsByTagName( 'img' )->length > ( $diagram > 0 ? 1 : 0 ) ) {
			$lossless = false;
		}

		$xpath = new \DOMXPath( $doc );
		$notes = array();
		$nodes = $xpath->query( self::NOTE_XPATH );
		foreach ( false !== $nodes ? $nodes : array() as $node ) {
			if ( ! $node instanceof \DOMElement ) {
				continue;
			}
			$text = self::note_text( $node );
			if ( '' !== $text ) {
				$notes[] = ( 'li' === strtolower( $node->nodeName ) ? '• ' : '' ) . $text; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
			}
		}

		$chart = array(
			'unit'    => self::detect_unit( $first, $columns, $rows, $html ),
			'columns' => $columns,
			'rows'    => $rows,
		);

		return array(
			'chart'    => Size_Chart::sanitize( $chart ),
			'notes'    => implode( "\n\n", array_merge( $notes, $extra ) ),
			'diagram'  => $diagram,
			'lossless' => $lossless,
		);
	}

	/**
	 * Whether the content is only elements the chart, notes, and diagram can
	 * carry, with no text outside them.
	 *
	 * @param \DOMDocument $doc Parsed guide.
	 */
	private static function only_lossless_markup( \DOMDocument $doc ): bool {
		foreach ( $doc->getElementsByTagName( '*' ) as $element ) {
			if ( ! in_array( strtolower( $element->nodeName ), self::LOSSLESS_TAGS, true ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
				return false;
			}
		}

		$stray = ( new \DOMXPath( $doc ) )->query( '//text()[normalize-space()][not(ancestor::p or ancestor::li or ancestor::h1 or ancestor::h2 or ancestor::h3 or ancestor::h4 or ancestor::h5 or ancestor::h6 or ancestor::figcaption or ancestor::table)]' );

		return false !== $stray && 0 === $stray->length;
	}

	/**
	 * Whether a later table is the first one in the other unit: the same
	 * sizes, with every number pair ×2.54 apart the same way.
	 *
	 * @param array $first First table.
	 * @param array $other Later table.
	 * @phpstan-param Table $first
	 * @phpstan-param Table $other
	 */
	private static function is_unit_copy( array $first, array $other ): bool {
		$sizes = static fn( array $table ): array => array_values( array_filter( array_map( static fn( array $line ): string => $line[0] ?? '', $table['lines'] ) ) );
		if ( $sizes( $first ) !== $sizes( $other ) || count( $first['lines'] ) !== count( $other['lines'] ) ) {
			return false;
		}

		$ratios = array();
		foreach ( $first['lines'] as $row => $line ) {
			foreach ( array_slice( $line, 1, null, true ) as $column => $value ) {
				$a = Size_Chart::numbers( $value );
				$b = Size_Chart::numbers( $other['lines'][ $row ][ $column ] ?? '' );
				if ( array() === $a || count( $a ) !== count( $b ) ) {
					continue;
				}
				foreach ( $a as $i => $number ) {
					if ( $number > 0 ) {
						$ratios[] = $b[ $i ] / $number;
					}
				}
			}
		}
		if ( array() === $ratios ) {
			return false;
		}

		foreach ( array( self::CM_PER_INCH, 1 / self::CM_PER_INCH ) as $target ) {
			$matches = array_filter( $ratios, static fn( float $ratio ): bool => abs( $ratio / $target - 1 ) <= 0.07 );
			if ( count( $matches ) === count( $ratios ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The media library ID of the guide's first image, or 0.
	 *
	 * @param \DOMDocument $doc Parsed guide.
	 */
	private static function diagram( \DOMDocument $doc ): int {
		$image = $doc->getElementsByTagName( 'img' )->item( 0 );
		if ( ! $image instanceof \DOMElement ) {
			return 0;
		}

		if ( 1 === preg_match( '/\bwp-image-(\d+)\b/', $image->getAttribute( 'class' ), $match ) ) {
			$id = (int) $match[1];
		} else {
			// Runs only when converting a guide, never on a storefront request.
			$id = attachment_url_to_postid( $image->getAttribute( 'src' ) ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.attachment_url_to_postid_attachment_url_to_postid
		}

		return $id > 0 && wp_attachment_is_image( $id ) ? $id : 0;
	}

	/**
	 * An element's text, with each link's address after its text
	 * ("size chart (https://…)") so it stays clickable in the notes.
	 *
	 * @param \DOMNode $node Element.
	 */
	private static function note_text( \DOMNode $node ): string {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property names.
		$text = '';
		foreach ( $node->childNodes as $child ) {
			if ( $child instanceof \DOMElement && 'a' === strtolower( $child->nodeName ) ) {
				$label = self::clean( $child->textContent );
				$href  = trim( $child->getAttribute( 'href' ) );
				$text .= ( '' !== $href && '#' !== $href[0] && $href !== $label ) ? $label . ' (' . $href . ')' : $label;
			} elseif ( $child instanceof \DOMElement && 'br' === strtolower( $child->nodeName ) ) {
				$text .= ' ';
			} elseif ( $child instanceof \DOMElement ) {
				$text .= self::note_text( $child );
			} else {
				$text .= $child->textContent;
			}
		}
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		return self::clean( $text );
	}

	/**
	 * Header and body lines of one table.
	 *
	 * The header is the thead row, or a first row made of th cells.
	 *
	 * @param \DOMElement $table Table element.
	 * @return Table
	 */
	private static function read_table( \DOMElement $table ): array {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property names.
		$header = null;
		$lines  = array();
		foreach ( $table->getElementsByTagName( 'tr' ) as $index => $tr ) {
			$cells   = array();
			$has_th  = false;
			$in_head = $tr->parentNode instanceof \DOMElement && 'thead' === strtolower( $tr->parentNode->nodeName );
			foreach ( $tr->childNodes as $cell ) {
				if ( $cell instanceof \DOMElement && in_array( strtolower( $cell->nodeName ), array( 'th', 'td' ), true ) ) {
					$has_th  = $has_th || 'th' === strtolower( $cell->nodeName );
					$cells[] = self::clean( $cell->textContent );
				}
			}
			if ( null === $header && ( $in_head || ( 0 === $index && $has_th ) ) ) {
				$header = $cells;
				continue;
			}
			$lines[] = $cells;
		}
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		return array(
			'header' => $header ?? array(),
			'lines'  => $lines,
		);
	}

	/**
	 * A table as readable note lines: "M — Chest 42, Length 29".
	 *
	 * @param array $table Table.
	 * @phpstan-param Table $table
	 */
	private static function table_notes( array $table ): string {
		$labels = array_slice( $table['header'], 1 );
		$out    = array();
		foreach ( $table['lines'] as $line ) {
			$parts = array();
			foreach ( array_slice( $line, 1 ) as $i => $value ) {
				if ( '' !== $value ) {
					$parts[] = trim( ( $labels[ $i ] ?? '' ) . ' ' . $value );
				}
			}
			if ( '' !== ( $line[0] ?? '' ) ) {
				$out[] = $line[0] . ( array() !== $parts ? ' — ' . implode( ', ', $parts ) : '' );
			}
		}

		return implode( "\n", $out );
	}

	/**
	 * The unit a chart was written in.
	 *
	 * Most reliable first: units in the column headers ("Chest (cm)"), then
	 * unambiguous words in the text ("inches", "centimetres" — never a bare
	 * "in", which ordinary sentences use), then the size of the numbers.
	 *
	 * @param array  $table   First table.
	 * @param array  $columns Columns.
	 * @param array  $rows    Rows.
	 * @param string $html    Whole guide.
	 * @phpstan-param Table $table
	 * @phpstan-param list<array{label: string, type: string}> $columns
	 * @phpstan-param list<array{size: string, values: list<string>}> $rows
	 */
	private static function detect_unit( array $table, array $columns, array $rows, string $html ): string {
		$header = strtolower( implode( ' ', $table['header'] ) );
		$cm     = 1 === preg_match( '/\bcm\b|centimet/', $header );
		$in     = 1 === preg_match( '/\binch|\(in\.?\)|\bin\.?$|"/', $header );
		if ( $cm !== $in ) {
			return $cm ? 'cm' : 'in';
		}

		$text = strtolower( wp_strip_all_tags( $html ) );
		$cm   = 1 === preg_match( '/\bcm\b|centimet/', $text );
		$in   = 1 === preg_match( '/\binch(es)?\b/', $text );
		if ( $cm !== $in ) {
			return $cm ? 'cm' : 'in';
		}

		$numbers = array();
		foreach ( $columns as $i => $column ) {
			if ( Size_Chart::TYPE_MEASURE !== $column['type'] ) {
				continue;
			}
			foreach ( $rows as $row ) {
				$numbers = array_merge( $numbers, Size_Chart::numbers( $row['values'][ $i ] ?? '' ) );
			}
		}
		if ( array() === $numbers ) {
			return 'in';
		}
		sort( $numbers );

		return $numbers[ intdiv( count( $numbers ), 2 ) ] > self::CM_MEDIAN_THRESHOLD ? 'cm' : 'in';
	}

	/**
	 * Whether a column holds measurements.
	 *
	 * @param array  $values Column values.
	 * @param string $label  Column name.
	 * @phpstan-param list<string> $values
	 */
	private static function is_measure( array $values, string $label ): bool {
		if ( 1 === preg_match( self::SIZE_SYSTEM, trim( $label ) ) ) {
			return false;
		}

		$filled = array_filter( $values, static fn( string $value ): bool => '' !== $value );

		return array() !== $filled && count( $filled ) === count( array_filter( $filled, static fn( string $value ): bool => array() !== Size_Chart::numbers( $value ) ) );
	}

	/**
	 * Collapse whitespace (including non-breaking spaces).
	 *
	 * @param string $text Text.
	 */
	private static function clean( string $text ): string {
		return trim( (string) preg_replace( '/[\s\x{00A0}]+/u', ' ', $text ) );
	}
}
