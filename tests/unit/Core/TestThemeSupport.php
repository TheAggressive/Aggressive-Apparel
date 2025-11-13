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
	 * Test editor styles
	 */
	public function test_editor_styles_support() {
		$this->assertTrue(
			current_theme_supports( 'editor-styles' ),
			'Theme should support editor-styles'
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
}
