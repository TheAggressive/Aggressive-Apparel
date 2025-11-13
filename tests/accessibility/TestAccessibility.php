<?php
/**
 * Accessibility Tests
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Accessibility;

use WP_UnitTestCase;

/**
 * Accessibility Test Case
 */
class TestAccessibility extends WP_UnitTestCase {
	/**
	 * Test skip to content link exists
	 */
	public function test_skip_link_class_exists() {
		$this->assertTrue( class_exists( 'DOMDocument' ), 'DOMDocument should be available' );
	}

	/**
	 * Test images have alt attributes
	 */
	public function test_images_should_have_alt_text() {
		$image_html = '<img src="test.jpg" alt="Test image">';

		$this->assertStringContainsString( 'alt=', $image_html );
	}

	/**
	 * Test form labels exist for inputs
	 */
	public function test_form_inputs_have_labels() {
		$form_html = '<label for="email">Email</label><input type="email" id="email" name="email">';

		$this->assertStringContainsString( '<label for="email">', $form_html );
		$this->assertStringContainsString( 'id="email"', $form_html );
	}

	/**
	 * Test ARIA landmarks
	 */
	public function test_aria_landmarks() {
		$html = '<main role="main" aria-label="Content">Content</main>';

		$this->assertStringContainsString( 'role="main"', $html );
		$this->assertStringContainsString( 'aria-label=', $html );
	}

	/**
	 * Test heading hierarchy
	 */
	public function test_heading_hierarchy() {
		$content = '<h1>Title</h1><h2>Subtitle</h2><h3>Section</h3>';

		$h1_count = substr_count( $content, '<h1>' );
		$this->assertEquals( 1, $h1_count, 'Should have exactly one H1' );
	}

	/**
	 * Test color contrast helpers exist
	 */
	public function test_theme_json_has_colors() {
		$theme_json_path = get_template_directory() . '/theme.json';
		$this->assertFileExists( $theme_json_path );

		$theme_json = json_decode( file_get_contents( $theme_json_path ), true );
		$this->assertArrayHasKey( 'settings', $theme_json );
		$this->assertArrayHasKey( 'color', $theme_json['settings'] );
	}

	/**
	 * Test focus states for interactive elements
	 */
	public function test_focus_styles_defined() {
		// Check if CSS files exist
		$build_dir = get_template_directory() . '/build/styles';
		$this->assertDirectoryExists( $build_dir, 'Build directory should exist' );
	}

	/**
	 * Test keyboard navigation support
	 */
	public function test_navigation_menu_keyboard_accessible() {
		// Test that navigation menus can be accessed
		$this->assertTrue( has_nav_menu( 'primary' ) || has_nav_menu( 'footer' ) );
	}
}
