<?php
/**
 * Product Specs — diagram transfer
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Core\Request_Cache;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carries a size guide's diagram image through a Size & Specs export file:
 * embeds it (within size and type limits) on export, and on import writes it
 * to the media library once, recognising a re-import by its content hash.
 * The guide/spec transfer itself is Product_Specs_Transfer.
 */
final class Product_Specs_Diagram {

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
	 * A guide's diagram for the file: the image (at "large" size when there
	 * is one), or a note that it couldn't be carried; null when it has none.
	 *
	 * @param int $guide_id Size guide ID.
	 * @return array<string, mixed>|null
	 */
	public static function export( int $guide_id ): ?array {
		$attachment = (int) get_post_meta( $guide_id, Size_Chart::DIAGRAM, true );
		if ( $attachment <= 0 || ! wp_attachment_is_image( $attachment ) ) {
			return null;
		}
		$file = self::file( $attachment );
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
	private static function file( int $attachment ): ?string {
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
	public static function hash( int $attachment ): string {
		if ( $attachment <= 0 ) {
			return '';
		}

		return Request_Cache::remember( self::key( 'hash:' . $attachment ), static fn(): string => self::compute_hash( $attachment ) );
	}

	/**
	 * Work out a diagram's hash: the stored import hash, else the file's.
	 *
	 * @param int $attachment Attachment ID.
	 * @return string
	 */
	private static function compute_hash( int $attachment ): string {
		$hash = (string) get_post_meta( $attachment, self::DIAGRAM_HASH, true );
		if ( '' !== $hash ) {
			return $hash;
		}
		$file = self::file( $attachment );

		return null !== $file ? (string) sha1_file( $file ) : '';
	}

	/**
	 * Request-cache key for diagram hashes; flush() bumps the generation,
	 * which drops them all at once.
	 *
	 * @param string $part Key suffix.
	 * @return string
	 */
	private static function key( string $part ): string {
		return 'product-specs-diagram:' . (int) Request_Cache::get( 'product-specs-diagram:generation', 0 ) . ':' . $part;
	}

	/**
	 * A file's diagram as an attachment here: the one an earlier import of
	 * the same image made, else a new one (0 when it isn't a usable image).
	 *
	 * @param array<mixed> $diagram  Diagram from the file.
	 * @param int          $guide_id Size guide the attachment belongs to.
	 */
	public static function import( array $diagram, int $guide_id ): int {
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
		Request_Cache::set( self::key( 'hash:' . $attachment ), $hash );

		return $attachment;
	}

	/**
	 * Reset the per-request hash cache.
	 *
	 * @return void
	 */
	public static function flush(): void {
		Request_Cache::set( 'product-specs-diagram:generation', (int) Request_Cache::get( 'product-specs-diagram:generation', 0 ) + 1 );
	}
}
