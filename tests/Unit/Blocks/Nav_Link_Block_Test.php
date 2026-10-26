<?php
/**
 * Unit tests for the nav-link block's current-page detection.
 *
 * @package Aggressive_Apparel\Tests\Unit\Blocks
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\Blocks;

use Aggressive_Apparel\Blocks\Blocks;
use WP_UnitTestCase;

/**
 * A link is current on its path, and on its query too when it has one.
 */
class Nav_Link_Block_Test extends WP_UnitTestCase {

	/**
	 * Request URI before the test.
	 *
	 * @var string|null
	 */
	private ?string $request_uri = null;

	/**
	 * Register blocks and remember the request URI.
	 */
	public function setUp(): void {
		parent::setUp();
		Blocks::init();
		$this->request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : null;
	}

	/**
	 * Restore the request URI.
	 */
	public function tearDown(): void {
		if ( null === $this->request_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->request_uri;
		}
		parent::tearDown();
	}

	/**
	 * Whether a link renders as the current page for a request.
	 *
	 * @param string $url     Link URL.
	 * @param string $request Request URI.
	 * @return bool
	 */
	private function is_current( string $url, string $request ): bool {
		$_SERVER['REQUEST_URI'] = $request;
		$html                   = render_block(
			array(
				'blockName' => 'aggressive-apparel/nav-link',
				'attrs'     => array(
					'label' => 'Link',
					'url'   => $url,
				),
			)
		);

		return str_contains( $html, 'aria-current="page"' );
	}

	/**
	 * Paths match with or without a trailing slash.
	 */
	public function test_path_match(): void {
		$this->assertTrue( $this->is_current( '/shop/', '/shop' ) );
		$this->assertFalse( $this->is_current( '/shop/sales/', '/shop/' ) );
	}

	/**
	 * A query link is current only with its query.
	 */
	public function test_query_link_needs_its_query(): void {
		$this->assertFalse( $this->is_current( '/shop?orderby=date', '/shop/' ) );
		$this->assertTrue( $this->is_current( '/shop?orderby=date', '/shop/?orderby=date&paged=2' ) );
		$this->assertTrue( $this->is_current( '/shop/t-shirts/?collection=mens', '/shop/t-shirts/?collection=mens' ) );
		$this->assertFalse( $this->is_current( '/shop/t-shirts/?collection=mens', '/shop/t-shirts/?collection=womens' ) );
	}
}
