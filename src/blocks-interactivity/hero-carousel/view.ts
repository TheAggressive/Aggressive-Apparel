/**
 * Hero Carousel — Interactivity API store + engine.
 *
 * Slides are Cover blocks. Three transition modes share one `activeIndex`:
 *   - `slide`     drives a native scroll-snap track (touch momentum, drag).
 *   - `fade`      cross-fades absolutely-stacked slides.
 *   - `crossfade` fades the stack with an added scale-in on entry.
 *
 * State getters own the reactive chrome (aria-current, is-active, inert). A
 * per-root controller (kept in a WeakMap) owns the imperative concerns that
 * don't map cleanly to directives: scroll↔index sync, autoplay, seamless
 * looping, background-motion focal origins, deep linking, and the public
 * `aa:slide-change` event.
 *
 * @package Aggressive_Apparel
 */

import {
  store,
  getContext,
  getElement,
  withSyncEvent,
} from '@wordpress/interactivity';

import {
  announceSlide,
  focusSlide,
  isSlideFocused,
  syncDeepLinkHash,
} from './a11y';
import { AutoplayEngine } from './autoplay';
import {
  CLONE_ATTR,
  COVER_BG_SELECTOR,
  ROOT_SELECTOR,
  SCROLL_IDLE_MS,
  SLIDE_SELECTOR,
  TRACK_SELECTOR,
  VISIBILITY_THRESHOLD,
} from './constants';
import {
  activeIndexFromScroll,
  clampIndex,
  focalToTransformOrigin,
  isLoopWrap,
  logicalToPhysical,
  nextIndex,
  parseSlideHash,
  physicalToLogical,
  prevIndex,
  scrollLeftForIndex,
  usesSeamlessLoop,
} from './logic';
import { clearMotionHolds, holdMotion, releaseMotion } from './motion-hold';
import { prefersReducedData, prefersReducedMotion } from './prefs';
import {
  clearSettle,
  mountEdgeClones,
  ProgrammaticScrollGuard,
  removeEdgeClones,
  setScrollSnap,
  snapFromClone,
  startSeamlessWrap,
  type SeamlessWrapHost,
} from './seamless';
import type {
  InteractivityActions,
  InteractivityCallbacks,
} from '../../../types/interactivity-shared';

interface HeroContext {
  activeIndex: number;
  /**
   * Index reflected in dots / fraction. During a seamless wrap this advances
   * to the destination while `activeIndex` stays on the outgoing slide until
   * the clone→real snap completes.
   */
  displayIndex: number;
  slideIndex: number;
  isPlaying: boolean;
  /** True while autoplay is held (hover, focus, recent interaction). */
  isPaused: boolean;
  autoplay: boolean;
  autoplaySpeed: number;
  loop: boolean;
  pauseOnHover: boolean;
  count: number;
  transition: 'slide' | 'fade' | 'crossfade';
  /** Reflect the active slide in the URL hash and honor it on load. */
  deepLink: boolean;
  i18n: {
    play: string;
    pause: string;
    /** sprintf-style template: %1$s = slide number, %2$s = count. */
    slide: string;
  };
}

/**
 * Per-carousel imperative controller. One instance per root element,
 * created in `callbacks.init` and looked up by actions via the WeakMap.
 */
class HeroController {
  private readonly track: HTMLElement | null;
  private readonly slides: HTMLElement[];
  private readonly announcer: HTMLElement | null;
  private readonly io: IntersectionObserver;
  private readonly autoplay: AutoplayEngine;
  private readonly scrollGuard = new ProgrammaticScrollGuard();
  private resizeObserver: ResizeObserver | null = null;
  private layoutTimer = 0;
  private wrapCancel: (() => void) | null = null;
  private isVisible = true;
  private syncingFromScroll = false;
  private wrapping = false;
  private readonly isRtl: boolean;
  private readonly seamless: boolean;
  private lastEmitted: number;
  private lastTrackWidth = 0;
  /**
   * When true, the next committed index change moves focus to the new active
   * slide. Set when navigation starts while a slide already has focus (keyboard
   * arrows / Home / End) so inert/tabindex updates don't drop the user.
   */
  private retainSlideFocus = false;

  constructor(
    private readonly root: HTMLElement,
    private readonly ctx: HeroContext
  ) {
    this.track = root.querySelector<HTMLElement>(TRACK_SELECTOR);
    this.slides = Array.from(
      root.querySelectorAll<HTMLElement>(
        `${SLIDE_SELECTOR}:not([${CLONE_ATTR}])`
      )
    );
    this.announcer = root.querySelector<HTMLElement>('.aa-hero__live');
    this.isRtl = getComputedStyle(root).direction === 'rtl';
    this.seamless = usesSeamlessLoop(
      this.ctx.transition,
      this.ctx.loop,
      this.ctx.count
    );
    this.lastEmitted = ctx.activeIndex;
    if (typeof this.ctx.displayIndex !== 'number') {
      this.ctx.displayIndex = this.ctx.activeIndex;
    }

    const self = this;
    this.autoplay = new AutoplayEngine({
      root: this.root,
      get autoplay() {
        return ctx.autoplay;
      },
      get autoplaySpeed() {
        return ctx.autoplaySpeed;
      },
      get count() {
        return ctx.count;
      },
      get loop() {
        return ctx.loop;
      },
      get activeIndex() {
        return ctx.activeIndex;
      },
      get isVisible() {
        return self.isVisible;
      },
      get wrapping() {
        return self.wrapping;
      },
      get isPlaying() {
        return ctx.isPlaying;
      },
      set isPlaying(value: boolean) {
        ctx.isPlaying = value;
      },
      get isPaused() {
        return ctx.isPaused;
      },
      set isPaused(value: boolean) {
        ctx.isPaused = value;
      },
      onTick: () => self.next(true),
    });

    this.applyFocalOrigins();
    this.setupSeamlessLoop();
    this.observeLayout();

    if (this.ctx.transition === 'slide' && this.track) {
      this.track.addEventListener('scroll', this.onScroll, { passive: true });
    }

    this.io = new IntersectionObserver(
      entries => {
        for (const entry of entries) {
          this.isVisible = entry.isIntersecting;
          this.autoplay.reconcile();
        }
      },
      { threshold: VISIBILITY_THRESHOLD }
    );
    this.io.observe(root);

    document.addEventListener('visibilitychange', this.onVisibilityChange);

    if (this.ctx.deepLink && this.root.id) {
      const initial = parseSlideHash(
        window.location.hash,
        this.root.id,
        this.ctx.count
      );
      if (initial !== null && initial !== this.ctx.activeIndex) {
        this.goTo(initial, true);
      }
      window.addEventListener('hashchange', this.onHashChange);
    }

    this.autoplay.reconcile();
  }

  private seamlessHost(): SeamlessWrapHost | null {
    if (!this.track) return null;
    return {
      track: this.track,
      slides: this.slides,
      count: this.ctx.count,
      isRtl: this.isRtl,
      setDisplayIndex: index => {
        this.ctx.displayIndex = index;
      },
      setIndices: index => this.setIndices(index),
      emitChange: index => this.emitChange(index),
      onWrapStart: () => {
        this.wrapping = true;
        this.autoplay.reconcile();
      },
      onWrapEnd: () => {
        this.wrapping = false;
        this.autoplay.reconcile();
      },
      scrollGuard: this.scrollGuard,
    };
  }

  /** Keep the track scrolled to the active real slide after layout changes. */
  private observeLayout(): void {
    if (
      !this.seamless ||
      !this.track ||
      typeof ResizeObserver === 'undefined'
    ) {
      return;
    }
    this.lastTrackWidth = this.track.clientWidth;
    this.resizeObserver = new ResizeObserver(() => {
      window.clearTimeout(this.layoutTimer);
      this.layoutTimer = window.setTimeout(() => this.syncScrollToActive(), 50);
    });
    this.resizeObserver.observe(this.track);
  }

  private syncScrollToActive(): void {
    if (!this.track || this.wrapping || this.ctx.transition !== 'slide') return;
    const width = this.track.clientWidth || 1;
    if (width === this.lastTrackWidth) return;
    this.lastTrackWidth = width;
    const physical = this.seamless
      ? logicalToPhysical(this.ctx.activeIndex, this.ctx.count)
      : this.ctx.activeIndex;
    this.scrollGuard.begin();
    this.track.scrollTo({
      left: scrollLeftForIndex(physical, width, this.isRtl),
      behavior: 'auto',
    });
  }

  /**
   * Clone the last/first real slides onto the track edges so wrapping can
   * animate forward/backward without reversing through the strip.
   */
  private setupSeamlessLoop(): void {
    if (!this.seamless || !this.track || this.slides.length < 2) return;

    const width = mountEdgeClones(this.track, this.slides);
    this.lastTrackWidth = width;

    const align = (): void => {
      if (!this.track) return;
      this.lastTrackWidth = this.track.clientWidth || 1;
      this.scrollGuard.begin();
      this.track.scrollTo({
        left: scrollLeftForIndex(1, this.lastTrackWidth, this.isRtl),
        behavior: 'auto',
      });
    };
    align();
    requestAnimationFrame(align);
  }

  /** Background motion should pan around the Cover's focal point, not the center. */
  private applyFocalOrigins(): void {
    for (const slide of this.slides) {
      const bg = slide.querySelector<HTMLElement>(COVER_BG_SELECTOR);
      const objectPosition = bg ? getComputedStyle(bg).objectPosition : null;
      slide.style.setProperty(
        '--aa-hero-motion-origin',
        focalToTransformOrigin(objectPosition)
      );
    }
  }

  private onVisibilityChange = (): void => {
    this.autoplay.reconcile();
  };

  private onHashChange = (): void => {
    if (!this.ctx.deepLink || !this.root.id || this.wrapping) return;
    const index = parseSlideHash(
      window.location.hash,
      this.root.id,
      this.ctx.count
    );
    if (index !== null && index !== this.ctx.activeIndex) {
      this.goTo(index);
    }
  };

  /** Map a manual scroll back to the active index without re-scrolling. */
  private onScroll = (): void => {
    // Wrap scrolls are engine-driven; still debounce so we don't thrash, but
    // the guard marks them programmatic so autoplay is not paused.
    this.scrollGuard.consumeAfterIdle(wasProgrammatic => {
      if (!this.track || this.wrapping) return;

      const width = this.track.clientWidth || 1;
      const physical = activeIndexFromScroll(
        this.track.scrollLeft,
        width,
        this.seamless ? this.ctx.count + 2 : this.ctx.count
      );

      if (this.seamless) {
        if (physical === 0 || physical === this.ctx.count + 1) {
          // User (or residual momentum) landed on a clone — snap to the real slide.
          const host = this.seamlessHost();
          if (host) snapFromClone(host, physical);
          return;
        }
      }

      const index = this.seamless
        ? physicalToLogical(physical, this.ctx.count)
        : physical;

      const changed = index !== this.ctx.activeIndex;
      if (changed) {
        this.syncingFromScroll = true;
        this.setIndices(index);
        this.emitChange(index);
        this.syncingFromScroll = false;
      }

      // Only pause for a real user swipe that changed the slide. Autoplay's
      // own scrollTo, snap micro-adjustments, and no-op settles must not
      // toggle is-playing — that restarts the progress pill mid-fill.
      if (!wasProgrammatic && changed) {
        this.autoplay.deferResume();
      }
    }, SCROLL_IDLE_MS);
  };

  /** True when keyboard focus is currently on a real slide (not chrome). */
  private shouldRetainSlideFocus(): boolean {
    return isSlideFocused(this.root);
  }

  /**
   * After Interactivity applies inert/tabindex for the new active slide, move
   * focus there so arrow/Home/End navigation stays on the carousel.
   */
  private focusActiveSlide(): void {
    focusSlide(this.slides[this.ctx.activeIndex]);
  }

  private setIndices(index: number): void {
    const previous = this.ctx.activeIndex;
    if (previous === index) {
      this.retainSlideFocus = false;
      this.ctx.activeIndex = index;
      this.ctx.displayIndex = index;
      return;
    }

    const outgoing = this.slides[previous];
    const incoming = this.slides[index];
    // Pin media at the live Ken Burns frame before `.is-active` drops —
    // otherwise the animation fill is cleared and the image snaps back.
    if (outgoing) {
      holdMotion(outgoing);
    }
    if (incoming) {
      releaseMotion(incoming);
    }
    this.ctx.activeIndex = index;
    this.ctx.displayIndex = index;
    if (this.retainSlideFocus) {
      this.retainSlideFocus = false;
      this.focusActiveSlide();
    }
  }

  /**
   * Move to an index. Slide mode scrolls the track (with edge clones when
   * looping). Fade/crossfade rely on `is-active` opacity — wrapping is
   * already continuous there, so the index updates immediately.
   */
  goTo(index: number, instant = false): void {
    if (this.wrapping) return;

    // Capture before index/inert changes blur the outgoing slide.
    this.retainSlideFocus = this.shouldRetainSlideFocus();

    const from = this.ctx.activeIndex;
    const target = clampIndex(index, this.ctx.count);
    const reduce = instant || prefersReducedMotion();
    const wrapping =
      this.seamless && isLoopWrap(from, target, this.ctx.count) && !reduce;

    if (
      this.ctx.transition === 'slide' &&
      this.track &&
      !this.syncingFromScroll
    ) {
      this.goToSlide(from, target, reduce, wrapping);
      return;
    }

    clearSettle(this.slides);
    this.setIndices(target);
    this.emitChange(target);
  }

  /** Slide-mode navigation, including seamless wrap via edge clones. */
  private goToSlide(
    from: number,
    target: number,
    reduce: boolean,
    wrapping: boolean
  ): void {
    if (!this.track) return;

    const width = this.track.clientWidth || 1;
    const realPhysical = this.seamless
      ? logicalToPhysical(target, this.ctx.count)
      : target;

    if (!wrapping) {
      clearSettle(this.slides);
      this.setIndices(target);
      this.emitChange(target);
      this.scrollGuard.begin();
      this.track.scrollTo({
        left: scrollLeftForIndex(realPhysical, width, this.isRtl),
        behavior: reduce ? 'auto' : 'smooth',
      });
      return;
    }

    // Wrap: keep activeIndex on the outgoing slide until the snap completes
    // so background motion / content don't restart on the off-screen real
    // destination. Chrome advances via displayIndex; the edge clone is what
    // the user sees during the scroll.
    const host = this.seamlessHost();
    if (!host) return;

    this.wrapCancel?.();
    const handle = startSeamlessWrap(host, from, target, reduce);
    if (!handle) {
      clearSettle(this.slides);
      this.setIndices(target);
      this.emitChange(target);
      this.scrollGuard.begin();
      this.track.scrollTo({
        left: scrollLeftForIndex(realPhysical, width, this.isRtl),
        behavior: reduce ? 'auto' : 'smooth',
      });
      return;
    }
    this.wrapCancel = handle.cancel;
  }

  /**
   * Central "slide changed" hook: announces to AT, dispatches the public
   * `aa:slide-change` event, and reflects the position in the URL hash when
   * deep linking is on. De-duped so a no-op move stays silent.
   */
  private emitChange(index: number): void {
    if (index === this.lastEmitted) return;
    this.lastEmitted = index;
    // New slide → full dwell + progress pill restart on the new active dot.
    this.autoplay.notifySlideChange();
    announceSlide(
      this.announcer,
      this.ctx.i18n?.slide ?? 'Slide %1$s of %2$s',
      index,
      this.ctx.count,
      this.ctx.isPlaying && !this.ctx.isPaused
    );
    this.root.dispatchEvent(
      new CustomEvent('aa:slide-change', {
        bubbles: true,
        detail: {
          index,
          count: this.ctx.count,
          slide: this.slides[index] ?? null,
          carousel: this.root,
        },
      })
    );
    syncDeepLinkHash(this.root.id, index, this.ctx.deepLink);
  }

  next(auto = false): void {
    if (this.wrapping) return;
    const target = nextIndex(
      this.ctx.activeIndex,
      this.ctx.count,
      this.ctx.loop
    );
    this.goTo(target);
    if (!auto) this.autoplay.deferResume();
    if (auto && !this.ctx.loop && target >= this.ctx.count - 1) {
      // Reached the end of a non-looping run — stop autoplay.
      this.autoplay.stop();
    }
  }

  prev(): void {
    if (this.wrapping) return;
    this.goTo(prevIndex(this.ctx.activeIndex, this.ctx.count, this.ctx.loop));
    this.autoplay.deferResume();
  }

  togglePlay(): void {
    this.autoplay.togglePlay();
  }

  hold(hold: boolean): void {
    this.autoplay.hold(hold);
  }

  destroy(): void {
    this.wrapCancel?.();
    this.autoplay.destroy();
    this.scrollGuard.destroy();
    window.clearTimeout(this.layoutTimer);
    this.io.disconnect();
    this.resizeObserver?.disconnect();
    document.removeEventListener('visibilitychange', this.onVisibilityChange);
    window.removeEventListener('hashchange', this.onHashChange);
    this.track?.removeEventListener('scroll', this.onScroll);
    setScrollSnap(this.track, true);
    clearSettle(this.slides);
    clearMotionHolds(this.slides);
    removeEdgeClones(this.track);
  }
}

const controllers = new WeakMap<HTMLElement, HeroController>();

function controllerFor(el: Element | null): HeroController | undefined {
  const root = el?.closest<HTMLElement>(ROOT_SELECTOR) ?? null;
  return root ? controllers.get(root) : undefined;
}

function slideIsActive(ctx: HeroContext): boolean {
  return ctx.activeIndex === ctx.slideIndex;
}

function chromeIndex(ctx: HeroContext): number {
  return typeof ctx.displayIndex === 'number'
    ? ctx.displayIndex
    : ctx.activeIndex;
}

function slideIsCurrentChrome(ctx: HeroContext): boolean {
  return chromeIndex(ctx) === ctx.slideIndex;
}

function isStackedInactive(ctx: HeroContext): boolean {
  return ctx.transition !== 'slide' && !slideIsActive(ctx);
}

function boolAttr(value: boolean): 'true' | 'false' {
  return value ? 'true' : 'false';
}

interface HeroState {
  readonly isActiveSlide: boolean;
  readonly slideInert: boolean;
  readonly ariaHiddenSlide: string;
  readonly ariaCurrentDot: string;
  readonly slideTabindex: string;
  readonly isPlayingClass: boolean;
  readonly playLabel: string;
  readonly fraction: string;
  readonly ariaLive: string;
  readonly prevDisabled: boolean;
  readonly nextDisabled: boolean;
}

interface HeroStore {
  state: HeroState;
  actions: InteractivityActions;
  callbacks: InteractivityCallbacks;
}

store<HeroStore>('aggressive-apparel/hero-carousel', {
  state: {
    get isActiveSlide(): boolean {
      return slideIsActive(getContext<HeroContext>());
    },
    get slideInert(): boolean {
      return isStackedInactive(getContext<HeroContext>());
    },
    get ariaHiddenSlide(): string {
      return boolAttr(isStackedInactive(getContext<HeroContext>()));
    },
    get ariaCurrentDot(): string {
      return boolAttr(slideIsCurrentChrome(getContext<HeroContext>()));
    },
    get slideTabindex(): string {
      const ctx = getContext<HeroContext>();
      if (ctx.transition === 'slide') return '0';
      return slideIsActive(ctx) ? '0' : '-1';
    },
    get isPlayingClass(): boolean {
      return getContext<HeroContext>().isPlaying;
    },
    get playLabel(): string {
      const ctx = getContext<HeroContext>();
      return ctx.isPlaying
        ? (ctx.i18n?.pause ?? 'Pause slideshow')
        : (ctx.i18n?.play ?? 'Play slideshow');
    },
    get fraction(): string {
      const ctx = getContext<HeroContext>();
      return `${chromeIndex(ctx) + 1} / ${ctx.count}`;
    },
    get ariaLive(): string {
      const ctx = getContext<HeroContext>();
      return ctx.isPlaying && !ctx.isPaused ? 'off' : 'polite';
    },
    get prevDisabled(): boolean {
      const ctx = getContext<HeroContext>();
      return !ctx.loop && chromeIndex(ctx) <= 0;
    },
    get nextDisabled(): boolean {
      const ctx = getContext<HeroContext>();
      return !ctx.loop && chromeIndex(ctx) >= ctx.count - 1;
    },
  },

  actions: {
    next(): void {
      controllerFor(getElement().ref)?.next();
    },
    prev(): void {
      controllerFor(getElement().ref)?.prev();
    },
    goTo(): void {
      const ctx = getContext<HeroContext>();
      controllerFor(getElement().ref)?.goTo(ctx.slideIndex);
    },
    togglePlay(): void {
      controllerFor(getElement().ref)?.togglePlay();
    },
    handleKeydown: withSyncEvent((event: KeyboardEvent): void => {
      // Don't steal arrows from form fields / editable content inside a Cover.
      const target = event.target;
      if (
        target instanceof HTMLElement &&
        (target.closest('input, textarea, select, [contenteditable="true"]') ||
          target.isContentEditable)
      ) {
        return;
      }

      const ctx = getContext<HeroContext>();
      const controller = controllerFor(getElement().ref);
      if (!controller) return;
      switch (event.key) {
        case 'ArrowRight':
          event.preventDefault();
          controller.next();
          break;
        case 'ArrowLeft':
          event.preventDefault();
          controller.prev();
          break;
        case 'Home':
          event.preventDefault();
          controller.goTo(0);
          break;
        case 'End':
          event.preventDefault();
          controller.goTo(ctx.count - 1);
          break;
        default:
      }
    }),
    pause(): void {
      const ctx = getContext<HeroContext>();
      if (ctx.pauseOnHover) controllerFor(getElement().ref)?.hold(true);
    },
    resume(): void {
      const ctx = getContext<HeroContext>();
      if (ctx.pauseOnHover) controllerFor(getElement().ref)?.hold(false);
    },
    pauseFocus(): void {
      controllerFor(getElement().ref)?.hold(true);
    },
    resumeFocus(event: FocusEvent): void {
      const { ref } = getElement();
      const root = ref?.closest<HTMLElement>(ROOT_SELECTOR) ?? null;
      if (!root) return;
      if (
        event.relatedTarget instanceof Node &&
        root.contains(event.relatedTarget)
      ) {
        return;
      }
      controllers.get(root)?.hold(false);
    },
  },

  callbacks: {
    init(): (() => void) | void {
      const { ref } = getElement();
      if (!(ref instanceof HTMLElement)) return;
      const ctx = getContext<HeroContext>();
      if (prefersReducedMotion() || prefersReducedData()) ctx.isPlaying = false;
      const controller = new HeroController(ref, ctx);
      controllers.set(ref, controller);
      return () => {
        controller.destroy();
        controllers.delete(ref);
      };
    },
  },
});
