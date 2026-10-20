<?php
/**
 * Printful Import
 *
 * Turns a Printful catalog product into a size guide draft for review: its
 * size tables become charts (garment measurements and "measure yourself"),
 * their how-to-measure text becomes notes, and each bullet of its
 * description becomes a fact mapped to a spec where it can tell:
 *
 * - sure:    a rule the store saved, or an unmistakable pattern (fabric
 *            weight in oz or g/m², a fiber blend, "… construction");
 * - guess:   a likely match to confirm (a fit word on the Fit scale);
 * - unsure:  no idea; the store picks a spec, makes one, or skips it;
 * - skipped: not about the garment (sourcing, disclaimers).
 *
 * The store reviews and edits the draft, then creates an ordinary guide
 * (Size_Guide_Rest::create). On creation this copies the diagram into the
 * media library, records where the guide came from, and remembers each
 * mapping the store chose, so later imports ask less.
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
 * Printful product → size guide draft.
 *
 * @phpstan-import-type Chart from Size_Chart
 * @phpstan-import-type Item from Product_Specs_Registry
 * @phpstan-type Fact array{key: string, text: string, target: string, value: string, status: string}
 * @phpstan-type Draft array{source: array{provider: string, id: int, title: string}, title: string, image: string, charts: array{garment: Chart|null, body: Chart|null}, notes: array{garment: string, body: string}, diagrams: array{garment: string, body: string}, facts: list<Fact>, guide: int}
 */
final class Printful_Import {

	/**
	 * Guide meta: where an imported guide came from.
	 *
	 * @var string
	 */
	public const SOURCE = '_aa_guide_source';

	/**
	 * Guide meta: "provider:id", flat for lookups.
	 *
	 * @var string
	 */
	public const SOURCE_KEY = '_aa_guide_source_key';

	/**
	 * Background hook that copies a diagram into the media library.
	 *
	 * @var string
	 */
	public const DIAGRAM_ACTION = 'aggressive_apparel_import_diagram';

	/**
	 * Option: the store's mapping choices, provider => fact key => target.
	 *
	 * @var string
	 */
	public const RULES = 'aggressive_apparel_import_rules';

	/**
	 * Target meaning "not a spec, leave it out".
	 *
	 * @var string
	 */
	public const SKIP = 'skip';

	/**
	 * Most remembered rules per provider.
	 *
	 * @var int
	 */
	private const MAX_RULES = 300;

	/**
	 * Lines that describe sourcing or policy, not the garment.
	 *
	 * @var string
	 */
	private const NOT_A_SPEC = '/\b(sourced from|blank product|printed in|made in|imported|disclaimer|ships? from|fulfilled|may vary|see-through)\b/i';

	/**
	 * Text that would tell shoppers where products are made: the supplier's
	 * name or its print-on-demand boilerplate. Never offered as a spec (not
	 * even by a saved rule) and dropped from imported notes.
	 *
	 * @var string
	 */
	private const SUPPLIER = '/\b(printful|print[- ]on[- ]demand|on[- ]demand|made especially for you|as soon as you place an order|made to order)\b/i';

	/**
	 * Register hooks.
	 */
	public function init(): void {
		add_action( 'aggressive_apparel_size_guide_created', array( self::class, 'created' ), 10, 2 );
		add_action( self::DIAGRAM_ACTION, array( self::class, 'attach_diagram' ), 10, 2 );
	}

	/**
	 * A product as a draft guide for review.
	 *
	 * @param int $id Printful catalog product ID.
	 * @return Draft|\WP_Error
	 */
	public static function draft( int $id ) {
		$product = Printful_Catalog::product( $id );
		if ( is_wp_error( $product ) ) {
			return $product;
		}
		// No size tables (a 404: hats, bags) is fine and specs still import;
		// any other failure is Printful being unavailable, so say so rather
		// than make a guide without its chart.
		$sizes = Printful_Catalog::sizes( $id );
		if ( is_wp_error( $sizes ) ) {
			$data = $sizes->get_error_data();
			if ( ! is_array( $data ) || 404 !== ( $data['status'] ?? 0 ) ) {
				return $sizes;
			}
			$sizes = array();
		}

		// Garment and "measure yourself" tables only (not size conversions).
		$kinds  = array(
			'product_measure'  => 'garment',
			'measure_yourself' => 'body',
		);
		$tables = array();
		foreach ( is_array( $sizes['size_tables'] ?? null ) ? $sizes['size_tables'] : array() as $table ) {
			$kind = is_array( $table ) ? ( $kinds[ (string) ( $table['type'] ?? '' ) ] ?? '' ) : '';
			if ( '' !== $kind && ! isset( $tables[ $kind ] ) ) {
				$tables[ $kind ] = $table;
			}
		}
		$order = array_values( array_filter( array_map( 'strval', is_array( $sizes['available_sizes'] ?? null ) ? $sizes['available_sizes'] : array() ) ) );

		$brand = self::text( $product['brand'] ?? '' );
		$model = self::text( $product['model'] ?? '' );

		return array(
			'source'   => array(
				'provider' => 'printful',
				'id'       => $id,
				'title'    => self::text( $product['title'] ?? '' ),
			),
			'title'    => '' !== $brand && '' !== $model ? $brand . ' ' . $model : self::text( $product['title'] ?? '' ),
			'image'    => esc_url_raw( (string) ( $product['image'] ?? '' ) ),
			'charts'   => array(
				'garment' => isset( $tables['garment'] ) ? self::chart( $tables['garment'], $order ) : null,
				'body'    => isset( $tables['body'] ) ? self::chart( $tables['body'], $order ) : null,
			),
			'notes'    => array(
				'garment' => isset( $tables['garment'] ) ? self::notes( $tables['garment'] ) : '',
				'body'    => isset( $tables['body'] ) ? self::notes( $tables['body'] ) : '',
			),
			'diagrams' => array(
				'garment' => isset( $tables['garment'] ) ? self::diagram_url( $tables['garment']['image_url'] ?? '' ) : '',
				'body'    => isset( $tables['body'] ) ? self::diagram_url( $tables['body']['image_url'] ?? '' ) : '',
			),
			'facts'    => self::facts( (string) ( $product['description'] ?? '' ) ),
			'guide'    => self::guide_for( 'printful', $id ),
		);
	}

	/**
	 * After a guide is created from an import: copy its diagram into the
	 * media library, record its source, and remember the store's mappings.
	 *
	 * @param int                  $guide_id Size guide ID.
	 * @param array<string, mixed> $body     The create request's body.
	 */
	public static function created( int $guide_id, array $body ): void {
		$source = is_array( $body['importSource'] ?? null ) ? $body['importSource'] : null;
		if ( null === $source || 'printful' !== ( $source['provider'] ?? '' ) || absint( $source['id'] ?? 0 ) <= 0 ) {
			return;
		}

		update_post_meta(
			$guide_id,
			self::SOURCE,
			array(
				'provider' => 'printful',
				'id'       => absint( $source['id'] ),
				'title'    => mb_substr( self::text( $source['title'] ?? '' ), 0, 120 ),
				'imported' => time(),
			)
		);
		// A flat, indexed copy for the "already imported" lookup.
		update_post_meta( $guide_id, self::SOURCE_KEY, 'printful:' . absint( $source['id'] ) );

		// The diagram downloads in the background, so a slow CDN never makes
		// a created guide look failed.
		$url = self::diagram_url( $body['diagramUrl'] ?? '' );
		if ( '' !== $url ) {
			if ( function_exists( 'as_enqueue_async_action' ) ) {
				as_enqueue_async_action( self::DIAGRAM_ACTION, array( $guide_id, $url ), 'aggressive-apparel' );
			} else {
				wp_schedule_single_event( time(), self::DIAGRAM_ACTION, array( $guide_id, $url ) );
			}
		}

		self::remember( 'printful', is_array( $body['importRules'] ?? null ) ? $body['importRules'] : array() );
		( new Size_Guide() )->flush_all_caches();
	}

	/**
	 * Where a guide was imported from, for the editor (null when it wasn't).
	 *
	 * @param int $guide_id Size guide ID.
	 * @return array{provider: string, id: int, title: string}|null
	 */
	public static function source( int $guide_id ): ?array {
		$source = get_post_meta( $guide_id, self::SOURCE, true );
		if ( ! is_array( $source ) || 'printful' !== ( $source['provider'] ?? '' ) ) {
			return null;
		}

		return array(
			'provider' => 'printful',
			'id'       => absint( $source['id'] ?? 0 ),
			'title'    => self::text( $source['title'] ?? '' ),
		);
	}

	/**
	 * Guides already imported from a provider, by source product ID.
	 *
	 * @param string $provider Provider.
	 * @return array<int, int> Source product ID => guide ID.
	 */
	public static function imported( string $provider ): array {
		global $wpdb;

		// One query over the flat source key, however many guides exist.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- admin-only lookup; meta_value prefix match has no API.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.post_id, m.meta_value FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = %s AND m.meta_value LIKE %s AND p.post_type = %s AND p.post_status = 'publish' ORDER BY m.post_id",
				self::SOURCE_KEY,
				$wpdb->esc_like( $provider . ':' ) . '%',
				Size_Guide_Post_Type::POST_TYPE
			)
		);

		$map = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$map[ absint( substr( (string) $row->meta_value, strlen( $provider ) + 1 ) ) ] = (int) $row->post_id;
		}

		return $map;
	}

	/**
	 * Copy an imported guide's diagram into the media library (background).
	 *
	 * @param int    $guide_id Size guide ID.
	 * @param string $url      Diagram URL.
	 */
	public static function attach_diagram( $guide_id, $url ): void {
		$guide_id = absint( $guide_id );
		$url      = self::diagram_url( $url );
		if ( $guide_id <= 0 || '' === $url || Size_Guide_Post_Type::POST_TYPE !== get_post_type( $guide_id ) || (int) get_post_meta( $guide_id, Size_Chart::DIAGRAM, true ) > 0 ) {
			return;
		}

		$attachment = self::sideload( $url, $guide_id );
		if ( $attachment > 0 ) {
			update_post_meta( $guide_id, Size_Chart::DIAGRAM, $attachment );
			( new Size_Guide() )->flush_all_caches();
		}
	}

	/**
	 * Description bullets as facts mapped to specs.
	 *
	 * @param string $description Product description.
	 * @return list<Fact>
	 */
	public static function facts( string $description ): array {
		// Only specs the review can offer: store-wide and on. A rule or match
		// pointing anywhere else would be silently lost, so it isn't used.
		$items = array_column(
			array_filter( Product_Specs_Registry::get()['items'], static fn( array $item ): bool => $item['enabled'] && array() === $item['guides'] ),
			null,
			'key'
		);
		$rules = self::rules( 'printful' );
		$facts = array();
		$lines = preg_split( '/\R/u', $description );
		foreach ( false !== $lines ? $lines : array() as $line ) {
			if ( 1 !== preg_match( '/^\s*[•\-\*]\s*(.+)$/u', $line, $bullet ) ) {
				continue;
			}
			$text = self::text( $bullet[1] );
			if ( '' === $text ) {
				continue;
			}

			$key                 = self::fact_key( $text );
			[ $target, $status ] = self::classify( $text, $items );
			if ( 1 !== preg_match( self::SUPPLIER, $text ) && isset( $rules[ $key ] ) && ( self::SKIP === $rules[ $key ] || isset( $items[ $rules[ $key ] ] ) ) ) {
				[ $target, $status ] = array( $rules[ $key ], self::SKIP === $rules[ $key ] ? 'skipped' : 'sure' );
			}

			$facts[] = array(
				'key'    => $key,
				'text'   => $text,
				'target' => $target,
				'value'  => isset( $items[ $target ] ) ? self::value_for( $items[ $target ], $text ) : '',
				'status' => $status,
			);
		}

		return $facts;
	}

	/**
	 * A fact's value for a spec: a number (g/m² from ounces too) for a
	 * number scale, the step it names for a picked scale, else the text
	 * without its trailing aside ("100% cotton (Heather …)" → "100% cotton").
	 *
	 * @param array  $item Item.
	 * @param string $text Fact text.
	 * @phpstan-param Item $item
	 */
	public static function value_for( array $item, string $text ): string {
		if ( Product_Specs_Registry::TYPE_NUMBER === $item['type'] ) {
			$number = Product_Specs_Attributes::parse_number( $text, $item['unit'] );

			return $number > 0 ? (string) $number : '';
		}
		if ( Product_Specs_Registry::TYPE_PICK === $item['type'] ) {
			foreach ( Product_Specs_Taxonomy::step_names( $item ) as $i => $step ) {
				if ( '' !== $step && 1 === preg_match( '/\b' . preg_quote( strtolower( $step ), '/' ) . '\b/u', strtolower( $text ) ) ) {
					return (string) $i;
				}
			}

			return '';
		}

		$value = trim( (string) preg_replace( '/\s*\(.*$/u', '', $text ) );
		$value = trim( (string) preg_replace( '/^(fabric|material|composition)\s*:\s*/iu', '', $value ) );
		$value = trim( (string) preg_replace( '/\s+construction$/iu', '', $value ) );

		return ucfirst( $value );
	}

	/**
	 * A fact's rule key: its text with numbers generalized, so a rule for
	 * "Fabric weight: 4.2 oz" also covers "Fabric weight: 6.1 oz".
	 *
	 * @param string $text Fact text.
	 */
	public static function fact_key( string $text ): string {
		return mb_substr( mb_strtolower( trim( (string) preg_replace( array( '/[\d.,]+/', '/\s+/' ), array( '#', ' ' ), $text ) ) ), 0, 120 );
	}

	/**
	 * Best target for a fact and how sure it is.
	 *
	 * @param string              $text  Fact text.
	 * @param array<string, Item> $items Spec items by key.
	 * @return array{0: string, 1: string} Target (spec key, 'skip', or '') and status.
	 */
	private static function classify( string $text, array $items ): array {
		if ( 1 === preg_match( self::NOT_A_SPEC, $text ) || 1 === preg_match( self::SUPPLIER, $text ) ) {
			return array( self::SKIP, 'skipped' );
		}

		$find = static function ( string $kind, array $names ) use ( $items ): string {
			foreach ( $items as $key => $item ) {
				$haystack = array_map( 'strtolower', array_merge( array( $item['name'], $key ), $item['attributes'] ) );
				if ( $item['type'] === $kind && array() !== array_intersect( $haystack, $names ) ) {
					return $key;
				}
			}
			return '';
		};

		if ( 1 === preg_match( '/\b(oz|ounces?|g\s*\/\s*m|gsm)\b/iu', $text ) ) {
			$key = $find( Product_Specs_Registry::TYPE_NUMBER, array( 'weight', 'fabric weight', 'gsm' ) );
			return array( $key, '' !== $key ? 'sure' : 'unsure' );
		}
		// A composition ("50% cotton, 50% polyester", "Fabric: …"), not any
		// line that mentions a fiber ("rib-knit cuffs with spandex").
		if ( 1 === preg_match( '/^\s*(fabric|material|composition)\s*:|\d+\s*%\s*[^,;]*\b(cotton|polyester|rayon|spandex|elastane|linen|wool|fleece|viscose|nylon|modal|bamboo|acrylic|silk|hemp|lyocell|tencel)\b/iu', $text ) ) {
			$key = $find( Product_Specs_Registry::TYPE_TEXT, array( 'fabric', 'material', 'composition' ) );
			return array( $key, '' !== $key ? 'sure' : 'unsure' );
		}
		if ( 1 === preg_match( '/\b(construction|side-seamed|tubular|double-needle|twin-needle|taping|stitched|stitching)\b/iu', $text ) ) {
			$key = $find( Product_Specs_Registry::TYPE_TEXT, array( 'construction' ) );
			return array( $key, '' !== $key ? ( 1 === preg_match( '/\bconstruction\b/iu', $text ) ? 'sure' : 'guess' ) : 'unsure' );
		}
		if ( 1 === preg_match( '/\b(slim|fitted|regular|classic|relaxed|oversized|boxy|loose)\b[\s-]*fit\b|\bfit\b/iu', $text ) ) {
			$key = $find( Product_Specs_Registry::TYPE_PICK, array( 'fit' ) );
			if ( '' !== $key && '' !== self::value_for( $items[ $key ], $text ) ) {
				return array( $key, 'guess' );
			}
		}

		return array( '', 'unsure' );
	}

	/**
	 * A size table as our chart: one column per measurement, one row per
	 * size in Printful's order, ranges as "36–39".
	 *
	 * @param array<mixed> $table Printful size table.
	 * @param array        $order Sizes in order.
	 * @phpstan-param list<string> $order
	 * @return Chart|null
	 */
	private static function chart( array $table, array $order ): ?array {
		$columns = array();
		$cells   = array();
		foreach ( is_array( $table['measurements'] ?? null ) ? $table['measurements'] : array() as $measurement ) {
			if ( ! is_array( $measurement ) ) {
				continue;
			}
			$index     = count( $columns );
			$columns[] = array(
				'label' => self::text( $measurement['type_label'] ?? '' ),
				'type'  => Size_Chart::TYPE_MEASURE,
			);
			foreach ( is_array( $measurement['values'] ?? null ) ? $measurement['values'] : array() as $value ) {
				if ( ! is_array( $value ) ) {
					continue;
				}
				$size = self::text( $value['size'] ?? '' );
				$cell = isset( $value['value'] )
					? self::text( $value['value'] )
					: trim( self::text( $value['min_value'] ?? '' ) . '–' . self::text( $value['max_value'] ?? '' ), '–' );

				$cells[ $size ][ $index ] = $cell;
				if ( ! in_array( $size, $order, true ) ) {
					$order[] = $size;
				}
			}
		}
		if ( array() === $columns ) {
			return null;
		}

		$rows = array();
		foreach ( $order as $size ) {
			if ( isset( $cells[ $size ] ) ) {
				$rows[] = array(
					'size'   => $size,
					'values' => array_map( static fn( int $i ): string => $cells[ $size ][ $i ] ?? '', array_keys( $columns ) ),
				);
			}
		}

		$unit = strtolower( (string) ( $table['unit'] ?? '' ) );

		return Size_Chart::sanitize(
			array(
				'unit'    => str_starts_with( $unit, 'c' ) ? 'cm' : 'in',
				'columns' => $columns,
				'rows'    => $rows,
			)
		);
	}

	/**
	 * A table's description and how-to-measure text as plain notes.
	 *
	 * @param array<mixed> $table Printful size table.
	 */
	private static function notes( array $table ): string {
		$html = (string) ( $table['image_description'] ?? '' ) . "\n" . (string) ( $table['description'] ?? '' );
		$text = wp_strip_all_tags( (string) preg_replace( '#</(p|h\d|li)>|<br\s*/?>#i', "\n", $html ) );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = (string) preg_replace( "/[ \t]+/", ' ', $text );

		// Notes show on product pages: drop any sentence naming the supplier.
		$lines = array();
		foreach ( explode( "\n", $text ) as $line ) {
			$sentences = preg_split( '/(?<=[.!?])\s+/u', $line );
			$lines[]   = implode( ' ', array_filter( false !== $sentences ? $sentences : array( $line ), static fn( string $part ): bool => 1 !== preg_match( self::SUPPLIER, $part ) ) );
		}
		$text = (string) preg_replace( "/\n\s*\n+/", "\n\n", implode( "\n", $lines ) );

		return trim( $text );
	}

	/**
	 * A diagram URL, only from Printful's file CDN (the one host this copies
	 * images from), else ''.
	 *
	 * @param mixed $url URL.
	 */
	private static function diagram_url( $url ): string {
		$url = is_string( $url ) ? esc_url_raw( $url, array( 'https' ) ) : '';

		return '' !== $url && 'files.cdn.printful.com' === wp_parse_url( $url, PHP_URL_HOST ) ? $url : '';
	}

	/**
	 * Copy an image into the media library, attached to the guide.
	 *
	 * @param string $url      Image URL.
	 * @param int    $guide_id Size guide ID.
	 * @return int Attachment ID, or 0.
	 */
	private static function sideload( string $url, int $guide_id ): int {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Printful's URLs end in "_l?v=…", without an image extension.
		$file = download_url( $url, 20 );
		if ( is_wp_error( $file ) ) {
			return 0;
		}

		$type = wp_get_image_mime( $file );
		$ext  = array(
			'image/png'  => 'png',
			'image/jpeg' => 'jpg',
			'image/webp' => 'webp',
			'image/gif'  => 'gif',
		)[ (string) $type ] ?? '';
		if ( '' === $ext ) {
			wp_delete_file( $file );
			return 0;
		}

		$id = media_handle_sideload(
			array(
				'name'     => 'size-guide-diagram-' . $guide_id . '.' . $ext,
				'tmp_name' => $file,
			),
			$guide_id,
			get_the_title( $guide_id )
		);
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $file );
			return 0;
		}

		return (int) $id;
	}

	/**
	 * Remember the store's mapping choices for a provider.
	 *
	 * @param string       $provider Provider.
	 * @param array<mixed> $choices  [ { key, target } ].
	 */
	private static function remember( string $provider, array $choices ): void {
		$rules = self::rules( $provider );
		foreach ( $choices as $choice ) {
			if ( ! is_array( $choice ) ) {
				continue;
			}
			$key    = self::fact_key( self::text( $choice['key'] ?? '' ) );
			$target = sanitize_key( (string) ( $choice['target'] ?? '' ) );
			if ( '' !== $key && '' !== $target ) {
				unset( $rules[ $key ] );
				$rules[ $key ] = $target;
			}
		}

		$all              = get_option( self::RULES, array() );
		$all              = is_array( $all ) ? $all : array();
		$all[ $provider ] = array_slice( $rules, -self::MAX_RULES, null, true );
		update_option( self::RULES, $all, false );
	}

	/**
	 * A provider's remembered rules.
	 *
	 * @param string $provider Provider.
	 * @return array<string, string> Fact key => target.
	 */
	private static function rules( string $provider ): array {
		$all   = get_option( self::RULES, array() );
		$rules = is_array( $all ) && is_array( $all[ $provider ] ?? null ) ? $all[ $provider ] : array();

		return array_filter( array_map( static fn( $target ): string => is_scalar( $target ) ? (string) $target : '', $rules ) );
	}

	/**
	 * The guide already imported from a source product (0 for none).
	 *
	 * @param string $provider Provider.
	 * @param int    $id       Source product ID.
	 */
	private static function guide_for( string $provider, int $id ): int {
		return self::imported( $provider )[ $id ] ?? 0;
	}

	/**
	 * Plain text from an API string.
	 *
	 * @param mixed $value Value.
	 */
	private static function text( $value ): string {
		return is_scalar( $value ) ? trim( sanitize_text_field( (string) $value ) ) : '';
	}
}
