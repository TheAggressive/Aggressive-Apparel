/**
 * Pure behavior and geometry helpers for the horizontal-scroll block.
 *
 * Keeping these independent from the DOM makes the three runtime modes small
 * and lets their boundary behavior be tested without simulating a browser.
 */

export type HScrollMode = 'pinned' | 'paged' | 'native' | 'static';

export type SnapBehavior = 'off' | 'proximity' | 'paged';

export type SwipeHintStyle = 'off' | 'cue' | 'label' | 'badge';

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

/**
 * Map a keyboard navigation key to a target slide index, or null when the key
 * is not one we handle. Shared by every controller so keyboard paging behaves
 * identically — Home/End, Page Up/Down, and the Arrow keys (mirrored for RTL,
 * where ArrowRight moves toward the visually-right previous slide).
 */
export function resolveKeyboardTarget(params: {
  key: string;
  currentIndex: number;
  slideCount: number;
  rtl: boolean;
}): number | null {
  const { key, currentIndex, slideCount, rtl } = params;
  const lastIndex = slideCount - 1;

  switch (key) {
    case 'Home':
      return 0;
    case 'End':
      return lastIndex;
    case 'PageDown':
      return clamp(currentIndex + 1, 0, lastIndex);
    case 'PageUp':
      return clamp(currentIndex - 1, 0, lastIndex);
    case 'ArrowRight':
      return clamp(currentIndex + (rtl ? -1 : 1), 0, lastIndex);
    case 'ArrowLeft':
      return clamp(currentIndex + (rtl ? 1 : -1), 0, lastIndex);
    default:
      return null;
  }
}

/**
 * Cubic ease-in-out (0 → 1). Used to drive the step controller's own scroll
 * tween so the slide glide is smooth and fully under our control, instead of
 * the browser's untunable `scrollTo({ behavior: 'smooth' })`.
 */
export function easeInOutCubic(t: number): number {
  const clamped = clamp(t, 0, 1);
  return clamped < 0.5
    ? 4 * clamped * clamped * clamped
    : 1 - (-2 * clamped + 2) ** 3 / 2;
}

/**
 * Document scroll position (px) that pins a given slide index at its stop.
 * Slide stops are ratios of the sticky range, so the absolute position is the
 * range's start plus that ratio of the range's scroll distance.
 */
export function getStepScrollPosition(
  index: number,
  scrollStart: number,
  scrollDistance: number,
  slideStops: number[]
): number {
  if (slideStops.length === 0) return scrollStart;

  const target = clamp(index, 0, slideStops.length - 1);
  return scrollStart + (slideStops[target] ?? 0) * scrollDistance;
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
