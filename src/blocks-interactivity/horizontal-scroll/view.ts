/**
 * Horizontal Scroll Block — Interactivity API entry point.
 *
 * Runtime behavior lives in isolated controllers; this file only connects the
 * block context and element to the WordPress Interactivity API store.
 */

/// <reference types="@wordpress/interactivity" />
import { store, getContext, getElement } from '@wordpress/interactivity';
import { clamp } from './logic';
import { setupHorizontalScroll, type HScrollContext } from './runtime';

interface HScrollStore {
  callbacks: {
    init: () => void | (() => void);
    progressStyle: () => string;
  };
}

store<HScrollStore>('aggressive-apparel/horizontal-scroll', {
  callbacks: {
    init() {
      const context = getContext<HScrollContext>();
      const { ref } = getElement();

      if (!(ref instanceof HTMLElement)) return;
      return setupHorizontalScroll(ref, context);
    },

    progressStyle() {
      const context = getContext<HScrollContext>();
      const progress = clamp(Number(context.progress) || 0, 0, 100);

      return `transform: scaleX(${progress / 100})`;
    },
  },
});
