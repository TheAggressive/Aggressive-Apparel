<?php
/**
 * Test Theme Support Class
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Unit\Core;

use WP_UnitTestCase;
use Aggressive_Apparel\Core\Theme_Support;

/**
 * Theme Support Test Case
 */
class TestThemeSupport extends WP_UnitTestCase {
	/**
	 * Theme support instance
	 *
	 * @var Theme_Support
	 */
	private $theme_support;

	/**
	 * Set up test
	 */
	public function setUp(): void {
		parent::setUp();
		$this->theme_support = new Theme_Support();
		// Note: We don't call init() or register_theme_support() here
		// because they need to run before wp_loaded, which has already happened.
		// Instead, we test that the Bootstrap class has initialized it.
	}

	/**
	 * Test theme supports are registered
	 *
	 * These are registered via Bootstrap during theme initialization.
	 */
	public function test_theme_supports_registered() {
		// These should already be registered by Bootstrap
		$this->assertTrue(
			current_theme_supports( 'title-tag' ),
			'Theme should support title-tag'
		);

		$this->assertTrue(
			current_theme_supports( 'post-thumbnails' ),
			'Theme should support post-thumbnails'
		);

		$this->assertTrue(
			current_theme_supports( 'automatic-feed-links' ),
			'Theme should support automatic-feed-links'
		);

		$this->assertTrue(
			current_theme_supports( 'align-wide' ),
			'Theme should support align-wide'
		);
	}

	/**
	 * Test Theme_Support class can be instantiated
	 */
	public function test_class_instantiation() {
		$this->assertInstanceOf(
			Theme_Support::class,
			$this->theme_support,
			'Should be able to instantiate Theme_Support'
		);
	}

	/**
	 * Theme languages directory exists and matches Domain Path.
	 */
	public function test_languages_directory_exists(): void {
		$languages = get_template_directory() . '/languages';

		$this->assertDirectoryExists(
			$languages,
			'Theme should ship a languages/ directory (Domain Path)'
		);

		$this->assertFileExists(
			$languages . '/aggressive-apparel.pot',
			'Theme should ship an aggressive-apparel.pot catalog'
		);
	}

	/**
	 * Bootstrap registers the theme languages path via load_theme_textdomain.
	 */
	public function test_load_theme_textdomain_path(): void {
		global $wp_textdomain_registry;

		$expected = get_template_directory() . '/languages';
		$path     = $wp_textdomain_registry->get( 'aggressive-apparel', determine_locale() );

		$this->assertNotFalse(
			$path,
			'Textdomain registry should resolve aggressive-apparel (Bootstrap / Theme_Support)'
		);
		$this->assertSame(
			trailingslashit( $expected ),
			trailingslashit( (string) $path ),
			'Theme translations should load from languages/'
		);
	}
}
