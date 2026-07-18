<?php
/**
 * Tests for Adaptive_Colors per-block channel injection.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\Core;

use Aggressive_Apparel\Core\Adaptive_Colors;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * @covers \Aggressive_Apparel\Core\Adaptive_Colors
 */
class Adaptive_Colors_Test extends WP_UnitTestCase {

	/**
	 * @var Adaptive_Colors
	 */
	private Adaptive_Colors $adaptive;

	/**
	 * Set up.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->adaptive = new Adaptive_Colors();
	}

	/**
	 * Invoke a private instance method.
	 *
	 * @param string       $method Method name.
	 * @param array<mixed> $args   Arguments.
	 * @return mixed
	 */
	private function call_private( string $method, array $args = [] ) {
		$reflection = new ReflectionMethod( Adaptive_Colors::class, $method );
		$reflection->setAccessible( true );
		return $reflection->invokeArgs( $this->adaptive, $args );
	}

	/**
	 * Legacy attributes still inject background + text styles.
	 */
	public function test_inject_adaptive_styles_supports_legacy_attributes(): void {
		$html = '<div class="wp-block-group">Content</div>';

		$result = $this->adaptive->inject_adaptive_styles(
			$html,
			array(
				'attrs' => array(
					'adaptiveBackground' => array(
						'light' => '#ffffff',
						'dark'  => '#000000',
					),
					'adaptiveText'       => array(
						'light' => '#111111',
						'dark'  => '#eeeeee',
					),
				),
			)
		);

		$this->assertStringContainsString( 'background:light-dark(#ffffff, #000000)', $result );
		$this->assertStringContainsString( 'color:light-dark(#111111, #eeeeee)', $result );
		$this->assertStringContainsString( 'aa-has-adaptive-bg', $result );
		$this->assertStringContainsString( 'aa-has-adaptive-color', $result );
	}

	/**
	 * Channel map injects CSS vars + marker classes for descendant channels.
	 */
	public function test_inject_adaptive_styles_supports_channel_map(): void {
		$html = '<p>Hello</p>';

		$result = $this->adaptive->inject_adaptive_styles(
			$html,
			array(
				'attrs' => array(
					'adaptiveColors' => array(
						'link'      => array(
							'light' => 'blue',
							'dark'  => 'cyan',
						),
						'linkHover' => array(
							'light' => 'navy',
							'dark'  => 'aqua',
						),
						'border'    => array(
							'light' => '#ccc',
							'dark'  => '#333',
						),
					),
				),
			)
		);

		$this->assertStringContainsString( '--aa-adaptive-link:light-dark(blue, cyan)', $result );
		$this->assertStringContainsString( '--aa-adaptive-link-hover:light-dark(navy, aqua)', $result );
		$this->assertStringContainsString( 'border-color:light-dark(#ccc, #333)', $result );
		$this->assertStringContainsString( 'aa-has-adaptive-link', $result );
		$this->assertStringContainsString( 'aa-has-adaptive-border', $result );
		// link + linkHover share one marker class (not aa-has-adaptive-link-hover).
		$this->assertStringNotContainsString( 'aa-has-adaptive-link-hover', $result );
	}

	/**
	 * Preset CSS restores light-dark() from theme.json pairs.
	 */
	public function test_build_adaptive_preset_css_uses_light_dark(): void {
		$css = $this->call_private(
			'build_adaptive_preset_css',
			array(
				array(
					array(
						'slug'  => 'surface',
						'name'  => 'Surface',
						'light' => 'oklch(87% 0 0)',
						'dark'  => 'oklch(20.5% 0 0)',
					),
				),
			)
		);

		$this->assertStringContainsString(
			'--wp--preset--color--surface:light-dark(oklch(87% 0 0),oklch(20.5% 0 0))',
			$css
		);
		$this->assertStringStartsWith( ':root{', $css );
	}

	/**
	 * Preset CSS merge is idempotent across repeated injects.
	 */
	public function test_merge_adaptive_preset_css_is_idempotent(): void {
		$pairs = array(
			array(
				'slug'  => 'surface',
				'name'  => 'Surface',
				'light' => '#fff',
				'dark'  => '#000',
			),
		);

		$once = $this->call_private(
			'merge_adaptive_preset_css',
			array( 'body{color:red}', $pairs )
		);
		$twice = $this->call_private(
			'merge_adaptive_preset_css',
			array( $once, $pairs )
		);

		$this->assertSame( 1, substr_count( $twice, '--wp--preset--color--surface:' ) );
		$this->assertStringContainsString( 'body{color:red}', $twice );
	}

	/**
	 * Reject unsafe tokens in preset CSS builder.
	 */
	public function test_sanitize_css_color_token_rejects_unsafe_values(): void {
		$this->assertSame(
			'',
			$this->call_private( 'sanitize_css_color_token', array( 'url(https://evil.test)' ) )
		);
		$this->assertSame(
			'oklch(50% 0 0)',
			$this->call_private( 'sanitize_css_color_token', array( 'oklch(50% 0 0)' ) )
		);
	}

	/**
	 * Map values win over legacy when both are present.
	 */
	public function test_resolve_adaptive_colors_prefers_map_over_legacy(): void {
		$resolved = $this->call_private(
			'resolve_adaptive_colors',
			array(
				array(
					'adaptiveText'   => array(
						'light' => '#000',
						'dark'  => '#fff',
					),
					'adaptiveColors' => array(
						'text' => array(
							'light' => '#111',
							'dark'  => '#eee',
						),
					),
				),
			)
		);

		$this->assertSame(
			array(
				'light' => '#111',
				'dark'  => '#eee',
			),
			$resolved['text']
		);
	}
}
