/**
 * Navigation Trigger Block View Script
 *
 * This block has no store of its own — it drives the
 * aggressive-apparel/navigation-panel store defined by the navigation-panel
 * block. We import that store here so the namespace (state + actions) is
 * always registered on pages that contain a trigger, regardless of whether
 * the panel block's own view module has loaded yet.
 *
 * @package Aggressive_Apparel
 */

import '../navigation-panel/store';
