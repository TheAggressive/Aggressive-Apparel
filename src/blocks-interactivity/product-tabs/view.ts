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

/**
 * Keep `anchor` visually stationary while sibling panels collapse. When a
 * sibling accordion panel above the clicked section collapses, the document
 * height above the anchor shrinks and would otherwise shove the clicked header
 * (and its freshly opened content) up and out of view. We compensate the scroll
 * position every frame so the tapped header stays put.
 *
 * The loop runs on a fixed deadline (a little past the collapse animation)
 * rather than keying off `Animation.finished`: WAAPI reverts the panel to its
 * natural height at the finish frame (fill: none) and the actual collapse
 * happens afterwards when `closeDetails` removes the `open` attribute, so
 * stopping on `finished` would disarm one frame too early and let the panel
 * snap the anchor out of view.
 *
 * The pin yields immediately if the user tries to scroll during the window. We
 * can't watch the `scroll` event to detect that — the pin drives `scrollBy`
 * itself, which would fire it — so we listen for the input events that signal
 * user intent (`wheel`, `touchmove`, scroll keys) and bail on the first one.
 */
const SCROLL_KEYS = new Set([
  'ArrowUp',
  'ArrowDown',
  'PageUp',
  'PageDown',
  'Home',
  'End',
  ' ',
  'Spacebar',
]);

function pinScrollDuring(anchor: HTMLElement, shouldPin: boolean): void {
  if (!shouldPin) return;

  const startTop = anchor.getBoundingClientRect().top;
  const deadline = performance.now() + ANIM_DURATION + 120;
  let cancelled = false;

  const listenerOptions: AddEventListenerOptions = { passive: true };
  const onWheelOrTouch = (): void => {
    cancelled = true;
  };
  const onKeydown = (event: KeyboardEvent): void => {
    if (SCROLL_KEYS.has(event.key)) cancelled = true;
  };

  const cleanup = (): void => {
    window.removeEventListener('wheel', onWheelOrTouch, listenerOptions);
    window.removeEventListener('touchmove', onWheelOrTouch, listenerOptions);
    window.removeEventListener('keydown', onKeydown, listenerOptions);
  };

  window.addEventListener('wheel', onWheelOrTouch, listenerOptions);
  window.addEventListener('touchmove', onWheelOrTouch, listenerOptions);
  window.addEventListener('keydown', onKeydown, listenerOptions);

  const correct = (): void => {
    const delta = anchor.getBoundingClientRect().top - startTop;
    if (delta) window.scrollBy(0, delta);
  };

  const tick = (): void => {
    if (cancelled) {
      cleanup();
      return;
    }
    correct();
    if (performance.now() < deadline) {
      requestAnimationFrame(tick);
    } else {
      cleanup();
    }
  };
  requestAnimationFrame(tick);
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
        const details = ref?.closest('details') as HTMLDetailsElement | null;
        if (!details) return;

        if (details.open) {
          closeDetails(details);
        } else {
          const parent = details.closest('.aa-product-info--accordion');
          let closingAbove = false;
          if (parent) {
            for (const sibling of parent.querySelectorAll('details[open]')) {
              if (sibling !== details) {
                closeDetails(sibling as HTMLDetailsElement);
                // Only siblings that sit above the tapped section shift it.
                if (
                  sibling.compareDocumentPosition(details) &
                  Node.DOCUMENT_POSITION_FOLLOWING
                ) {
                  closingAbove = true;
                }
              }
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
          // Hold the tapped header in place while siblings above collapse.
          pinScrollDuring(details, closingAbove);
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
