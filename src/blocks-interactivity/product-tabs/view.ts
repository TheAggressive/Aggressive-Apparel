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

// Per-<details> canceller for an in-flight reveal finalizer, so a rapid re-tap
// tears the old one down instead of letting stale transitionend/timeout
// handlers fire.
const pendingReveal = new WeakMap<HTMLElement, () => void>();

function prefersReducedMotion(): boolean {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function getReveal(details: HTMLElement): HTMLElement | null {
  return details.querySelector('.aa-product-info__reveal');
}

// Read the reveal's transition duration from CSS so the JS safety timeout stays
// in lockstep with the stylesheet rather than a duplicated constant. Reduced
// motion resolves this to 0s.
function revealDurationMs(reveal: HTMLElement): number {
  const first = getComputedStyle(reveal)
    .transitionDuration.split(',')[0]
    .trim();
  const seconds = Number.parseFloat(first);
  return Number.isFinite(seconds) ? seconds * 1000 : 250;
}

/**
 * Logical open state, accounting for an in-flight transition: while a panel is
 * animating we track the *intended* end state on `data-aa-target` so re-taps
 * decide against the destination, not the lingering `open` attribute.
 */
export function isPanelOpen(details: HTMLElement): boolean {
  const target = details.dataset.aaTarget;
  return target ? target === 'open' : details.hasAttribute('open');
}

/**
 * Run `done` once the reveal's `grid-template-rows` transition ends (with a
 * safety timeout). Cancels any previously pending finalizer for this panel.
 */
function afterReveal(
  details: HTMLElement,
  reveal: HTMLElement,
  done: () => void
): void {
  pendingReveal.get(details)?.();

  let settled = false;
  const teardown = (): void => {
    if (settled) return;
    settled = true;
    reveal.removeEventListener('transitionend', onEnd);
    window.clearTimeout(timer);
    pendingReveal.delete(details);
  };
  const onEnd = (event: TransitionEvent): void => {
    if (
      event.target === reveal &&
      event.propertyName === 'grid-template-rows'
    ) {
      teardown();
      done();
    }
  };
  const timer = window.setTimeout(
    () => {
      teardown();
      done();
    },
    revealDurationMs(reveal) + 50
  );

  reveal.addEventListener('transitionend', onEnd);
  // The canceller only tears down; it never runs `done` (intent was superseded).
  pendingReveal.set(details, teardown);
}

/**
 * Open or close one accordion panel via the CSS grid-rows reveal. The `open`
 * attribute drives semantics/a11y and content rendering; on collapse we keep it
 * until the transition finishes so the panel can animate before it's hidden.
 */
function setPanel(details: HTMLElement, open: boolean): void {
  const target = open ? 'open' : 'closed';
  if (details.dataset.aaTarget === target) return;
  details.dataset.aaTarget = target;

  const reveal = getReveal(details);

  if (!reveal || prefersReducedMotion()) {
    pendingReveal.get(details)?.();
    if (open) details.setAttribute('open', '');
    else details.removeAttribute('open');
    delete details.dataset.aaTarget;
    return;
  }

  if (open) {
    details.setAttribute('open', '');
    // Force a paint at the collapsed size, then release to the open size so the
    // grid-template-rows transition has a start value to animate from.
    reveal.style.gridTemplateRows = '0fr';
    void reveal.offsetHeight;
    reveal.style.gridTemplateRows = '1fr';
    afterReveal(details, reveal, () => {
      if (details.dataset.aaTarget !== 'open') return;
      reveal.style.gridTemplateRows = '';
      delete details.dataset.aaTarget;
    });
  } else {
    reveal.style.gridTemplateRows = '1fr';
    void reveal.offsetHeight;
    reveal.style.gridTemplateRows = '0fr';
    afterReveal(details, reveal, () => {
      if (details.dataset.aaTarget !== 'closed') return;
      details.removeAttribute('open');
      reveal.style.gridTemplateRows = '';
      delete details.dataset.aaTarget;
    });
  }
}

/**
 * Exclusive mode collapses a section above the tapped one, which pulls the
 * tapped header up. That's fine while it stays on screen; only if the collapse
 * pushes it off the top do we gently bring it back — a single scroll after the
 * transition settles, never a per-frame tug-of-war. If the reader starts
 * scrolling in that window we yield to them entirely.
 */
function keepHeaderVisible(details: HTMLElement): void {
  const summary = details.querySelector('summary');
  if (!summary) return;

  let userScrolled = false;
  const onUserScroll = (): void => {
    userScrolled = true;
  };
  const options: AddEventListenerOptions = { passive: true, once: true };
  window.addEventListener('wheel', onUserScroll, options);
  window.addEventListener('touchmove', onUserScroll, options);

  const reveal = getReveal(details);
  const settleMs = (reveal ? revealDurationMs(reveal) : 250) + 30;

  window.setTimeout(() => {
    window.removeEventListener('wheel', onUserScroll);
    window.removeEventListener('touchmove', onUserScroll);
    if (userScrolled) return;
    if (summary.getBoundingClientRect().top < 0) {
      summary.scrollIntoView({
        block: 'start',
        behavior: prefersReducedMotion() ? 'auto' : 'smooth',
      });
    }
  }, settleMs);
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

// Exported so unit tests can drive the actions/getters directly; the
// Interactivity runtime imports this module for its `store()` side effect.
export const productTabsStore = store<ProductTabsStore>(
  'aggressive-apparel/product-tabs',
  {
    state: {
      get isActiveTab(): boolean {
        return state.activeTab === getContext<TabContext>().tabIndex;
      },
      get isPanelVisible(): boolean {
        return state.activeTab === getContext<TabContext>().tabIndex;
      },
      get tabTabindex(): string {
        return state.activeTab === getContext<TabContext>().tabIndex
          ? '0'
          : '-1';
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
        const details = ref?.closest('details') as HTMLElement | null;
        if (!details) return;

        const willOpen = !isPanelOpen(details);
        const root = details.closest('.aa-product-info--accordion');

        // Exclusive mode: opening one panel closes the others. Independent mode
        // (the default) leaves siblings untouched, so nothing shifts.
        const exclusive = !!root && root.hasAttribute('data-aa-exclusive');
        if (willOpen && exclusive && root) {
          for (const sibling of root.querySelectorAll('details')) {
            const panel = sibling as HTMLElement;
            if (panel !== details && isPanelOpen(panel)) {
              setPanel(panel, false);
            }
          }
        }

        setPanel(details, willOpen);

        if (willOpen && exclusive) {
          keepHeaderVisible(details);
        }
      }),

      selectTab(): void {
        const ctx = getContext<TabContext>();
        state.activeTab = ctx.tabIndex;
        const { ref } = getElement();
        if (ref) ref.focus();
      },

      handleTabKeydown(event: KeyboardEvent): void {
        const tabNav = (event.target as HTMLElement).closest(
          '[role="tablist"]'
        );
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

        // On small screens the sidebar collapses to a sticky horizontal bar.
        // Keep the active link scrolled into view as the reader moves through
        // sections, so the highlight is never off-screen in the overflow rail.
        const sidebar = ref.querySelector(
          '.aa-product-info__sidebar'
        ) as HTMLElement | null;
        const reducedMotion = window.matchMedia(
          '(prefers-reduced-motion: reduce)'
        ).matches;

        const syncNavIntoView = (sectionId: string): void => {
          if (!sidebar || sidebar.scrollWidth <= sidebar.clientWidth) return;
          const link = sidebar.querySelector(
            `.aa-product-info__nav-link[href="#${CSS.escape(sectionId)}"]`
          ) as HTMLElement | null;
          if (!link) return;
          const barRect = sidebar.getBoundingClientRect();
          const linkRect = link.getBoundingClientRect();
          const offset =
            linkRect.left +
            linkRect.width / 2 -
            (barRect.left + barRect.width / 2);
          sidebar.scrollBy({
            left: offset,
            behavior: reducedMotion ? 'auto' : 'smooth',
          });
        };

        const setActiveSection = (sectionId: string): void => {
          if (state.activeSection === sectionId) return;
          state.activeSection = sectionId;
          syncNavIntoView(sectionId);
        };

        const observer = new IntersectionObserver(
          (entries: IntersectionObserverEntry[]) => {
            for (const entry of entries) {
              if (entry.isIntersecting && (entry.target as HTMLElement).id) {
                setActiveSection((entry.target as HTMLElement).id);
              }
            }
          },
          { rootMargin: '-20% 0px -60% 0px', threshold: 0 }
        );

        sections.forEach(section => observer.observe(section));
      },
    },
  }
);

const { state } = productTabsStore;
