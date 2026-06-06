/**
 * Product Info Display — Interactivity API Module
 *
 * Handles accordion toggle animation, modern tab switching with
 * keyboard navigation, and scrollspy with IntersectionObserver.
 *
 * @since 1.17.0
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

/* ---------------------------------------------------------------
 * Accordion animation helpers
 * ------------------------------------------------------------- */

import type {
  InteractivityActions,
  InteractivityCallbacks,
} from '../../types/interactivity-shared';

const ANIM_DURATION = 200;

/**
 * Animate a panel's height + padding between natural size and zero.
 */
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

/**
 * Smoothly close a <details> element.
 */
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

/* ---------------------------------------------------------------
 * Interactivity store
 * ------------------------------------------------------------- */

interface ProductTabsState {
  // Imperative state set in actions/callbacks
  activeTab: number;
  activeSection: string;
  // Getters
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
      const ctx = getContext<TabContext>();
      return state.activeTab === ctx.tabIndex;
    },

    get isPanelVisible(): boolean {
      const ctx = getContext<TabContext>();
      return state.activeTab === ctx.tabIndex;
    },

    get tabTabindex(): string {
      const ctx = getContext<TabContext>();
      return state.activeTab === ctx.tabIndex ? '0' : '-1';
    },

    /**
     * Returns "true"/"false" string for aria-selected.
     * data-wp-bind-- removes attributes for falsy values,
     * but ARIA spec requires aria-selected="false" on inactive tabs.
     */
    get ariaSelected(): string {
      const ctx = getContext<TabContext>();
      return state.activeTab === ctx.tabIndex ? 'true' : 'false';
    },

    get isActiveNav(): boolean {
      const ctx = getContext<TabContext>();
      return state.activeSection === ctx.sectionId;
    },

    /**
     * Returns "true"/"false" string for aria-current on scrollspy nav.
     */
    get ariaCurrent(): string {
      const ctx = getContext<TabContext>();
      return state.activeSection === ctx.sectionId ? 'true' : 'false';
    },
  },

  actions: {
    /**
     * Accordion: animated open/close with single-open behaviour.
     * Intercepts the <summary> click so we can animate before
     * the browser toggles the <details> open state.
     */
    toggleAccordion: withSyncEvent((event: Event): void => {
      event.preventDefault();
      const { ref } = getElement();
      const details = ref?.closest('details') as HTMLDetailsElement | null;
      if (!details) return;

      if (details.open) {
        // --- Close ---
        closeDetails(details);
      } else {
        // --- Open: close siblings first ---
        const parent = details.closest('.aa-product-info--accordion');
        if (parent) {
          for (const sibling of parent.querySelectorAll('details[open]')) {
            if (sibling !== details) {
              closeDetails(sibling as HTMLDetailsElement);
            }
          }
        }

        // Open and animate in.
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

    /**
     * Modern tabs: select a tab by click.
     */
    selectTab(): void {
      const ctx = getContext<TabContext>();
      state.activeTab = ctx.tabIndex;

      const { ref } = getElement();
      if (ref) {
        ref.focus();
      }
    },

    /**
     * Modern tabs: keyboard navigation (Arrow keys, Home, End).
     */
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
      if (tabs[newIndex]) {
        tabs[newIndex].focus();
      }
    },

    /**
     * Scrollspy: scroll to a section on nav click.
     */
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
    /**
     * Handle URL hash on page load — open the matching accordion
     * section or switch to the matching modern-tab panel.
     * Enables deep links like product-url/#pi-reviews.
     */
    initHashNav(): void {
      const hash = window.location.hash?.slice(1);
      if (!hash) return;

      const { ref } = getElement();
      if (!ref) return;

      const target = document.getElementById(hash);
      if (!target || !ref.contains(target)) return;

      // Accordion: close others, open target.
      if (ref.classList.contains('aa-product-info--accordion')) {
        for (const details of ref.querySelectorAll('details[open]')) {
          details.removeAttribute('open');
        }
        const details =
          target.tagName === 'DETAILS' ? target : target.closest('details');
        if (details) {
          details.setAttribute('open', '');
        }
      }

      // Modern tabs: switch to the target panel.
      if (ref.classList.contains('aa-product-info--modern-tabs')) {
        const panels = Array.from(ref.querySelectorAll('[role="tabpanel"]'));
        const index = panels.indexOf(target);
        if (index >= 0) {
          state.activeTab = index;
        }
      }

      // Scroll into view after layout settles.
      requestAnimationFrame(() => {
        target.scrollIntoView({ block: 'start', behavior: 'auto' });
      });
    },

    /**
     * Scrollspy: set up IntersectionObserver on mount.
     */
    initScrollspy(): void {
      const { ref } = getElement();
      if (!ref) return;

      const sections = ref.querySelectorAll('.aa-product-info__section');
      if (!sections.length) return;

      // Set initial active section (prefer URL hash if it matches).
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
        {
          rootMargin: '-20% 0px -60% 0px',
          threshold: 0,
        }
      );

      sections.forEach(section => observer.observe(section));
    },
  },
});
