<?php
/**
 * Plugin Name: Aggressive Apparel Quiet Notices E2E Fixture
 * Description: Keeps PHP notices out of the page during local E2E runs.
 *
 * A Studio site shows PHP notices, and any plugin that raises one early (PayPal
 * does, on every request) prints it above the page. That output stops WordPress
 * sending headers and login cookies, adds a link the accessibility checks fail
 * and pushes the layout around, so the suite ends up testing the notice rather
 * than the store. wp-env sites and production don't show them, so this is the
 * one place it's needed.
 *
 * Only display is affected: WP_DEBUG_LOG still records every notice. Linked in
 * by bin/local/studio-e2e.mjs for one run and removed afterwards. bin/ is removed
 * from releases.
 *
 * @package Aggressive_Apparel
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.PHP.IniSet.display_errors_Disallowed, WordPress.PHP.IniSet.display_errors_Blacklisted -- the point of this local-only fixture.
ini_set( 'display_errors', '0' );
