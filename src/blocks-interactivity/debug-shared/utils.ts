/**
 * Pure helpers for the shared scroll-debug tooling.
 *
 * @package Aggressive Apparel
 */

import type { DebugBoundary, IntersectionPhase } from './types';

/** Human-readable labels for the state badge. */
export const PHASE_LABELS: Record<IntersectionPhase, string> = {
  waiting: 'Waiting',
  approaching: 'Approaching',
  active: 'Active',
};

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

/** True when any side of the boundary extends beyond the viewport. */
export const boundaryExtendsViewport = (boundary: DebugBoundary): boolean =>
  [boundary.top, boundary.right, boundary.bottom, boundary.left].some(
    value => marginPartToPx(value || '0%', 100) > 0
  );

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
