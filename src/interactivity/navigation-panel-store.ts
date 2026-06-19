/**
 * Shared Navigation Panel Store Module
 *
 * Registers the `aggressive-apparel/navigation-panel` Interactivity store once
 * as a standalone script module. The mobile panel and the navigation-trigger
 * blocks import this via the WordPress import map (bare specifier
 * `@aggressive-apparel/navigation-panel-store`) instead of each bundling its own
 * copy of the store — see webpack.config.mjs THEME_MODULE_EXTERNALS.
 *
 * @package Aggressive_Apparel
 */

import '../blocks-interactivity/navigation-panel/store';
