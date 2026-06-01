/**
 * Overlay Controller
 *
 * Shared open/close behavior for modal overlays: scroll lock, focus trap,
 * transitionend + hidden attribute pattern.
 *
 * @package Aggressive_Apparel
 * @since 1.77.0
 */

import { lockScroll, unlockScroll } from '@aggressive-apparel/scroll-lock';
import { setupFocusTrap } from '@aggressive-apparel/helpers';

const FALLBACK_MS = 350;

export interface PrepareOverlayOpenOptions {
  /** When false, caller toggles is-open via Interactivity API (data-wp-class). */
  manageOpenClass?: boolean;
  lockScroll?: boolean;
}

export interface OpenOverlayOptions {
  shell: HTMLElement;
  panel: HTMLElement;
  triggerElement?: HTMLElement | null;
  focusSelector?: string;
}

export interface CloseOverlayOptions {
  shell: HTMLElement;
  panel: HTMLElement;
  focusTrapCleanup?: (() => void) | null;
  triggerElement?: HTMLElement | null;
  isStillOpen?: () => boolean;
  onFinish?: () => void;
  /** When false, caller toggles is-open via Interactivity API (data-wp-class). */
  manageOpenClass?: boolean;
  /** Panel property that signals close animation completion. */
  transitionProperty?: 'opacity' | 'transform';
}

/**
 * Prepare shell for open animation (hidden removal, reflow, is-open class, scroll lock).
 */
export function prepareOverlayOpen(
  shell: HTMLElement,
  options: PrepareOverlayOpenOptions = {}
): void {
  const { manageOpenClass = true, lockScroll: shouldLockScroll = true } =
    options;

  shell.hidden = false;
  void shell.offsetHeight;

  if (manageOpenClass) {
    shell.classList.add('is-open');
  }

  if (shouldLockScroll) {
    lockScroll();
  }
}

/**
 * Install focus trap and move focus into the overlay panel.
 */
export function activateOverlayFocus(options: OpenOverlayOptions): () => void {
  const { panel, focusSelector } = options;
  const cleanup = setupFocusTrap(panel);

  requestAnimationFrame(() => {
    const focusTarget = focusSelector
      ? panel.querySelector<HTMLElement>(focusSelector)
      : panel.querySelector<HTMLElement>(
          'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );

    focusTarget?.focus();
  });

  return cleanup;
}

/**
 * Close overlay with transitionend on panel opacity, then unlock scroll and hide shell.
 */
export function closeOverlay(options: CloseOverlayOptions): void {
  const {
    shell,
    panel,
    focusTrapCleanup,
    triggerElement,
    isStillOpen,
    onFinish,
    manageOpenClass = true,
    transitionProperty = 'opacity',
  } = options;

  focusTrapCleanup?.();

  if (triggerElement) {
    triggerElement.focus();
  }

  if (manageOpenClass) {
    shell.classList.remove('is-open');
  }

  let done = false;
  const finish = (): void => {
    if (done || isStillOpen?.()) {
      return;
    }
    done = true;
    unlockScroll();
    shell.hidden = true;
    onFinish?.();
  };

  panel.addEventListener(
    'transitionend',
    (event: Event) => {
      if ((event as TransitionEvent).propertyName === transitionProperty) {
        finish();
      }
    },
    { once: true }
  );

  setTimeout(finish, FALLBACK_MS);
}
