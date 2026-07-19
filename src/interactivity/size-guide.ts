/**
 * Size Guide — Interactivity API Store.
 *
 * Close sequence is two-phase: panel slides off-screen (is-closing), then the
 * scrim fades (is-backdrop-closing). CSS classes drive both phases synchronously
 * so there is no hang waiting on Interactivity state to flush.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

import { store, getContext, getElement } from '@wordpress/interactivity';
import { unlockScroll } from '@aggressive-apparel/scroll-lock';
import {
  prepareOverlayOpen,
  activateOverlayFocus,
} from '@aggressive-apparel/use-overlay';

interface SizeGuideContext {
  isOpen: boolean;
}

interface SizeGuideStore {
  state: Record<string, never>;
  actions: {
    toggle: () => void;
    close: () => void;
    handleKeydown: (event?: Event) => void;
  };
}

const PANEL_EXIT_FALLBACK_MS = 200;
const BACKDROP_FADE_FALLBACK_MS = 200;

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

function prefersReducedMotion(): boolean {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function readTransitionMs(element: HTMLElement, property: string): number {
  const raw = getComputedStyle(element).transitionDuration;
  const parts = raw.split(',').map(part => part.trim());

  if (parts.length === 1) {
    return parseDurationMs(parts[0]);
  }

  const properties = getComputedStyle(element)
    .transitionProperty.split(',')
    .map(part => part.trim());

  const index = properties.indexOf(property);
  if (index >= 0 && parts[index]) {
    return parseDurationMs(parts[index]);
  }

  return parseDurationMs(parts[0]);
}

function parseDurationMs(value: string): number {
  const trimmed = value.trim();
  if (trimmed.endsWith('ms')) {
    return parseFloat(trimmed) || 0;
  }
  if (trimmed.endsWith('s')) {
    return (parseFloat(trimmed) || 0) * 1000;
  }
  return parseFloat(trimmed) || 0;
}

/**
 * Two-phase close: panel exit animation, then backdrop fade, then teardown.
 */
function closeSizeGuide(
  ctx: SizeGuideContext,
  overlay: HTMLElement,
  modal: HTMLElement
): void {
  if (overlay.classList.contains('is-closing')) {
    return;
  }

  focusTrapCleanup?.();
  focusTrapCleanup = null;
  triggerElement?.focus();
  triggerElement = null;

  const backdrop = overlay.querySelector<HTMLElement>(
    '.aggressive-apparel-size-guide__backdrop'
  );

  let finished = false;

  const finish = (): void => {
    if (finished) {
      return;
    }
    finished = true;
    unlockScroll();
    overlay.hidden = true;
    overlay.classList.remove('is-closing', 'is-backdrop-closing');
    ctx.isOpen = false;
  };

  if (prefersReducedMotion()) {
    ctx.isOpen = false;
    finish();
    return;
  }

  // Phase 1 — panel slides off-screen; is-open keeps the scrim fully up.
  overlay.classList.add('is-closing');
  void modal.offsetHeight;

  let panelDone = false;

  const startBackdropFade = (): void => {
    if (panelDone) {
      return;
    }
    panelDone = true;

    // Phase 2 — hide panel and fade scrim via CSS. Keep is-open true so the
    // panel does not snap back to its resting translateY before hidden is set.
    overlay.classList.add('is-backdrop-closing');
    void backdrop?.offsetHeight;

    if (!backdrop) {
      finish();
      return;
    }

    let backdropDone = false;
    const fallbackMs =
      (readTransitionMs(backdrop, 'opacity') || BACKDROP_FADE_FALLBACK_MS) + 50;

    const onBackdropEnd = (event: Event): void => {
      if (backdropDone) {
        return;
      }
      if ((event as TransitionEvent).propertyName === 'opacity') {
        backdropDone = true;
        finish();
      }
    };

    backdrop.addEventListener('transitionend', onBackdropEnd);
    window.setTimeout(() => {
      backdrop.removeEventListener('transitionend', onBackdropEnd);
      if (!backdropDone) {
        finish();
      }
    }, fallbackMs);
  };

  const panelFallbackMs =
    (Math.max(
      readTransitionMs(modal, 'transform'),
      readTransitionMs(modal, 'opacity')
    ) || PANEL_EXIT_FALLBACK_MS) + 50;

  let panelTransformDone = false;
  let panelOpacityDone = false;

  const tryStartBackdropFade = (): void => {
    if (!panelTransformDone || !panelOpacityDone) {
      return;
    }
    modal.removeEventListener('transitionend', onPanelEnd);
    startBackdropFade();
  };

  const onPanelEnd = (event: Event): void => {
    const propertyName = (event as TransitionEvent).propertyName;

    if (propertyName === 'transform') {
      panelTransformDone = true;
    } else if (propertyName === 'opacity') {
      panelOpacityDone = true;
    } else {
      return;
    }

    tryStartBackdropFade();
  };

  modal.addEventListener('transitionend', onPanelEnd);
  window.setTimeout(() => {
    modal.removeEventListener('transitionend', onPanelEnd);
    startBackdropFade();
  }, panelFallbackMs);
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
          overlay.classList.remove('is-closing', 'is-backdrop-closing');
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

      const overlay = getSizeGuideOverlay(ref);
      const modal = overlay?.querySelector<HTMLElement>(
        '.aggressive-apparel-size-guide__modal'
      );

      if (!overlay || !modal) {
        focusTrapCleanup?.();
        focusTrapCleanup = null;
        triggerElement = null;
        ctx.isOpen = false;
        return;
      }

      closeSizeGuide(ctx, overlay, modal);
    },

    handleKeydown(event?: Event): void {
      const keyboardEvent = event as KeyboardEvent | undefined;
      if (keyboardEvent?.key !== 'Escape') {
        return;
      }

      const ctx = getContext<SizeGuideContext>();
      if (!ctx.isOpen) {
        return;
      }

      keyboardEvent.preventDefault();
      const { actions } = store<SizeGuideStore>(
        'aggressive-apparel/size-guide'
      );
      actions.close();
    },
  },
});
