/**
 * Product Info Display — Interactivity API Module
 *
 * Handles accordion toggle animation, modern tab switching with
 * keyboard navigation, and scrollspy with IntersectionObserver.
 *
 * @since 1.17.0
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

/* ---------------------------------------------------------------
 * Accordion animation helpers
 * ------------------------------------------------------------- */

const ANIM_DURATION = 200;

/**
 * Animate a panel's height + padding between natural size and zero.
 *
 * @param {HTMLElement} content The .aa-product-info__content element.
 * @param {boolean}     open    True = expand, false = collapse.
 * @return {Animation}
 */
function animatePanel(content, open) {
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
 *
 * @param {HTMLDetailsElement} details
 */
function closeDetails(details) {
  const content = details.querySelector('.aa-product-info__content');
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

const { state, actions, callbacks } = store('aggressive-apparel/product-tabs', {
  state: {
    get isActiveTab() {
      const ctx = getContext();
      return state.activeTab === ctx.tabIndex;
    },

    get isPanelVisible() {
      const ctx = getContext();
      return state.activeTab === ctx.tabIndex;
    },

    get tabTabindex() {
      const ctx = getContext();
      return state.activeTab === ctx.tabIndex ? '0' : '-1';
    },

    /**
     * Returns "true"/"false" string for aria-selected.
     * data-wp-bind-- removes attributes for falsy values,
     * but ARIA spec requires aria-selected="false" on inactive tabs.
     */
    get ariaSelected() {
      const ctx = getContext();
      return state.activeTab === ctx.tabIndex ? 'true' : 'false';
    },

    get isActiveNav() {
      const ctx = getContext();
      return state.activeSection === ctx.sectionId;
    },

    /**
     * Returns "true"/"false" string for aria-current on scrollspy nav.
     */
    get ariaCurrent() {
      const ctx = getContext();
      return state.activeSection === ctx.sectionId ? 'true' : 'false';
    },
  },

  actions: {
    /**
     * Accordion: animated open/close with single-open behaviour.
     * Intercepts the <summary> click so we can animate before
     * the browser toggles the <details> open state.
     *
     * @param {Event} event
     */
    toggleAccordion(event) {
      event.preventDefault();
      const { ref } = getElement();
      const details = ref.closest('details');
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
              closeDetails(sibling);
            }
          }
        }

        // Open and animate in.
        details.setAttribute('open', '');
        const content = details.querySelector('.aa-product-info__content');
        if (content) {
          const anim = animatePanel(content, true);
          anim.onfinish = () => {
            content.style.overflow = '';
          };
        }
      }
    },

    /**
     * Modern tabs: select a tab by click.
     */
    selectTab() {
      const ctx = getContext();
      state.activeTab = ctx.tabIndex;

      const { ref } = getElement();
      if (ref) {
        ref.focus();
      }
    },

    /**
     * Modern tabs: keyboard navigation (Arrow keys, Home, End).
     *
     * @param {KeyboardEvent} event
     */
    handleTabKeydown(event) {
      const ctx = getContext();
      const tabNav = event.target.closest('[role="tablist"]');
      if (!tabNav) return;

      const tabs = Array.from(tabNav.querySelectorAll('[role="tab"]'));
      const count = tabs.length;
      let newIndex = state.activeTab;

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
     *
     * @param {Event} event
     */
    scrollToSection(event) {
      event.preventDefault();
      const ctx = getContext();
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
    initHashNav() {
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
    initScrollspy() {
      const { ref } = getElement();
      if (!ref) return;

      const sections = ref.querySelectorAll('.aa-product-info__section');
      if (!sections.length) return;

      // Set initial active section (prefer URL hash if it matches).
      const hash = window.location.hash?.slice(1);
      const hashTarget = hash && ref.querySelector(`#${CSS.escape(hash)}`);
      if (hashTarget) {
        state.activeSection = hashTarget.id;
        requestAnimationFrame(() => {
          hashTarget.scrollIntoView({ block: 'start', behavior: 'auto' });
        });
      } else if (sections[0] && sections[0].id) {
        state.activeSection = sections[0].id;
      }

      const observer = new IntersectionObserver(
        entries => {
          for (const entry of entries) {
            if (entry.isIntersecting && entry.target.id) {
              state.activeSection = entry.target.id;
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
