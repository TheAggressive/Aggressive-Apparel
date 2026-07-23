<?php
/**
 * Block pill helper DOM round-trip tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Block_Pill_Helper;
use WP_UnitTestCase;

/**
 * DOMDocument::loadHTML() assumes ISO-8859-1 without a charset hint, which
 * corrupts multibyte variation labels on the load/save round-trip. Block_Pill_Helper
 * must pre-encode non-ASCII so those characters survive intact.
 */
class TestBlockPillHelper extends WP_UnitTestCase {

	/**
	 * Multibyte characters survive the load/save round-trip.
	 *
	 * @return void
	 */
	public function test_multibyte_labels_survive_round_trip(): void {
		$html = '<button class="wc-block-product-filter-chips__item">Café Rouge — Größe</button>';

		$dom = Block_Pill_Helper::load_dom( $html );
		$this->assertNotNull( $dom, 'Well-formed markup should load.' );

		$output  = Block_Pill_Helper::save_dom( $dom, $html );
		$decoded = html_entity_decode( $output, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		$this->assertStringContainsString( 'Café Rouge — Größe', $decoded, 'Multibyte characters must not be mojibake.' );
		$this->assertStringNotContainsString( 'CafÃ', $decoded, 'Output must not contain UTF-8-as-Latin-1 corruption.' );
	}

	/**
	 * Empty content still returns null (unchanged contract).
	 *
	 * @return void
	 */
	public function test_empty_content_returns_null(): void {
		$this->assertNull( Block_Pill_Helper::load_dom( '' ) );
	}
}
