<?php
/**
 * Lazy brand icon provider for the centralized Icons library.
 *
 * @package Aggressive_Apparel
 * @since 1.16.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Core;

/**
 * Resolves build-generated brand SVG definitions only when requested.
 */
class Brand_Icons {

	/**
	 * Generated manifest path relative to the theme root.
	 */
	private const MANIFEST_PATH = 'build/icons/manifest.php';

	/**
	 * Provider identifier used by the shared icon registry.
	 */
	private const PROVIDER_ID = 'aggressive-apparel-brand-icons';

	/**
	 * Generated editor thumbnail catalog path relative to build/icons.
	 */
	private const EDITOR_THUMBNAILS_PATH = 'editor-thumbnails.php';

	/**
	 * Register the lazy provider with the shared icon library.
	 */
	public static function init(): void {
		Icons::register_provider(
			self::PROVIDER_ID,
			array( self::class, 'get_definition' ),
			array( self::class, 'list_slugs' )
		);
	}

	/**
	 * Load one generated icon definition.
	 *
	 * @param string $slug Icon slug.
	 * @return array<string, mixed>|null
	 */
	public static function get_definition( string $slug ): ?array {
		return Request_Cache::remember( 'brand-icons:definition:' . $slug, static fn(): ?array => self::load_definition( $slug ) );
	}

	/**
	 * Read one definition from build/icons. Fires
	 * `aggressive_apparel_brand_icon_loaded` when a file is actually read, so
	 * lazy loading (only the icons a page renders) is observable.
	 *
	 * @param string $slug Icon slug.
	 * @return array<string, mixed>|null
	 */
	private static function load_definition( string $slug ): ?array {
		$manifest = self::manifest();

		if ( ! isset( $manifest[ $slug ] ) ) {
			return null;
		}

		$relative_path = $manifest[ $slug ];
		$expected_path = 'definitions/' . $slug . '.php';

		// The generated manifest must never be able to escape build/icons.
		if ( $expected_path !== $relative_path ) {
			return null;
		}

		$definition_path = self::icons_directory() . '/' . $relative_path;

		if ( ! is_readable( $definition_path ) ) {
			return null;
		}

		$definition = require $definition_path;

		if ( ! is_array( $definition ) ) {
			return null;
		}

		/**
		 * Fires when a brand icon definition is read from disk.
		 *
		 * @param string $slug Icon slug.
		 */
		do_action( 'aggressive_apparel_brand_icon_loaded', $slug );

		return $definition;
	}

	/**
	 * Return generated brand icon slugs without loading their definitions.
	 *
	 * @return array<int, string>
	 */
	public static function list_slugs(): array {
		return array_keys( self::manifest() );
	}

	/**
	 * Return the build-time editor thumbnail catalog for brand icons.
	 *
	 * Oversized glyphs are omitted at build time. Missing catalog (pre-build)
	 * returns an empty structure so the REST layer can fall back safely.
	 *
	 * @return array{hash: string, size: int, thumbnails: array<string, string>}
	 */
	public static function editor_thumbnail_catalog(): array {
		return Request_Cache::remember( 'brand-icons:editor-thumbnails', static fn(): array => self::load_editor_thumbnail_catalog() );
	}

	/**
	 * Read the generated editor thumbnail catalog.
	 *
	 * @return array{hash: string, size: int, thumbnails: array<string, string>}
	 */
	private static function load_editor_thumbnail_catalog(): array {
		$empty = array(
			'hash'       => '',
			'size'       => 24,
			'thumbnails' => array(),
		);

		$catalog_path = self::icons_directory() . '/' . self::EDITOR_THUMBNAILS_PATH;

		if ( ! is_readable( $catalog_path ) ) {
			return $empty;
		}

		$catalog = require $catalog_path;

		if ( ! is_array( $catalog ) ) {
			return $empty;
		}

		$hash = isset( $catalog['hash'] ) && is_string( $catalog['hash'] )
			? $catalog['hash']
			: '';
		$size = isset( $catalog['size'] ) ? absint( $catalog['size'] ) : 24;
		$raw  = isset( $catalog['thumbnails'] ) && is_array( $catalog['thumbnails'] )
			? $catalog['thumbnails']
			: array();

		$thumbnails = array();

		foreach ( $raw as $slug => $svg ) {
			if (
				! is_string( $slug ) ||
				! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug ) ||
				! is_string( $svg ) ||
				'' === $svg ||
				! str_contains( $svg, '<svg' )
			) {
				continue;
			}

			$thumbnails[ $slug ] = $svg;
		}

		return array(
			'hash'       => $hash,
			'size'       => $size > 0 ? $size : 24,
			'thumbnails' => $thumbnails,
		);
	}

	/**
	 * Backward-compatible eager registration helper.
	 *
	 * New theme code uses the lazy provider registered by init(). Keeping this
	 * method avoids breaking integrations that called the previous public API.
	 *
	 * @param array<string, string|array<string, mixed>> $icons Existing icons.
	 * @return array<string, string|array<string, mixed>>
	 */
	public static function register_definitions( array $icons ): array {
		foreach ( self::list_slugs() as $slug ) {
			$definition = self::get_definition( $slug );

			if ( null !== $definition ) {
				$icons[ $slug ] = $definition;
			}
		}

		return $icons;
	}

	/**
	 * Read and validate the generated icon manifest.
	 *
	 * @return array<string, string>
	 */
	private static function manifest(): array {
		return Request_Cache::remember( 'brand-icons:manifest', static fn(): array => self::load_manifest() );
	}

	/**
	 * Read and validate build/icons/manifest.php.
	 *
	 * @return array<string, string>
	 */
	private static function load_manifest(): array {
		$manifest_path = dirname( __DIR__, 2 ) . '/' . self::MANIFEST_PATH;

		if ( ! is_readable( $manifest_path ) ) {
			return array();
		}

		$manifest = require $manifest_path;

		if ( ! is_array( $manifest ) ) {
			return array();
		}

		$validated = array();

		foreach ( $manifest as $slug => $relative_path ) {
			if (
				! is_string( $slug ) ||
				! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug ) ||
				! is_string( $relative_path ) ||
				'definitions/' . $slug . '.php' !== $relative_path
			) {
				continue;
			}

			$validated[ $slug ] = $relative_path;
		}

		return $validated;
	}

	/**
	 * Absolute path to generated icon artifacts.
	 */
	private static function icons_directory(): string {
		return dirname( __DIR__, 2 ) . '/build/icons';
	}
}
