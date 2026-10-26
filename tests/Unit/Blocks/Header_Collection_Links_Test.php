<?php
/**
 * The header's Men's / Women's menus route to the Collection filter.
 *
 * @package Aggressive_Apparel\Tests\Unit\Blocks
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\Blocks;

use WP_UnitTestCase;

/** Audience menus link with `?collection=`, never the legacy `?fit=`. */
class Header_Collection_Links_Test extends WP_UnitTestCase {

	/**
	 * Every link in each audience submenu targets that audience's collection.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function audiences(): array {
		return array(
			"Men's"   => array( 'MENS', 'mens' ),
			"Women's" => array( 'WOMENS', 'womens' ),
		);
	}

	/**
	 * Each submenu link carries exactly its own collection.
	 *
	 * @dataProvider audiences
	 * @param string $label      Submenu label.
	 * @param string $collection Expected collection slug.
	 */
	public function test_submenu_links_use_collection( string $label, string $collection ): void {
		$links = $this->submenu_links( $label );
		$this->assertNotEmpty( $links );

		foreach ( $links as $name => $url ) {
			$query = array();
			wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
			$this->assertSame( array( 'collection' => $collection ), $query, "{$label} → {$name} ({$url})" );
		}
	}

	/** Regression: Women's → Tank Tops used to point at the men's filter. */
	public function test_womens_tank_tops_link(): void {
		$this->assertSame( '/shop/tank-tops/?collection=womens', $this->submenu_links( 'WOMENS' )['TANK TOPS'] ?? '' );
	}

	/**
	 * Link label → URL for one submenu of parts/header.html.
	 *
	 * @param string $label Submenu label.
	 * @return array<string, string>
	 */
	private function submenu_links( string $label ): array {
		$blocks = parse_blocks( (string) file_get_contents( get_template_directory() . '/parts/header.html' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local theme file.
		$links  = array();
		$walk   = function ( array $blocks ) use ( &$walk, &$links, $label ): void {
			foreach ( $blocks as $block ) {
				if ( 'aggressive-apparel/nav-submenu-dropdown' === $block['blockName'] && $label === ( $block['attrs']['label'] ?? '' ) ) {
					foreach ( $block['innerBlocks'] as $link ) {
						$links[ (string) ( $link['attrs']['label'] ?? '' ) ] = (string) ( $link['attrs']['url'] ?? '' );
					}
				}
				$walk( $block['innerBlocks'] );
			}
		};
		$walk( $blocks );
		return $links;
	}
}
