<?php
/**
 * Block System Manager for Aggressive Apparel.
 *
 * Handles automatic discovery and registration of Aggressive Apparel blocks
 * from the build directory with utilities for block validation.
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

declare(strict_types=1);

// phpcs:disable WordPress.Files.FileName, WordPress.Classes.ClassFileName

namespace Aggressive_Apparel\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Aggressive Apparel block types.
 */
class Blocks {

	/**
	 * Build directories to scan for blocks (relative to the active theme).
	 *
	 * @var array<string>
	 */
	private const BUILD_DIRS = array(
		'build/blocks',
		'build/blocks-interactivity',
	);

	/**
	 * Aggressive Apparel block namespace prefix.
	 */
	private const BLOCK_NAMESPACE = 'aggressive-apparel/';

	// === INITIALIZATION ===

	/**
	 * Initialize the Aggressive Apparel block system.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init(): void {
		\add_action( 'init', array( __CLASS__, 'register' ) );
		\add_action( 'enqueue_block_assets', array( __CLASS__, 'enqueue_rating_mark_var' ) );
	}

	/**
	 * Feed the product-rating block its brand mark from the icon system.
	 *
	 * The rating draws its marks with a CSS `mask`; this injects the mark as a
	 * `--aa-rating-mark` custom property on the block's stylesheet (front end and
	 * editor), sourced from `Icons`. That keeps the icon a single source of truth
	 * — swap the 'brand-mark' definition (or this slug) and the rating follows —
	 * while the marks themselves stay empty spans that survive the reviews-tab
	 * `wp_kses` pass.
	 *
	 * @since 1.17.0
	 * @return void
	 */
	public static function enqueue_rating_mark_var(): void {
		if ( ! function_exists( 'wp_add_inline_style' ) || ! class_exists( '\WP_Block_Type_Registry' ) ) {
			return;
		}

		$block = \WP_Block_Type_Registry::get_instance()->get_registered( self::BLOCK_NAMESPACE . 'product-rating' );

		if ( ! $block ) {
			return;
		}

		// Resolve the block's front-end style handle (array since WP 6.1,
		// falling back to the older scalar `style` property).
		$handle = '';
		if ( ! empty( $block->style_handles ) ) {
			$handles = (array) $block->style_handles;
			$handle  = (string) reset( $handles );
		} elseif ( ! empty( $block->style ) ) {
			$styles = (array) $block->style;
			$handle = (string) reset( $styles );
		}

		if ( '' === $handle || ! \wp_style_is( $handle, 'registered' ) ) {
			return;
		}

		$svg = \Aggressive_Apparel\Core\Icons::get( 'brand-mark' );

		if ( '' === $svg ) {
			return;
		}

		$data_uri = 'data:image/svg+xml,' . rawurlencode( $svg );

		\wp_add_inline_style( $handle, '.aa-rating{--aa-rating-mark:url("' . $data_uri . '")}' );
	}

	/**
	 * Automatically discover and register all Aggressive Apparel blocks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register(): void {
		// Skip registration if WordPress functions aren't available.
		if ( ! function_exists( 'register_block_type_from_metadata' ) || ! function_exists( 'get_template_directory' ) ) {
			return;
		}

		try {
			// Auto-discover and register all blocks from build directories.
			foreach ( self::BUILD_DIRS as $build_dir ) {
				$full_build_dir = self::get_build_directory( $build_dir );

				if ( empty( $full_build_dir ) || ! is_dir( $full_build_dir ) || ! is_readable( $full_build_dir ) ) {
					continue;
				}

				$block_directories = self::get_block_directories( $full_build_dir );

				self::register_blocks_from_directories( $block_directories );
			}
		} catch ( \Throwable $e ) {
			// Log error but don't break the site.
			if ( function_exists( 'error_log' ) ) {
				\error_log( 'Aggressive Apparel Blocks registration error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
	}


	/**
	 * Get the build directory path for blocks.
	 *
	 * @param string $build_dir The relative build directory path (e.g. 'build/blocks').
	 * @return string The full path to the blocks build directory with trailing slash, or empty string on failure.
	 */
	private static function get_build_directory( string $build_dir ): string {
		if ( ! function_exists( 'get_template_directory' ) ) {
			return '';
		}

		$template_dir = \get_template_directory();
		if ( ! $template_dir ) {
			return '';
		}

		// Normalize and ensure a trailing slash without relying on WP helpers.
		$template_dir = rtrim( $template_dir, '/\\' );
		$build_dir    = trim( $build_dir, '/\\' );

		return $template_dir . '/' . $build_dir . '/';
	}

	/**
	 * Get all block directories from the build directory.
	 *
	 * Each immediate subdirectory is treated as a block directory if it exists and is readable,
	 * and is expected to contain a block.json file.
	 *
	 * @param string $build_dir The build directory path.
	 * @return array<string> Array of block directory paths.
	 */
	private static function get_block_directories( string $build_dir ): array {
		$dirs = glob( rtrim( $build_dir, '/' ) . '/*', GLOB_ONLYDIR );
		if ( ! is_array( $dirs ) ) {
			return array();
		}
		return array_values( array_filter( $dirs, 'is_readable' ) );
	}

	/**
	 * Register blocks from directory paths.
	 *
	 * A directory is considered a block if it contains a block.json file.
	 *
	 * @param array<string> $block_directories Array of block directory paths.
	 * @return void
	 */
	private static function register_blocks_from_directories( array $block_directories ): void {
		foreach ( $block_directories as $block_location ) {
			$block_json = $block_location . '/block.json';

			if ( ! file_exists( $block_json ) || ! is_readable( $block_json ) ) {
				continue;
			}

			try {
				// Register the block using WordPress metadata.
				// WordPress will automatically load render.php if it exists.
				\register_block_type_from_metadata( $block_location );
			} catch ( \Throwable $e ) {
				// Log error but continue with other blocks.
				if ( function_exists( 'error_log' ) ) {
					\error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						'Block registration error for ' . $block_location . ': ' . $e->getMessage()
					);
				}
			}
		}
	}

	/**
	 * Check if a specific block is registered.
	 *
	 * @param string $block_name The block name to check (e.g. 'aggressive-apparel/dark-mode-toggle').
	 * @return bool True if block is registered.
	 */
	public static function is_block_registered( string $block_name ): bool {
		return \WP_Block_Type_Registry::get_instance()->is_registered( $block_name );
	}

	/**
	 * Get all registered Aggressive Apparel blocks.
	 *
	 * @return array<string> Array of registered block names.
	 */
	public static function get_registered_blocks(): array {
		$blocks   = array();
		$registry = \WP_Block_Type_Registry::get_instance();

		foreach ( $registry->get_all_registered() as $block_name => $block_type ) {
			if ( strpos( $block_type->name, self::BLOCK_NAMESPACE ) === 0 ) {
				$blocks[] = $block_name;
			}
		}

		return $blocks;
	}

	/**
	 * Check if blocks are built and available.
	 *
	 * @return bool True if any build directory exists and contains more than . and ..
	 */
	public static function blocks_available(): bool {
		foreach ( self::BUILD_DIRS as $build_dir ) {
			$full_build_dir = self::get_build_directory( $build_dir );

			if ( ! is_dir( $full_build_dir ) ) {
				continue;
			}

			$files = scandir( $full_build_dir );

			if ( is_array( $files ) && count( $files ) > 2 ) { // More than just . and ..
				return true;
			}
		}

		return false;
	}
}
