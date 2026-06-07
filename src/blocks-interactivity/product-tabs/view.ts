/**
 * Product Info Display — Interactivity API Module
 *
 * Handles accordion toggle animation, modern tab switching with
 * keyboard navigation, and scrollspy with IntersectionObserver.
 *
 * @package Aggressive_Apparel
 * @since 1.87.0
 */

import {
  store,
  getContext,
  getElement,
  withSyncEvent,
} from '@wordpress/interactivity';

interface TabContext {
  tabIndex: number;
  sectionId: string;
}

import type {
  InteractivityActions,
  InteractivityCallbacks,
} from '../../../types/interactivity-shared';

const ANIM_DURATION = 200;

function animatePanel(content: HTMLElement, open: boolean): Animation {
  const duration = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    ? 0
    : ANIM_DURATION;

  const { height, paddingTop, paddingBottom } = getComputedStyle(content);
  const natural = { height, paddingTop, paddingBottom };
  const collapsed = { height: '0px', paddingTop: '0px', paddingBottom: '0px' };

  content.style.overflow = 'hidden';

  return content.animate(open ? [collapsed, natural] : [natural, collapsed], {
    duration,
    easing: 'ease-out',
  });
}

function closeDetails(details: HTMLDetailsElement): void {
  const content = details.querySelector(
    '.aa-product-info__content'
  ) as HTMLElement | null;
  if (!content) {
    details.removeAttribute('open');
    return;
  }

  const anim = animatePanel(content, false);
  anim.onfinish = () => {
    details.removeAttribute('open');
    content.style.overflow = '';
  };
}

interface ProductTabsState {
  activeTab: number;
  activeSection: string;
  readonly isActiveTab: boolean;
  readonly isPanelVisible: boolean;
  readonly tabTabindex: string;
  readonly ariaSelected: string;
  readonly isActiveNav: boolean;
  readonly ariaCurrent: string;
}

interface ProductTabsStore {
  state: ProductTabsState;
  actions: InteractivityActions;
  callbacks: InteractivityCallbacks;
}

const { state } = store<ProductTabsStore>('aggressive-apparel/product-tabs', {
  state: {
    get isActiveTab(): boolean {
      return state.activeTab === getContext<TabContext>().tabIndex;
    },
    get isPanelVisible(): boolean {
      return state.activeTab === getContext<TabContext>().tabIndex;
    },
    get tabTabindex(): string {
      return state.activeTab === getContext<TabContext>().tabIndex ? '0' : '-1';
    },
    get ariaSelected(): string {
      return state.activeTab === getContext<TabContext>().tabIndex
        ? 'true'
        : 'false';
    },
    get isActiveNav(): boolean {
      return state.activeSection === getContext<TabContext>().sectionId;
    },
    get ariaCurrent(): string {
      return state.activeSection === getContext<TabContext>().sectionId
        ? 'true'
        : 'false';
    },
  },

  actions: {
    toggleAccordion: withSyncEvent((event: Event): void => {
      event.preventDefault();
      const { ref } = getElement();
      const details = ref?.closest('details') as HTMLDetailsElement | null;
      if (!details) return;

      if (details.open) {
        closeDetails(details);
      } else {
        const parent = details.closest('.aa-product-info--accordion');
        if (parent) {
          for (const sibling of parent.querySelectorAll('details[open]')) {
            if (sibling !== details)
              closeDetails(sibling as HTMLDetailsElement);
          }
        }
        details.setAttribute('open', '');
        const content = details.querySelector(
          '.aa-product-info__content'
        ) as HTMLElement | null;
        if (content) {
          const anim = animatePanel(content, true);
          anim.onfinish = () => {
            content.style.overflow = '';
          };
        }
      }
    }),

    selectTab(): void {
      const ctx = getContext<TabContext>();
      state.activeTab = ctx.tabIndex;
      const { ref } = getElement();
      if (ref) ref.focus();
    },

    handleTabKeydown(event: KeyboardEvent): void {
      const tabNav = (event.target as HTMLElement).closest('[role="tablist"]');
      if (!tabNav) return;

      const tabs = Array.from(
        tabNav.querySelectorAll('[role="tab"]')
      ) as HTMLElement[];
      const count = tabs.length;
      let newIndex: number = state.activeTab;

      switch (event.key) {
        case 'ArrowRight':
        case 'ArrowDown':
          event.preventDefault();
          newIndex = (state.activeTab + 1) % count;
          break;
        case 'ArrowLeft':
        case 'ArrowUp':
          event.preventDefault();
          newIndex = (state.activeTab - 1 + count) % count;
          break;
        case 'Home':
          event.preventDefault();
          newIndex = 0;
          break;
        case 'End':
          event.preventDefault();
          newIndex = count - 1;
          break;
        default:
          return;
      }

      state.activeTab = newIndex;
      if (tabs[newIndex]) tabs[newIndex].focus();
    },

    scrollToSection(event: Event): void {
      event.preventDefault();
      const ctx = getContext<TabContext>();
      const target = document.getElementById(ctx.sectionId);
      if (!target) return;

      const reducedMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)'
      ).matches;
      target.scrollIntoView({
        behavior: reducedMotion ? 'auto' : 'smooth',
        block: 'start',
      });
      state.activeSection = ctx.sectionId;
    },
  },

  callbacks: {
    initHashNav(): void {
      const hash = window.location.hash?.slice(1);
      if (!hash) return;

      const { ref } = getElement();
      if (!ref) return;

      const target = document.getElementById(hash);
      if (!target || !ref.contains(target)) return;

      if (ref.classList.contains('aa-product-info--accordion')) {
        for (const details of ref.querySelectorAll('details[open]')) {
          details.removeAttribute('open');
        }
        const details =
          target.tagName === 'DETAILS' ? target : target.closest('details');
        if (details) details.setAttribute('open', '');
      }

      if (ref.classList.contains('aa-product-info--modern-tabs')) {
        const panels = Array.from(ref.querySelectorAll('[role="tabpanel"]'));
        const index = panels.indexOf(target);
        if (index >= 0) state.activeTab = index;
      }

      requestAnimationFrame(() => {
        target.scrollIntoView({ block: 'start', behavior: 'auto' });
      });
    },

    initScrollspy(): void {
      const { ref } = getElement();
      if (!ref) return;

      const sections = ref.querySelectorAll('.aa-product-info__section');
      if (!sections.length) return;

      const hash = window.location.hash?.slice(1);
      const hashTarget = hash && ref.querySelector(`#${CSS.escape(hash)}`);
      if (hashTarget) {
        state.activeSection = (hashTarget as HTMLElement).id;
        requestAnimationFrame(() => {
          hashTarget.scrollIntoView({ block: 'start', behavior: 'auto' });
        });
      } else if (sections[0] && (sections[0] as HTMLElement).id) {
        state.activeSection = (sections[0] as HTMLElement).id;
      }

      const observer = new IntersectionObserver(
        (entries: IntersectionObserverEntry[]) => {
          for (const entry of entries) {
            if (entry.isIntersecting && (entry.target as HTMLElement).id) {
              state.activeSection = (entry.target as HTMLElement).id;
            }
          }
        },
        { rootMargin: '-20% 0px -60% 0px', threshold: 0 }
      );

      sections.forEach(section => observer.observe(section));
    },
  },
});
