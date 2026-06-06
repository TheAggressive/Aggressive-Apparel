<?php
/**
 * Accessibility Tests
 *
 * These exercise the theme's actual templates, parts and configuration rather
 * than asserting against hardcoded HTML strings.
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
	 * The header template part must expose a skip-to-content link.
	 */
	public function test_header_part_has_skip_link() {
		$header = get_template_directory() . '/parts/header.html';
		$this->assertFileExists( $header );

		$markup = (string) file_get_contents( $header );
		$this->assertStringContainsString(
			'skip-to-content',
			$markup,
			'Header part should include a skip-to-content link.'
		);
		$this->assertStringContainsString(
			'screen-reader-text',
			$markup,
			'Skip link should be visually hidden via screen-reader-text.'
		);
	}

	/**
	 * Every page-level template must declare a <main> landmark.
	 */
	public function test_all_templates_have_main_landmark() {
		$templates = glob( get_template_directory() . '/templates/*.html' );
		$this->assertNotEmpty( $templates, 'Theme should ship block templates.' );

		foreach ( $templates as $template ) {
			$markup = (string) file_get_contents( $template );

			// Email templates under templates/emails are not page documents.
			if ( false !== strpos( $markup, '<!DOCTYPE' ) ) {
				continue;
			}

			$this->assertStringContainsString(
				'<main',
				$markup,
				sprintf( 'Template %s should contain a <main> landmark.', basename( $template ) )
			);
		}
	}

	/**
	 * Every image in the theme's block patterns must declare an alt attribute.
	 *
	 * Patterns are rendered (so PHP-embedded src values resolve) and parsed
	 * with DOMDocument, which is robust against markup that regex cannot
	 * reliably tokenise.
	 */
	public function test_pattern_images_have_alt_text() {
		if ( ! class_exists( '\WP_Block_Patterns_Registry' ) ) {
			$this->markTestSkipped( 'Block pattern registry unavailable.' );
		}

		$patterns = \WP_Block_Patterns_Registry::get_instance()->get_all_registered();
		$theme_patterns = array_filter(
			$patterns,
			static fn( $pattern ) => isset( $pattern['name'] )
				&& 0 === strpos( (string) $pattern['name'], 'aggressive-apparel/' )
		);

		$this->assertNotEmpty( $theme_patterns, 'Theme should register block patterns.' );

		$images_checked = 0;

		foreach ( $theme_patterns as $pattern ) {
			$html = do_blocks( (string) ( $pattern['content'] ?? '' ) );
			if ( '' === trim( $html ) || false === strpos( $html, '<img' ) ) {
				continue;
			}

			$doc = new \DOMDocument();
			libxml_use_internal_errors( true );
			$doc->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
			libxml_clear_errors();

			foreach ( $doc->getElementsByTagName( 'img' ) as $img ) {
				++$images_checked;
				$this->assertTrue(
					$img->hasAttribute( 'alt' ),
					sprintf( 'Image without alt attribute in pattern %s', $pattern['name'] )
				);
			}
		}

		$this->assertGreaterThan( 0, $images_checked, 'Expected to validate at least one pattern image.' );
	}

	/**
	 * theme.json must define a colour palette so users get accessible presets.
	 */
	public function test_theme_json_defines_color_palette() {
		$theme_json_path = get_template_directory() . '/theme.json';
		$this->assertFileExists( $theme_json_path );

		$theme_json = json_decode( (string) file_get_contents( $theme_json_path ), true );
		$this->assertIsArray( $theme_json, 'theme.json should be valid JSON.' );
		$this->assertArrayHasKey( 'settings', $theme_json );
		$this->assertArrayHasKey( 'color', $theme_json['settings'] );
		$this->assertArrayHasKey( 'palette', $theme_json['settings']['color'] );

		$palette = $theme_json['settings']['color']['palette'];
		$this->assertNotEmpty( $palette, 'Colour palette should define at least one colour.' );

		foreach ( $palette as $color ) {
			$this->assertArrayHasKey( 'slug', $color );
			$this->assertArrayHasKey( 'color', $color );
		}
	}

	/**
	 * theme.json must define global styles where focus/element styling lives.
	 */
	public function test_theme_json_defines_styles() {
		$theme_json_path = get_template_directory() . '/theme.json';
		$theme_json      = json_decode( (string) file_get_contents( $theme_json_path ), true );

		$this->assertIsArray( $theme_json );
		$this->assertArrayHasKey( 'styles', $theme_json, 'theme.json should define global styles.' );
	}
}
