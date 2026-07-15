<?php
/**
 * Tests for Color_Scheme_Bootstrap.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\Core;

use Aggressive_Apparel\Core\Color_Scheme;
use Aggressive_Apparel\Core\Color_Scheme_Bootstrap;
use WP_UnitTestCase;

/**
 * Color Scheme Bootstrap Test Case
 */
class TestColorSchemeBootstrap extends WP_UnitTestCase {

	/**
	 * Bootstrap instance under test.
	 *
	 * @var Color_Scheme_Bootstrap
	 */
	private Color_Scheme_Bootstrap $bootstrap;

	/**
	 * Set up test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->bootstrap = new Color_Scheme_Bootstrap();
	}

	/**
	 * init() registers the early wp_head callback.
	 */
	public function test_init_registers_wp_head_at_priority_zero(): void {
		$this->bootstrap->init();

		$this->assertSame(
			0,
			has_action( 'wp_head', array( $this->bootstrap, 'print_inline_script' ) ),
			'Color scheme bootstrap should print at wp_head priority 0'
		);
	}

	/**
	 * Inline script uses shared Color_Scheme reader and applies theme attrs.
	 */
	public function test_inline_script_uses_shared_reader_and_theme_attrs(): void {
		$script = $this->bootstrap->get_inline_script();

		$this->assertStringContainsString( 'aaReadStoredColorScheme', $script );
		$this->assertStringContainsString( Color_Scheme::STORAGE_KEY, $script );
		$this->assertStringContainsString( "html.style.colorScheme='dark'", $script );
		$this->assertStringContainsString( "setAttribute('data-theme','dark')", $script );
		$this->assertStringContainsString( "setAttribute('data-theme','light')", $script );
		$this->assertStringContainsString( 'is-dark-mode', $script );
		$this->assertStringContainsString( 'is-light-mode', $script );
		$this->assertStringNotContainsString( 'localStorage.setItem', $script );
		$this->assertStringNotContainsString( 'localStorage.removeItem', $script );
	}

	/**
	 * Filter can disable the bootstrap script output.
	 */
	public function test_filter_can_disable_print(): void {
		add_filter( 'aggressive_apparel_color_scheme_bootstrap', '__return_false' );

		ob_start();
		$this->bootstrap->print_inline_script();
		$output = ob_get_clean();

		remove_filter( 'aggressive_apparel_color_scheme_bootstrap', '__return_false' );

		$this->assertSame( '', $output );
	}

	/**
	 * Default print includes the bootstrap script id.
	 */
	public function test_print_outputs_script_with_id(): void {
		ob_start();
		$this->bootstrap->print_inline_script();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'aggressive-apparel-color-scheme-bootstrap', $output );
		$this->assertStringContainsString( 'colorScheme', $output );
	}
}
