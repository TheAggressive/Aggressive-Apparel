<?php
/**
 * Brand icon load recording for tests.
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Support;

/**
 * Records every brand icon definition read from disk, in order, through the
 * `aggressive_apparel_brand_icon_loaded` action. A slug read twice appears
 * twice, so the list proves both laziness (only what rendered) and caching
 * (read once per request).
 */
trait Records_Brand_Icon_Loads {

	/**
	 * Slugs read from disk since recording started.
	 *
	 * @var array<int, string>
	 */
	private array $brand_icon_loads = array();

	/**
	 * Start from an empty request cache and record from here on.
	 *
	 * @return void
	 */
	protected function record_brand_icon_loads(): void {
		wp_cache_flush();
		$this->brand_icon_loads = array();
		add_action(
			'aggressive_apparel_brand_icon_loaded',
			function ( string $slug ): void {
				$this->brand_icon_loads[] = $slug;
			}
		);
	}

	/**
	 * Slugs read from disk, in order.
	 *
	 * @return array<int, string>
	 */
	protected function brand_icon_loads(): array {
		return $this->brand_icon_loads;
	}
}
