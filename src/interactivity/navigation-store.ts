/**
 * Shared Navigation Store Module
 *
 * Registers the `aggressive-apparel/navigation` Interactivity store once as a
 * standalone script module. The desktop navigation, dropdown, and mega blocks
 * import this via the WordPress import map (bare specifier
 * `@aggressive-apparel/navigation-store`) instead of each bundling its own copy
 * of the ~32KB store — see webpack.config.mjs THEME_MODULE_EXTERNALS.
 *
 * @package Aggressive_Apparel
 */

import '../blocks-interactivity/navigation/store';
