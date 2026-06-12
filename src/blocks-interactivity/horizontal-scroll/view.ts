/**
 * Horizontal Scroll Block — Interactivity API Store.
 *
 * Desktop (pointer:fine): maps vertical scroll through the sentinel to
 * horizontal translateX. Arrow keys advance the page scroll.
 *
 * Mobile (pointer:coarse): CSS scroll-snap handles touch swiping.
 * Arrow keys advance viewport.scrollLeft for keyboard/tablet users.
 *
 * @package Aggressive_Apparel
 */

/// <reference types="@wordpress/interactivity" />
import { store, getContext, getElement } from '@wordpress/interactivity';

interface HScrollContext {
  itemWidth: string;
  progress: number;
}

interface HScrollStore {
  callbacks: {
    init: () => void;
    progressStyle: () => string;
  };
}

const isFine =
  typeof window !== 'undefined'
    ? window.matchMedia('(pointer: fine)').matches
    : true;

store<HScrollStore>('aggressive-apparel/horizontal-scroll', {
  callbacks: {
    init() {
      const ctx = getContext<HScrollContext>();
      const { ref } = getElement();
      if (!ref) return;

      const viewport = ref.querySelector<HTMLElement>('.aa-hscroll__viewport');
      const track = ref.querySelector<HTMLElement>('.aa-hscroll__track');
      const progressEl = ref.querySelector<HTMLElement>(
        '.aa-hscroll__progress'
      );
      if (!viewport || !track) return;

      // Carousel semantics: label each slide for AT
      const slides = Array.from(track.children) as HTMLElement[];
      const total = slides.length;
      slides.forEach((slide, i) => {
        slide.setAttribute('role', 'group');
        slide.setAttribute('aria-roledescription', 'slide');
        slide.setAttribute('aria-label', `${i + 1} of ${total}`);
      });

      // Make the section focusable so keydown fires when user tabs to it
      ref.setAttribute('tabindex', '0');

      let currentIndex = 0;

      if (!isFine) {
        // ── Mobile: CSS scroll-snap drives the visuals; we handle arrow keys ──

        const getSlideWidth = () =>
          slides[0]?.getBoundingClientRect().width ?? 0;

        const scrollToIndex = (idx: number) => {
          currentIndex = Math.max(0, Math.min(total - 1, idx));
          viewport.scrollTo({
            left: currentIndex * getSlideWidth(),
            behavior: 'smooth',
          });
        };

        // Keep currentIndex in sync with native touch scroll
        viewport.addEventListener(
          'scroll',
          () => {
            const w = getSlideWidth();
            if (w > 0) currentIndex = Math.round(viewport.scrollLeft / w);
          },
          { passive: true }
        );

        ref.addEventListener('keydown', (e: KeyboardEvent) => {
          if (e.key === 'ArrowRight') {
            e.preventDefault();
            scrollToIndex(currentIndex + 1);
          } else if (e.key === 'ArrowLeft') {
            e.preventDefault();
            scrollToIndex(currentIndex - 1);
          }
        });

        return;
      }

      // ── Desktop: scroll-sentinel drives translateX ──

      let raf: number | null = null;
      let scrollableH = 0;
      let maxTranslate = 0;

      const recalc = () => {
        scrollableH = ref.offsetHeight - window.innerHeight;
        maxTranslate = track.scrollWidth - viewport.clientWidth;
      };

      const update = () => {
        raf = null;
        if (scrollableH <= 0) return;

        const progress = Math.max(
          0,
          Math.min(1, -ref.getBoundingClientRect().top / scrollableH)
        );

        track.style.transform = `translateX(${-progress * maxTranslate}px)`;
        ctx.progress = Math.round(progress * 100);

        progressEl?.classList.toggle(
          'is-active',
          progress > 0.01 && progress < 0.99
        );

        if (progress > 0 && progress < 1) {
          document.body.setAttribute('data-aa-cursor', 'drag');
        } else if (document.body.getAttribute('data-aa-cursor') === 'drag') {
          document.body.setAttribute('data-aa-cursor', 'default');
        }

        currentIndex = Math.round(progress * (total - 1));
      };

      const onScroll = () => {
        if (raf !== null) return;
        raf = requestAnimationFrame(update);
      };

      // Arrow keys: scroll the page through the sentinel, driving translateX
      ref.addEventListener('keydown', (e: KeyboardEvent) => {
        if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
        e.preventDefault();
        if (scrollableH <= 0) return;
        const perSlide = scrollableH / Math.max(total - 1, 1);
        window.scrollBy({
          top: e.key === 'ArrowRight' ? perSlide : -perSlide,
          behavior: 'smooth',
        });
      });

      recalc();
      window.addEventListener('resize', recalc, { passive: true });
      window.addEventListener('scroll', onScroll, { passive: true });
      update();
    },

    progressStyle() {
      const ctx = getContext<HScrollContext>();
      return `width: ${ctx.progress}%`;
    },
  },
});
