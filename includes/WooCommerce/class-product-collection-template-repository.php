<?php
/**
 * Product Collection Template Repository
 *
 * @package Aggressive_Apparel
 * @since 1.66.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves Product Collection blocks from active block templates.
 *
 * @since 1.66.0
 */
final class Product_Collection_Template_Repository {

	/**
	 * Parsed Product Collection blocks keyed by template slug for this request.
	 * Null means the template contains no collection block.
	 *
	 * @var array<string, ?array<string, mixed>>
	 */
	private array $cache = array();

	/**
	 * Resolve a template's `woocommerce/product-collection` block.
	 *
	 * A Site Editor customization takes precedence over the theme file so dynamic
	 * pages always render the same card structure as the initial document.
	 *
	 * @param string $template_slug Template slug.
	 * @return ?array<string, mixed>
	 */
	public function collection_block( string $template_slug ): ?array {
		if ( array_key_exists( $template_slug, $this->cache ) ) {
			return $this->cache[ $template_slug ];
		}

		$content = $this->template_content( $template_slug );
		$block   = '' === $content
			? null
			: $this->find_block( parse_blocks( $content ), 'woocommerce/product-collection' );

		$this->cache[ $template_slug ] = $block;

		return $block;
	}

	/**
	 * Load raw template content for a slug.
	 *
	 * @param string $template_slug Template slug.
	 * @return string
	 */
	private function template_content( string $template_slug ): string {
		$templates = get_block_templates( array( 'slug__in' => array( $template_slug ) ), 'wp_template' );

		if ( ! empty( $templates ) ) {
			return (string) $templates[0]->content;
		}

		$file = AGGRESSIVE_APPAREL_DIR . '/templates/' . $template_slug . '.html';
		if ( ! file_exists( $file ) ) {
			return '';
		}

		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		if ( ! $wp_filesystem ) {
			return '';
		}

		$content = $wp_filesystem->get_contents( $file );
		return false !== $content ? (string) $content : '';
	}

	/**
	 * Recursively find the first block matching a block name.
	 *
	 * @param array<int|string, array<string, mixed>> $blocks     Parsed blocks.
	 * @param string                                  $block_name Block name to match.
	 * @return ?array<string, mixed>
	 */
	private function find_block( array $blocks, string $block_name ): ?array {
		foreach ( $blocks as $block ) {
			if ( ( $block['blockName'] ?? '' ) === $block_name ) {
				return $block;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = $this->find_block( $block['innerBlocks'], $block_name );
				if ( null !== $found ) {
					return $found;
				}
			}
		}

		return null;
	}
}
