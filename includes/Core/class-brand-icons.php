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
	 * Generated icon manifest cache.
	 *
	 * @var array<string, string>|null
	 */
	private static ?array $manifest_cache = null;

	/**
	 * Definitions loaded during the current request.
	 *
	 * @var array<string, array<string, mixed>|null>
	 */
	private static array $definition_cache = array();

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
		if ( array_key_exists( $slug, self::$definition_cache ) ) {
			return self::$definition_cache[ $slug ];
		}

		$manifest = self::manifest();

		if ( ! isset( $manifest[ $slug ] ) ) {
			self::$definition_cache[ $slug ] = null;
			return null;
		}

		$relative_path = $manifest[ $slug ];
		$expected_path = 'definitions/' . $slug . '.php';

		// The generated manifest must never be able to escape build/icons.
		if ( $expected_path !== $relative_path ) {
			self::$definition_cache[ $slug ] = null;
			return null;
		}

		$definition_path = self::icons_directory() . '/' . $relative_path;

		if ( ! is_readable( $definition_path ) ) {
			self::$definition_cache[ $slug ] = null;
			return null;
		}

		$definition = require $definition_path;

		if ( ! is_array( $definition ) ) {
			self::$definition_cache[ $slug ] = null;
			return null;
		}

		self::$definition_cache[ $slug ] = $definition;

		return self::$definition_cache[ $slug ];
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
		if ( null !== self::$manifest_cache ) {
			return self::$manifest_cache;
		}

		$manifest_path = dirname( __DIR__, 2 ) . '/' . self::MANIFEST_PATH;

		if ( ! is_readable( $manifest_path ) ) {
			self::$manifest_cache = array();
			return self::$manifest_cache;
		}

		$manifest = require $manifest_path;

		if ( ! is_array( $manifest ) ) {
			self::$manifest_cache = array();
			return self::$manifest_cache;
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

		self::$manifest_cache = $validated;

		return self::$manifest_cache;
	}

	/**
	 * Absolute path to generated icon artifacts.
	 */
	private static function icons_directory(): string {
		return dirname( __DIR__, 2 ) . '/build/icons';
	}

	/**
	 * Reset runtime caches for unit tests.
	 *
	 * @internal
	 */
	public static function flush_cache_for_tests(): void {
		self::$manifest_cache   = null;
		self::$definition_cache = array();
	}

	/**
	 * Report lazily loaded definitions for unit tests.
	 *
	 * @internal
	 * @return array<int, string>
	 */
	public static function loaded_slugs_for_tests(): array {
		return array_keys(
			array_filter(
				self::$definition_cache,
				static fn ( ?array $definition ): bool => null !== $definition
			)
		);
	}
}
