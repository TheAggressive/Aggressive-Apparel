<?php
/**
 * Taxonomy registration cleanup for tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Support;

/**
 * Unregisters the taxonomies a test registered.
 *
 * Creating a product attribute registers its `pa_*` taxonomy. The test's
 * transaction rolls back the attribute row but not the registration, and
 * WordPress resets taxonomies only between its own core tests, so a leaked
 * one makes wc_create_attribute() refuse that slug in every later test.
 */
trait Restores_Taxonomies {

	/**
	 * Taxonomies registered before the test.
	 *
	 * @var list<string>
	 */
	private array $taxonomy_baseline = array();

	/** Record the registered taxonomies; call from setUp(). */
	private function snapshot_taxonomies(): void {
		$this->taxonomy_baseline = array_values( get_taxonomies() );
	}

	/** Unregister taxonomies added since the snapshot; call from tearDown(). */
	private function restore_taxonomies(): void {
		foreach ( array_diff( array_values( get_taxonomies() ), $this->taxonomy_baseline ) as $taxonomy ) {
			unregister_taxonomy( $taxonomy );
		}
	}
}
