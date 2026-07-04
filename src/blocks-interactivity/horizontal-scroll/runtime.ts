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
  PAGED_COMMIT_RATIO,
  pickMode,
  progressToPercentage,
  resolveSpeed,
  shouldShowSwipeHint,
  toLogicalSlideOffsets,
  type HScrollMode,
  type SnapBehavior,
  type SnapStrength,
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
  snapBehavior?: SnapBehavior;
  snapStrength?: SnapStrength;
  /** Paged-mode commit sensitivity as a ratio of the gap to the adjacent slide. */
  commitRatio?: number;
  swipeHintStyle?: SwipeHintStyle;
  i18n?: HScrollI18n;
}

interface RuntimePresentation extends Presentation {
  setMode: (mode: HScrollMode) => void;
  setSlides: (slides: HTMLElement[]) => void;
}

const DESKTOP_QUERY = '(pointer: fine) and (min-width: 782px)';
const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)';
const MODE_CLASSES = [
  'is-enhanced',
  'is-horizontal',
  'is-snap',
  'is-static',
  'is-paged',
];

const runtimes = new WeakMap<HTMLElement, () => void>();

function createPresentation(
  ref: HTMLElement,
  context: HScrollContext,
  progressElement: HTMLElement | null,
  liveRegion: HTMLElement | null,
  swipeHint: HTMLElement | null
): RuntimePresentation {
  let mode: HScrollMode = 'static';
  let slides: HTMLElement[] = [];
  let currentIndex = 0;
  let announcedIndex = -1;
  let swipeHintDismissed = false;

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

      if (context.progress !== nextProgress) {
        context.progress = nextProgress;
      }

      progressElement?.classList.toggle(
        'is-active',
        (mode === 'pinned' || mode === 'paged') &&
          bounded > 0.01 &&
          bounded < 0.99
      );
    },

    dismissSwipeHint() {
      if (swipeHintDismissed) return;
      swipeHintDismissed = true;
      updateSwipeHint();
    },
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

  if (!viewport || !track) return () => {};

  const elements: ControllerElements = { ref, viewport };
  const presentation = createPresentation(
    ref,
    context,
    progressElement,
    liveRegion,
    swipeHint
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
    const nextMode = pickMode({
      reducedMotion: reducedMotionMedia.matches,
      desktopMatches: desktopMedia.matches,
      maxTranslate,
      pinned: context.desktopBehavior !== 'inline',
      snapBehavior: context.snapBehavior ?? 'paged',
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

    geometry = {
      slides,
      slideStops,
      maxTranslate,
      scrollDistance,
      scrollStart,
      rtl,
    };

    presentation.setSlides(slides);
    presentation.setMode(nextMode);
    observeSlides(slides);

    if (mode !== nextMode) {
      controller?.destroy();
      mode = nextMode;
      controller = createController(
        nextMode,
        elements,
        presentation,
        geometry,
        {
          snapBehavior: context.snapBehavior ?? 'paged',
          snapStrength: context.snapStrength ?? 'medium',
          commitRatio: context.commitRatio ?? PAGED_COMMIT_RATIO,
        }
      );
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
  ref.addEventListener(
    'keydown',
    event => {
      if (controller?.keydown(event)) event.preventDefault();
    },
    { signal: abortController.signal }
  );

  // Keyboard/AT users can Tab into content on a not-yet-visible slide. The
  // browser then auto-scrolls the overflow:hidden viewport to reveal it,
  // which visually corrupts the transform-driven track. Undo that native
  // scroll and jump the *document* to the slide's stop instead, so focus and
  // the pinned animation stay in agreement.
  ref.addEventListener(
    'focusin',
    event => {
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
    progressElement?.classList.remove('is-active');
    restoreTabstop();
    runtimes.delete(ref);
  };

  runtimes.set(ref, destroy);
  scheduleMeasure();

  return destroy;
}
