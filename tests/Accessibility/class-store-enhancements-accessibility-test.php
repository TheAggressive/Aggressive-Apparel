<?php
/**
 * Accessibility tests for the Store Enhancements admin screen.
 *
 * @package Aggressive_Apparel\Tests\Accessibility
 * @since 1.81.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Accessibility;

use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Feature_Settings_Page;
use WP_UnitTestCase;

/**
 * Store Enhancements admin accessibility compliance.
 */
class Store_Enhancements_Accessibility_Test extends WP_UnitTestCase {

	/**
	 * Settings page under test.
	 *
	 * @var Feature_Settings_Page
	 */
	private Feature_Settings_Page $page;

	/**
	 * Set up admin user and register settings.
	 */
	public function setUp(): void {
		parent::setUp();

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$this->page = new Feature_Settings_Page();
		$this->page->register_settings();
	}

	/**
	 * Tear down option noise from feature toggles.
	 */
	public function tearDown(): void {
		delete_option( Feature_Settings::OPTION_KEY );
		parent::tearDown();
	}

	/**
	 * Capture the rendered settings page HTML.
	 */
	private function render_page_html(): string {
		ob_start();
		$this->page->render_settings_page();
		return (string) ob_get_clean();
	}

	/**
	 * Save capability matches the Appearance menu capability.
	 */
	public function test_settings_save_capability_matches_page(): void {
		$this->assertSame( 'edit_theme_options', $this->page->get_settings_capability() );

		$this->page->init();
		$capability = apply_filters(
			'option_page_capability_' . Feature_Settings::SETTINGS_GROUP,
			'manage_options'
		);

		$this->assertSame( 'edit_theme_options', $capability );
	}

	/**
	 * Tabs use WAI-ARIA tab pattern markup.
	 */
	public function test_tabs_expose_accessible_tab_pattern(): void {
		$html = $this->render_page_html();

		$this->assertStringContainsString( 'role="tablist"', $html );
		$this->assertStringContainsString( 'aria-label="Store Enhancements sections"', $html );
		$this->assertMatchesRegularExpression(
			'/role="tab"[^>]*aria-controls="tab-catalog"[^>]*aria-selected="/',
			$html
		);
		$this->assertMatchesRegularExpression(
			'/role="tabpanel"[^>]*id="tab-catalog"[^>]*aria-labelledby="aa-features-tab-catalog"/',
			$html
		);
		$this->assertStringNotContainsString( 'href="#"', $html );
	}

	/**
	 * Feature toggles are labeled via Settings API label_for + control ids.
	 */
	public function test_feature_toggles_have_programmatic_names(): void {
		$html = $this->render_page_html();

		$this->assertStringContainsString( 'id="aa-feature-quick_view"', $html );
		$this->assertStringContainsString( 'for="aa-feature-quick_view"', $html );
		$this->assertStringContainsString( 'aria-describedby="aa-feature-quick_view-desc"', $html );
	}

	/**
	 * Quick View selects are labeled and described.
	 */
	public function test_quick_view_selects_are_labeled(): void {
		$html = $this->render_page_html();

		$style_option = Feature_Settings::QUICK_VIEW_TRIGGER_STYLE_OPTION;
		$this->assertStringContainsString( 'id="' . $style_option . '"', $html );
		$this->assertStringContainsString( 'for="' . $style_option . '"', $html );
		$this->assertStringContainsString( 'aria-describedby="' . $style_option . '-desc"', $html );
		$this->assertStringContainsString( 'id="' . $style_option . '-desc"', $html );
	}

	/**
	 * Sub-fields carry progressive-disclosure dependency classes.
	 */
	public function test_sub_fields_declare_parent_dependencies(): void {
		$html = $this->render_page_html();

		$this->assertStringContainsString( 'aa-features-depends-on-quick_view', $html );
		$this->assertStringContainsString( 'aa-features-depends-on-wishlist', $html );
		$this->assertStringContainsString( 'aa-features-depends-on-social_proof', $html );
		$this->assertStringContainsString( 'aa-features-depends-on-product_filters', $html );
	}

	/**
	 * Tab counts expose a human-readable accessible name.
	 */
	public function test_tab_counts_have_accessible_names(): void {
		$html = $this->render_page_html();

		$this->assertMatchesRegularExpression(
			'/aa-features-tab-count"[^>]*aria-label="\d+ of \d+ features enabled"/',
			$html
		);
	}
}
