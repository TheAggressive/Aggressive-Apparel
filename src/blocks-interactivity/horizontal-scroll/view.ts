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

const runtimes = new WeakMap<HTMLElement, HScrollRuntime>();

function clamp(value: number, min: number, max: number): number {
  return Math.max(min, Math.min(max, value));
}

function getSlides(track: HTMLElement): HTMLElement[] {
  return Array.from(track.children).filter(
    (child): child is HTMLElement => child instanceof HTMLElement
  );
}

function isEditableTarget(target: EventTarget | null): boolean {
  if (!(target instanceof HTMLElement)) return false;

  const tagName = target.tagName.toLowerCase();
  return (
    target.isContentEditable ||
    tagName === 'input' ||
    tagName === 'select' ||
    tagName === 'textarea'
  );
}

function addMediaChangeListener(
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

  const readSpeed = (): number => {
    const contextSpeed = Number(ctx.speed);
    const cssSpeed = parseFloat(
      window.getComputedStyle(ref).getPropertyValue('--aa-hscroll-speed')
    );

    return clamp(
      Number.isFinite(contextSpeed) && contextSpeed > 0
        ? contextSpeed
        : cssSpeed || 1,
      0.5,
      3
    );
  };

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
      return;
    }

    ref.style.removeProperty('--aa-hscroll-distance');
    ref.style.removeProperty('--aa-hscroll-x');
    progressEl?.classList.remove('is-active');

    if (nextMode === 'snap') {
      ref.classList.remove('is-static');
      ref.classList.add('is-horizontal', 'is-snap');
      enableTabstop();
      return;
    }

    restoreTabstop();
    viewport.scrollLeft = 0;
    setProgress(0);
  };

  const getStickyTop = (): number =>
    parseFloat(window.getComputedStyle(viewport).top) || 0;

  const getScrollProgress = (): number => {
    if (scrollDistance <= 0) return 0;

    const rect = ref.getBoundingClientRect();
    return clamp((getStickyTop() - rect.top) / scrollDistance, 0, 1);
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

    currentIndex = getNearestSlideIndex();
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
    currentIndex = Math.round(progress * Math.max(slides.length - 1, 0));
    setProgress(progress);
  };

  function scheduleUpdate(): void {
    if (updateFrame || isDestroyed) return;
    updateFrame = window.requestAnimationFrame(update);
  }

  const measure = (): void => {
    measureFrame = 0;
    if (isDestroyed) return;

    const wantsHorizontalLayout = !reducedMotionMql.matches;
    ref.classList.toggle('is-horizontal', wantsHorizontalLayout);

    updateSlideSemantics();
    observeCurrentSlides();
    watchImages();

    maxTranslate = wantsHorizontalLayout
      ? Math.max(0, track.scrollWidth - viewport.clientWidth)
      : 0;
    scrollDistance = Math.ceil(maxTranslate * readSpeed());

    if (reducedMotionMql.matches || maxTranslate <= 1 || scrollDistance <= 1) {
      setMode('static');
      return;
    }

    setMode(desktopMql.matches ? 'desktop' : 'snap');

    if (mode === 'desktop') {
      update();
    } else {
      updateNativeProgress();
    }
  };

  function scheduleMeasure(): void {
    if (measureFrame || isDestroyed) return;
    measureFrame = window.requestAnimationFrame(measure);
  }

  const scrollToIndex = (index: number): void => {
    if (slides.length === 0) return;

    currentIndex = clamp(index, 0, slides.length - 1);
    viewport.scrollTo({
      left: getSlideLeft(slides[currentIndex]),
      behavior: reducedMotionMql.matches ? 'auto' : 'smooth',
    });
  };

  const onScroll = (): void => {
    if (mode !== 'desktop' || !isNearViewport) return;
    scheduleUpdate();
  };

  const onNativeScroll = (): void => {
    if (mode !== 'snap') return;
    updateNativeProgress();
  };

  const onResize = (): void => scheduleMeasure();

  const onKeydown = (event: KeyboardEvent): void => {
    if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') return;
    if (isEditableTarget(event.target)) return;

    if (mode === 'desktop') {
      event.preventDefault();
      if (scrollDistance <= 0) return;

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
    window.removeEventListener('scroll', onScroll);
    window.removeEventListener('resize', onResize);
    viewport.removeEventListener('scroll', onNativeScroll);
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
  window.addEventListener('resize', onResize, { passive: true });
  viewport.addEventListener('scroll', onNativeScroll, { passive: true });
  ref.addEventListener('keydown', onKeydown);

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
