<?php
/**
 * Test Styles Class
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Unit\Assets;

use WP_UnitTestCase;
use Aggressive_Apparel\Assets\Asset_Loader;
use Aggressive_Apparel\Assets\Body_Font_Token;
use Aggressive_Apparel\Assets\Styles;

/**
 * Styles Test Case
 *
 * The PHP side of styling: enqueue order, editor styles and theme.json wiring.
 * CSS contracts (selectors, values, layers) are asserted on compiled output in
 * src/styles/__tests__/css-contracts.test.ts.
 */
class Test_Styles extends WP_UnitTestCase {
	/**
	 * Styles instance
	 *
	 * @var Styles
	 */
	private $styles;

	/**
	 * Set up test
	 */
	public function setUp(): void {
		parent::setUp();
		$this->styles = new Styles();
		$this->styles->init();
	}

	/**
	 * Test styles are registered
	 */
	public function test_styles_enqueued() {
		// Trigger the enqueue action.
		do_action( 'wp_enqueue_scripts' );

		$this->assertTrue(
			wp_style_is( 'aggressive-apparel-main', 'registered' ),
			'Main stylesheet should be registered'
		);

		$this->assertTrue(
			wp_style_is( 'aggressive-apparel-woocommerce-notices', 'enqueued' ),
			'WooCommerce notices stylesheet should load through the normal WooCommerce style queue'
		);

		$this->assertTrue(
			wp_style_is( 'aggressive-apparel-mini-cart', 'enqueued' ),
			'Mini-cart stylesheet should load through the normal WooCommerce style queue'
		);
	}

	/**
	 * The forms.css file depends on main.css, which fixes the cascade-layer order its
	 * aa-forms layer takes its position from; the baseline also loads in the
	 * editor canvas so server-rendered previews match.
	 */
	public function test_form_baseline_loads_after_main_and_in_the_editor(): void {
		do_action( 'wp_enqueue_scripts' );

		$forms = wp_styles()->registered['aggressive-apparel-forms'] ?? null;
		$this->assertNotNull( $forms, 'Form baseline stylesheet should be registered on the frontend' );
		$this->assertContains( 'aggressive-apparel-main', $forms->deps );
		$this->assertContains(
			'build/styles/components/forms.css',
			array_map( array( $this, 'relative_to_theme' ), get_editor_stylesheets() )
		);
	}

	/**
	 * Theme.json reads the shared design tokens instead of restating values,
	 * and never forces a value over the editor.
	 */
	public function test_theme_json_wires_shared_tokens(): void {
		$theme_json = $this->theme_json();
		$custom     = $theme_json['settings']['custom'];
		$button     = $theme_json['styles']['elements']['button'];

		// White text sits on the primary fill, so it uses the accent's fill role.
		$this->assertSame( 'var(--wp--preset--color--accent-fill)', $custom['button']['primary']['background'] );
		$this->assertSame( 'var(--wp--custom--button--primary--background)', $button['color']['background'] );
		$this->assertSame( 'var(--wp--custom--radius--button)', $button['border']['radius'] );
		$this->assertSame( 'var(--aa-field-bg)', $theme_json['styles']['elements']['select']['color']['background'] );
		$this->assertStringContainsString( '--wp--preset--color--surface', $custom['focusRing'], 'The focus ring needs a surface halo to read on any fill.' );

		// Interaction states belong to the shared button stylesheet, and icon
		// controls keep their resting background on hover.
		$this->assertArrayNotHasKey( ':focus', $button, 'Keyboard focus must preserve the active button variant paint.' );
		$this->assertArrayNotHasKey( ':active', $button );
		$this->assertArrayNotHasKey( 'hoverBackground', $custom['button']['icon'] );

		$this->assertStringNotContainsString( '!important', (string) wp_json_encode( $theme_json['styles'] ), 'theme.json must never force a value over editor choices.' );
	}

	/**
	 * Interactive targets meet WCAG 2.2: 44px for primary controls (2.5.5
	 * enhanced), 24px minimum everywhere (2.5.8), with the card tiers between.
	 */
	public function test_theme_json_touch_targets_meet_minimums(): void {
		$size = $this->theme_json()['settings']['custom']['size'];
		$px   = static fn( string $rem ): float => 16 * (float) rtrim( $rem, 'rem' );

		$this->assertGreaterThanOrEqual( 44, $px( $size['iconButton'] ) );
		$this->assertGreaterThanOrEqual( 44, $px( $size['controlMin'] ) );
		$this->assertGreaterThanOrEqual( 32, $px( $size['denseControlMin'] ) );
		$this->assertGreaterThanOrEqual( 32, $px( $size['cardSwatchMin'] ) );
		$this->assertGreaterThanOrEqual( 24, $px( $size['cardSwatchDenseMin'] ) );
	}

	/**
	 * Decoded theme.json.
	 *
	 * @return array<string, mixed>
	 */
	private function theme_json(): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- Local theme configuration.
		return json_decode( (string) file_get_contents( get_template_directory() . '/theme.json' ), true, 512, JSON_THROW_ON_ERROR );
	}

	/**
	 * The --aa-button-* fallback tokens (settings.custom.button) must equal the
	 * editable styles.elements.button values they mirror.
	 */
	public function test_button_fallback_tokens_mirror_the_button_element() {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local source artifacts in a test.
		$theme_json = json_decode( (string) file_get_contents( get_template_directory() . '/theme.json' ), true );
		$this->assertIsArray( $theme_json );

		$custom  = $theme_json['settings']['custom']['button'];
		$element = $theme_json['styles']['elements']['button'];
		$preset  = static function ( string $value ): string {
			// "var:preset|font-size|medium" → "var(--wp--preset--font-size--medium)".
			return (string) preg_replace( '/^var:preset\\|([a-z-]+)\\|([a-z0-9-]+)$/', 'var(--wp--preset--$1--$2)', $value );
		};

		foreach ( array( 'fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'letterSpacing', 'textTransform' ) as $key ) {
			$this->assertSame( $preset( $element['typography'][ $key ] ), $custom[ $key ], "button {$key}" );
		}
		$this->assertSame( $preset( $element['spacing']['padding']['top'] ), $custom['paddingBlock'] );
		$this->assertSame( $preset( $element['spacing']['padding']['left'] ), $custom['paddingInline'] );
	}

	/**
	 * The body-font token resolves from global styles and rejects anything
	 * that could break out of the declaration.
	 */
	public function test_body_font_token() {
		$this->assertSame( 'var(--wp--preset--font-family--space-grotesk)', Body_Font_Token::to_css_value( 'var:preset|font-family|space-grotesk' ) );
		$this->assertSame( 'var(--wp--preset--font-family--bebas-neue)', Body_Font_Token::to_css_value( 'var(--wp--preset--font-family--bebas-neue)' ) );
		$this->assertSame( "'Inter', Helvetica, sans-serif", Body_Font_Token::to_css_value( " 'Inter', Helvetica, sans-serif " ) );
		$this->assertSame( '', Body_Font_Token::to_css_value( 'serif;}body{display:none' ) );
		$this->assertSame( '', Body_Font_Token::to_css_value( "'Inter, sans-serif" ) );
		$this->assertSame( '', Body_Font_Token::to_css_value( '' ) );
		$this->assertSame( '', Body_Font_Token::to_css_value( 'var(--x)' ) );
		$this->assertSame( '', Body_Font_Token::to_css_value( null ) );
		$this->assertSame( '', Body_Font_Token::to_css_value( array( 'serif' ) ) );

		// theme.json sets a body font, so the token is printed — once, even
		// though enqueue_tokens() runs from several hooks.
		do_action( 'wp_enqueue_scripts' );
		Asset_Loader::enqueue_tokens();
		$after = wp_styles()->get_data( Asset_Loader::TOKENS_HANDLE, 'after' );
		$this->assertIsArray( $after );
		$this->assertSame( array( ':root{--aa-font-body:var(--wp--preset--font-family--space-grotesk)}' ), array_values( array_filter( $after, fn( $css ) => str_contains( $css, Body_Font_Token::PROPERTY ) ) ) );
	}

	/**
	 * Path of an editor stylesheet relative to the theme directory.
	 *
	 * @param string $path Absolute path or URL.
	 * @return string
	 */
	private function relative_to_theme( string $path ): string {
		foreach ( array( get_template_directory(), get_template_directory_uri() ) as $base ) {
			if ( str_starts_with( $path, $base . '/' ) ) {
				return substr( $path, strlen( $base ) + 1 );
			}
		}
		return $path;
	}
}
