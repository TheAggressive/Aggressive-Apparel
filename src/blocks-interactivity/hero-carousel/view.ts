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
 * don't map cleanly to directives: scroll<->index sync, the autoplay timer,
 * visibility/interaction gating, Ken Burns focal origins, deep linking, and
 * the public `aa:slide-change` event.
 *
 * @package Aggressive_Apparel
 */

import { store, getContext, getElement } from '@wordpress/interactivity';

import {
  activeIndexFromScroll,
  canAdvance,
  clampIndex,
  focalToTransformOrigin,
  nextIndex,
  normalizeAutoplaySpeed,
  parseSlideHash,
  prevIndex,
  scrollLeftForIndex,
  slideHash,
} from './logic';
import type {
  InteractivityActions,
  InteractivityCallbacks,
} from '../../../types/interactivity-shared';

const ROOT_SELECTOR = '.wp-block-aggressive-apparel-hero-carousel';
const SLIDE_SELECTOR = '.aa-hero__slide';
const TRACK_SELECTOR = '.aa-hero__track';
const COVER_BG_SELECTOR = '.wp-block-cover__image-background';
/** Pause after manual navigation before autoplay may resume, per WCAG intent. */
const RESUME_DELAY_MS = 400;
/** Debounce before a manual scroll settles into an active slide index. */
const SCROLL_IDLE_MS = 90;
/** Fraction of the carousel that must be on-screen for autoplay to run. */
const VISIBILITY_THRESHOLD = 0.25;

interface HeroContext {
  activeIndex: number;
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

function prefersReducedMotion(): boolean {
  return (
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches
  );
}

/**
 * True when the visitor has opted into reduced data usage (Save-Data header
 * or the `prefers-reduced-data` media query). Autoplay preloads later slide
 * media, so we suppress it under either signal.
 */
function prefersReducedData(): boolean {
  if (typeof navigator !== 'undefined') {
    const connection = (
      navigator as Navigator & { connection?: { saveData?: boolean } }
    ).connection;
    if (connection?.saveData) return true;
  }
  return (
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-reduced-data: reduce)').matches
  );
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
  private timer = 0;
  private resumeTimer = 0;
  private isVisible = true;
  private syncingFromScroll = false;
  private scrollIdle = 0;
  private readonly isRtl: boolean;
  private lastEmitted: number;

  constructor(
    private readonly root: HTMLElement,
    private readonly ctx: HeroContext
  ) {
    this.track = root.querySelector<HTMLElement>(TRACK_SELECTOR);
    this.slides = Array.from(
      root.querySelectorAll<HTMLElement>(SLIDE_SELECTOR)
    );
    this.announcer = root.querySelector<HTMLElement>('.aa-hero__live');
    this.isRtl = getComputedStyle(root).direction === 'rtl';
    this.lastEmitted = ctx.activeIndex;

    this.applyFocalOrigins();

    if (this.ctx.transition === 'slide' && this.track) {
      this.track.addEventListener('scroll', this.onScroll, { passive: true });
    }

    // Gate autoplay on the carousel being on-screen.
    this.io = new IntersectionObserver(
      entries => {
        for (const entry of entries) {
          this.isVisible = entry.isIntersecting;
          this.reconcileTimer();
        }
      },
      { threshold: VISIBILITY_THRESHOLD }
    );
    this.io.observe(root);

    document.addEventListener('visibilitychange', this.onVisibilityChange);

    // Deep linking: honor an incoming #<id>-slide-<n> on load, and respond to
    // later hash changes (e.g. an external link into a specific slide).
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

    this.reconcileTimer();
  }

  /** Ken Burns should pan around the Cover's focal point, not the center. */
  private applyFocalOrigins(): void {
    for (const slide of this.slides) {
      const bg = slide.querySelector<HTMLElement>(COVER_BG_SELECTOR);
      const objectPosition = bg ? getComputedStyle(bg).objectPosition : null;
      slide.style.setProperty(
        '--aa-hero-kb-origin',
        focalToTransformOrigin(objectPosition)
      );
    }
  }

  private onVisibilityChange = (): void => {
    this.reconcileTimer();
  };

  private onHashChange = (): void => {
    if (!this.ctx.deepLink || !this.root.id) return;
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
    window.clearTimeout(this.scrollIdle);
    this.scrollIdle = window.setTimeout(() => {
      if (!this.track) return;
      const width = this.track.clientWidth || 1;
      const index = activeIndexFromScroll(
        this.track.scrollLeft,
        width,
        this.ctx.count
      );
      if (index !== this.ctx.activeIndex) {
        this.syncingFromScroll = true;
        this.ctx.activeIndex = index;
        this.emitChange(index);
        this.syncingFromScroll = false;
      }
      // A manual scroll counts as interaction — hold autoplay briefly.
      this.deferResume();
    }, SCROLL_IDLE_MS);
  };

  /**
   * Move to an index, scrolling the track when in slide mode. `instant`
   * skips the smooth animation (used for the initial deep-link jump).
   */
  goTo(index: number, instant = false): void {
    const target = clampIndex(index, this.ctx.count);
    this.ctx.activeIndex = target;
    this.emitChange(target);
    if (
      this.ctx.transition === 'slide' &&
      this.track &&
      !this.syncingFromScroll
    ) {
      const width = this.track.clientWidth || 1;
      this.track.scrollTo({
        left: scrollLeftForIndex(target, width, this.isRtl),
        behavior: instant || prefersReducedMotion() ? 'auto' : 'smooth',
      });
    }
  }

  /**
   * Central "slide changed" hook: announces to AT, dispatches the public
   * `aa:slide-change` event, and reflects the position in the URL hash when
   * deep linking is on. De-duped so a no-op move stays silent.
   */
  private emitChange(index: number): void {
    if (index === this.lastEmitted) return;
    this.lastEmitted = index;
    this.announce(index);
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
    if (this.ctx.deepLink && this.root.id) {
      const hash = slideHash(this.root.id, index);
      if (window.location.hash !== hash) {
        window.history.replaceState(null, '', hash);
      }
    }
  }

  next(auto = false): void {
    const target = nextIndex(
      this.ctx.activeIndex,
      this.ctx.count,
      this.ctx.loop
    );
    this.goTo(target);
    if (!auto) this.deferResume();
    if (
      auto &&
      !this.ctx.loop &&
      !canAdvance(target, this.ctx.count, this.ctx.loop)
    ) {
      // Reached the end of a non-looping run — stop autoplay.
      this.ctx.isPlaying = false;
      this.reconcileTimer();
    }
  }

  prev(): void {
    this.goTo(prevIndex(this.ctx.activeIndex, this.ctx.count, this.ctx.loop));
    this.deferResume();
  }

  togglePlay(): void {
    this.ctx.isPlaying = !this.ctx.isPlaying;
    this.reconcileTimer();
  }

  /** Hover / focus pause. `hold` true = pause, false = allow resume. */
  hold(hold: boolean): void {
    this.ctx.isPaused = hold;
    this.reconcileTimer();
  }

  /** After manual interaction, resume autoplay only after a short delay. */
  private deferResume(): void {
    if (!this.ctx.autoplay || !this.ctx.isPlaying) return;
    this.ctx.isPaused = true;
    this.reconcileTimer();
    window.clearTimeout(this.resumeTimer);
    this.resumeTimer = window.setTimeout(() => {
      this.ctx.isPaused = false;
      this.reconcileTimer();
    }, RESUME_DELAY_MS);
  }

  private shouldRun(): boolean {
    return (
      this.ctx.isPlaying &&
      this.isVisible &&
      !this.ctx.isPaused &&
      !document.hidden &&
      !prefersReducedMotion() &&
      !prefersReducedData() &&
      this.ctx.count > 1 &&
      canAdvance(this.ctx.activeIndex, this.ctx.count, this.ctx.loop)
    );
  }

  private reconcileTimer(): void {
    window.clearInterval(this.timer);
    this.timer = 0;
    this.root.classList.toggle(
      'is-playing',
      this.ctx.isPlaying && !this.ctx.isPaused
    );
    if (this.shouldRun()) {
      const interval = normalizeAutoplaySpeed(this.ctx.autoplaySpeed);
      this.timer = window.setInterval(() => this.next(true), interval);
    }
  }

  private announce(index: number): void {
    // Only announce when not actively autoplaying, to avoid AT chatter.
    if (!this.announcer || (this.ctx.isPlaying && !this.ctx.isPaused)) return;
    const template = this.ctx.i18n?.slide ?? 'Slide %1$s of %2$s';
    this.announcer.textContent = template
      .replace('%1$s', String(index + 1))
      .replace('%2$s', String(this.ctx.count));
  }

  destroy(): void {
    window.clearInterval(this.timer);
    window.clearTimeout(this.resumeTimer);
    window.clearTimeout(this.scrollIdle);
    this.io.disconnect();
    document.removeEventListener('visibilitychange', this.onVisibilityChange);
    window.removeEventListener('hashchange', this.onHashChange);
    this.track?.removeEventListener('scroll', this.onScroll);
  }
}

const controllers = new WeakMap<HTMLElement, HeroController>();

function controllerFor(el: Element | null): HeroController | undefined {
  const root = el?.closest<HTMLElement>(ROOT_SELECTOR) ?? null;
  return root ? controllers.get(root) : undefined;
}

/** A slide is active when its index matches the carousel's active index. */
function slideIsActive(ctx: HeroContext): boolean {
  return ctx.activeIndex === ctx.slideIndex;
}

/**
 * Inactive slides in the stacked modes (fade/crossfade) are hidden and pulled
 * from the a11y tree. The slide-track mode keeps every slide reachable, so
 * nothing there is inert.
 */
function isStackedInactive(ctx: HeroContext): boolean {
  return ctx.transition !== 'slide' && !slideIsActive(ctx);
}

/** Serialize a boolean to an ARIA attribute value. */
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
      return boolAttr(slideIsActive(getContext<HeroContext>()));
    },
    get slideTabindex(): string {
      const ctx = getContext<HeroContext>();
      // Every slide stays tabbable in the slide-track mode; in the stacked
      // (fade/crossfade) modes only the active slide is a tab stop.
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
      return `${ctx.activeIndex + 1} / ${ctx.count}`;
    },
    // Live region is polite only while autoplay is not advancing slides
    // (WAI-ARIA carousel pattern) — otherwise it would announce every auto
    // transition.
    get ariaLive(): string {
      const ctx = getContext<HeroContext>();
      return ctx.isPlaying && !ctx.isPaused ? 'off' : 'polite';
    },
    get prevDisabled(): boolean {
      const ctx = getContext<HeroContext>();
      return !ctx.loop && ctx.activeIndex <= 0;
    },
    get nextDisabled(): boolean {
      const ctx = getContext<HeroContext>();
      return !ctx.loop && ctx.activeIndex >= ctx.count - 1;
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
    handleKeydown(event: KeyboardEvent): void {
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
    },
    pause(): void {
      const ctx = getContext<HeroContext>();
      if (ctx.pauseOnHover) controllerFor(getElement().ref)?.hold(true);
    },
    resume(): void {
      const ctx = getContext<HeroContext>();
      if (ctx.pauseOnHover) controllerFor(getElement().ref)?.hold(false);
    },
    // Keyboard focus inside the carousel always pauses autoplay (WCAG
    // 2.2.2), independent of the pause-on-hover setting.
    pauseFocus(): void {
      controllerFor(getElement().ref)?.hold(true);
    },
    resumeFocus(event: FocusEvent): void {
      const { ref } = getElement();
      const root = ref?.closest<HTMLElement>(ROOT_SELECTOR) ?? null;
      if (!root) return;
      // Only release when focus actually left the carousel.
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
      // Never auto-advance for visitors who prefer reduced motion or reduced
      // data, regardless of the setting.
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
