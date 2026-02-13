/**
 * Product Info Display — Interactivity API Module
 *
 * Handles accordion toggle animation, modern tab switching with
 * keyboard navigation, and scrollspy with IntersectionObserver.
 *
 * @since 1.17.0
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

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

    get isActiveNav() {
      const ctx = getContext();
      return state.activeSection === ctx.sectionId;
    },
  },

  actions: {
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
     * Scrollspy: set up IntersectionObserver on mount.
     */
    initScrollspy() {
      const { ref } = getElement();
      if (!ref) return;

      const sections = ref.querySelectorAll('.aa-product-info__section');
      if (!sections.length) return;

      // Set initial active section.
      if (sections[0] && sections[0].id) {
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
