<?php
/**
 * Product Specs Transfer
 *
 * Moves the whole Size & Specs setup between sites (say, from a local copy
 * to the live store) as one file: every spec, every size guide with its
 * chart, notes, diagram, values, wording and categories, and each spec's
 * per-category boundaries. Nothing is retyped by hand.
 *
 * IDs differ between sites, so the file names things by what they are:
 * specs by key, categories by their slug path ("clothing/hoodies"), guides
 * by their Printful source, else their name, and attributes not at all (a
 * spec finds or makes its own here, as when it's created). Diagrams travel
 * inside the file, since one site often can't reach the other's media.
 *
 * Importing merges: specs and guides in the file are added or updated, and
 * nothing that's only on this site is touched or deleted. plan() says what
 * an import will do before apply() does it; both match things the same way.
 * Specs save through the Specs tab's own route (Product_Specs_Settings::
 * store) and guides through the editor's (Size_Guide_Rest::save).
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
 * Export and import of the Size & Specs setup.
 *
 * @phpstan-import-type Item from Product_Specs_Registry
 * @phpstan-type Plan array{site: string, exported: string, specs: array{added: list<string>, changed: list<string>, same: int}, guides: array{added: list<string>, changed: list<string>, same: int}, missing: list<string>}
 */
final class Product_Specs_Transfer {

	/**
	 * What the file is.
	 *
	 * @var string
	 */
	public const FORMAT = 'aggressive-apparel/size-and-specs';

	/**
	 * File version this site writes and reads.
	 *
	 * @var int
	 */
	public const VERSION = 1;

	/**
	 * Largest diagram carried in a file, in bytes.
	 *
	 * @var int
	 */
	private const DIAGRAM_MAX = 5 * MB_IN_BYTES;

	/**
	 * Attachment meta: a hash of a diagram an import made, so importing the
	 * same file again reuses it.
	 *
	 * @var string
	 */
	private const DIAGRAM_HASH = '_aa_transfer_hash';

	/**
	 * Image types a diagram may be.
	 *
	 * @var list<string>
	 */
	private const IMAGE_TYPES = array( 'image/png', 'image/jpeg', 'image/gif', 'image/webp' );

	/**
	 * Spec fields that only mean something on the site that has them.
	 *
	 * @var list<string>
	 */
	private const LOCAL_FIELDS = array( 'taxonomy', 'step_terms', 'default_term', 'terms' );

	/**
	 * This site's setup, as a file.
	 *
	 * @return array<string, mixed>
	 */
	public static function export(): array {
		$registry = Product_Specs_Registry::get();
		$items    = array_map( array( self::class, 'export_item' ), $registry['items'] );

		$guides = array();
		foreach ( Size_Guide_Post_Type::ids() as $id ) {
			$guides[] = self::export_guide( $id, true );
		}

		return array(
			'format'   => self::FORMAT,
			'version'  => self::VERSION,
			'exported' => gmdate( 'c' ),
			'site'     => home_url(),
			'specs'    => array(
				'hide_dimensions' => $registry['hide_dimensions'],
				'items'           => $items,
			),
			'guides'   => $guides,
		);
	}

	/**
	 * A file's contents, checked, or why it can't be imported.
	 *
	 * @param mixed $raw Decoded file.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function read( $raw ) {
		if ( ! is_array( $raw ) || self::FORMAT !== ( $raw['format'] ?? '' ) ) {
			return new \WP_Error( 'aa_transfer_not_a_file', __( 'That isn’t a Size & Specs export file.', 'aggressive-apparel' ), array( 'status' => 400 ) );
		}
		if ( (int) ( $raw['version'] ?? 0 ) > self::VERSION ) {
			return new \WP_Error( 'aa_transfer_newer', __( 'That file comes from a newer version of the theme. Update this site’s theme, then import it.', 'aggressive-apparel' ), array( 'status' => 400 ) );
		}
		$specs = is_array( $raw['specs'] ?? null ) ? $raw['specs'] : array();

		return array(
			'site'     => is_string( $raw['site'] ?? null ) ? esc_url_raw( $raw['site'] ) : '',
			'exported' => is_string( $raw['exported'] ?? null ) ? sanitize_text_field( $raw['exported'] ) : '',
			'specs'    => array(
				'hide_dimensions' => ! empty( $specs['hide_dimensions'] ),
				'items'           => array_values( array_filter( is_array( $specs['items'] ?? null ) ? $specs['items'] : array(), 'is_array' ) ),
			),
			'guides'   => array_values( array_filter( is_array( $raw['guides'] ?? null ) ? $raw['guides'] : array(), 'is_array' ) ),
		);
	}

	/**
	 * What importing a file would do, without doing it.
	 *
	 * @param array<string, mixed> $file A read() file.
	 * @return Plan
	 */
	public static function plan( array $file ): array {
		$missing = array();
		$saved   = array_column( Product_Specs_Registry::get()['items'], null, 'key' );
		$specs   = array(
			'added'   => array(),
			'changed' => array(),
			'same'    => 0,
		);
		foreach ( $file['specs']['items'] as $entry ) {
			$key   = self::key( $entry );
			$name  = is_scalar( $entry['name'] ?? null ) ? (string) $entry['name'] : $key;
			$local = self::import_item( $entry, $missing );
			if ( ! isset( $saved[ $key ] ) ) {
				$specs['added'][] = $name;
			} elseif ( self::same_item( array_merge( $saved[ $key ], $local ), $saved[ $key ] ) ) {
				++$specs['same'];
			} else {
				$specs['changed'][] = $name;
			}
		}

		$guides = array(
			'added'   => array(),
			'changed' => array(),
			'same'    => 0,
		);
		$match  = self::guide_matcher();
		foreach ( $file['guides'] as $entry ) {
			$title = self::title( $entry );
			$data  = self::import_guide( $entry, $missing );
			$id    = $match( $entry );
			if ( 0 === $id ) {
				$guides['added'][] = $title;
			} elseif ( self::differs( $id, $entry, $data ) ) {
				$guides['changed'][] = $title;
			} else {
				++$guides['same'];
			}
		}

		return array(
			'site'     => $file['site'],
			'exported' => $file['exported'],
			'specs'    => $specs,
			'guides'   => $guides,
			'missing'  => array_values( array_unique( $missing ) ),
		);
	}

	/**
	 * Import a file: specs first, then guides (whose values need the specs).
	 *
	 * @param array<string, mixed> $file A read() file.
	 * @return Plan|\WP_Error What it did, or why nothing was saved.
	 */
	public static function apply( array $file ) {
		$plan    = self::plan( $file );
		$missing = array();

		$saved = Product_Specs_Registry::get();
		$items = array_column( $saved['items'], null, 'key' );
		foreach ( $file['specs']['items'] as $entry ) {
			$key           = self::key( $entry );
			$local         = self::import_item( $entry, $missing );
			$items[ $key ] = isset( $items[ $key ] )
				// The attribute and its terms stay this site's own.
				? array_merge( $items[ $key ], $local )
				: $local;
		}
		$errors = Product_Specs_Settings::store( array_values( $items ), $file['specs']['hide_dimensions'] );
		if ( array() !== $errors ) {
			return new \WP_Error(
				'aa_transfer_specs_invalid',
				__( 'The file’s specs couldn’t be saved, so nothing was imported.', 'aggressive-apparel' ),
				array(
					'status' => 400,
					'errors' => $errors,
				)
			);
		}

		$match = self::guide_matcher();
		foreach ( $file['guides'] as $entry ) {
			$data = self::import_guide( $entry, $missing );
			$id   = $match( $entry );
			if ( $id > 0 && ! self::differs( $id, $entry, $data ) ) {
				continue;
			}
			if ( 0 === $id ) {
				$id = wp_insert_post(
					array(
						'post_type'   => Size_Guide_Post_Type::POST_TYPE,
						'post_status' => 'publish',
						'post_title'  => self::title( $entry ),
					),
					true
				);
				if ( is_wp_error( $id ) ) {
					return $id;
				}
				self::adopt_source( $id, $entry );
			}
			$post = array(
				'ID'         => $id,
				'post_title' => self::title( $entry ),
			);
			if ( is_string( $entry['content'] ?? null ) && '' !== $entry['content'] ) {
				$post['post_content'] = wp_kses_post( $entry['content'] );
			}
			wp_update_post( $post );
			$data['diagram'] = self::import_diagram( $entry['diagram'] ?? null, $id );
			Size_Guide_Rest::save( $id, $data );
		}

		return $plan;
	}

	/**
	 * A spec as the file carries it: no site-specific IDs, categories by path.
	 *
	 * @param array $item Item.
	 * @phpstan-param Item $item
	 * @return array<string, mixed>
	 */
	private static function export_item( array $item ): array {
		$steps = array();
		foreach ( $item['category_steps'] as $row ) {
			$path = self::category_path( $row['category'] );
			if ( '' !== $path ) {
				$steps[] = array(
					'category'   => $path,
					'thresholds' => $row['thresholds'],
				);
			}
		}

		return array_merge(
			array_diff_key( $item, array_flip( self::LOCAL_FIELDS ) ),
			array(
				'categories'     => array_values( array_filter( array_map( array( self::class, 'category_path' ), $item['categories'] ) ) ),
				'category_steps' => $steps,
			)
		);
	}

	/**
	 * Whether a spec as the file would leave it is the spec as saved.
	 *
	 * @param array<string, mixed> $raw   The saved spec with the file's fields.
	 * @param array                $saved The saved spec.
	 * @phpstan-param Item $saved
	 */
	private static function same_item( array $raw, array $saved ): bool {
		$item = Product_Specs_Registry::validate( array( 'items' => array( $raw ) ) )['items'][0] ?? null;

		return null !== $item && wp_json_encode( self::canonical( self::export_item( $item ) ) ) === wp_json_encode( self::canonical( self::export_item( $saved ) ) );
	}

	/**
	 * A file's spec with this site's category IDs (missing ones noted).
	 *
	 * @param array<mixed> $entry   Spec from the file.
	 * @param array        $missing Category paths this site lacks.
	 * @phpstan-param list<string> $missing
	 * @return array<string, mixed>
	 */
	private static function import_item( array $entry, array &$missing ): array {
		$entry = array_diff_key( $entry, array_flip( self::LOCAL_FIELDS ) );

		$entry['categories'] = self::category_ids( is_array( $entry['categories'] ?? null ) ? $entry['categories'] : array(), $missing );
		$steps               = array();
		foreach ( is_array( $entry['category_steps'] ?? null ) ? $entry['category_steps'] : array() as $row ) {
			$ids = self::category_ids( array( is_array( $row ) ? ( $row['category'] ?? '' ) : '' ), $missing );
			if ( array() !== $ids ) {
				$steps[] = array(
					'category'   => $ids[0],
					'thresholds' => is_array( $row['thresholds'] ?? null ) ? $row['thresholds'] : array(),
				);
			}
		}
		$entry['category_steps'] = $steps;
		$entry['key']            = self::key( $entry );

		return $entry;
	}

	/**
	 * A guide as the file carries it.
	 *
	 * @param int  $id       Size guide ID.
	 * @param bool $with_art Include the diagram's image (else just its hash).
	 * @return array<string, mixed>
	 */
	private static function export_guide( int $id, bool $with_art ): array {
		$guide = Size_Guide_Rest::guide( $id );
		$post  = get_post( $id );

		return array(
			'title'       => $guide['title'],
			'source'      => $guide['source'],
			'chart'       => $guide['chart'],
			'notes'       => $guide['notes'],
			'showNotes'   => $guide['showNotes'],
			'diagram'     => null !== $guide['diagram'] ? self::export_diagram( $guide['diagram']['id'], $with_art ) : null,
			'showDiagram' => $guide['showDiagram'],
			// Empty values too, so a value cleared there is cleared here.
			'specs'       => $guide['specs'],
			'hidden'      => $guide['hidden'],
			'wording'     => $guide['wording'],
			'categories'  => array_values( array_filter( array_map( array( self::class, 'category_path' ), $guide['categories'] ) ) ),
			'isDefault'   => $guide['isDefault'],
			'content'     => null === Size_Chart::get( $id ) && $post instanceof \WP_Post ? $post->post_content : '',
		);
	}

	/**
	 * A file's guide as the fields Size_Guide_Rest::save takes (no diagram).
	 *
	 * @param array<mixed> $entry   Guide from the file.
	 * @param array        $missing Category paths this site lacks.
	 * @phpstan-param list<string> $missing
	 * @return array<string, mixed>
	 */
	private static function import_guide( array $entry, array &$missing ): array {
		$data = array_intersect_key( $entry, array_flip( array( 'chart', 'notes', 'showNotes', 'showDiagram', 'specs', 'hidden', 'wording', 'isDefault' ) ) );

		$data['categories'] = self::category_ids( is_array( $entry['categories'] ?? null ) ? $entry['categories'] : array(), $missing );

		return $data;
	}

	/**
	 * Whether a file's guide differs from this site's.
	 *
	 * @param int                  $id    Size guide ID.
	 * @param array<mixed>         $entry Guide from the file.
	 * @param array<string, mixed> $data  Its fields for this site.
	 */
	private static function differs( int $id, array $entry, array $data ): bool {
		$here  = self::export_guide( $id, false );
		$there = array_merge(
			$here,
			array_intersect_key( $entry, $here ),
			array(
				// Values only for specs the file has; this site's others stay.
				'specs'      => array_merge( $here['specs'], is_array( $entry['specs'] ?? null ) ? $entry['specs'] : array() ),
				'categories' => array_values( array_filter( array_map( array( self::class, 'category_path' ), $data['categories'] ) ) ),
				'diagram'    => is_array( $entry['diagram'] ?? null ) ? array( 'hash' => sha1( (string) ( $entry['diagram']['data'] ?? '' ) ) ) : null,
			)
		);

		return wp_json_encode( self::canonical( $here ) ) !== wp_json_encode( self::canonical( $there ) );
	}

	/**
	 * A guide's diagram for the file: its image, or (to compare) its hash.
	 * An imported diagram compares by the hash of the image it came from,
	 * since WordPress may store it converted (to WebP, say).
	 *
	 * @param int  $attachment Attachment ID.
	 * @param bool $with_art   Include the image.
	 * @return array<string, string>|null
	 */
	private static function export_diagram( int $attachment, bool $with_art ): ?array {
		$imported = (string) get_post_meta( $attachment, self::DIAGRAM_HASH, true );
		if ( ! $with_art && '' !== $imported ) {
			return array( 'hash' => $imported );
		}
		$file = get_attached_file( $attachment );
		$type = (string) get_post_mime_type( $attachment );
		if ( ! is_string( $file ) || ! is_readable( $file ) || filesize( $file ) > self::DIAGRAM_MAX || ! in_array( $type, self::IMAGE_TYPES, true ) ) {
			return null;
		}
		$data = base64_encode( (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- An uploaded image (a local path) carried inside a JSON file.

		return $with_art
			? array(
				'name' => wp_basename( $file ),
				'type' => $type,
				'data' => $data,
			)
			: array( 'hash' => sha1( $data ) );
	}

	/**
	 * A file's diagram as an attachment here: the one an earlier import of
	 * the same image made, else a new one (0 when there's none or it isn't
	 * an image).
	 *
	 * @param mixed $diagram  Diagram from the file.
	 * @param int   $guide_id Size guide the attachment belongs to.
	 */
	private static function import_diagram( $diagram, int $guide_id ): int {
		if ( ! is_array( $diagram ) || ! is_string( $diagram['data'] ?? null ) ) {
			return 0;
		}
		$hash  = sha1( $diagram['data'] );
		$found = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'meta_key'       => self::DIAGRAM_HASH, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Indexed meta lookup, once per imported diagram.
				'meta_value'     => $hash, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- As above.
				'fields'         => 'ids',
				'posts_per_page' => 1,
			)
		);
		if ( array() !== $found ) {
			return (int) $found[0];
		}

		$bytes = base64_decode( $diagram['data'], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- The image carried inside the file.
		if ( false === $bytes || strlen( $bytes ) > self::DIAGRAM_MAX ) {
			return 0;
		}
		$size = getimagesizefromstring( $bytes );
		if ( false === $size || ! in_array( $size['mime'], self::IMAGE_TYPES, true ) ) {
			return 0;
		}

		$name = sanitize_file_name( is_string( $diagram['name'] ?? null ) ? $diagram['name'] : '' );
		if ( '' === pathinfo( $name, PATHINFO_FILENAME ) ) {
			$name = 'size-guide-diagram.' . wp_get_default_extension_for_mime_type( $size['mime'] );
		}
		if ( '' === $name ) {
			return 0;
		}
		$upload = wp_upload_bits( $name, null, $bytes );
		if ( '' !== (string) ( $upload['error'] ?? '' ) ) {
			return 0;
		}
		$attachment = wp_insert_attachment(
			array(
				'post_mime_type' => $size['mime'],
				'post_title'     => sanitize_text_field( pathinfo( $name, PATHINFO_FILENAME ) ),
				'post_status'    => 'inherit',
			),
			$upload['file'],
			$guide_id
		);
		if ( ! is_int( $attachment ) || $attachment <= 0 ) {
			return 0;
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $attachment, wp_generate_attachment_metadata( $attachment, $upload['file'] ) );
		update_post_meta( $attachment, self::DIAGRAM_HASH, $hash );

		return $attachment;
	}

	/**
	 * Finds this site's guide for a file's guide: by its Printful source,
	 * else by its name (ignoring case); 0 when there's none.
	 *
	 * @return callable(array<mixed>): int
	 */
	private static function guide_matcher(): callable {
		$by_source = Printful_Import::imported( 'printful' );
		$by_title  = array();
		foreach ( Size_Guide_Post_Type::ids() as $id ) {
			$by_title[ mb_strtolower( trim( get_the_title( $id ) ) ) ] ??= $id;
		}

		return static function ( array $entry ) use ( $by_source, $by_title ): int {
			$source = is_array( $entry['source'] ?? null ) ? absint( $entry['source']['id'] ?? 0 ) : 0;
			if ( $source > 0 && isset( $by_source[ $source ] ) ) {
				return $by_source[ $source ];
			}

			return $by_title[ mb_strtolower( self::title( $entry ) ) ] ?? 0;
		};
	}

	/**
	 * Note a new guide's Printful source, so later imports match it by that.
	 *
	 * @param int          $id    Size guide ID.
	 * @param array<mixed> $entry Guide from the file.
	 */
	private static function adopt_source( int $id, array $entry ): void {
		$source = is_array( $entry['source'] ?? null ) ? $entry['source'] : array();
		$pid    = absint( $source['id'] ?? 0 );
		if ( 'printful' !== ( $source['provider'] ?? '' ) || 0 === $pid ) {
			return;
		}
		update_post_meta(
			$id,
			Printful_Import::SOURCE,
			array(
				'provider' => 'printful',
				'id'       => $pid,
				'title'    => sanitize_text_field( (string) ( $source['title'] ?? '' ) ),
				'imported' => time(),
			)
		);
		update_post_meta( $id, Printful_Import::SOURCE_KEY, 'printful:' . $pid );
	}

	/**
	 * A category's slug path from the top ("clothing/hoodies"); '' if gone.
	 *
	 * @param int $id Category ID.
	 */
	private static function category_path( int $id ): string {
		$term = get_term( $id, 'product_cat' );
		if ( ! $term instanceof \WP_Term ) {
			return '';
		}
		$slugs = array( $term->slug );
		foreach ( get_ancestors( $id, 'product_cat', 'taxonomy' ) as $parent ) {
			$parent_term = get_term( (int) $parent, 'product_cat' );
			if ( $parent_term instanceof \WP_Term ) {
				array_unshift( $slugs, $parent_term->slug );
			}
		}

		return implode( '/', $slugs );
	}

	/**
	 * This site's IDs for category paths; a path it lacks is noted.
	 *
	 * @param array<mixed> $paths   Category paths.
	 * @param array        $missing Paths this site lacks.
	 * @phpstan-param list<string> $missing
	 * @return list<int>
	 */
	private static function category_ids( array $paths, array &$missing ): array {
		$ids = array();
		foreach ( $paths as $path ) {
			$path = is_string( $path ) ? trim( $path, '/' ) : '';
			if ( '' === $path ) {
				continue;
			}
			$parent = 0;
			$term   = null;
			foreach ( explode( '/', $path ) as $slug ) {
				$found = get_terms(
					array(
						'taxonomy'   => 'product_cat',
						'slug'       => sanitize_title( $slug ),
						'parent'     => $parent,
						'hide_empty' => false,
						'number'     => 1,
					)
				);
				$term  = is_array( $found ) && isset( $found[0] ) && $found[0] instanceof \WP_Term ? $found[0] : null;
				if ( null === $term ) {
					break;
				}
				$parent = $term->term_id;
			}
			if ( null === $term ) {
				$missing[] = $path;
			} else {
				$ids[] = $term->term_id;
			}
		}

		return $ids;
	}

	/**
	 * A spec entry's key, as the registry would make it.
	 *
	 * @param array<mixed> $entry Spec.
	 */
	private static function key( array $entry ): string {
		return sanitize_key( is_scalar( $entry['key'] ?? null ) ? (string) $entry['key'] : '' );
	}

	/**
	 * A guide entry's name.
	 *
	 * @param array<mixed> $entry Guide.
	 */
	private static function title( array $entry ): string {
		$title = is_scalar( $entry['title'] ?? null ) ? trim( sanitize_text_field( (string) $entry['title'] ) ) : '';

		return '' !== $title ? mb_substr( $title, 0, 120 ) : __( 'Untitled size guide', 'aggressive-apparel' );
	}

	/**
	 * A value with its maps' keys sorted, for comparing.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function canonical( $value ) {
		if ( ! is_array( $value ) ) {
			return is_float( $value ) && floor( $value ) === $value ? (int) $value : $value;
		}
		if ( ! array_is_list( $value ) ) {
			ksort( $value );
		}

		return array_map( array( self::class, 'canonical' ), $value );
	}
}
