/**
 * Pure helpers for the shared scroll-debug tooling.
 *
 * @package Aggressive Apparel
 */

import type { DebugBoundary, IntersectionPhase } from './types';

/**
 * Invert a rootMargin component for viewport `inset` visualization:
 * a positive margin expands the root box beyond the viewport, which is
 * a negative inset, and vice versa.
 */
export const invertValue = (value: string): string => {
  const match = value?.match(/(-?\d+\.?\d*)(px|%)/);
  if (!match) {
    return value;
  }
  return `${-parseFloat(match[1])}${match[2]}`;
};

/** Parse the visibility threshold from context (string or number). */
export const getVisibilityThreshold = (
  visibilityTrigger: string | number | undefined
): number => {
  if (typeof visibilityTrigger === 'string') {
    const parsed = parseFloat(visibilityTrigger);
    return isNaN(parsed) ? 0.3 : parsed;
  }
  if (typeof visibilityTrigger === 'number' && !isNaN(visibilityTrigger)) {
    return visibilityTrigger;
  }
  return 0.3;
};

/** Clamp a raw intersectionRatio into [0, 1], falling back on NaN. */
export const getValidIntersectionRatio = (
  ratio: number | undefined,
  fallback = 0
): number =>
  Math.max(
    0,
    Math.min(
      1,
      typeof ratio === 'number' && !isNaN(ratio)
        ? ratio
        : typeof fallback === 'number' && !isNaN(fallback)
          ? fallback
          : 0
    )
  );

/** Derive the lifecycle phase from raw observer data. */
export const getIntersectionPhase = (
  isIntersecting: boolean,
  ratio: number,
  threshold: number
): IntersectionPhase => {
  if (!isIntersecting) {
    return 'waiting';
  }
  return ratio >= threshold ? 'active' : 'approaching';
};

/** Serialize a boundary into a rootMargin string with sensible defaults. */
export const boundaryToRootMargin = (boundary: DebugBoundary): string =>
  `${boundary.top || '0%'} ${boundary.right || '0%'} ${
    boundary.bottom || '0%'
  } ${boundary.left || '0%'}`;

/** Resolve one rootMargin component to pixels against a viewport size. */
export const marginPartToPx = (value: string, viewportPx: number): number => {
  const match = value?.match(/(-?\d+\.?\d*)(px|%)/);
  if (!match) {
    return 0;
  }
  const amount = parseFloat(match[1]);
  return match[2] === '%' ? (amount / 100) * viewportPx : amount;
};

/**
 * Height of the observer's root box: viewport height expanded/shrunk by
 * the top and bottom rootMargin components.
 */
export const getRootBoxHeight = (
  rootMargin: string,
  viewportHeight: number
): number => {
  const parts = rootMargin.trim().split(/\s+/);
  const top = parts[0] ?? '0px';
  const bottom = parts[2] ?? parts[0] ?? '0px';
  return (
    viewportHeight +
    marginPartToPx(top, viewportHeight) +
    marginPartToPx(bottom, viewportHeight)
  );
};

/**
 * The highest intersectionRatio an element can ever reach inside a root
 * box: elements taller than the root box can never be fully visible.
 */
export const getMaxReachableRatio = (
  elementHeight: number,
  rootBoxHeight: number
): number => {
  if (elementHeight <= 0) {
    return 1;
  }
  return Math.max(0, Math.min(1, rootBoxHeight / elementHeight));
};

/**
 * Threshold a production observer should actually use.
 *
 * Elements taller than the observer's root box can never reach the
 * configured intersection ratio (max reachable = rootHeight /
 * elementHeight), so their trigger would never fire at all. When the
 * configured value is unreachable, trigger at 90% of the reachable
 * maximum instead; always cap at 0.99 so IntersectionObserver reliably
 * reports the final crossing. Pure — safe to import from production
 * view code without pulling in any debug UI.
 */
export const getEffectiveThreshold = (
  configured: number,
  elementHeight: number,
  viewportHeight: number,
  rootMargin: string
): number => {
  const capped = Math.min(Math.max(configured, 0), 0.99);
  if (elementHeight <= 0 || viewportHeight <= 0) {
    return capped;
  }
  const maxReachable = getMaxReachableRatio(
    elementHeight,
    getRootBoxHeight(rootMargin, viewportHeight)
  );
  if (maxReachable >= capped) {
    return capped;
  }
  return Math.max(0.01, maxReachable * 0.9);
};

/** True when any side of the boundary extends beyond the viewport. */
export const boundaryExtendsViewport = (boundary: DebugBoundary): boolean =>
  [boundary.top, boundary.right, boundary.bottom, boundary.left].some(
    value => marginPartToPx(value || '0%', 100) > 0
  );

/**
 * Cross-bundle debug instance counter.
 *
 * Each block type ships its OWN compiled copy of this module, so
 * module-level state cannot coordinate parallax and animate-on-scroll
 * instances on the same page (both copies would count from zero). The
 * DOM is the shared channel: a dataset counter on <html> is visible to
 * every copy. Drives identity-color assignment.
 */
export const nextDebugInstanceIndex = (): number => {
  const root = document.documentElement;
  const next = parseInt(root.dataset.aaDbgSeq ?? '0', 10) || 0;
  root.dataset.aaDbgSeq = String(next + 1);
  return next;
};

/** Read + parse JSON from localStorage; never throws. */
export const safeStorageGet = <T>(key: string): T | null => {
  try {
    const raw = window.localStorage.getItem(key);
    return raw ? (JSON.parse(raw) as T) : null;
  } catch {
    return null;
  }
};

/** Write JSON to localStorage; never throws (private browsing, quota). */
export const safeStorageSet = (key: string, value: unknown): void => {
  try {
    window.localStorage.setItem(key, JSON.stringify(value));
  } catch {
    // Ignore storage failures.
  }
};

/** Tiny DOM builder for the debug UI. */
export const el = (
  tag: string,
  className: string,
  text?: string
): HTMLElement => {
  const node = document.createElement(tag);
  node.className = className;
  if (text !== undefined) {
    node.textContent = text;
  }
  return node;
};
