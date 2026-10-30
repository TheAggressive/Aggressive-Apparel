<?php
/**
 * Audience Sitemap Provider
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Rank Math sitemap of the audience landing pages (`audience-sitemap.xml`).
 *
 * Only loaded through Audience_Landing_Pages::sitemap_providers(), after it
 * checks that Rank Math's provider interface exists.
 */
final class Audience_Sitemap_Provider implements \RankMath\Sitemap\Providers\Provider {

	/** Sitemap type, the `{type}-sitemap.xml` prefix. */
	private const TYPE = 'audience';

	/**
	 * Whether this provider serves a sitemap type.
	 *
	 * @param string $type Sitemap type.
	 */
	public function handles_type( $type ) {
		return self::TYPE === $type;
	}

	/**
	 * The sitemap index entry, when any landing page has products.
	 *
	 * @param int $max_entries Entries per sitemap.
	 * @return array<int, array{loc: string, lastmod: string}>
	 */
	public function get_index_links( $max_entries ) {
		$links = Audience_Landing_Pages::sitemap_links();
		if ( array() === $links ) {
			return array();
		}

		$lastmod = max( array_column( $links, 'mod' ) );

		return array(
			array(
				'loc'     => \RankMath\Sitemap\Router::get_base_url( self::TYPE . '-sitemap.xml' ),
				'lastmod' => (string) $lastmod,
			),
		);
	}

	/**
	 * The landing pages.
	 *
	 * @param string $type         Sitemap type.
	 * @param int    $max_entries  Entries per sitemap.
	 * @param int    $current_page Sitemap page.
	 * @return array<int, array{loc: string, mod: string}>
	 */
	public function get_sitemap_links( $type, $max_entries, $current_page ) {
		return 1 === (int) $current_page ? Audience_Landing_Pages::sitemap_links() : array();
	}
}
