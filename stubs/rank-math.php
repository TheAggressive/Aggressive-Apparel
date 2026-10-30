<?php
/**
 * Rank Math SEO classes the theme integrates with (PHPStan only).
 *
 * @package Aggressive_Apparel
 */

namespace RankMath\Sitemap\Providers;

interface Provider {
	/**
	 * @param string $type Sitemap type.
	 * @return bool
	 */
	public function handles_type( $type );

	/**
	 * @param int $max_entries Entries per sitemap.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_index_links( $max_entries );

	/**
	 * @param string $type         Sitemap type.
	 * @param int    $max_entries  Entries per sitemap.
	 * @param int    $current_page Sitemap page.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_sitemap_links( $type, $max_entries, $current_page );
}

namespace RankMath\Sitemap;

class Router {
	/**
	 * @param string $page Sitemap file name.
	 * @return string
	 */
	public static function get_base_url( $page ) {
		return '';
	}
}
