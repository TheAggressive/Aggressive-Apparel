<?php
/**
 * Color Scheme Bootstrap Class
 *
 * Prints a blocking inline script at the start of wp_head so the saved
 * light/dark preference (or OS preference) is applied before first paint.
 * Without this, the dark-mode-toggle view script runs too late and the page
 * flashes the wrong scheme.
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
 * Color Scheme Bootstrap
 *
 * @since 1.142.0
 */
class Color_Scheme_Bootstrap {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Priority 0: run before favicon (1), font preload (2), and styles.
		add_action( 'wp_head', array( $this, 'print_inline_script' ), 0 );
	}

	/**
	 * Print the anti-flash color-scheme bootstrap script.
	 *
	 * @return void
	 */
	public function print_inline_script(): void {
		/**
		 * Filter whether the early color-scheme bootstrap script is printed.
		 *
		 * @since 1.142.0
		 *
		 * @param bool $print True to print the script.
		 */
		if ( ! apply_filters( 'aggressive_apparel_color_scheme_bootstrap', true ) ) {
			return;
		}

		wp_print_inline_script_tag(
			$this->get_inline_script(),
			array(
				'id' => 'aggressive-apparel-color-scheme-bootstrap',
			)
		);
	}

	/**
	 * Inline JS that applies color-scheme before first paint.
	 *
	 * Kept public for unit tests. Mirrors dark-mode-toggle/view.ts applyTheme().
	 *
	 * @return string JavaScript source (no script tags).
	 */
	public function get_inline_script(): string {
		$read_fn = Color_Scheme::js_read_stored_scheme_function();

		return <<<JS
(function(){
	{$read_fn}
	var stored=aaReadStoredColorScheme();
	var isSystem=!stored;
	var isDark=stored?stored==='dark':window.matchMedia('(prefers-color-scheme: dark)').matches;
	var html=document.documentElement;
	if(isDark){
		html.style.colorScheme='dark';
		html.setAttribute('data-theme','dark');
		html.classList.add('is-dark-mode');
		html.classList.remove('is-light-mode');
	}else{
		html.style.colorScheme=isSystem?'':'light';
		html.setAttribute('data-theme','light');
		html.classList.add('is-light-mode');
		html.classList.remove('is-dark-mode');
	}
})();
JS;
	}
}
