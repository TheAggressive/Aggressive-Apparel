/**
 * Pure behavior and geometry helpers for the horizontal-scroll block.
 *
 * Keeping these independent from the DOM makes the three runtime modes small
 * and lets their boundary behavior be tested without simulating a browser.
 */

export type HScrollMode = 'pinned' | 'paged' | 'native' | 'static';

export type SnapBehavior = 'off' | 'proximity' | 'paged';

export type SnapStrength = 'soft' | 'medium' | 'strong' | 'aggressive';

export interface SnapStrengthPreset {
  maxDistance: number;
  maxSegmentRatio: number;
}

export const SNAP_STRENGTH_PRESETS: Readonly<
  Record<SnapStrength, SnapStrengthPreset>
> = {
  soft: { maxDistance: 240, maxSegmentRatio: 0.12 },
  medium: { maxDistance: 480, maxSegmentRatio: 0.25 },
  strong: { maxDistance: 720, maxSegmentRatio: 0.35 },
  aggressive: { maxDistance: 1600, maxSegmentRatio: 0.5 },
};

export const PAGED_COMMIT_RATIO = 0.05;

export type SwipeHintStyle = 'off' | 'cue' | 'label' | 'badge';

export interface ProximitySnapTarget {
  index: number;
  scrollPosition: number;
}

export function clamp(value: number, min: number, max: number): number {
  return Math.max(min, Math.min(max, value));
}

export function resolveSpeed(contextSpeed: number, cssSpeed: number): number {
  const base =
    Number.isFinite(contextSpeed) && contextSpeed > 0
      ? contextSpeed
      : cssSpeed || 1;

  return clamp(base, 0.5, 3);
}

export function pickMode(params: {
  reducedMotion: boolean;
  desktopMatches: boolean;
  maxTranslate: number;
  pinned?: boolean;
  snapBehavior?: SnapBehavior;
}): HScrollMode {
  const {
    reducedMotion,
    desktopMatches,
    maxTranslate,
    pinned = true,
    snapBehavior = 'off',
  } = params;

  if (reducedMotion || maxTranslate <= 1) return 'static';
  if (!desktopMatches || !pinned) return 'native';

  return snapBehavior === 'paged' ? 'paged' : 'pinned';
}

export function getSnapStrengthPreset(
  strength: SnapStrength
): SnapStrengthPreset {
  return SNAP_STRENGTH_PRESETS[strength];
}

export function computeProgress(
  scrollPosition: number,
  scrollStart: number,
  scrollDistance: number
): number {
  if (scrollDistance <= 0) return 0;
  return clamp((scrollPosition - scrollStart) / scrollDistance, 0, 1);
}

/** Report 100 only at the real endpoint so completion never precedes motion. */
export function progressToPercentage(progress: number): number {
  const bounded = clamp(progress, 0, 1);
  return bounded >= 1 ? 100 : Math.floor(bounded * 100);
}

export function computeScrollStart(
  rangeDocumentTop: number,
  stickyTop: number
): number {
  return rangeDocumentTop - stickyTop;
}

export function buildSlideStops(
  slideOffsets: number[],
  maxTranslate: number
): number[] {
  if (slideOffsets.length === 0) return [];
  if (maxTranslate <= 0) return slideOffsets.map(() => 0);

  return slideOffsets.map(
    offset => clamp(offset, 0, maxTranslate) / maxTranslate
  );
}

/**
 * Convert physical (left-edge) slide offsets into logical inline-start
 * distances. In LTR the two are identical; in RTL the first slide sits at the
 * physical right, so its logical offset is measured from the track's right
 * edge instead.
 */
export function toLogicalSlideOffsets(params: {
  offsets: number[];
  sizes: number[];
  trackSize: number;
  rtl: boolean;
}): number[] {
  const { offsets, sizes, trackSize, rtl } = params;

  if (!rtl) return offsets.slice();

  return offsets.map((offset, index) =>
    Math.max(0, trackSize - offset - (sizes[index] ?? 0))
  );
}

/**
 * Signed CSS translation for a given unsigned track distance. The pinned
 * track moves left in LTR (negative X) and right in RTL (positive X).
 */
export function toSignedTranslate(distance: number, rtl: boolean): number {
  const rounded = Math.round(distance * 100) / 100;
  return rtl ? rounded : -rounded;
}

export function getSlideIndexFromProgress(
  progress: number,
  slideStops: number[]
): number {
  if (slideStops.length <= 1) return 0;

  const boundedProgress = clamp(progress, 0, 1);
  if (boundedProgress >= 1) return slideStops.length - 1;

  return slideStops.reduce(
    (nearestIndex, stop, index) =>
      Math.abs(stop - boundedProgress) <
      Math.abs(slideStops[nearestIndex] - boundedProgress)
        ? index
        : nearestIndex,
    0
  );
}

export function getSlideTarget(
  slideIndex: number,
  slideStops: number[],
  maxTranslate: number
): number {
  if (slideStops.length === 0 || maxTranslate <= 0) return 0;

  const target = clamp(slideIndex, 0, slideStops.length - 1);
  return clamp(slideStops[target] * maxTranslate, 0, maxTranslate);
}

/** Resolve the next adjacent slide once movement crosses a commit threshold. */
export function getPagedSnapTarget(params: {
  progress: number;
  settledIndex: number;
  slideStops: number[];
  commitRatio?: number;
}): number | null {
  const {
    progress,
    slideStops,
    commitRatio: rawCommitRatio = PAGED_COMMIT_RATIO,
  } = params;
  if (slideStops.length <= 1) return null;

  const settledIndex = clamp(params.settledIndex, 0, slideStops.length - 1);
  const commitRatio = clamp(rawCommitRatio, 0.05, 0.95);
  const currentStop = slideStops[settledIndex] ?? 0;

  if (progress > currentStop && settledIndex < slideStops.length - 1) {
    const nextStop = slideStops[settledIndex + 1];
    const threshold = currentStop + (nextStop - currentStop) * commitRatio;
    return progress >= threshold ? settledIndex + 1 : null;
  }

  if (progress < currentStop && settledIndex > 0) {
    const previousStop = slideStops[settledIndex - 1];
    const threshold = currentStop - (currentStop - previousStop) * commitRatio;
    return progress <= threshold ? settledIndex - 1 : null;
  }

  return null;
}

/**
 * Resolve a small, post-scroll correction to a nearby slide. Endpoint stops
 * are eligible only while the document remains inside the sticky range, so
 * snapping cannot pull the page back after the user has already left.
 */
export function getProximitySnapTarget(params: {
  scrollPosition: number;
  scrollStart: number;
  scrollDistance: number;
  slideStops: number[];
  maxDistance?: number;
  maxSegmentRatio?: number;
}): ProximitySnapTarget | null {
  const {
    scrollPosition,
    scrollStart,
    scrollDistance,
    slideStops,
    maxDistance = 480,
    maxSegmentRatio = 0.25,
  } = params;

  if (scrollDistance <= 0 || slideStops.length < 3) return null;

  const progress = (scrollPosition - scrollStart) / scrollDistance;
  if (progress <= 0 || progress >= 1) return null;

  const candidates = slideStops
    .map((stop, index) => ({ index, stop }))
    .filter(({ stop }) => stop >= 0 && stop <= 1);
  if (candidates.length === 0) return null;

  const nearest = candidates.reduce((current, candidate) =>
    Math.abs(candidate.stop - progress) < Math.abs(current.stop - progress)
      ? candidate
      : current
  );
  const targetPosition = scrollStart + nearest.stop * scrollDistance;
  const distanceToTarget = Math.abs(targetPosition - scrollPosition);
  if (distanceToTarget <= 1) return null;

  const lowerStop = Math.max(
    0,
    ...slideStops.filter(stop => stop < nearest.stop)
  );
  const upperStop = Math.min(
    1,
    ...slideStops.filter(stop => stop > nearest.stop)
  );
  const adjacentSpan =
    Math.max(nearest.stop - lowerStop, upperStop - nearest.stop) *
    scrollDistance;
  const threshold = Math.min(
    Math.max(0, maxDistance),
    Math.max(0, adjacentSpan * maxSegmentRatio)
  );

  if (distanceToTarget > threshold) return null;

  return {
    index: nearest.index,
    scrollPosition: targetPosition,
  };
}

export const DEFAULT_SLIDE_ANNOUNCEMENT = 'Slide %1$s of %2$s';

/**
 * Fill a translated sprintf-style template (`%1$s` position, `%2$s` count).
 * The default English template is a fallback for missing context data.
 */
export function formatSlideAnnouncement(
  slideIndex: number,
  slideCount: number,
  template: string = DEFAULT_SLIDE_ANNOUNCEMENT
): string {
  const safeTemplate =
    template && template.includes('%1$s') && template.includes('%2$s')
      ? template
      : DEFAULT_SLIDE_ANNOUNCEMENT;

  return safeTemplate
    .split('%1$s')
    .join(String(slideIndex + 1))
    .split('%2$s')
    .join(String(slideCount));
}

export function shouldShowSwipeHint(params: {
  mode: HScrollMode;
  slideCount: number;
  currentIndex: number;
  dismissed: boolean;
  style: SwipeHintStyle;
}): boolean {
  const { mode, slideCount, currentIndex, dismissed, style } = params;

  return (
    style !== 'off' &&
    !dismissed &&
    mode === 'native' &&
    slideCount > 1 &&
    currentIndex < slideCount - 1
  );
}

export function getSlides(track: HTMLElement): HTMLElement[] {
  return Array.from(track.children).filter(
    (child): child is HTMLElement => child instanceof HTMLElement
  );
}

export function isEditableTarget(target: EventTarget | null): boolean {
  if (!(target instanceof HTMLElement)) return false;

  const tagName = target.tagName.toLowerCase();
  return (
    target.isContentEditable ||
    tagName === 'input' ||
    tagName === 'select' ||
    tagName === 'textarea'
  );
}

export function addMediaChangeListener(
  mediaQueryList: MediaQueryList,
  listener: () => void
): () => void {
  const handler = () => listener();
  const legacyMediaQueryList = mediaQueryList as MediaQueryList & {
    addListener?: (listener: () => void) => void;
    removeListener?: (listener: () => void) => void;
  };

  if (typeof mediaQueryList.addEventListener === 'function') {
    mediaQueryList.addEventListener('change', handler);
    return () => mediaQueryList.removeEventListener('change', handler);
  }

  legacyMediaQueryList.addListener?.(handler);
  return () => legacyMediaQueryList.removeListener?.(handler);
}
