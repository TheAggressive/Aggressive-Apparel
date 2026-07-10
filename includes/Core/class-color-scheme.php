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
	 * Legacy frontend key (pre-unification).
	 * Keep in sync with LEGACY_FRONTEND_STORAGE_KEY in color-scheme-storage.ts.
	 */
	public const LEGACY_FRONTEND_KEY = 'aggressive-apparel-dark-mode';

	/**
	 * Legacy editor-only key (pre-unification).
	 * Keep in sync with LEGACY_EDITOR_STORAGE_KEY in color-scheme-storage.ts.
	 */
	public const LEGACY_EDITOR_KEY = 'aggressive-apparel-editor-color-scheme';

	/**
	 * Storage keys in lookup order (canonical first, then legacy).
	 *
	 * @return list<string>
	 */
	public static function storage_keys(): array {
		return array(
			self::STORAGE_KEY,
			self::LEGACY_FRONTEND_KEY,
			self::LEGACY_EDITOR_KEY,
		);
	}

	/**
	 * Inline JS function that reads the manual preference and migrates legacy keys.
	 *
	 * Returns 'dark' | 'light' | '' (empty = follow system).
	 * Declares `aaReadStoredColorScheme` in the current scope.
	 *
	 * @return string JavaScript function declaration (no script tags).
	 */
	public static function js_read_stored_scheme_function(): string {
		$keys_json       = wp_json_encode( self::storage_keys() );
		$canonical       = wp_json_encode( self::STORAGE_KEY );
		$legacy_frontend = wp_json_encode( self::LEGACY_FRONTEND_KEY );
		$legacy_editor   = wp_json_encode( self::LEGACY_EDITOR_KEY );

		if ( false === $keys_json || false === $canonical || false === $legacy_frontend || false === $legacy_editor ) {
			return 'function aaReadStoredColorScheme(){return "";}';
		}

		return <<<JS
function aaReadStoredColorScheme(){
	var keys={$keys_json};
	var stored='';
	try{
		for(var i=0;i<keys.length;i++){
			var value=localStorage.getItem(keys[i])||'';
			if(value==='dark'||value==='light'){stored=value;break;}
		}
		if(stored){
			localStorage.setItem({$canonical},stored);
			localStorage.removeItem({$legacy_frontend});
			localStorage.removeItem({$legacy_editor});
		}
	}catch(e){}
	return stored;
}
JS;
	}
}
