<?php
/**
 * Product Specs Transfer
 *
 * Moves the whole Size & Specs setup between sites (say, from a local copy
 * to the live store) as one file: every spec, every size guide with its
 * chart, notes, diagram, values, wording and categories, each spec's
 * per-category boundaries, and the store-wide dimensions setting.
 *
 * IDs differ between sites, so the file names things by what they are:
 * specs by key, categories by their slug path ("clothing/hoodies"), guides
 * by their Printful source, else by name, and attributes not at all (a spec
 * finds or makes its own here, as when it's created). Diagrams travel inside
 * the file at WordPress's "large" size, since one site often can't reach the
 * other's media.
 *
 * Importing merges: specs and guides in the file are added or updated, the
 * file's settings apply, and nothing that's only on this site is deleted. A
 * diagram the file couldn't carry, or that won't import, never removes the
 * one a guide here has. plan() says what an import will do; apply() does it
 * and reports what it did, including anything it couldn't. Specs save
 * through the Specs tab's own route (Product_Specs_Settings::store) and
 * guides through the editor's (Size_Guide_Rest::save).
 *
 * Built for large stores: categories are read in one query and resolved in
 * memory, guides are compared from what's stored (no rendering), and a
 * diagram is compared by a hash recorded when it was imported, else one
 * streamed from its file, never by re-encoding it.
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
 * @phpstan-type Changes array{added: list<string>, changed: list<string>, same: int}
 * @phpstan-type Plan array{site: string, exported: string, specs: Changes, guides: Changes, settings: list<string>, missing: list<string>, problems: list<string>}
 * @phpstan-type Catalog array{paths: array<int, string>, ids: array<string, int>, guides: array<int, list<int>>}
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
	private const DIAGRAM_MAX = 3 * MB_IN_BYTES;

	/**
	 * Attachment meta: the hash of the image a diagram was imported from, so
	 * importing it again reuses it and compares as the same.
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
	 * Transient prefix for a file waiting to be imported.
	 *
	 * @var string
	 */
	private const STASH = 'aa_specs_import_';

	/**
	 * Categories for this request (see catalog()).
	 *
	 * @var array{paths: array<int, string>, ids: array<string, int>, guides: array<int, list<int>>}|null
	 */
	private static ?array $catalog = null;

	/**
	 * Diagram hashes worked out this request, by attachment ID.
	 *
	 * @var array<int, string>
	 */
	private static array $hashes = array();

	/**
	 * This site's setup, as a file.
	 *
	 * @return array<string, mixed>
	 */
	public static function export(): array {
		$registry = Product_Specs_Registry::get();
		$guides   = array();
		foreach ( Size_Guide_Post_Type::ids() as $id ) {
			$guide            = self::snapshot( $id );
			$guide['diagram'] = self::export_diagram( $id );
			$guides[]         = $guide;
		}

		return array(
			'format'   => self::FORMAT,
			'version'  => self::VERSION,
			'exported' => gmdate( 'c' ),
			'site'     => home_url(),
			'specs'    => array(
				'hide_dimensions' => $registry['hide_dimensions'],
				'items'           => array_map( array( self::class, 'export_item' ), $registry['items'] ),
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
	 * Keep a checked file for the user to confirm, so it's only sent once.
	 *
	 * @param array<string, mixed> $file A read() file.
	 * @return string Token to import it with.
	 */
	public static function stash( array $file ): string {
		$token = wp_generate_password( 32, false );
		set_transient( self::stash_key( $token ), $file, HOUR_IN_SECONDS );

		return $token;
	}

	/**
	 * Take back a stashed file (once), or null when it's gone or not theirs.
	 *
	 * @param mixed $token Token from stash().
	 * @return array<string, mixed>|null
	 */
	public static function unstash( $token ): ?array {
		$token = is_string( $token ) ? (string) preg_replace( '/[^A-Za-z0-9]/', '', $token ) : '';
		if ( '' === $token ) {
			return null;
		}
		$file = get_transient( self::stash_key( $token ) );
		delete_transient( self::stash_key( $token ) );

		return is_array( $file ) ? $file : null;
	}

	/**
	 * What importing a file would do, without doing it.
	 *
	 * @param array<string, mixed> $file A read() file.
	 * @return Plan
	 */
	public static function plan( array $file ): array {
		$plan = self::run( $file, false );

		return is_wp_error( $plan ) ? self::empty_plan( $file ) : $plan;
	}

	/**
	 * Import a file: the setting and specs first, together or not at all,
	 * then each guide on its own, so one that fails is reported and the
	 * rest still import.
	 *
	 * @param array<string, mixed> $file A read() file.
	 * @return Plan|\WP_Error What it did, or why nothing was saved.
	 */
	public static function apply( array $file ) {
		return self::run( $file, true );
	}

	/**
	 * Forget what this request read (after categories change, and in tests).
	 */
	public static function flush(): void {
		self::$catalog = null;
		self::$hashes  = array();
	}

	/**
	 * Work out, and if asked do, an import, in one pass.
	 *
	 * @param array<string, mixed> $file  A read() file.
	 * @param bool                 $write Import it, or only say what it would do.
	 * @return Plan|\WP_Error
	 */
	private static function run( array $file, bool $write ) {
		$plan   = self::empty_plan( $file );
		$saved  = Product_Specs_Registry::get();
		$by_key = array_column( $saved['items'], null, 'key' );
		$items  = $by_key;
		foreach ( $file['specs']['items'] as $entry ) {
			$key   = self::key( $entry );
			$local = self::import_item( $entry, $plan['missing'] );
			$name  = is_scalar( $entry['name'] ?? null ) ? (string) $entry['name'] : $key;
			if ( ! isset( $by_key[ $key ] ) ) {
				$plan['specs']['added'][] = $name;
				$items[ $key ]            = $local;
			} elseif ( self::same_item( array_merge( $by_key[ $key ], $local ), $by_key[ $key ] ) ) {
				++$plan['specs']['same'];
			} else {
				$plan['specs']['changed'][] = $name;
				// The attribute and its terms stay this site's own.
				$items[ $key ] = array_merge( $by_key[ $key ], $local );
			}
		}

		if ( $file['specs']['hide_dimensions'] !== $saved['hide_dimensions'] ) {
			$plan['settings'][] = $file['specs']['hide_dimensions']
				? __( 'Shipping weight and dimensions will be hidden from Additional information.', 'aggressive-apparel' )
				: __( 'Shipping weight and dimensions will show in Additional information again.', 'aggressive-apparel' );
		}

		if ( $write && ( array() !== $plan['specs']['added'] || array() !== $plan['specs']['changed'] || array() !== $plan['settings'] ) ) {
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
		}

		$match = self::guide_matcher();
		foreach ( $file['guides'] as $entry ) {
			$data = self::import_guide( $entry, $plan['missing'] );
			$id   = $match( $entry );
			if ( $id > 0 && ! self::differs( $id, $entry, $data ) ) {
				++$plan['guides']['same'];
				continue;
			}
			$plan['guides'][ 0 === $id ? 'added' : 'changed' ][] = self::title( $entry );
			if ( $write ) {
				$problem = self::write_guide( $id, $entry, $data );
				if ( null !== $problem ) {
					$plan['problems'][] = $problem;
				}
			}
		}
		$plan['missing'] = array_values( array_unique( $plan['missing'] ) );

		return $plan;
	}

	/**
	 * A plan with nothing in it yet.
	 *
	 * @param array<string, mixed> $file A read() file.
	 * @return Plan
	 */
	private static function empty_plan( array $file ): array {
		$none = array(
			'added'   => array(),
			'changed' => array(),
			'same'    => 0,
		);

		return array(
			'site'     => (string) $file['site'],
			'exported' => (string) $file['exported'],
			'specs'    => $none,
			'guides'   => $none,
			'settings' => array(),
			'missing'  => array(),
			'problems' => array(),
		);
	}

	/**
	 * Write one guide from the file: create it if new, then its fields.
	 *
	 * @param int                  $id    Size guide ID here, or 0 for new.
	 * @param array<mixed>         $entry Guide from the file.
	 * @param array<string, mixed> $data  Its fields for this site.
	 * @return string|null What went wrong, if anything.
	 */
	private static function write_guide( int $id, array $entry, array $data ): ?string {
		$title = self::title( $entry );
		$post  = array( 'post_title' => $title );
		if ( is_string( $entry['content'] ?? null ) && '' !== $entry['content'] ) {
			$post['post_content'] = wp_kses_post( $entry['content'] );
		}

		// Both unslash what they're given, so it goes in slashed.
		$saved = 0 === $id
			? wp_insert_post(
				wp_slash(
					$post + array(
						'post_type'   => Size_Guide_Post_Type::POST_TYPE,
						'post_status' => 'publish',
					)
				),
				true
			)
			: wp_update_post( wp_slash( $post + array( 'ID' => $id ) ), true );
		if ( is_wp_error( $saved ) ) {
			/* translators: 1: size guide name, 2: error message. */
			return sprintf( __( '“%1$s” couldn’t be saved: %2$s', 'aggressive-apparel' ), $title, $saved->get_error_message() );
		}
		$id = (int) $saved;
		self::adopt_source( $id, $entry );

		$problem = null;
		$diagram = $entry['diagram'] ?? null;
		if ( null === $diagram ) {
			// None there, so none here.
			$data['diagram'] = 0;
		} elseif ( is_array( $diagram ) && is_string( $diagram['data'] ?? null ) ) {
			$attachment = self::import_diagram( $diagram, $id );
			if ( $attachment > 0 ) {
				$data['diagram'] = $attachment;
			} else {
				/* translators: %s: size guide name. */
				$problem = sprintf( __( '“%s”: its diagram couldn’t be added, so the guide keeps the one it had.', 'aggressive-apparel' ), $title );
			}
		}
		// One the file couldn't carry leaves this guide's as it is.

		Size_Guide_Rest::save( $id, $data );
		self::reassign( $id, $data['categories'] );

		return $problem;
	}

	/**
	 * Keep this request's categories right after a guide takes some: a
	 * category holds one guide, so it leaves whichever guide had it.
	 *
	 * @param int        $guide_id   Size guide ID.
	 * @param array<int> $categories Its categories now.
	 */
	private static function reassign( int $guide_id, array $categories ): void {
		$catalog = self::catalog();
		foreach ( $catalog['guides'] as $guide => $ids ) {
			$catalog['guides'][ $guide ] = array_values( array_diff( $ids, $categories ) );
		}
		$catalog['guides'][ $guide_id ] = array_values( array_map( 'intval', $categories ) );
		self::$catalog                  = $catalog;
	}

	/**
	 * A spec as the file carries it: no site-specific IDs, categories by path.
	 *
	 * @param array $item Item.
	 * @phpstan-param Item $item
	 * @return array<string, mixed>
	 */
	private static function export_item( array $item ): array {
		$paths = self::catalog()['paths'];
		$steps = array();
		foreach ( $item['category_steps'] as $row ) {
			if ( isset( $paths[ $row['category'] ] ) ) {
				$steps[] = array(
					'category'   => $paths[ $row['category'] ],
					'thresholds' => $row['thresholds'],
				);
			}
		}

		return array_merge(
			array_diff_key( $item, array_flip( self::LOCAL_FIELDS ) ),
			array(
				'categories'     => self::paths( $item['categories'] ),
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

		return null !== $item && self::same( self::export_item( $item ), self::export_item( $saved ) );
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
	 * A guide's stored fields as the file carries them (diagram aside), read
	 * straight from what's stored, with no rendering.
	 *
	 * @param int $id Size guide ID.
	 * @return array<string, mixed>
	 */
	private static function snapshot( int $id ): array {
		$post  = get_post( $id );
		$chart = Size_Chart::get( $id );
		$specs = Product_Specs_Values::guide( $id );

		return array(
			'title'       => $post instanceof \WP_Post ? Size_Guide_Rest::display_title( $post ) : '',
			'source'      => Printful_Import::source( $id ),
			'chart'       => $chart ?? Size_Chart::sanitize( array() ),
			'notes'       => (string) get_post_meta( $id, Size_Chart::NOTES, true ),
			'showNotes'   => Size_Chart::shows( $id, 'notes' ),
			'showDiagram' => Size_Chart::shows( $id, 'diagram' ),
			// Empty values too, so a value cleared there is cleared here.
			'specs'       => $specs['values'],
			'hidden'      => $specs['hidden'],
			'wording'     => $specs['wording'],
			'categories'  => self::paths( self::catalog()['guides'][ $id ] ?? array() ),
			'isDefault'   => Size_Guide_Assignments::is_default( $id ),
			'content'     => null === $chart && $post instanceof \WP_Post ? $post->post_content : '',
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
	 * Whether importing a file's guide would change this site's.
	 *
	 * @param int                  $id    Size guide ID.
	 * @param array<mixed>         $entry Guide from the file.
	 * @param array<string, mixed> $data  Its fields for this site.
	 */
	private static function differs( int $id, array $entry, array $data ): bool {
		$here  = self::snapshot( $id );
		$there = array_merge(
			$here,
			array_intersect_key( $entry, $here ),
			array(
				'title'      => self::title( $entry ),
				// A guide here keeps its own source; one without takes the file's.
				'source'     => $here['source'] ?? ( is_array( $entry['source'] ?? null ) ? $entry['source'] : null ),
				// Values only for specs the file has; this site's others stay.
				'specs'      => array_merge( $here['specs'], is_array( $entry['specs'] ?? null ) ? $entry['specs'] : array() ),
				'categories' => self::paths( $data['categories'] ),
			)
		);
		if ( ! self::same( $here, $there ) ) {
			return true;
		}

		$diagram = $entry['diagram'] ?? null;
		$current = (int) get_post_meta( $id, Size_Chart::DIAGRAM, true );
		if ( null === $diagram ) {
			return $current > 0;
		}

		// One the file couldn't carry is left as it is, so it's no change.
		return is_array( $diagram ) && is_string( $diagram['hash'] ?? null ) && self::diagram_hash( $current ) !== $diagram['hash'];
	}

	/**
	 * A guide's diagram for the file: the image (at "large" size when there
	 * is one), or a note that it couldn't be carried; null when it has none.
	 *
	 * @param int $guide_id Size guide ID.
	 * @return array<string, mixed>|null
	 */
	private static function export_diagram( int $guide_id ): ?array {
		$attachment = (int) get_post_meta( $guide_id, Size_Chart::DIAGRAM, true );
		if ( $attachment <= 0 || ! wp_attachment_is_image( $attachment ) ) {
			return null;
		}
		$file = self::diagram_file( $attachment );
		$type = null !== $file ? (string) wp_check_filetype( $file )['type'] : '';
		if ( null === $file || filesize( $file ) > self::DIAGRAM_MAX || ! in_array( $type, self::IMAGE_TYPES, true ) ) {
			return array( 'skipped' => true );
		}
		$bytes = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- An uploaded image (a local path) carried inside the file.

		return array(
			'name' => wp_basename( $file ),
			'type' => $type,
			'hash' => sha1( $bytes ),
			'data' => base64_encode( $bytes ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- An image carried inside a JSON file.
		);
	}

	/**
	 * The file a diagram travels as: its "large" size, else the original.
	 *
	 * @param int $attachment Attachment ID.
	 */
	private static function diagram_file( int $attachment ): ?string {
		$original = get_attached_file( $attachment );
		if ( ! is_string( $original ) || '' === $original ) {
			return null;
		}
		$large = image_get_intermediate_size( $attachment, 'large' );
		$file  = is_array( $large ) && is_string( $large['path'] ?? null )
			? trailingslashit( wp_get_upload_dir()['basedir'] ) . $large['path']
			: $original;
		if ( is_readable( $file ) ) {
			return $file;
		}

		return is_readable( $original ) ? $original : null;
	}

	/**
	 * The hash a diagram here compares by: the image it was imported from,
	 * else the file it would travel as (streamed, once per request).
	 *
	 * @param int $attachment Attachment ID (0 for none).
	 */
	private static function diagram_hash( int $attachment ): string {
		if ( $attachment <= 0 ) {
			return '';
		}
		if ( ! isset( self::$hashes[ $attachment ] ) ) {
			$hash = (string) get_post_meta( $attachment, self::DIAGRAM_HASH, true );
			if ( '' === $hash ) {
				$file = self::diagram_file( $attachment );
				$hash = null !== $file ? (string) sha1_file( $file ) : '';
			}
			self::$hashes[ $attachment ] = $hash;
		}

		return self::$hashes[ $attachment ];
	}

	/**
	 * A file's diagram as an attachment here: the one an earlier import of
	 * the same image made, else a new one (0 when it isn't a usable image).
	 *
	 * @param array<mixed> $diagram  Diagram from the file.
	 * @param int          $guide_id Size guide the attachment belongs to.
	 */
	private static function import_diagram( array $diagram, int $guide_id ): int {
		$bytes = base64_decode( (string) $diagram['data'], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- The image carried inside the file.
		if ( false === $bytes || strlen( $bytes ) > self::DIAGRAM_MAX ) {
			return 0;
		}
		$hash  = sha1( $bytes );
		$found = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'meta_key'       => self::DIAGRAM_HASH, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One lookup per imported diagram.
				'meta_value'     => $hash, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- As above.
				'fields'         => 'ids',
				'posts_per_page' => 1,
			)
		);
		if ( array() !== $found ) {
			return (int) $found[0];
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
		self::$hashes[ $attachment ] = $hash;

		return $attachment;
	}

	/**
	 * Finds this site's guide for a file's guide (0 when there's none). A
	 * guide with a Printful source is that product's: it matches the guide
	 * here with the same source, else one with its name and no source of its
	 * own, never another product's guide that shares the name. A guide
	 * without a source matches by name.
	 *
	 * @return callable(array<mixed>): int
	 */
	private static function guide_matcher(): callable {
		$by_source = Printful_Import::imported( 'printful' );
		$sourced   = array_flip( $by_source );
		$by_name   = array();
		foreach ( Size_Guide_Post_Type::ids() as $id ) {
			$post = get_post( $id );
			if ( $post instanceof \WP_Post ) {
				$by_name[ self::name_key( Size_Guide_Rest::display_title( $post ) ) ][] = $id;
			}
		}

		return static function ( array $entry ) use ( $by_source, $sourced, $by_name ): int {
			$source = is_array( $entry['source'] ?? null ) && 'printful' === ( $entry['source']['provider'] ?? '' ) ? absint( $entry['source']['id'] ?? 0 ) : 0;
			if ( $source > 0 && isset( $by_source[ $source ] ) ) {
				return $by_source[ $source ];
			}
			foreach ( $by_name[ self::name_key( self::title( $entry ) ) ] ?? array() as $id ) {
				if ( 0 === $source || ! isset( $sourced[ $id ] ) ) {
					return $id;
				}
			}

			return 0;
		};
	}

	/**
	 * Note a guide's Printful source when it has none here, so later imports
	 * match it by that.
	 *
	 * @param int          $id    Size guide ID.
	 * @param array<mixed> $entry Guide from the file.
	 */
	private static function adopt_source( int $id, array $entry ): void {
		$source = is_array( $entry['source'] ?? null ) ? $entry['source'] : array();
		$pid    = absint( $source['id'] ?? 0 );
		if ( 'printful' !== ( $source['provider'] ?? '' ) || 0 === $pid || null !== Printful_Import::source( $id ) ) {
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
	 * Every product category, read once per request: each one's slug path,
	 * the other way round, and the categories each guide is the default for.
	 *
	 * @return array{paths: array<int, string>, ids: array<string, int>, guides: array<int, list<int>>}
	 */
	private static function catalog(): array {
		if ( null !== self::$catalog ) {
			return self::$catalog;
		}
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);
		$by_id = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( $term instanceof \WP_Term ) {
				$by_id[ $term->term_id ] = $term;
			}
		}

		$paths  = array();
		$guides = array();
		foreach ( $by_id as $id => $term ) {
			$slugs = array( $term->slug );
			$seen  = array( $id => true );
			for ( $up = $term->parent; 0 < $up && isset( $by_id[ $up ] ) && ! isset( $seen[ $up ] ); $up = $by_id[ $up ]->parent ) {
				$seen[ $up ] = true;
				array_unshift( $slugs, $by_id[ $up ]->slug );
			}
			$paths[ $id ] = implode( '/', $slugs );

			// get_terms() primed term meta, so this reads no database.
			$guide = (int) get_term_meta( $id, Size_Guide::CPT_META_KEY, true );
			if ( $guide > 0 ) {
				$guides[ $guide ][] = $id;
			}
		}

		self::$catalog = array(
			'paths'  => $paths,
			'ids'    => array_flip( $paths ),
			'guides' => $guides,
		);

		return self::$catalog;
	}

	/**
	 * Category IDs as their slug paths, sorted (unknown ones dropped).
	 *
	 * @param array<int> $ids Category IDs.
	 * @return list<string>
	 */
	private static function paths( array $ids ): array {
		$paths = self::catalog()['paths'];
		$list  = array();
		foreach ( $ids as $id ) {
			if ( isset( $paths[ (int) $id ] ) ) {
				$list[] = $paths[ (int) $id ];
			}
		}
		sort( $list );

		return $list;
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
		$known = self::catalog()['ids'];
		$ids   = array();
		foreach ( $paths as $path ) {
			$path = is_string( $path ) ? trim( $path, '/' ) : '';
			if ( '' === $path ) {
				continue;
			}
			if ( isset( $known[ $path ] ) ) {
				$ids[] = $known[ $path ];
			} else {
				$missing[] = $path;
			}
		}

		return $ids;
	}

	/**
	 * The transient a stashed file waits in, per user.
	 *
	 * @param string $token Token.
	 */
	private static function stash_key( string $token ): string {
		return self::STASH . get_current_user_id() . '_' . $token;
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
	 * A guide entry's name, as stored.
	 *
	 * @param array<mixed> $entry Guide.
	 */
	private static function title( array $entry ): string {
		$title = is_scalar( $entry['title'] ?? null ) ? trim( sanitize_text_field( (string) $entry['title'] ) ) : '';

		return '' !== $title ? mb_substr( $title, 0, 120 ) : __( 'Untitled size guide', 'aggressive-apparel' );
	}

	/**
	 * A name for matching: entities decoded, spaces collapsed, lowercase.
	 *
	 * @param string $name Name.
	 */
	private static function name_key( string $name ): string {
		$name = html_entity_decode( $name, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return mb_strtolower( trim( (string) preg_replace( '/\s+/u', ' ', $name ) ) );
	}

	/**
	 * Whether two values are the same once maps are ordered and whole
	 * numbers read alike.
	 *
	 * @param mixed $a One value.
	 * @param mixed $b The other.
	 */
	private static function same( $a, $b ): bool {
		return wp_json_encode( self::canonical( $a ) ) === wp_json_encode( self::canonical( $b ) );
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
