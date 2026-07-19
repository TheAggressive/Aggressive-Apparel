<?php
/**
 * Product Gallery Navigation
 *
 * Replaces WooCommerce's tiny off-center gallery arrows with theme chevrons
 * and registers block-scoped styles via wp_enqueue_block_style().
 *
 * @package Aggressive_Apparel
 * @since 1.77.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Assets\Asset_Loader;
use Aggressive_Apparel\Core\Icons;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product gallery prev/next button enhancements.
 *
 * @since 1.77.0
 */
class Product_Gallery_Nav {

	/**
	 * Block name for the gallery prev/next control.
	 */
	private const BLOCK_NAME = 'woocommerce/product-gallery-large-image-next-previous';

	/**
	 * Block-specific render filter (see WP_Block::render()).
	 */
	private const RENDER_FILTER = 'render_block_woocommerce/product-gallery-large-image-next-previous';

	/**
	 * Theme stylesheet handle for gallery nav overrides.
	 */
	private const STYLE_HANDLE = 'aggressive-apparel-product-gallery-nav';

	/**
	 * WooCommerce block style handle (generated from block.json).
	 */
	private const WC_STYLE_HANDLE = 'woocommerce-product-gallery-large-image-next-previous-style';

	/**
	 * Built CSS path relative to the theme root.
	 */
	private const STYLE_RELATIVE_PATH = 'build/styles/woocommerce/product-gallery.css';

	/**
	 * Block name for the gallery thumbnail strip.
	 */
	private const THUMBNAILS_BLOCK_NAME = 'woocommerce/product-gallery-thumbnails';

	/**
	 * Theme stylesheet handle for thumbnail strip overrides.
	 */
	private const THUMBNAILS_STYLE_HANDLE = 'aggressive-apparel-product-gallery-thumbnails';

	/**
	 * WooCommerce parent gallery style handle (carries the thumbnail rules).
	 */
	private const WC_GALLERY_STYLE_HANDLE = 'woocommerce-product-gallery-style';

	/**
	 * Built thumbnail CSS path relative to the theme root.
	 */
	private const THUMBNAILS_STYLE_RELATIVE_PATH = 'build/styles/woocommerce/product-gallery-thumbnails.css';

	/**
	 * Icon pixel size for gallery navigation buttons.
	 */
	private const ICON_SIZE = 22;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register_block_styles' ) );
		add_filter( self::RENDER_FILTER, array( $this, 'replace_nav_icons' ), 15 );
	}

	/**
	 * Register gallery nav overrides to load after WooCommerce block CSS.
	 *
	 * @return void
	 */
	public function register_block_styles(): void {
		$style_path = $this->get_style_path( self::STYLE_RELATIVE_PATH );

		if ( null !== $style_path ) {
			wp_enqueue_block_style(
				self::BLOCK_NAME,
				array(
					'handle' => self::STYLE_HANDLE,
					'src'    => AGGRESSIVE_APPAREL_URI . '/' . self::STYLE_RELATIVE_PATH,
					'path'   => $style_path,
					'deps'   => array(
						Asset_Loader::TOKENS_HANDLE,
						self::WC_STYLE_HANDLE,
					),
					'ver'    => (string) filemtime( $style_path ),
				)
			);
		}

		$thumbnails_path = $this->get_style_path( self::THUMBNAILS_STYLE_RELATIVE_PATH );

		if ( null !== $thumbnails_path ) {
			wp_enqueue_block_style(
				self::THUMBNAILS_BLOCK_NAME,
				array(
					'handle' => self::THUMBNAILS_STYLE_HANDLE,
					'src'    => AGGRESSIVE_APPAREL_URI . '/' . self::THUMBNAILS_STYLE_RELATIVE_PATH,
					'path'   => $thumbnails_path,
					'deps'   => array(
						Asset_Loader::TOKENS_HANDLE,
						self::WC_GALLERY_STYLE_HANDLE,
					),
					'ver'    => (string) filemtime( $thumbnails_path ),
				)
			);
		}
	}

	/**
	 * Swap default WooCommerce SVG arrows for centered theme chevrons.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @return string Modified block HTML.
	 */
	public function replace_nav_icons( string $block_content ): string {
		if ( ! str_contains( $block_content, 'wc-block-next-previous-buttons__icon' ) ) {
			return $block_content;
		}

		return $this->swap_nav_icon_markup( $block_content );
	}

	/**
	 * Absolute path to a built gallery stylesheet, if it exists.
	 *
	 * @param string $relative_path Theme-relative path to the built stylesheet.
	 * @return string|null Theme path or null when the build artifact is missing.
	 */
	private function get_style_path( string $relative_path ): ?string {
		$style_path = AGGRESSIVE_APPAREL_DIR . '/' . $relative_path;

		return file_exists( $style_path ) ? $style_path : null;
	}

	/**
	 * Replace WooCommerce arrow SVGs with centered theme chevrons.
	 *
	 * @param string $html Block HTML.
	 * @return string Modified HTML.
	 */
	private function swap_nav_icon_markup( string $html ): string {
		$is_rtl = is_rtl();

		$previous_icon = $is_rtl ? 'chevron-right' : 'chevron-left';
		$next_icon     = $is_rtl ? 'chevron-left' : 'chevron-right';

		$previous_svg = Icons::get(
			$previous_icon,
			array(
				'width'       => self::ICON_SIZE,
				'height'      => self::ICON_SIZE,
				'class'       => 'wc-block-next-previous-buttons__icon wc-block-next-previous-buttons__icon--left',
				'aria-hidden' => 'true',
			)
		);

		$next_svg = Icons::get(
			$next_icon,
			array(
				'width'       => self::ICON_SIZE,
				'height'      => self::ICON_SIZE,
				'class'       => 'wc-block-next-previous-buttons__icon wc-block-next-previous-buttons__icon--right',
				'aria-hidden' => 'true',
			)
		);

		if ( '' === $previous_svg || '' === $next_svg ) {
			return $html;
		}

		$updated = preg_replace(
			'#<svg(?=[^>]*wc-block-next-previous-buttons__icon--left)[^>]*>.*?</svg>#s',
			$previous_svg,
			$html,
			1
		);

		if ( ! is_string( $updated ) ) {
			return $html;
		}

		$updated = preg_replace(
			'#<svg(?=[^>]*wc-block-next-previous-buttons__icon--right)[^>]*>.*?</svg>#s',
			$next_svg,
			$updated,
			1
		);

		return is_string( $updated ) ? $updated : $html;
	}
}
