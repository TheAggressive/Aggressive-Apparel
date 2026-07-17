/**
 * Horizontal-scroll runtime coordinator.
 *
 * Measurement, mode selection, and shared presentation live here. Each actual
 * scrolling model is isolated in its own controller module.
 */

import {
  addMediaChangeListener,
  buildSlideStops,
  clamp,
  computeScrollStart,
  formatSlideAnnouncement,
  getSlides,
  normalizeSnapBehavior,
  pickMode,
  progressToPercentage,
  resolveSpeed,
  resolveStepDurationMs,
  shouldShowSwipeHint,
  toLogicalSlideOffsets,
  toSignedTranslate,
  type HScrollMode,
  type SnapBehavior,
  type SwipeHintStyle,
} from './logic';
import {
  createController,
  type Controller,
  type ControllerElements,
  type Geometry,
  type Presentation,
} from './controllers';

export interface HScrollI18n {
  /** sprintf-style template for the live announcement, e.g. "Slide %1$s of %2$s". */
  slideAnnouncement?: string;
  /** sprintf-style template for each slide's aria-label, e.g. "%1$s of %2$s". */
  slideLabel?: string;
}

export interface HScrollContext {
  speed: number;
  progress: number;
  desktopBehavior?: 'pinned' | 'inline';
  snapBehavior?: SnapBehavior | 'proximity';
  /** Author step glide length in seconds (0.2–2). */
  stepDuration?: number;
  swipeHintStyle?: SwipeHintStyle;
  i18n?: HScrollI18n;
}

interface RuntimePresentation extends Presentation {
  setMode: (mode: HScrollMode) => void;
  setSlides: (slides: HTMLElement[]) => void;
}

const DESKTOP_QUERY = '(pointer: fine) and (min-width: 782px)';
const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)';

/**
 * Whether the browser supports CSS scroll-driven animations. When true the
 * runtime applies the compositor scrub animation (inline, see applyScrubTimeline)
 * on top of the JS baseline; the animation runs off the main thread for perfect
 * scroll sync. A static browser capability, evaluated once.
 */
const SUPPORTS_SCROLL_TIMELINE =
  typeof CSS !== 'undefined' &&
  typeof CSS.supports === 'function' &&
  CSS.supports('animation-timeline: scroll()');

/** Inline animation longhands that make up the compositor scrub timeline. */
const SCRUB_ANIMATION_PROPS = [
  'animation-name',
  'animation-timing-function',
  'animation-fill-mode',
  'animation-duration',
  'animation-timeline',
  'animation-range',
] as const;
const MODE_CLASSES = [
  'is-enhanced',
  'is-horizontal',
  'is-snap',
  'is-static',
  'is-paged',
];

const runtimes = new WeakMap<HTMLElement, () => void>();

/**
 * Apply (or clear) the compositor scroll-driven scrub animation on the track via
 * inline style. Kept out of the stylesheet on purpose: setting it here means the
 * CSS minifier never sees `animation-timeline` and so cannot fold it into the
 * `animation` shorthand (which would invalidate the whole declaration). Applied
 * only for pinned/paged modes on supporting browsers; otherwise the JS
 * `--aa-hscroll-x` baseline drives the transform.
 */
function applyScrubTimeline(
  track: HTMLElement,
  active: boolean,
  scrollStart: number,
  scrollDistance: number,
  maxTranslate: number,
  rtl: boolean
): void {
  if (!SUPPORTS_SCROLL_TIMELINE || !active) {
    track.style.removeProperty('--aa-hscroll-translate-end');
    SCRUB_ANIMATION_PROPS.forEach(prop => track.style.removeProperty(prop));
    return;
  }

  track.style.setProperty(
    '--aa-hscroll-translate-end',
    `${toSignedTranslate(maxTranslate, rtl)}px`
  );
  track.style.setProperty('animation-name', 'aa-hscroll-scrub');
  track.style.setProperty('animation-timing-function', 'linear');
  track.style.setProperty('animation-fill-mode', 'both');
  track.style.setProperty('animation-duration', 'auto');
  track.style.setProperty('animation-timeline', 'scroll(root block)');
  track.style.setProperty(
    'animation-range',
    `${scrollStart}px ${scrollStart + scrollDistance}px`
  );
}

function createPresentation(
  ref: HTMLElement,
  context: HScrollContext,
  progressElement: HTMLElement | null,
  liveRegion: HTMLElement | null,
  swipeHint: HTMLElement | null,
  prevButton: HTMLButtonElement | null,
  nextButton: HTMLButtonElement | null
): RuntimePresentation {
  let mode: HScrollMode = 'static';
  let slides: HTMLElement[] = [];
  let currentIndex = 0;
  let announcedIndex = -1;
  let swipeHintDismissed = false;
  /** Cached progress-bar active flag to avoid per-frame classList churn. */
  let progressActive: boolean | null = null;

  const updateSwipeHint = (): void => {
    const style = context.swipeHintStyle ?? 'cue';
    const visible = shouldShowSwipeHint({
      mode,
      slideCount: slides.length,
      currentIndex,
      dismissed: swipeHintDismissed,
      style,
    });

    ref.classList.toggle('is-swipe-hint-visible', visible);
    swipeHint?.toggleAttribute('hidden', !visible);
  };

  const syncControls = (index: number, slideCount: number): void => {
    const interactive = mode !== 'static' && slideCount > 1;
    if (prevButton) {
      prevButton.disabled = !interactive || index <= 0;
      prevButton.hidden = mode === 'static';
    }
    if (nextButton) {
      nextButton.disabled = !interactive || index >= slideCount - 1;
      nextButton.hidden = mode === 'static';
    }
  };

  /*
   * Deliberately no aria-hidden management here: in pinned/paged mode
   * several slides can be partially visible at once, and hiding focusable
   * content from assistive tech while it remains reachable is a WCAG
   * violation. Position is conveyed by each slide's aria-label plus the
   * polite live-region announcement instead.
   */

  return {
    getIndex: () => currentIndex,

    setMode(nextMode) {
      mode = nextMode;
      updateSwipeHint();
      syncControls(currentIndex, slides.length);
    },

    setSlides(nextSlides) {
      slides = nextSlides;
      currentIndex = clamp(currentIndex, 0, Math.max(0, slides.length - 1));
      announcedIndex = -1;

      slides.forEach((slide, index) => {
        slide.setAttribute('role', 'group');
        slide.setAttribute('aria-roledescription', 'slide');
        slide.setAttribute(
          'aria-label',
          formatSlideAnnouncement(
            index,
            slides.length,
            context.i18n?.slideLabel ?? '%1$s of %2$s'
          )
        );
      });

      updateSwipeHint();
      syncControls(currentIndex, slides.length);
    },

    setActive(index, options = {}) {
      if (slides.length === 0) return 0;

      const { announce = false } = options;
      const nextIndex = clamp(index, 0, slides.length - 1);

      // Fast path: called once per animation frame while scrolling, so skip
      // all DOM side effects when nothing observable changes.
      if (nextIndex !== currentIndex) {
        currentIndex = nextIndex;
        updateSwipeHint();
        syncControls(currentIndex, slides.length);
      }

      if (announce && announcedIndex !== currentIndex && liveRegion) {
        announcedIndex = currentIndex;
        liveRegion.textContent = formatSlideAnnouncement(
          currentIndex,
          slides.length,
          context.i18n?.slideAnnouncement
        );
      }

      return currentIndex;
    },

    setProgress(progress) {
      const bounded = clamp(progress, 0, 1);
      const nextProgress = progressToPercentage(bounded);
      const active =
        (mode === 'pinned' || mode === 'paged') &&
        bounded > 0.01 &&
        bounded < 0.99;

      if (context.progress !== nextProgress) {
        context.progress = nextProgress;
      }

      // Skip redundant classList work — called every animation frame while scrubbing.
      if (progressActive !== active) {
        progressActive = active;
        progressElement?.classList.toggle('is-active', active);
      }
    },

    dismissSwipeHint() {
      if (swipeHintDismissed) return;
      swipeHintDismissed = true;
      updateSwipeHint();
    },

    syncControls,
  };
}

export function setupHorizontalScroll(
  ref: HTMLElement,
  context: HScrollContext
): () => void {
  runtimes.get(ref)?.();

  const range = ref.querySelector<HTMLElement>('.aa-hscroll__range') ?? ref;
  const viewport = ref.querySelector<HTMLElement>('.aa-hscroll__viewport');
  const track = ref.querySelector<HTMLElement>('.aa-hscroll__track');
  const progressElement = ref.querySelector<HTMLElement>(
    '.aa-hscroll__progress'
  );
  const liveRegion = ref.querySelector<HTMLElement>('.aa-hscroll__live-region');
  const swipeHint = ref.querySelector<HTMLElement>('.aa-hscroll__swipe-hint');
  const prevButton = ref.querySelector<HTMLButtonElement>(
    '.aa-hscroll__control--prev'
  );
  const nextButton = ref.querySelector<HTMLButtonElement>(
    '.aa-hscroll__control--next'
  );

  if (!viewport || !track) return () => {};

  const elements: ControllerElements = { ref, viewport };
  const presentation = createPresentation(
    ref,
    context,
    progressElement,
    liveRegion,
    swipeHint,
    prevButton,
    nextButton
  );
  const desktopMedia = window.matchMedia(DESKTOP_QUERY);
  const reducedMotionMedia = window.matchMedia(REDUCED_MOTION_QUERY);
  const abortController = new AbortController();
  const mediaCleanups: Array<() => void> = [];
  const observedSlides = new Set<HTMLElement>();
  const hadTabindex = ref.hasAttribute('tabindex');
  const originalTabindex = ref.getAttribute('tabindex');

  let mode: HScrollMode | null = null;
  let controller: Controller | null = null;
  let geometry: Geometry | null = null;
  let measureFrame = 0;
  let destroyed = false;

  const restoreTabstop = (): void => {
    if (hadTabindex && originalTabindex !== null) {
      ref.setAttribute('tabindex', originalTabindex);
    } else if (!hadTabindex) {
      ref.removeAttribute('tabindex');
    }
  };

  const applyMode = (nextMode: HScrollMode, scrollDistance: number): void => {
    // Always reconcile the full class set: measure() adds a temporary
    // is-horizontal class before reading layout, so a change-only reset
    // would leak it into static mode on the second measure of a resize.
    MODE_CLASSES.forEach(className => ref.classList.remove(className));

    if (nextMode === 'static') {
      ref.classList.add('is-static');
    } else if (nextMode === 'native') {
      ref.classList.add('is-horizontal', 'is-snap');
    } else if (nextMode === 'paged') {
      ref.classList.add('is-horizontal', 'is-enhanced', 'is-paged');
    } else {
      ref.classList.add('is-horizontal', 'is-enhanced');
    }

    if (nextMode === 'pinned' || nextMode === 'paged') {
      ref.style.setProperty('--aa-hscroll-distance', `${scrollDistance}px`);
    } else {
      ref.style.removeProperty('--aa-hscroll-distance');
    }

    if (nextMode === 'static') {
      restoreTabstop();
    } else if (!hadTabindex) {
      ref.setAttribute('tabindex', '0');
    }
  };

  const resizeObserver =
    typeof ResizeObserver === 'undefined'
      ? null
      : new ResizeObserver(() => scheduleMeasure());

  const observeSlides = (slides: HTMLElement[]): void => {
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

  const measure = (): void => {
    measureFrame = 0;
    if (destroyed) return;

    if (!reducedMotionMedia.matches) {
      ref.classList.add('is-horizontal');
    }

    const slides = getSlides(track);
    const maxTranslate = reducedMotionMedia.matches
      ? 0
      : Math.max(0, track.scrollWidth - viewport.clientWidth);
    const speed = resolveSpeed(
      Number(context.speed),
      parseFloat(
        window.getComputedStyle(ref).getPropertyValue('--aa-hscroll-speed')
      )
    );
    const scrollDistance = Math.ceil(maxTranslate * speed);
    const snapBehavior = normalizeSnapBehavior(context.snapBehavior);
    const nextMode = pickMode({
      reducedMotion: reducedMotionMedia.matches,
      desktopMatches: desktopMedia.matches,
      maxTranslate,
      pinned: context.desktopBehavior !== 'inline',
      snapBehavior,
    });

    applyMode(nextMode, scrollDistance);

    const stickyTop =
      nextMode === 'pinned' || nextMode === 'paged'
        ? parseFloat(window.getComputedStyle(viewport).top) || 0
        : 0;
    const scrollStart = computeScrollStart(
      window.scrollY + range.getBoundingClientRect().top,
      stickyTop
    );
    const rtl = window.getComputedStyle(ref).direction === 'rtl';
    const logicalOffsets = toLogicalSlideOffsets({
      offsets: slides.map(slide => slide.offsetLeft - track.offsetLeft),
      sizes: slides.map(slide => slide.offsetWidth),
      trackSize: track.scrollWidth,
      rtl,
    });
    const slideStops = buildSlideStops(logicalOffsets, maxTranslate);
    const stepDurationMs = resolveStepDurationMs(context.stepDuration);

    geometry = {
      slides,
      slideStops,
      maxTranslate,
      scrollDistance,
      scrollStart,
      rtl,
      stepDurationMs,
    };

    // Compositor scrub is continuous — only for free scrub mode. Directional
    // snap paints discrete stops via JS so there is no free play between slides.
    applyScrubTimeline(
      track,
      nextMode === 'pinned',
      scrollStart,
      scrollDistance,
      maxTranslate,
      rtl
    );

    presentation.setSlides(slides);
    presentation.setMode(nextMode);
    observeSlides(slides);

    if (mode !== nextMode) {
      controller?.destroy();
      mode = nextMode;
      controller = createController(nextMode, elements, presentation, geometry);
    } else {
      controller?.updateGeometry(geometry);
    }
  };

  function scheduleMeasure(): void {
    if (measureFrame || destroyed) return;
    measureFrame = window.requestAnimationFrame(measure);
  }

  const mutationObserver =
    typeof MutationObserver === 'undefined'
      ? null
      : new MutationObserver(scheduleMeasure);

  resizeObserver?.observe(viewport);
  resizeObserver?.observe(track);
  // Content above the block (lazy images, embeds, toggled sections) shifts the
  // block's document position and would leave scrollStart stale. Observing the
  // body catches those layout changes; the re-measure is rAF-batched and
  // settles immediately when nothing actually changed.
  resizeObserver?.observe(document.body);
  mutationObserver?.observe(track, { childList: true });
  mediaCleanups.push(
    addMediaChangeListener(desktopMedia, scheduleMeasure),
    addMediaChangeListener(reducedMotionMedia, scheduleMeasure)
  );

  window.addEventListener('resize', scheduleMeasure, {
    passive: true,
    signal: abortController.signal,
  });

  /**
   * Reveal prev/next only for Tab / focus-visible keyboard users — not for
   * Arrow Up/Down slide paging (those keys must work without showing chrome).
   */
  const setKeyboardChrome = (on: boolean): void => {
    if (on) {
      ref.dataset.aaHscrollKeyboard = '';
    } else {
      delete ref.dataset.aaHscrollKeyboard;
    }
  };

  // Window capture so Arrow Up/Down page slides while the gallery owns the
  // scroll range — no Tab-focus required (matches wheel ownership).
  window.addEventListener(
    'keydown',
    event => {
      if (!controller) return;

      // Tab into the gallery → show controls. Arrow paging stays chrome-free.
      if (
        event.key === 'Tab' &&
        !event.altKey &&
        !event.metaKey &&
        !event.ctrlKey
      ) {
        window.requestAnimationFrame(() => {
          if (ref.contains(document.activeElement)) {
            setKeyboardChrome(true);
          }
        });
      }

      if (controller.keydown(event)) {
        event.preventDefault();
      }
    },
    { capture: true, signal: abortController.signal }
  );

  window.addEventListener(
    'pointerdown',
    event => {
      if (event.pointerType !== 'mouse' && event.pointerType !== 'pen') return;
      if (event.target instanceof Node && ref.contains(event.target)) return;
      setKeyboardChrome(false);
    },
    { capture: true, signal: abortController.signal }
  );

  const onControlClick = (direction: 1 | -1): void => {
    if (!controller) return;
    const next = presentation.getIndex() + direction;
    if (controller.goToIndex(next)) {
      presentation.dismissSwipeHint();
    }
  };

  prevButton?.addEventListener('click', () => onControlClick(-1), {
    signal: abortController.signal,
  });
  nextButton?.addEventListener('click', () => onControlClick(1), {
    signal: abortController.signal,
  });

  // Keyboard/AT users can Tab into content on a not-yet-visible slide. The
  // browser then auto-scrolls the overflow:hidden viewport to reveal it,
  // which visually corrupts the transform-driven track. Undo that native
  // scroll and jump the *document* to the slide's stop instead, so focus and
  // the pinned animation stay in agreement.
  ref.addEventListener(
    'focusin',
    event => {
      if (
        event.target instanceof HTMLElement &&
        event.target.matches(':focus-visible')
      ) {
        setKeyboardChrome(true);
      }

      if (
        (mode !== 'pinned' && mode !== 'paged') ||
        !geometry ||
        !(event.target instanceof HTMLElement)
      ) {
        return;
      }

      viewport.scrollLeft = 0;

      const target = event.target;
      const slideIndex = geometry.slides.findIndex(slide =>
        slide.contains(target)
      );
      if (slideIndex < 0) return;

      const top =
        geometry.scrollStart +
        (geometry.slideStops[slideIndex] ?? 0) * geometry.scrollDistance;

      if (Math.abs(window.scrollY - top) > 1) {
        window.scrollTo({ top, behavior: 'auto' });
      }
    },
    { signal: abortController.signal }
  );

  if (document.fonts) {
    document.fonts.ready.then(() => {
      if (!destroyed) scheduleMeasure();
    });
  }

  const destroy = (): void => {
    if (destroyed) return;
    destroyed = true;

    window.cancelAnimationFrame(measureFrame);
    abortController.abort();
    controller?.destroy();
    resizeObserver?.disconnect();
    mutationObserver?.disconnect();
    mediaCleanups.forEach(cleanup => cleanup());
    MODE_CLASSES.forEach(className => ref.classList.remove(className));
    ref.style.removeProperty('--aa-hscroll-distance');
    ref.style.removeProperty('--aa-hscroll-x');
    applyScrubTimeline(track, false, 0, 0, 0, false);
    progressElement?.classList.remove('is-active');
    restoreTabstop();
    delete ref.dataset.aaHscrollKeyboard;
    runtimes.delete(ref);
  };

  runtimes.set(ref, destroy);
  scheduleMeasure();

  return destroy;
}
