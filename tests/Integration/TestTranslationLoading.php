<?php
/**
 * Test that compiled translation catalogs are actually loaded.
 *
 * This suite exists because every other i18n check passed while all four
 * locales were dead. The catalogs were valid, the POT was current, the
 * placeholders matched — and WordPress never opened a single file, because
 * `wp i18n make-mo` names its output `<domain>-<locale>.mo` while a theme's own
 * languages directory is read as `<locale>.mo`.
 *
 * Nothing surfaced it. Since WordPress 6.7 `load_theme_textdomain()` only
 * registers a path with WP_Textdomain_Registry and returns `true`
 * unconditionally, so "did it load?" cannot be answered from its return value.
 * The only honest test is to translate a string and look at what comes back.
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Integration;

use WP_UnitTestCase;

/**
 * Translation Loading Test Case
 */
class TestTranslationLoading extends WP_UnitTestCase {

	/**
	 * Absolute path to the theme's catalog directory.
	 *
	 * @var string
	 */
	private string $languages_dir;

	/**
	 * Set up test
	 */
	public function setUp(): void {
		parent::setUp();
		$this->languages_dir = get_template_directory() . '/languages';
		$this->reset_textdomain_state();
	}

	/**
	 * Return the gettext globals to the state a fresh process would have.
	 *
	 * These tests pass in isolation and fail in the full suite without this.
	 * Any earlier test that reaches a __() call for this domain leaves three
	 * pieces of cached state behind, and unload_textdomain() clears only the
	 * first:
	 *
	 * - $l10n holds the already-built translation set for the *previous*
	 *   locale, so the domain looks loaded and nothing re-reads the .mo.
	 * - $l10n_unloaded suppresses the just-in-time loader entirely.
	 * - WP_Textdomain_Registry caches the path lookup per domain+locale, so a
	 *   domain first resolved under en_US keeps answering for en_US.
	 *
	 * Clearing all three is what makes the assertion measure this theme's
	 * catalogs rather than whatever ran before it in the process.
	 */
	private function reset_textdomain_state(): void {
		global $l10n, $l10n_unloaded;

		unset( $l10n['aggressive-apparel'], $l10n_unloaded['aggressive-apparel'] );
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		remove_all_filters( 'determine_locale' );

		/*
		 * The second argument matters. unload_textdomain( $domain ) records the
		 * domain in $l10n_unloaded, and _load_textdomain_just_in_time() bails
		 * out for anything listed there — so a plain unload in tearDown leaves
		 * every later test in the process unable to load a catalog at all, and
		 * they fail for a reason that has nothing to do with the code. Passing
		 * true marks it reloadable instead.
		 */
		unload_textdomain( 'aggressive-apparel', true );

		parent::tearDown();
	}

	/**
	 * Locales that ship a source catalog.
	 *
	 * Derived from the committed .po files rather than hardcoded, so adding a
	 * locale automatically extends the coverage instead of quietly bypassing it.
	 *
	 * @return array<string, array{string}>
	 */
	public static function shipped_locales(): array {
		$cases = array();

		foreach ( glob( __DIR__ . '/../../languages/aggressive-apparel-*.po' ) ?: array() as $po ) {
			$locale = str_replace( 'aggressive-apparel-', '', basename( $po, '.po' ) );

			$cases[ $locale ] = array( $locale );
		}

		return $cases;
	}

	/**
	 * The compiled catalog must use the filename WordPress looks for.
	 *
	 * `_load_textdomain_just_in_time()` picks between two names based on
	 * whether the registered path sits inside the template or stylesheet
	 * directory. Ours always does, so only `<locale>.mo` is ever opened, and
	 * there is no fallback to the prefixed form.
	 *
	 * @dataProvider shipped_locales
	 *
	 * @param string $locale Locale code.
	 */
	public function test_catalog_uses_the_filename_wordpress_looks_for( string $locale ): void {
		$expected = $this->languages_dir . '/' . $locale . '.mo';
		$prefixed = $this->languages_dir . '/aggressive-apparel-' . $locale . '.mo';

		$this->assertFileExists(
			$expected,
			"Missing {$locale}.mo. A theme's own languages/ directory is read as <locale>.mo, "
				. 'not <domain>-<locale>.mo. Run: pnpm i18n:compile'
		);

		$this->assertFileDoesNotExist(
			$prefixed,
			"aggressive-apparel-{$locale}.mo is the wp-content/languages/themes/ convention and "
				. 'is never opened from inside the theme. compile.sh should have renamed it.'
		);
	}

	/**
	 * A translated string must come back translated.
	 *
	 * The assertion that would have caught the original defect: not that a
	 * file exists, and not what load_theme_textdomain() returned, but that
	 * __() gives back something other than the English it was handed.
	 *
	 * @dataProvider shipped_locales
	 *
	 * @param string $locale Locale code.
	 */
	public function test_translations_reach_the_gettext_call( string $locale ): void {
		$pair = $this->first_translated_entry( $locale );

		if ( null === $pair ) {
			$this->markTestSkipped( "No usable translated entry in the {$locale} catalog." );
		}

		$this->force_locale( $locale );
		// After the filter, not before: the registry caches per domain+locale,
		// so clearing it while determine_locale still says en_US would just
		// re-cache the wrong answer.
		$this->reset_textdomain_state();
		load_theme_textdomain( 'aggressive-apparel', $this->languages_dir );

		$this->assertSame(
			$pair['translation'],
			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- the string under test is read from the catalog.
			__( $pair['source'], 'aggressive-apparel' ),
			"The {$locale} catalog is not being loaded. Note that load_theme_textdomain() "
				. 'returns true regardless since WP 6.7, so only this assertion can tell.'
		);
	}

	/**
	 * An untranslated domain must fall through to English rather than to empty.
	 *
	 * Guards the inverse failure: a catalog that loads but returns blanks is
	 * worse than one that never loads at all.
	 */
	public function test_unknown_string_falls_through_to_english(): void {
		$this->force_locale( 'de_DE' );
		load_theme_textdomain( 'aggressive-apparel', $this->languages_dir );

		$this->assertSame(
			'A string that is not in any catalog',
			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- deliberately absent from every catalog.
			__( 'A string that is not in any catalog', 'aggressive-apparel' )
		);
	}

	/**
	 * Forces the locale the just-in-time loader will resolve.
	 *
	 * switch_to_locale() is deliberately not used. It refuses any locale
	 * missing from get_available_languages(), which is empty in the test
	 * install because no core language packs are installed there — so it
	 * silently leaves the locale at en_US and every assertion below it reads
	 * as "the catalog did not load". Two of the four locales appeared to pass
	 * for that reason alone.
	 *
	 * _load_textdomain_just_in_time() calls determine_locale(), so filtering
	 * that is both sufficient and closer to what is under test: whether this
	 * theme's catalogs load, not whether core ships a translation for the
	 * locale.
	 *
	 * @param string $locale Locale code.
	 */
	private function force_locale( string $locale ): void {
		add_filter(
			'determine_locale',
			static function () use ( $locale ) {
				return $locale;
			}
		);
	}

	/**
	 * Reads the first entry from a catalog that survives a round trip.
	 *
	 * Skips plurals, contexts, fuzzy entries and anything with a placeholder,
	 * and requires the translation to differ from its source — an entry that
	 * translates to itself proves nothing about whether the catalog loaded.
	 *
	 * @param string $locale Locale code.
	 * @return array{source: string, translation: string}|null
	 */
	private function first_translated_entry( string $locale ): ?array {
		$po = $this->languages_dir . '/aggressive-apparel-' . $locale . '.po';

		if ( ! is_readable( $po ) ) {
			return null;
		}

		$contents = file_get_contents( $po );

		if ( false === $contents ) {
			return null;
		}

		foreach ( preg_split( '/\R{2,}/', $contents ) ?: array() as $block ) {
			if ( preg_match( '/^#,.*fuzzy/m', $block ) ) {
				continue;
			}

			if ( preg_match( '/^(msgctxt|msgid_plural) /m', $block ) ) {
				continue;
			}

			if ( ! preg_match( '/^msgid "((?:[^"\\\\]|\\\\.)+)"$/m', $block, $source ) ) {
				continue;
			}

			if ( ! preg_match( '/^msgstr "((?:[^"\\\\]|\\\\.)+)"$/m', $block, $translation ) ) {
				continue;
			}

			if ( str_contains( $source[1], '%' ) || $source[1] === $translation[1] ) {
				continue;
			}

			return array(
				'source'      => stripcslashes( $source[1] ),
				'translation' => stripcslashes( $translation[1] ),
			);
		}

		return null;
	}
}
