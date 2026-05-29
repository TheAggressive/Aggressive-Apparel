/**
 * Shared Interactivity API store typing helpers.
 *
 * Prefer explicit per-store action interfaces where practical; use these
 * records for store refs that only need a subset of actions at runtime.
 *
 * @package Aggressive_Apparel
 */

/** Handler on an Interactivity API store action or callback. */
export type InteractivityHandler = (...args: unknown[]) => unknown;

/** Open-ended actions map (directive handlers may pass events). */
export type InteractivityActions = Record<string, InteractivityHandler>;

/** Open-ended callbacks map (data-wp-watch, data-wp-init, etc.). */
export type InteractivityCallbacks = Record<string, InteractivityHandler>;
