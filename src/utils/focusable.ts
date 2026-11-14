/**
 * Selector for natively focusable controls (sequential keyboard navigation).
 *
 * Shared by the focus trap in `@aggressive-apparel/helpers` and the
 * navigation stores, which bundle it directly.
 *
 * @package Aggressive_Apparel
 */

export const FOCUSABLE_SELECTOR =
	'a[href], button:not([disabled]), input:not([disabled]), ' +
	'select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
