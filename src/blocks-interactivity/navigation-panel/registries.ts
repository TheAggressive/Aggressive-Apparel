/**
 * Navigation Panel Block — Module Registries
 *
 * Process-wide registries shared by the panel store and its helper modules.
 *
 * @package Aggressive_Apparel
 */

// Focus trap cleanup registry using WeakMap for proper garbage collection.
export const focusTrapRegistry = new WeakMap<Element, () => void>();

// Swipe-to-close cleanup registry.
export const swipeRegistry = new WeakMap<Element, () => void>();
