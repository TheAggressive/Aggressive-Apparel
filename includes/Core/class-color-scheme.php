<?php
/**
 * Color Scheme helpers.
 *
 * PHP mirror of src/utils/color-scheme-storage.ts — storage key names and
 * shared inline-JS snippets for early head scripts (bootstrap, favicon).
 *
 * @package Aggressive_Apparel
 * @since 1.142.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Color Scheme
 *
 * @since 1.142.0
 */
final class Color_Scheme {

	/**
	 * Canonical preference key (frontend + editor).
	 * Keep in sync with COLOR_SCHEME_STORAGE_KEY in color-scheme-storage.ts.
	 */
	public const STORAGE_KEY = 'aggressive-apparel-color-scheme';

	/**
	 * Inline JS function that reads the manual preference.
	 *
	 * Returns 'dark' | 'light' | '' (empty = follow system).
	 * Declares `aaReadStoredColorScheme` in the current scope.
	 *
	 * @return string JavaScript function declaration (no script tags).
	 */
	public static function js_read_stored_scheme_function(): string {
		$canonical = wp_json_encode( self::STORAGE_KEY );

		if ( false === $canonical ) {
			return 'function aaReadStoredColorScheme(){return "";}';
		}

		return <<<JS
function aaReadStoredColorScheme(){
	try{
		var stored=localStorage.getItem({$canonical})||'';
		return stored==='dark'||stored==='light'?stored:'';
	}catch(e){}
	return '';
}
JS;
	}
}
