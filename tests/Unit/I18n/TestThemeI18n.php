<?php
/**
 * Theme i18n runtime and structural coverage.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\I18n;

use Aggressive_Apparel\Assets\Asset_Loader;
use Aggressive_Apparel\WooCommerce\Free_Shipping;
use WP_UnitTestCase;

/**
 * Verifies catalogs load, bags translate, patterns re-evaluate gettext, and templates compose patterns.
 */
class TestThemeI18n extends WP_UnitTestCase {

	private const FIXTURE_LOCALE = 'aa_TEST';

	/**
	 * Path to the committed test .mo fixture.
	 */
	private function fixture_mo(): string {
		return dirname( __DIR__, 2 ) . '/fixtures/i18n/aggressive-apparel-' . self::FIXTURE_LOCALE . '.mo';
	}

	/**
	 * Load the fixture catalog for aggressive-apparel (unload first for a clean slate).
	 */
	private function load_fixture_textdomain(): void {
		unload_textdomain( Asset_Loader::TEXT_DOMAIN );

		$loaded = load_textdomain( Asset_Loader::TEXT_DOMAIN, $this->fixture_mo() );

		$this->assertTrue(
			$loaded,
			'Fixture .mo must load (msgfmt + committed tests/fixtures/i18n/).'
		);
	}

	/**
	 * Restore default (English / empty) domain after assertions.
	 */
	private function unload_fixture_textdomain(): void {
		unload_textdomain( Asset_Loader::TEXT_DOMAIN );
	}

	/**
	 * style.css must declare Domain Path for theme language packs.
	 */
	public function test_style_css_domain_path(): void {
		$style = file_get_contents( get_template_directory() . '/style.css' );

		$this->assertIsString( $style );
		$this->assertMatchesRegularExpression(
			'/^Text Domain:\s*aggressive-apparel\s*$/m',
			$style
		);
		$this->assertMatchesRegularExpression(
			'/^Domain Path:\s*\/languages\s*$/m',
			$style
		);
	}

	/**
	 * A theme .mo must change gettext output for the text domain.
	 */
	public function test_fixture_mo_translates_gettext(): void {
		$this->load_fixture_textdomain();

		try {
			$this->assertSame(
				'ZZ-PAGE-NOT-FOUND',
				__( 'Page Not Found', 'aggressive-apparel' )
			);
			$this->assertSame(
				'ZZ-ADDING',
				__( 'Adding…', 'aggressive-apparel' )
			);
		} finally {
			$this->unload_fixture_textdomain();
		}
	}

	/**
	 * Public i18n bags must run through gettext (not hardcoded English).
	 */
	public function test_free_shipping_i18n_bag_uses_gettext(): void {
		$this->load_fixture_textdomain();

		try {
			$i18n = Free_Shipping::get_message_i18n();

			$this->assertSame(
				'ZZ-%s-AWAY-FREE-SHIPPING',
				$i18n['progressDefault']
			);
			$this->assertSame(
				'ZZ-FREE-SHIPPING-UNLOCKED',
				$i18n['unlockedDefault']
			);

			$bar = Free_Shipping::get_bar_message_i18n();
			$this->assertSame(
				'ZZ-%s-AWAY-FREE-SHIPPING',
				$bar['progress']
			);
		} finally {
			$this->unload_fixture_textdomain();
		}
	}

	/**
	 * Pattern PHP files must re-evaluate esc_html__() under a loaded locale.
	 *
	 * (Registry content is snapshotted at registration; re-including the file
	 * matches a front-end request where the locale is already set.)
	 */
	public function test_pattern_php_reevaluates_gettext(): void {
		$this->load_fixture_textdomain();

		try {
			$page_404 = $this->capture_pattern( 'page-404.php' );
			$this->assertStringContainsString( 'ZZ-PAGE-NOT-FOUND', $page_404 );
			$this->assertStringContainsString( 'ZZ-BROWSE-SHOP', $page_404 );
			$this->assertStringNotContainsString(
				'Page Not Found',
				$page_404,
				'English source should not remain when fixture MO is loaded.'
			);

			$no_products = $this->capture_pattern( 'template-no-products.php' );
			$this->assertStringContainsString( 'ZZ-NO-PRODUCTS', $no_products );
		} finally {
			$this->unload_fixture_textdomain();
		}
	}

	/**
	 * Template chrome patterns must wrap copy with the theme text domain.
	 */
	public function test_template_chrome_patterns_use_gettext(): void {
		$files = glob( get_template_directory() . '/patterns/template-*.php' );

		$this->assertNotFalse( $files );
		$this->assertNotEmpty( $files, 'Expected template-* chrome patterns.' );

		foreach ( $files as $file ) {
			$contents = (string) file_get_contents( $file );
			$this->assertMatchesRegularExpression(
				"/esc_html__\s*\(\s*['\"].+['\"]\s*,\s*['\"]aggressive-apparel['\"]\s*\)/",
				$contents,
				basename( $file ) . ' should wrap visible copy with esc_html__().'
			);
		}
	}

	/**
	 * HTML templates should compose PHP patterns instead of hardcoding chrome English.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function template_pattern_provider(): array {
		return array(
			'404'            => array( '404.html', 'aggressive-apparel/page-404' ),
			'archive'        => array( 'archive-product.html', 'aggressive-apparel/template-no-products' ),
			'category'       => array( 'taxonomy-product_cat.html', 'aggressive-apparel/template-no-products' ),
			'home'           => array( 'home.html', 'aggressive-apparel/template-latest-posts-heading' ),
			'index'          => array( 'index.html', 'aggressive-apparel/template-no-posts' ),
			'search'         => array( 'search.html', 'aggressive-apparel/template-no-search-results' ),
			'cart'           => array( 'page-cart.html', 'aggressive-apparel/template-cart-heading' ),
			'checkout'       => array( 'page-checkout.html', 'aggressive-apparel/template-checkout-heading' ),
			'single-related' => array( 'single-product.html', 'aggressive-apparel/template-related-products-heading' ),
		);
	}

	/**
	 * @dataProvider template_pattern_provider
	 */
	public function test_templates_compose_i18n_patterns( string $template, string $pattern_slug ): void {
		$path = get_template_directory() . '/templates/' . $template;
		$this->assertFileExists( $path );

		$html = (string) file_get_contents( $path );

		$this->assertStringContainsString(
			'<!-- wp:pattern {"slug":"' . $pattern_slug . '"} /-->',
			$html,
			$template . ' should reference ' . $pattern_slug
		);

		foreach ( array(
			'Page Not Found',
			'No products were found matching your selection.',
			'Nothing found',
			'Your cart',
			'Checkout',
		) as $english ) {
			$this->assertStringNotContainsString(
				$english,
				$html,
				$template . ' must not hardcode chrome string: ' . $english
			);
		}
	}

	/**
	 * Capture pattern file output after gettext is active.
	 */
	private function capture_pattern( string $basename ): string {
		$path = get_template_directory() . '/patterns/' . $basename;
		$this->assertFileExists( $path );

		ob_start();
		include $path;
		return (string) ob_get_clean();
	}
}
