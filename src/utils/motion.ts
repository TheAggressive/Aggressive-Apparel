/**
 * Reduced-motion preference.
 *
 * Dependency-free so block views, classic scripts and admin bundles can import
 * it directly; the `@aggressive-apparel/helpers` module re-exports it.
 *
 * @package Aggressive_Apparel
 */

export const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)';

/** Whether the visitor asked the OS for reduced motion. */
export function prefersReducedMotion(): boolean {
	return (
		typeof window !== 'undefined' &&
		typeof window.matchMedia === 'function' &&
		window.matchMedia( REDUCED_MOTION_QUERY ).matches
	);
}

/** `scrollIntoView` / `scrollTo` behaviour that honours the preference. */
export function scrollBehavior(): ScrollBehavior {
	return prefersReducedMotion() ? 'auto' : 'smooth';
}
