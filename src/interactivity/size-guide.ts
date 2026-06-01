/**
 * Size Guide — Interactivity API Store.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

import { store, getContext, getElement } from '@wordpress/interactivity';
import {
  prepareOverlayOpen,
  activateOverlayFocus,
  closeOverlay,
} from '@aggressive-apparel/use-overlay';

interface SizeGuideContext {
  isOpen: boolean;
}

interface SizeGuideStore {
  state: Record<string, never>;
  actions: {
    toggle: () => void;
    close: () => void;
  };
}

let triggerElement: HTMLElement | null = null;
let focusTrapCleanup: (() => void) | null = null;

function getSizeGuideRoot(ref: Element | null): HTMLElement | null {
  return (
    ref?.closest<HTMLElement>(
      '[data-wp-interactive="aggressive-apparel/size-guide"]'
    ) ?? null
  );
}

function getSizeGuideOverlay(ref: Element | null): HTMLElement | null {
  return (
    getSizeGuideRoot(ref)?.querySelector<HTMLElement>(
      '.aggressive-apparel-size-guide__overlay'
    ) ?? null
  );
}

store<SizeGuideStore>('aggressive-apparel/size-guide', {
  actions: {
    toggle(): void {
      const ctx = getContext<SizeGuideContext>();
      const { ref } = getElement();

      if (!ctx.isOpen) {
        triggerElement = document.activeElement as HTMLElement | null;

        const overlay = getSizeGuideOverlay(ref);

        if (overlay) {
          prepareOverlayOpen(overlay, { manageOpenClass: false });
        }

        ctx.isOpen = true;

        const modal = overlay?.querySelector<HTMLElement>(
          '.aggressive-apparel-size-guide__modal'
        );

        if (modal) {
          focusTrapCleanup = activateOverlayFocus({
            shell: overlay!,
            panel: modal,
            focusSelector: '.aggressive-apparel-size-guide__close',
          });
        }
      } else {
        const { actions } = store<SizeGuideStore>(
          'aggressive-apparel/size-guide'
        );
        actions.close();
      }
    },

    close(): void {
      const ctx = getContext<SizeGuideContext>();
      const { ref } = getElement();

      ctx.isOpen = false;

      const overlay = getSizeGuideOverlay(ref);
      const modal = overlay?.querySelector<HTMLElement>(
        '.aggressive-apparel-size-guide__modal'
      );

      if (!overlay || !modal) {
        focusTrapCleanup?.();
        focusTrapCleanup = null;
        triggerElement = null;
        return;
      }

      closeOverlay({
        shell: overlay,
        panel: modal,
        focusTrapCleanup,
        triggerElement,
        manageOpenClass: false,
        isStillOpen: () => ctx.isOpen,
      });

      focusTrapCleanup = null;
      triggerElement = null;
    },
  },
});
