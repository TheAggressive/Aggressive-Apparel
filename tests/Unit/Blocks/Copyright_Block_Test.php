<?php
/**
 * Copyright block unit tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\Blocks
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\Blocks;

use Aggressive_Apparel\Blocks\Blocks;
use Aggressive_Apparel\Blocks\Copyright;
use Aggressive_Apparel\Core\Legal_Identity;
use WP_UnitTestCase;

/**
 * Copyright block render and helper tests.
 */
class Copyright_Block_Test extends WP_UnitTestCase {

	/**
	 * Ensure the copyright block is registered.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		Copyright::reset_schema_emitted_for_tests();
		Copyright::flush_presets_cache_for_tests();

		if ( ! Blocks::is_block_registered( 'aggressive-apparel/copyright' ) ) {
			Blocks::register();
		}
	}

	/**
	 * Remove copyright filters and options after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_all_filters( 'aggressive_apparel_copyright_parts' );
		remove_all_filters( 'aggressive_apparel_copyright_html' );
		remove_all_filters( 'aggressive_apparel_copyright_schema' );
		remove_all_filters( 'aggressive_apparel_copyright_legal_links' );
		delete_option( Legal_Identity::LEGAL_NAME_OPTION );
		delete_option( Legal_Identity::TERMS_PAGE_OPTION );
		Copyright::reset_schema_emitted_for_tests();
		parent::tearDown();
	}

	/**
	 * Render the copyright block with attributes.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	private function render( array $attributes = array() ): string {
		return (string) render_block(
			array(
				'blockName'    => 'aggressive-apparel/copyright',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerContent' => array(),
			)
		);
	}

	/**
	 * Current year alone when start year is disabled.
	 *
	 * @return void
	 */
	public function test_year_display_current_only(): void {
		$this->assertSame(
			'2026',
			Copyright::year_display( false, 2012, '–', 2026 )
		);
	}

	/**
	 * Year range when start year is earlier than current.
	 *
	 * @return void
	 */
	public function test_year_display_range(): void {
		$this->assertSame(
			'2012–2026',
			Copyright::year_display( true, 2012, '–', 2026 )
		);
		$this->assertSame(
			'2012/2026',
			Copyright::year_display( true, '2012', '/', 2026 )
		);
	}

	/**
	 * Equal start and current years collapse to a single year.
	 *
	 * @return void
	 */
	public function test_year_display_collapses_when_start_equals_current(): void {
		$this->assertSame(
			'2026',
			Copyright::year_display( true, 2026, '–', 2026 )
		);
	}

	/**
	 * Invalid or future start years fall back to the current year.
	 *
	 * @return void
	 */
	public function test_sanitize_year_rejects_invalid(): void {
		$this->assertSame( 2026, Copyright::sanitize_year( 'abc', 2026 ) );
		$this->assertSame( 2026, Copyright::sanitize_year( 999, 2026 ) );
		$this->assertSame( 2026, Copyright::sanitize_year( 2099, 2026 ) );
		$this->assertSame( 2015, Copyright::sanitize_year( 2015, 2026 ) );
	}

	/**
	 * Site title is used when ownerSource is site_title.
	 *
	 * @return void
	 */
	public function test_render_uses_site_title(): void {
		update_option( 'blogname', 'Acme Outfitters' );

		$html = $this->render(
			array(
				'ownerSource' => 'site_title',
				'ownerName'   => 'Ignored Name',
				'prefix'      => '©',
				'showSchema'  => false,
			)
		);

		$this->assertStringContainsString( 'wp-block-aggressive-apparel-copyright', $html );
		$this->assertStringContainsString( 'Acme Outfitters', $html );
		$this->assertStringNotContainsString( 'Ignored Name', $html );
		$this->assertStringContainsString( (string) gmdate( 'Y' ), $html );
	}

	/**
	 * Legal name option is used when ownerSource is legal_name.
	 *
	 * @return void
	 */
	public function test_render_uses_legal_name(): void {
		update_option( 'blogname', 'Marketing Brand' );
		update_option( Legal_Identity::LEGAL_NAME_OPTION, 'Acme Holdings' );

		$html = $this->render(
			array(
				'ownerSource' => 'legal_name',
				'legalEntity' => 'LLC',
				'showSchema'  => false,
			)
		);

		$this->assertStringContainsString( 'Acme Holdings, LLC', $html );
		$this->assertStringNotContainsString( 'Marketing Brand', $html );
	}

	/**
	 * Legacy useSiteTitle=false still resolves to a custom owner.
	 *
	 * @return void
	 */
	public function test_render_legacy_use_site_title_false(): void {
		update_option( 'blogname', 'Site Title Corp' );

		$html = $this->render(
			array(
				'useSiteTitle' => false,
				'ownerName'    => 'Custom LLC',
				'prefix'       => '©',
				'showSchema'   => false,
			)
		);

		$this->assertStringContainsString( 'Custom LLC', $html );
		$this->assertStringNotContainsString( 'Site Title Corp', $html );
	}

	/**
	 * Custom owner name is used when ownerSource is custom.
	 *
	 * @return void
	 */
	public function test_render_uses_custom_owner_name(): void {
		update_option( 'blogname', 'Site Title Corp' );

		$html = $this->render(
			array(
				'ownerSource' => 'custom',
				'ownerName'   => 'Custom LLC',
				'prefix'      => '©',
				'showSchema'  => false,
			)
		);

		$this->assertStringContainsString( 'Custom LLC', $html );
		$this->assertStringNotContainsString( 'Site Title Corp', $html );
	}

	/**
	 * Year range and suffix render with alignment class.
	 *
	 * @return void
	 */
	public function test_render_year_range_suffix_and_alignment(): void {
		$html = $this->render(
			array(
				'ownerSource'   => 'custom',
				'ownerName'     => 'Range Co',
				'showStartYear' => true,
				'startYear'     => 2018,
				'separator'     => '–',
				'suffix'        => '. All rights reserved.',
				'textAlign'     => 'center',
				'showSchema'    => false,
			)
		);

		$this->assertStringContainsString( 'has-text-align-center', $html );
		$this->assertStringContainsString( '2018–' . gmdate( 'Y' ), $html );
		$this->assertStringContainsString( 'All rights reserved.', $html );
		$this->assertStringContainsString( 'wp-block-aggressive-apparel-copyright__suffix', $html );
	}

	/**
	 * Parts filter can replace the owner name.
	 *
	 * @return void
	 */
	public function test_parts_filter_overrides_owner(): void {
		add_filter(
			'aggressive_apparel_copyright_parts',
			static function ( array $parts ): array {
				$parts['owner_name'] = 'Filtered Entity';
				return $parts;
			}
		);

		$html = $this->render(
			array(
				'ownerSource' => 'custom',
				'ownerName'   => 'Original',
				'showSchema'  => false,
			)
		);

		$this->assertStringContainsString( 'Filtered Entity', $html );
		$this->assertStringNotContainsString( 'Original', $html );
	}

	/**
	 * HTML filter can replace the entire output.
	 *
	 * @return void
	 */
	public function test_html_filter_overrides_markup(): void {
		add_filter(
			'aggressive_apparel_copyright_html',
			static function (): string {
				return '<p class="custom-copyright">Replaced</p>';
			}
		);

		$html = $this->render(
			array(
				'ownerName'  => 'Anyone',
				'showSchema' => false,
			)
		);

		$this->assertSame( '<p class="custom-copyright">Replaced</p>', $html );
	}

	/**
	 * Owner and prefix are escaped in output.
	 *
	 * @return void
	 */
	public function test_render_escapes_owner_and_prefix(): void {
		$html = $this->render(
			array(
				'ownerSource' => 'custom',
				'ownerName'   => 'Owner & Co "Ltd"',
				'prefix'      => '©',
				'showSchema'  => false,
			)
		);

		$this->assertStringContainsString( 'Owner &amp; Co &quot;Ltd&quot;', $html );
		$this->assertStringNotContainsString( 'Owner & Co "Ltd"', $html );
	}

	/**
	 * Legal entity designation is appended to the owner name.
	 *
	 * @return void
	 */
	public function test_render_appends_legal_entity(): void {
		$html = $this->render(
			array(
				'ownerSource' => 'custom',
				'ownerName'   => 'Aggressive Apparel',
				'legalEntity' => 'LLC',
				'prefix'      => '©',
				'showSchema'  => false,
			)
		);

		$this->assertStringContainsString( 'Aggressive Apparel, LLC', $html );
	}

	/**
	 * Custom legal entity designations are supported.
	 *
	 * @return void
	 */
	public function test_render_custom_legal_entity(): void {
		$html = $this->render(
			array(
				'ownerSource'       => 'custom',
				'ownerName'         => 'Acme',
				'legalEntity'       => 'custom',
				'legalEntityCustom' => 'PLLC',
				'showSchema'        => false,
			)
		);

		$this->assertStringContainsString( 'Acme, PLLC', $html );
	}

	/**
	 * Invalid legal entity presets are ignored.
	 *
	 * @return void
	 */
	public function test_format_owner_rejects_invalid_preset(): void {
		$this->assertSame(
			'Acme',
			Copyright::format_owner( 'Acme', 'NOTREAL' )
		);
		$this->assertSame(
			'Acme, LLC',
			Copyright::format_owner( 'Acme', 'LLC' )
		);
		$this->assertSame(
			'Acme, LLC',
			Copyright::format_owner( 'Acme, LLC', 'LLC' )
		);
	}

	/**
	 * Owner source resolution prefers raw ownerSource, then legacy useSiteTitle.
	 *
	 * @return void
	 */
	public function test_resolve_owner_source_back_compat(): void {
		$this->assertSame(
			'site_title',
			Copyright::resolve_owner_source(
				array( 'ownerSource' => 'site_title' ),
				array( 'ownerSource' => 'site_title' )
			)
		);
		$this->assertSame(
			'custom',
			Copyright::resolve_owner_source(
				array( 'ownerSource' => 'site_title' ),
				array( 'useSiteTitle' => false )
			)
		);
		$this->assertSame(
			'legal_name',
			Copyright::resolve_owner_source(
				array(),
				array( 'ownerSource' => 'legal_name' )
			)
		);
	}

	/**
	 * Schema.org JSON-LD is emitted once per request when enabled.
	 *
	 * @return void
	 */
	public function test_render_emits_schema_json_ld_once(): void {
		$first = $this->render(
			array(
				'ownerSource' => 'custom',
				'ownerName'   => 'Schema Co',
				'legalEntity' => 'Inc.',
				'showSchema'  => true,
			)
		);

		$this->assertStringContainsString( 'application/ld+json', $first );
		$this->assertStringContainsString( 'copyrightHolder', $first );
		$this->assertStringContainsString( 'copyrightYear', $first );
		$this->assertStringContainsString( 'Schema Co, Inc.', $first );

		$second = $this->render(
			array(
				'ownerSource' => 'custom',
				'ownerName'   => 'Schema Co',
				'showSchema'  => true,
			)
		);

		$this->assertStringNotContainsString( 'application/ld+json', $second );
	}

	/**
	 * Legal links render when Privacy / Terms pages exist.
	 *
	 * @return void
	 */
	public function test_render_legal_links(): void {
		$privacy_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Privacy Policy',
			)
		);
		$terms_id   = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Terms of Service',
			)
		);

		update_option( 'wp_page_for_privacy_policy', $privacy_id );
		update_option( Legal_Identity::TERMS_PAGE_OPTION, $terms_id );

		$html = $this->render(
			array(
				'ownerSource'    => 'custom',
				'ownerName'      => 'Link Co',
				'showLegalLinks' => true,
				'showSchema'     => false,
			)
		);

		$this->assertStringContainsString( 'wp-block-aggressive-apparel-copyright__legal-links', $html );
		$this->assertStringContainsString( 'Privacy', $html );
		$this->assertStringContainsString( 'Terms', $html );
		$this->assertStringContainsString( get_permalink( $privacy_id ), $html );
		$this->assertStringContainsString( get_permalink( $terms_id ), $html );
	}

	/**
	 * Privacy link still renders when the Privacy Policy page is a draft.
	 *
	 * Core get_privacy_policy_url() returns empty for drafts; we resolve the
	 * permalink so the Copyright block can show the link during setup.
	 *
	 * @return void
	 */
	public function test_render_legal_links_with_draft_privacy_page(): void {
		$privacy_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
				'post_title'  => 'Privacy Policy',
			)
		);

		update_option( 'wp_page_for_privacy_policy', $privacy_id );

		$this->assertSame( '', get_privacy_policy_url() );
		$this->assertNotSame( '', Legal_Identity::get_privacy_url() );

		$html = $this->render(
			array(
				'ownerSource'    => 'custom',
				'ownerName'      => 'Draft Privacy Co',
				'showLegalLinks' => true,
				'showSchema'     => false,
			)
		);

		$this->assertStringContainsString( 'Privacy', $html );
		$this->assertStringContainsString( Legal_Identity::get_privacy_url(), $html );
	}

	/**
	 * Legal links honor a custom separator.
	 *
	 * @return void
	 */
	public function test_render_legal_links_separator(): void {
		$privacy_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Privacy Policy',
			)
		);
		$terms_id   = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Terms of Service',
			)
		);

		update_option( 'wp_page_for_privacy_policy', $privacy_id );
		update_option( Legal_Identity::TERMS_PAGE_OPTION, $terms_id );

		$html = $this->render(
			array(
				'ownerSource'         => 'custom',
				'ownerName'           => 'Link Co',
				'showLegalLinks'      => true,
				'legalLinksSeparator' => '·',
				'showSchema'          => false,
			)
		);

		$this->assertStringContainsString( '>·</span>', $html );
		$this->assertStringContainsString( 'Privacy', $html );
		$this->assertStringContainsString( 'Terms', $html );
	}

	/**
	 * Presets load from the shared JSON file.
	 *
	 * @return void
	 */
	public function test_legal_entity_presets_include_llc(): void {
		$presets = Copyright::legal_entity_presets();
		$this->assertContains( 'LLC', $presets );
		$this->assertContains( 'GmbH', $presets );
	}
}
