/**
 * Horizontal Scroll Block — Interactivity API Store.
 *
 * Desktop uses CSS sticky plus a real-width scroll distance. Touch devices use
 * native horizontal scroll-snap. Reduced-motion users keep normal document flow.
 *
 * @package Aggressive_Apparel
 */

/// <reference types="@wordpress/interactivity" />
import { store, getContext, getElement } from '@wordpress/interactivity';

interface HScrollContext {
  itemWidth: string;
  speed: number;
  progress: number;
  desktopBehavior?: 'pinned' | 'inline';
  snapToNext?: boolean;
  swipeHintStyle?: SwipeHintStyle;
}

interface HScrollStore {
  callbacks: {
    init: () => void | (() => void);
    progressStyle: () => string;
  };
}

interface HScrollRuntime {
  destroy: () => void;
}

type HScrollMode = 'desktop' | 'snap' | 'static';

const DESKTOP_QUERY = '(pointer: fine) and (min-width: 782px)';
const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)';
const MODE_CLASSES = ['is-enhanced', 'is-horizontal', 'is-snap', 'is-static'];
const SLIDE_ANIMATION_FALLBACK_MS = 800;
const SCROLL_COMMIT_MS = 150;
const GESTURE_THRESHOLD = 40;
// Progress tolerance for "am I at the first/last slide?" boundary checks. The
// snap-to-next smooth scroll lands a sub-pixel short of an exact 0/1 progress,
// so a strict comparison would never release the wheel and lock the scroll.
const BOUNDARY_EPSILON = 0.02;
const WHEEL_GESTURE_IDLE_MS = 120;
const TOUCH_GESTURE_IDLE_MS = 120;

const runtimes = new WeakMap<HTMLElement, HScrollRuntime>();

export function clamp(value: number, min: number, max: number): number {
  return Math.max(min, Math.min(max, value));
}

/**
 * Resolve the effective scroll-speed multiplier: prefer a valid context value,
 * else the CSS variable, else 1 — clamped to the supported [0.5, 3] range.
 */
export function resolveSpeed(contextSpeed: number, cssSpeed: number): number {
  const base =
    Number.isFinite(contextSpeed) && contextSpeed > 0
      ? contextSpeed
      : cssSpeed || 1;
  return clamp(base, 0.5, 3);
}

/**
 * Decide the runtime mode from environment + measurements.
 * static — reduced motion or nothing to scroll; desktop — pinned scroll-jack;
 * snap — native horizontal scroll (touch/coarse pointer).
 */
export function pickMode(params: {
  reducedMotion: boolean;
  desktopMatches: boolean;
  maxTranslate: number;
  scrollDistance: number;
  pinned?: boolean;
}): HScrollMode {
  const {
    reducedMotion,
    desktopMatches,
    maxTranslate,
    scrollDistance,
    pinned = true,
  } = params;
  if (reducedMotion || maxTranslate <= 1 || scrollDistance <= 1) {
    return 'static';
  }
  // Inline (non-pinned) desktop uses the native snap carousel instead of the
  // scroll-jacked pin.
  return desktopMatches && pinned ? 'desktop' : 'snap';
}

/**
 * Map vertical scroll position to 0–1 horizontal progress.
 */
export function computeProgress(
  stickyTop: number,
  rectTop: number,
  scrollDistance: number
): number {
  if (scrollDistance <= 0) return 0;
  return clamp((stickyTop - rectTop) / scrollDistance, 0, 1);
}

/** Map a slide index to normalized horizontal progress (0–1). */
export function slideProgress(slideIndex: number, slideCount: number): number {
  if (slideCount <= 1) return 0;
  return clamp(slideIndex, 0, slideCount - 1) / (slideCount - 1);
}

/** Nearest slide index for a given scroll progress. */
export function getSlideIndexFromProgress(
  progress: number,
  slideCount: number
): number {
  if (slideCount <= 1) return 0;
  return Math.round(clamp(progress, 0, 1) * (slideCount - 1));
}

/** Vertical scroll delta needed to reach a target slide from the current progress. */
export function slideScrollDelta(
  currentProgress: number,
  targetIndex: number,
  slideCount: number,
  scrollDistance: number
): number {
  if (slideCount <= 1 || scrollDistance <= 0) return 0;

  const targetProgress = slideProgress(targetIndex, slideCount);
  return (targetProgress - clamp(currentProgress, 0, 1)) * scrollDistance;
}

/**
 * Whether a wheel event at the section boundary should scroll the page normally.
 */
export function shouldAllowWheelThrough(
  progress: number,
  direction: 1 | -1,
  slideCount: number
): boolean {
  if (slideCount <= 1) return true;

  const index = getSlideIndexFromProgress(progress, slideCount);
  const atFirst = index <= 0 && progress <= BOUNDARY_EPSILON;
  const atLast = index >= slideCount - 1 && progress >= 1 - BOUNDARY_EPSILON;

  return (direction < 0 && atFirst) || (direction > 0 && atLast);
}

/** Whether accumulated wheel delta has crossed the gesture threshold. */
export function getWheelGestureDirection(
  accumulatedDelta: number,
  threshold: number = GESTURE_THRESHOLD
): 1 | -1 | null {
  if (accumulatedDelta >= threshold) return 1;
  if (accumulatedDelta <= -threshold) return -1;
  return null;
}

/**
 * Resolve a completed touch swipe to a horizontal slide direction.
 * Returns null for vertical-dominant or sub-threshold swipes.
 */
export function getTouchSwipeDirection(
  deltaX: number,
  deltaY: number,
  threshold: number = GESTURE_THRESHOLD
): 1 | -1 | null {
  if (Math.abs(deltaX) <= Math.abs(deltaY)) return null;
  if (deltaX >= threshold) return 1;
  if (deltaX <= -threshold) return -1;
  return null;
}

/** Build the screen-reader announcement for the active slide. */
export function formatSlideAnnouncement(
  slideIndex: number,
  slideCount: number
): string {
  return `Slide ${slideIndex + 1} of ${slideCount}`;
}

/** Swipe hint presentation on mobile carousel. */
export type SwipeHintStyle = 'off' | 'cue' | 'label' | 'badge';

/** Whether the mobile swipe hint should be visible. */
export function shouldShowSwipeHint(params: {
  mode: HScrollMode;
  slideCount: number;
  currentIndex: number;
  dismissed: boolean;
  style: SwipeHintStyle;
}): boolean {
  const { mode, slideCount, currentIndex, dismissed, style } = params;

  if ('off' === style) {
    return false;
  }

  return (
    !dismissed &&
    mode === 'snap' &&
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

function setupHorizontalScroll(
  ref: HTMLElement,
  ctx: HScrollContext
): () => void {
  runtimes.get(ref)?.destroy();

  const viewport = ref.querySelector<HTMLElement>('.aa-hscroll__viewport');
  const track = ref.querySelector<HTMLElement>('.aa-hscroll__track');
  const progressEl = ref.querySelector<HTMLElement>('.aa-hscroll__progress');
  const liveRegion = ref.querySelector<HTMLElement>('.aa-hscroll__live-region');
  const swipeHintEl = ref.querySelector<HTMLElement>('.aa-hscroll__swipe-hint');

  if (!viewport || !track) return () => {};

  const desktopMql = window.matchMedia(DESKTOP_QUERY);
  const reducedMotionMql = window.matchMedia(REDUCED_MOTION_QUERY);
  const hadTabindex = ref.hasAttribute('tabindex');
  const originalTabindex = ref.getAttribute('tabindex');
  const watchedImages = new Set<HTMLImageElement>();
  const observedSlides = new Set<HTMLElement>();
  const mediaCleanups: Array<() => void> = [];

  let mode: HScrollMode = 'static';
  let slides: HTMLElement[] = [];
  let currentIndex = 0;
  let maxTranslate = 0;
  let scrollDistance = 0;
  let updateFrame = 0;
  let measureFrame = 0;
  let isNearViewport = true;
  let isDestroyed = false;
  let isAnimatingSlide = false;
  let slideAnimationTimer = 0;
  let scrollCommitTimer = 0;
  let wheelEnabled = false;
  let touchEnabled = false;
  let wheelAccum = 0;
  let wheelGestureLocked = false;
  let wheelIdleTimer = 0;
  let touchStartX = 0;
  let touchStartY = 0;
  let touchStartIndex = 0;
  let touchTracking = false;
  let touchGestureLocked = false;
  let touchIdleTimer = 0;
  let cachedStickyTop: number | null = null;
  let announcedIndex = -1;
  let swipeHintDismissed = false;
  const supportsScrollEnd = 'onscrollend' in window;

  const readSpeed = (): number =>
    resolveSpeed(
      Number(ctx.speed),
      parseFloat(
        window.getComputedStyle(ref).getPropertyValue('--aa-hscroll-speed')
      )
    );

  const enableTabstop = (): void => {
    if (!hadTabindex) {
      ref.setAttribute('tabindex', '0');
    }
  };

  const restoreTabstop = (): void => {
    if (hadTabindex && originalTabindex !== null) {
      ref.setAttribute('tabindex', originalTabindex);
      return;
    }

    if (!hadTabindex) {
      ref.removeAttribute('tabindex');
    }
  };

  const setProgress = (progress: number): void => {
    const nextProgress = Math.round(clamp(progress, 0, 1) * 100);
    if (ctx.progress !== nextProgress) {
      ctx.progress = nextProgress;
    }

    progressEl?.classList.toggle(
      'is-active',
      mode === 'desktop' && progress > 0.01 && progress < 0.99
    );
  };

  const setMode = (nextMode: HScrollMode): void => {
    mode = nextMode;

    MODE_CLASSES.forEach(className => ref.classList.remove(className));
    ref.classList.add('is-static');

    if (nextMode === 'desktop') {
      ref.classList.remove('is-static');
      ref.classList.add('is-horizontal', 'is-enhanced');
      ref.style.setProperty('--aa-hscroll-distance', `${scrollDistance}px`);
      viewport.scrollLeft = 0;
      enableTabstop();
      setWheelEnabled(Boolean(ctx.snapToNext));
      setTouchEnabled(false);
      return;
    }

    setWheelEnabled(false);

    ref.style.removeProperty('--aa-hscroll-distance');
    ref.style.removeProperty('--aa-hscroll-x');
    progressEl?.classList.remove('is-active');

    if (nextMode === 'snap') {
      ref.classList.remove('is-static');
      ref.classList.add('is-horizontal', 'is-snap');
      enableTabstop();
      setTouchEnabled(Boolean(ctx.snapToNext));
      updateSwipeHint();
      return;
    }

    setTouchEnabled(false);

    restoreTabstop();
    viewport.scrollLeft = 0;
    setProgress(0);
    updateSwipeHint();
  };

  const getStickyTop = (): number => {
    if (cachedStickyTop === null) {
      cachedStickyTop = parseFloat(window.getComputedStyle(viewport).top) || 0;
    }
    return cachedStickyTop;
  };

  const invalidateStickyTop = (): void => {
    cachedStickyTop = null;
  };

  const getScrollProgress = (): number =>
    computeProgress(
      getStickyTop(),
      ref.getBoundingClientRect().top,
      scrollDistance
    );

  const resetWheelGesture = (): void => {
    wheelAccum = 0;
    wheelGestureLocked = false;
    if (wheelIdleTimer) {
      window.clearTimeout(wheelIdleTimer);
      wheelIdleTimer = 0;
    }
  };

  const scheduleWheelGestureReset = (): void => {
    if (wheelIdleTimer) {
      window.clearTimeout(wheelIdleTimer);
    }

    wheelIdleTimer = window.setTimeout(
      resetWheelGesture,
      WHEEL_GESTURE_IDLE_MS
    );
  };

  const setActiveSlideIndex = (
    index: number,
    options: { announce?: boolean } = {}
  ): void => {
    if (slides.length === 0) return;

    const { announce = true } = options;
    const nextIndex = clamp(index, 0, slides.length - 1);
    currentIndex = nextIndex;

    slides.forEach((slide, slideIndex) => {
      slide.toggleAttribute('aria-hidden', slideIndex !== nextIndex);
    });

    updateSwipeHint();

    if (!announce || announcedIndex === nextIndex) return;

    announcedIndex = nextIndex;

    if (liveRegion) {
      liveRegion.textContent = formatSlideAnnouncement(
        nextIndex,
        slides.length
      );
    }
  };

  const updateSwipeHint = (): void => {
    const hintStyle = (ctx.swipeHintStyle ?? 'cue') as SwipeHintStyle;
    const shouldShow = shouldShowSwipeHint({
      mode,
      slideCount: slides.length,
      currentIndex,
      dismissed: swipeHintDismissed,
      style: hintStyle,
    });

    ref.classList.toggle('is-swipe-hint-visible', shouldShow);
    swipeHintEl?.toggleAttribute('hidden', !shouldShow);
  };

  const dismissSwipeHint = (): void => {
    if (swipeHintDismissed) return;

    swipeHintDismissed = true;
    updateSwipeHint();
  };

  const clearSlideAnimationFallback = (): void => {
    if (slideAnimationTimer) {
      window.clearTimeout(slideAnimationTimer);
      slideAnimationTimer = 0;
    }
  };

  const finishSlideAnimation = (): void => {
    clearSlideAnimationFallback();
    isAnimatingSlide = false;
  };

  const startSlideAnimationFallback = (): void => {
    clearSlideAnimationFallback();

    if (reducedMotionMql.matches) {
      finishSlideAnimation();
      return;
    }

    slideAnimationTimer = window.setTimeout(
      finishSlideAnimation,
      SLIDE_ANIMATION_FALLBACK_MS
    );
  };

  const setWheelEnabled = (enabled: boolean): void => {
    if (enabled === wheelEnabled) return;

    if (enabled) {
      ref.addEventListener('wheel', onWheel, { passive: false });
    } else {
      ref.removeEventListener('wheel', onWheel);
    }

    wheelEnabled = enabled;
  };

  const resetTouchGesture = (): void => {
    touchTracking = false;
    touchGestureLocked = false;
    if (touchIdleTimer) {
      window.clearTimeout(touchIdleTimer);
      touchIdleTimer = 0;
    }
  };

  const scheduleTouchGestureReset = (): void => {
    if (touchIdleTimer) {
      window.clearTimeout(touchIdleTimer);
    }

    touchIdleTimer = window.setTimeout(
      resetTouchGesture,
      TOUCH_GESTURE_IDLE_MS
    );
  };

  const setTouchEnabled = (enabled: boolean): void => {
    if (enabled === touchEnabled) return;

    if (enabled) {
      viewport.addEventListener('touchstart', onTouchStart, { passive: true });
      viewport.addEventListener('touchend', onTouchEnd, { passive: true });
      viewport.addEventListener('touchcancel', onTouchCancel, {
        passive: true,
      });
    } else {
      viewport.removeEventListener('touchstart', onTouchStart);
      viewport.removeEventListener('touchend', onTouchEnd);
      viewport.removeEventListener('touchcancel', onTouchCancel);
      resetTouchGesture();
    }

    touchEnabled = enabled;
  };

  const scrollToNativeSlide = (targetIndex: number): void => {
    if (slides.length === 0 || isAnimatingSlide) return;

    const target = clamp(targetIndex, 0, slides.length - 1);
    const targetLeft = getSlideLeft(slides[target]);

    if (Math.abs(viewport.scrollLeft - targetLeft) < 1) {
      setActiveSlideIndex(target, { announce: false });
      return;
    }

    isAnimatingSlide = true;
    resetTouchGesture();

    viewport.scrollTo({
      left: targetLeft,
      behavior: reducedMotionMql.matches ? 'auto' : 'smooth',
    });

    startSlideAnimationFallback();
  };

  const commitToNearestNativeSlide = (): void => {
    if (
      !ctx.snapToNext ||
      mode !== 'snap' ||
      isAnimatingSlide ||
      slides.length <= 1
    ) {
      return;
    }

    scrollToNativeSlide(getNearestSlideIndex());
  };

  const scrollToSlide = (targetIndex: number): void => {
    if (slides.length <= 1 || scrollDistance <= 0 || isAnimatingSlide) return;

    const maxIndex = slides.length - 1;
    const target = clamp(targetIndex, 0, maxIndex);
    const scrollDelta = slideScrollDelta(
      getScrollProgress(),
      target,
      slides.length,
      scrollDistance
    );

    if (Math.abs(scrollDelta) < 1) return;

    isAnimatingSlide = true;
    resetWheelGesture();

    window.scrollBy({
      top: scrollDelta,
      behavior: reducedMotionMql.matches ? 'auto' : 'smooth',
    });

    startSlideAnimationFallback();
  };

  const commitToNearestSlide = (): void => {
    if (
      !ctx.snapToNext ||
      mode !== 'desktop' ||
      isAnimatingSlide ||
      slides.length <= 1
    ) {
      return;
    }

    const progress = getScrollProgress();
    const nearestIndex = getSlideIndexFromProgress(progress, slides.length);
    const nearestProgress = slideProgress(nearestIndex, slides.length);

    if (Math.abs(progress - nearestProgress) > 0.02) {
      scrollToSlide(nearestIndex);
    }
  };

  const getSlideLeft = (slide: HTMLElement): number =>
    slide.offsetLeft - track.offsetLeft;

  const getNearestSlideIndex = (): number => {
    if (slides.length === 0) return 0;

    const left = viewport.scrollLeft;
    return slides.reduce(
      (nearestIndex, slide, index) =>
        Math.abs(getSlideLeft(slide) - left) <
        Math.abs(getSlideLeft(slides[nearestIndex]) - left)
          ? index
          : nearestIndex,
      0
    );
  };

  const updateSlideSemantics = (): void => {
    slides = getSlides(track);
    const total = slides.length;

    slides.forEach((slide, index) => {
      slide.setAttribute('role', 'group');
      slide.setAttribute('aria-roledescription', 'slide');
      slide.setAttribute('aria-label', `${index + 1} of ${total}`);
    });

    announcedIndex = -1;
    if (mode === 'desktop') {
      setActiveSlideIndex(
        getSlideIndexFromProgress(getScrollProgress(), slides.length),
        { announce: false }
      );
    } else if (mode === 'snap') {
      setActiveSlideIndex(getNearestSlideIndex(), { announce: false });
    } else if (total > 0) {
      setActiveSlideIndex(0, { announce: false });
    }
  };

  const resizeObserver =
    typeof ResizeObserver === 'undefined'
      ? null
      : new ResizeObserver(() => scheduleMeasure());

  const observeCurrentSlides = (): void => {
    if (!resizeObserver) return;

    const liveSlides = new Set(slides);
    observedSlides.forEach(slide => {
      if (!liveSlides.has(slide)) {
        resizeObserver.unobserve(slide);
        observedSlides.delete(slide);
      }
    });

    slides.forEach(slide => {
      if (observedSlides.has(slide)) return;

      resizeObserver.observe(slide);
      observedSlides.add(slide);
    });
  };

  const watchImages = (): void => {
    track.querySelectorAll('img').forEach(image => {
      if (watchedImages.has(image)) return;

      watchedImages.add(image);
      if (!image.complete) {
        image.addEventListener('load', scheduleMeasure);
        image.addEventListener('error', scheduleMeasure);
      }
    });
  };

  const updateNativeProgress = (): void => {
    if (mode !== 'snap') return;

    setActiveSlideIndex(getNearestSlideIndex(), { announce: false });
    setProgress(maxTranslate > 0 ? viewport.scrollLeft / maxTranslate : 0);
  };

  const update = (): void => {
    updateFrame = 0;
    if (isDestroyed || mode !== 'desktop') return;

    const progress = getScrollProgress();
    ref.style.setProperty(
      '--aa-hscroll-x',
      `${Math.round(-progress * maxTranslate * 100) / 100}px`
    );
    setActiveSlideIndex(getSlideIndexFromProgress(progress, slides.length), {
      announce: !isAnimatingSlide && !ctx.snapToNext,
    });
    setProgress(progress);
  };

  function scheduleUpdate(): void {
    if (updateFrame || isDestroyed) return;
    updateFrame = window.requestAnimationFrame(update);
  }

  const measure = (): void => {
    measureFrame = 0;
    if (isDestroyed) return;

    invalidateStickyTop();

    const wantsHorizontalLayout = !reducedMotionMql.matches;
    ref.classList.toggle('is-horizontal', wantsHorizontalLayout);

    updateSlideSemantics();
    observeCurrentSlides();
    watchImages();

    maxTranslate = wantsHorizontalLayout
      ? Math.max(0, track.scrollWidth - viewport.clientWidth)
      : 0;
    scrollDistance = Math.ceil(maxTranslate * readSpeed());

    const nextMode = pickMode({
      reducedMotion: reducedMotionMql.matches,
      desktopMatches: desktopMql.matches,
      maxTranslate,
      scrollDistance,
      pinned: ctx.desktopBehavior !== 'inline',
    });
    setMode(nextMode);

    if (nextMode === 'desktop') {
      update();
    } else if (nextMode === 'snap') {
      updateNativeProgress();
    }
  };

  function scheduleMeasure(): void {
    if (measureFrame || isDestroyed) return;
    measureFrame = window.requestAnimationFrame(measure);
  }

  const scrollToIndex = (index: number): void => {
    if (slides.length === 0) return;

    const target = clamp(index, 0, slides.length - 1);

    if (ctx.snapToNext && mode === 'snap') {
      scrollToNativeSlide(target);
      return;
    }

    viewport.scrollTo({
      left: getSlideLeft(slides[target]),
      behavior: reducedMotionMql.matches ? 'auto' : 'smooth',
    });
  };

  const onTouchStart = (event: TouchEvent): void => {
    if (
      mode !== 'snap' ||
      !ctx.snapToNext ||
      slides.length <= 1 ||
      isAnimatingSlide ||
      touchGestureLocked
    ) {
      return;
    }

    const touch = event.changedTouches[0] ?? event.touches[0];
    if (!touch) return;

    touchStartX = touch.clientX;
    touchStartY = touch.clientY;
    touchStartIndex = getNearestSlideIndex();
    touchTracking = true;
    dismissSwipeHint();
  };

  const onTouchCancel = (): void => {
    touchTracking = false;
  };

  const onTouchEnd = (event: TouchEvent): void => {
    if (
      !touchTracking ||
      mode !== 'snap' ||
      !ctx.snapToNext ||
      slides.length <= 1 ||
      isAnimatingSlide ||
      touchGestureLocked
    ) {
      touchTracking = false;
      return;
    }

    const touch = event.changedTouches[0];
    if (!touch) {
      touchTracking = false;
      return;
    }

    touchTracking = false;

    const deltaX = touchStartX - touch.clientX;
    const deltaY = touchStartY - touch.clientY;
    const swipeDirection = getTouchSwipeDirection(deltaX, deltaY);

    if (swipeDirection === null) {
      commitToNearestNativeSlide();
      return;
    }

    touchGestureLocked = true;
    scheduleTouchGestureReset();

    scrollToNativeSlide(touchStartIndex + swipeDirection);
  };

  const onScrollEnd = (): void => {
    if (isDestroyed) return;

    finishSlideAnimation();

    if (mode === 'desktop') {
      setActiveSlideIndex(
        getSlideIndexFromProgress(getScrollProgress(), slides.length),
        { announce: true }
      );
      return;
    }

    if (mode === 'snap') {
      if (ctx.snapToNext && !isAnimatingSlide) {
        const nearest = getNearestSlideIndex();
        const targetLeft = getSlideLeft(slides[nearest]);

        if (Math.abs(viewport.scrollLeft - targetLeft) > 2) {
          scrollToNativeSlide(nearest);
          return;
        }
      }

      setActiveSlideIndex(getNearestSlideIndex(), { announce: true });
      setProgress(maxTranslate > 0 ? viewport.scrollLeft / maxTranslate : 0);
    }
  };

  // Debounced "settle to the nearest slide once scrolling pauses" used by both
  // the pinned (window) and native (viewport) snap-to-next scroll handlers.
  const scheduleScrollCommit = (commit: () => void): void => {
    if (scrollCommitTimer) {
      window.clearTimeout(scrollCommitTimer);
    }

    scrollCommitTimer = window.setTimeout(() => {
      scrollCommitTimer = 0;
      commit();
    }, SCROLL_COMMIT_MS);
  };

  const onScroll = (): void => {
    if (mode !== 'desktop' || !isNearViewport) return;

    scheduleUpdate();

    if (!ctx.snapToNext || isAnimatingSlide) return;

    scheduleScrollCommit(commitToNearestSlide);
  };

  const onWheel = (event: WheelEvent): void => {
    if (
      mode !== 'desktop' ||
      !ctx.snapToNext ||
      !isNearViewport ||
      slides.length <= 1
    ) {
      return;
    }

    if (isAnimatingSlide) {
      event.preventDefault();
      return;
    }

    const progress = getScrollProgress();
    wheelAccum += event.deltaY;
    scheduleWheelGestureReset();

    const gestureDirection = getWheelGestureDirection(wheelAccum);

    if (gestureDirection !== null) {
      if (shouldAllowWheelThrough(progress, gestureDirection, slides.length)) {
        resetWheelGesture();
        return;
      }

      if (wheelGestureLocked) {
        event.preventDefault();
        return;
      }

      event.preventDefault();
      wheelGestureLocked = true;
      wheelAccum = 0;

      const currentSlide = getSlideIndexFromProgress(progress, slides.length);
      scrollToSlide(currentSlide + gestureDirection);
      return;
    }

    const tentativeDirection = event.deltaY > 0 ? 1 : -1;
    if (shouldAllowWheelThrough(progress, tentativeDirection, slides.length)) {
      resetWheelGesture();
      return;
    }

    event.preventDefault();
  };

  const onNativeScroll = (): void => {
    if (mode !== 'snap') return;

    const previousIndex = currentIndex;
    updateNativeProgress();

    if (currentIndex > 0 || previousIndex > 0) {
      dismissSwipeHint();
    }

    if (!ctx.snapToNext || isAnimatingSlide) return;

    scheduleScrollCommit(commitToNearestNativeSlide);
  };

  const onResize = (): void => scheduleMeasure();

  const onKeydown = (event: KeyboardEvent): void => {
    if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') return;
    if (isEditableTarget(event.target)) return;

    if (mode === 'desktop') {
      event.preventDefault();
      if (scrollDistance <= 0) return;

      const progress = getScrollProgress();
      const currentSlide = getSlideIndexFromProgress(progress, slides.length);
      const nextSlide = currentSlide + (event.key === 'ArrowRight' ? 1 : -1);

      if (ctx.snapToNext) {
        scrollToSlide(nextSlide);
        return;
      }

      const perSlide = scrollDistance / Math.max(slides.length - 1, 1);
      window.scrollBy({
        top: event.key === 'ArrowRight' ? perSlide : -perSlide,
        behavior: reducedMotionMql.matches ? 'auto' : 'smooth',
      });
      return;
    }

    if (mode === 'snap') {
      event.preventDefault();
      scrollToIndex(currentIndex + (event.key === 'ArrowRight' ? 1 : -1));
    }
  };

  const mutationObserver =
    typeof MutationObserver === 'undefined'
      ? null
      : new MutationObserver(() => {
          watchImages();
          scheduleMeasure();
        });

  const intersectionObserver =
    typeof IntersectionObserver === 'undefined'
      ? null
      : new IntersectionObserver(
          entries => {
            isNearViewport = entries.some(entry => entry.isIntersecting);
            if (isNearViewport) {
              scheduleUpdate();
            }
          },
          { rootMargin: '100% 0px' }
        );

  const destroy = (): void => {
    if (isDestroyed) return;

    isDestroyed = true;
    window.cancelAnimationFrame(updateFrame);
    window.cancelAnimationFrame(measureFrame);
    if (scrollCommitTimer) window.clearTimeout(scrollCommitTimer);
    if (touchIdleTimer) window.clearTimeout(touchIdleTimer);
    clearSlideAnimationFallback();
    resetWheelGesture();
    resetTouchGesture();
    setWheelEnabled(false);
    setTouchEnabled(false);
    window.removeEventListener('scroll', onScroll);
    if (supportsScrollEnd) {
      window.removeEventListener('scrollend', onScrollEnd);
    }
    window.removeEventListener('resize', onResize);
    viewport.removeEventListener('scroll', onNativeScroll);
    if (supportsScrollEnd) {
      viewport.removeEventListener('scrollend', onScrollEnd);
    }
    ref.removeEventListener('keydown', onKeydown);
    resizeObserver?.disconnect();
    mutationObserver?.disconnect();
    intersectionObserver?.disconnect();
    mediaCleanups.forEach(cleanup => cleanup());
    watchedImages.forEach(image => {
      image.removeEventListener('load', scheduleMeasure);
      image.removeEventListener('error', scheduleMeasure);
    });

    MODE_CLASSES.forEach(className => ref.classList.remove(className));
    ref.style.removeProperty('--aa-hscroll-distance');
    ref.style.removeProperty('--aa-hscroll-x');
    progressEl?.classList.remove('is-active');
    restoreTabstop();
    runtimes.delete(ref);
  };

  runtimes.set(ref, { destroy });

  resizeObserver?.observe(ref);
  resizeObserver?.observe(viewport);
  resizeObserver?.observe(track);
  mutationObserver?.observe(track, { childList: true, subtree: true });
  intersectionObserver?.observe(ref);
  mediaCleanups.push(
    addMediaChangeListener(desktopMql, scheduleMeasure),
    addMediaChangeListener(reducedMotionMql, scheduleMeasure)
  );

  window.addEventListener('scroll', onScroll, { passive: true });
  if (supportsScrollEnd) {
    window.addEventListener('scrollend', onScrollEnd);
  }
  viewport.addEventListener('scroll', onNativeScroll, { passive: true });
  if (supportsScrollEnd) {
    viewport.addEventListener('scrollend', onScrollEnd);
  }
  ref.addEventListener('keydown', onKeydown);
  window.addEventListener('resize', onResize, { passive: true });

  if (document.fonts) {
    document.fonts.ready.then(() => {
      if (!isDestroyed) {
        scheduleMeasure();
      }
    });
  }

  scheduleMeasure();

  return destroy;
}

store<HScrollStore>('aggressive-apparel/horizontal-scroll', {
  callbacks: {
    init() {
      const ctx = getContext<HScrollContext>();
      const { ref } = getElement();

      if (!(ref instanceof HTMLElement)) return;

      return setupHorizontalScroll(ref, ctx);
    },

    progressStyle() {
      const ctx = getContext<HScrollContext>();
      const progress = clamp(Number(ctx.progress) || 0, 0, 100);

      return `transform: scaleX(${progress / 100})`;
    },
  },
});
