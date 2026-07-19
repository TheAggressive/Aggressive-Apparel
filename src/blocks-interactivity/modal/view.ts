/// <reference types="@wordpress/interactivity" />

/**
 * Modal Block — Interactivity API store.
 *
 * Follows the theme's established modal pattern:
 *  - lockScroll() immediately on open.
 *  - hidden attribute managed imperatively (remove + force reflow on open,
 *    set inside finish() on close).
 *  - unlockScroll() deferred to transitionend on the dialog (propertyName === 'opacity'),
 *    with a safety setTimeout fallback.
 *  - Return focus to the element that triggered the modal.
 *  - External trigger elements (.modal-trigger-{id}) are bound in actions.init()
 *    so the ob_start output-buffering hack is not needed.
 *  - aria-live announcer updated on open/close for screen readers that do not
 *    fire on programmatic focus alone.
 *
 * @package Aggressive_Apparel
 */

import { store, getContext } from '@wordpress/interactivity';
import { lockScroll, unlockScroll } from '../../interactivity/scroll-lock';
import { setupFocusTrap } from '../../interactivity/helpers';
import {
  buildExitAnimation,
  canRestoreFocus,
  isExitIntentDismissed,
  markExitIntentDismissed,
  shouldSkipOpenOnLoadOnce,
} from './logic';

// ── Types ────────────────────────────────────────────────────────────────────

interface ModalState {
  isOpen: boolean;
  openOnLoad: boolean;
  openOnLoadOnce: boolean;
  animationDuration: number;
  exitIntentTrigger: boolean;
  exitIntentReshowDays: number;
  scrollDepthTrigger: boolean;
  scrollDepthPercent: number;
}

interface ModalContext {
  id: string;
}

interface ModalStore {
  state: {
    modals: Record<string, ModalState>;
  };
  actions: {
    init: () => void;
    openModal: () => void;
    closeModal: () => void;
  };
}

// ── Module-level references ───────────────────────────────────────────────────

/** Safety buffer added to animationDuration before the finish() fallback fires. */
const FALLBACK_BUFFER_MS = 50;

interface ModalRefs {
  /** Element that had focus before the modal opened; restored on close. */
  triggerElement: HTMLElement | null;
  /** Cleanup function returned by setupFocusTrap(); called on close. */
  focusTrapCleanup: (() => void) | null;
  /** Pending close fallback, cancelled when the modal reopens mid-transition. */
  closeTimer: ReturnType<typeof setTimeout> | null;
  /** Active transition listener, retained so rapid reopen can remove it. */
  transitionEndHandler: ((event: TransitionEvent) => void) | null;
  /** Whether an exit transition is currently in progress. */
  isClosing: boolean;
  /** State collection used by the global Escape handler. */
  modalsState: Record<string, ModalState>;
}

/** Per-modal focus and trap state, keyed by modal ID. */
const modalRefs = new Map<string, ModalRefs>();

/** Open order ensures Escape dismisses only the top-most modal. */
const modalStack: string[] = [];

/** Avoid duplicate listeners if an Interactivity API root initializes twice. */
const boundExternalTriggers = new WeakMap<HTMLElement, Set<string>>();
const initializedModalIds = new Set<string>();

// ── DOM helpers ───────────────────────────────────────────────────────────────

function getShell(id: string): HTMLElement | null {
  return (
    (Array.from(
      document.getElementsByClassName(
        'wp-block-aggressive-apparel-modal__shell'
      )
    ).find(el => (el as HTMLElement).dataset.modalId === id) as
      | HTMLElement
      | undefined) ?? null
  );
}

function getDialog(id: string): HTMLElement | null {
  const dialog = document.getElementById(id);
  return dialog?.classList.contains('wp-block-aggressive-apparel-modal__dialog')
    ? dialog
    : null;
}

function getAnnouncer(id: string): HTMLElement | null {
  const shell = getShell(id);
  const announcer = shell?.parentElement?.querySelector<HTMLElement>(
    '.wp-block-aggressive-apparel-modal__announcer'
  );
  return announcer?.dataset.modalId === id ? announcer : null;
}

function getExternalTriggers(id: string): HTMLElement[] {
  const classHolders = Array.from(
    document.getElementsByClassName(`modal-trigger-${id}`)
  ).filter((element): element is HTMLElement => element instanceof HTMLElement);
  const targets = new Set<HTMLElement>();

  classHolders.forEach(holder => {
    if (
      holder.matches(
        'button, a[href], input:not([type="hidden"]), select, textarea'
      )
    ) {
      targets.add(holder);
      return;
    }

    const nestedControls = holder.querySelectorAll<HTMLElement>(
      'button, a[href], input:not([type="hidden"]), select, textarea'
    );
    if (nestedControls.length > 0) {
      nestedControls.forEach(control => targets.add(control));
    } else {
      targets.add(holder);
    }
  });

  return Array.from(targets);
}

function getBuiltInTrigger(id: string): HTMLElement | null {
  return (
    getShell(id)?.parentElement?.querySelector<HTMLElement>(
      '.wp-block-aggressive-apparel-modal__trigger'
    ) ?? null
  );
}

/**
 * Keep built-in and external trigger ARIA in sync.
 */
function syncTriggerExpanded(id: string, isOpen: boolean): void {
  const expanded = isOpen ? 'true' : 'false';
  getBuiltInTrigger(id)?.setAttribute('aria-expanded', expanded);
  getExternalTriggers(id).forEach(el => {
    el.setAttribute('aria-expanded', expanded);
  });
}

/**
 * Bind click / keyboard open behavior and dialog ARIA on external triggers.
 */
function bindExternalTrigger(
  el: HTMLElement,
  id: string,
  modalsState: Record<string, ModalState>
): void {
  const boundIds = boundExternalTriggers.get(el) ?? new Set<string>();
  if (boundIds.has(id)) return;
  boundIds.add(id);
  boundExternalTriggers.set(el, boundIds);

  el.setAttribute('aria-haspopup', 'dialog');
  el.setAttribute('aria-controls', id);
  el.setAttribute('aria-expanded', modalsState[id]?.isOpen ? 'true' : 'false');

  const isNativeControl =
    el instanceof HTMLButtonElement ||
    el instanceof HTMLInputElement ||
    el instanceof HTMLSelectElement ||
    el instanceof HTMLTextAreaElement ||
    (el instanceof HTMLAnchorElement && el.hasAttribute('href'));

  if (!isNativeControl) {
    if (!el.hasAttribute('role')) el.setAttribute('role', 'button');
    if (!el.hasAttribute('tabindex')) el.tabIndex = 0;
  }

  const open = (event: Event): void => {
    event.preventDefault();
    openModal(id, modalsState);
  };

  el.addEventListener('click', open);

  // Anchors use native Enter activation; Space is added for button parity.
  // Custom elements receive both Enter and Space activation.
  if (el.tagName !== 'BUTTON') {
    el.addEventListener('keydown', (event: KeyboardEvent) => {
      const isSpace = event.key === ' ' || event.key === 'Spacebar';
      const isCustomEnter = !isNativeControl && event.key === 'Enter';
      if ((!isSpace && !isCustomEnter) || event.repeat) {
        return;
      }
      event.preventDefault();
      openModal(id, modalsState);
    });
  }
}

// ── Exit animation engine ─────────────────────────────────────────────────────

let cachedDefaultEase: string | null = null;

/** Read theme motion token (--aa-ease-default) for inline exit transitions. */
function getDefaultEase(): string {
  if (cachedDefaultEase !== null) {
    return cachedDefaultEase;
  }

  const raw = getComputedStyle(document.documentElement)
    .getPropertyValue('--aa-ease-default')
    .trim();

  cachedDefaultEase = raw || 'cubic-bezier(0.4, 0, 0.2, 1)';
  return cachedDefaultEase;
}

function clearExitStyles(dialog: HTMLElement): void {
  dialog.style.removeProperty('transition');
  dialog.style.removeProperty('opacity');
  dialog.style.removeProperty('transform');
  dialog.style.removeProperty('filter');
}

function removeFromModalStack(id: string): void {
  let index = modalStack.lastIndexOf(id);
  while (index !== -1) {
    modalStack.splice(index, 1);
    index = modalStack.lastIndexOf(id);
  }
}

function cancelPendingClose(dialog: HTMLElement | null, refs: ModalRefs): void {
  if (refs.closeTimer !== null) {
    clearTimeout(refs.closeTimer);
    refs.closeTimer = null;
  }
  if (dialog && refs.transitionEndHandler) {
    dialog.removeEventListener('transitionend', refs.transitionEndHandler);
  }
  refs.transitionEndHandler = null;
  refs.isClosing = false;
  if (dialog) clearExitStyles(dialog);
}

// ── Core open / close ─────────────────────────────────────────────────────────

function openModal(id: string, modalsState: Record<string, ModalState>): void {
  if (!id || !modalsState[id]) return;

  // Avoid re-entrancy when already open (e.g. double Space on a trigger).
  if (modalsState[id].isOpen) return;

  const dialogEl = getDialog(id);
  let refs = modalRefs.get(id);
  if (refs?.isClosing) {
    // The existing scroll lock belongs to this modal; retain it across a
    // close/reopen race instead of incrementing the global lock counter.
    cancelPendingClose(dialogEl, refs);
    refs.triggerElement = document.activeElement as HTMLElement | null;
    refs.modalsState = modalsState;
  } else {
    refs = {
      triggerElement: document.activeElement as HTMLElement | null,
      focusTrapCleanup: null,
      closeTimer: null,
      transitionEndHandler: null,
      isClosing: false,
      modalsState,
    };
    modalRefs.set(id, refs);
    lockScroll();
  }

  removeFromModalStack(id);
  modalStack.push(id);
  document.dispatchEvent(new CustomEvent('aa:modal:open', { detail: { id } }));

  // Clear any stale exit-animation inline styles from a previous close so
  // the CSS enter animation starts from a clean state.
  if (dialogEl) clearExitStyles(dialogEl);

  const shell = getShell(id);
  if (shell) {
    shell.hidden = false;
    void shell.offsetHeight; // Force reflow so the browser captures the "before" state.
    shell.classList.add('is-open');
  }

  modalsState[id].isOpen = true;
  syncTriggerExpanded(id, true);

  // Announce to screen readers that do not fire on programmatic focus alone.
  const announcer = getAnnouncer(id);
  if (announcer) {
    announcer.textContent = ''; // Clear so re-opening re-triggers aria-live.
    requestAnimationFrame(() => {
      if (modalsState[id]?.isOpen) {
        announcer.textContent = announcer.dataset.label ?? 'Dialog opened';
      }
    });
  }

  requestAnimationFrame(() => {
    const dialog = getDialog(id);
    const shellEl = getShell(id);
    if (dialog && shellEl && modalsState[id]?.isOpen) {
      const currentRefs = modalRefs.get(id);
      // Trap on the shell so outside-* close buttons stay in the Tab cycle.
      if (currentRefs && !currentRefs.focusTrapCleanup) {
        currentRefs.focusTrapCleanup = setupFocusTrap(shellEl);
      }
      // Focus the dialog (tabindex="-1") so screen readers announce
      // "Dialog: [label]" before the user tabs into content.
      dialog.focus();
    }
  });
}

function closeModal(id: string, modalsState: Record<string, ModalState>): void {
  if (!id || !modalsState[id]?.isOpen) return;

  modalsState[id].isOpen = false;
  syncTriggerExpanded(id, false);
  document.dispatchEvent(new CustomEvent('aa:modal:close', { detail: { id } }));

  const announcer = getAnnouncer(id);
  if (announcer) announcer.textContent = '';

  const refs = modalRefs.get(id);
  if (refs) refs.isClosing = true;

  const duration = modalsState[id].animationDuration ?? 300;
  const shell = getShell(id);
  const dialog = getDialog(id);

  // Remove is-open to dismiss backdrop and shell while the exit animation plays.
  if (shell) shell.classList.remove('is-open');

  // Apply exit animation via inline styles — completely independent of the CSS
  // enter-animation classes, so enter and exit can be mixed freely.
  const exitName = dialog?.dataset.exitAnimation ?? 'fade';
  const reducedMotion = window.matchMedia(
    '(prefers-reduced-motion: reduce)'
  ).matches;
  const effectiveDuration = reducedMotion || exitName === 'none' ? 0 : duration;

  if (dialog) {
    const { transition, styles } = buildExitAnimation(
      exitName,
      effectiveDuration,
      getDefaultEase()
    );
    dialog.style.transition = transition;
    Object.assign(dialog.style, styles);
  }

  let done = false;
  const finish = (): void => {
    if (done) return;
    done = true;
    if (refs?.closeTimer !== null && refs?.closeTimer !== undefined) {
      clearTimeout(refs.closeTimer);
      refs.closeTimer = null;
    }
    if (dialog && refs?.transitionEndHandler) {
      dialog.removeEventListener('transitionend', refs.transitionEndHandler);
      refs.transitionEndHandler = null;
    }
    // Clear inline exit styles so the next open starts from clean CSS state.
    if (dialog) clearExitStyles(dialog);
    if (shell) shell.hidden = true;

    if (refs?.focusTrapCleanup) {
      refs.focusTrapCleanup();
      refs.focusTrapCleanup = null;
    }

    const returnFocus = refs?.triggerElement ?? null;
    if (canRestoreFocus(returnFocus)) {
      returnFocus.focus({ preventScroll: true });
    }

    unlockScroll();
    removeFromModalStack(id);
    if (modalRefs.get(id) === refs) modalRefs.delete(id);
  };

  if (dialog) {
    const handleTransitionEnd = (event: TransitionEvent): void => {
      if (event.target === dialog && event.propertyName === 'opacity') finish();
    };
    if (refs) refs.transitionEndHandler = handleTransitionEnd;
    dialog.addEventListener('transitionend', handleTransitionEnd);
  }

  // Safety fallback: fires if transitionend never occurs (reduced motion, no CSS, etc.).
  const closeTimer = setTimeout(finish, effectiveDuration + FALLBACK_BUFFER_MS);
  if (refs) refs.closeTimer = closeTimer;
}

/** Escape remains reliable even if focus is moved outside the modal. */
function handleDocumentKeydown(event: KeyboardEvent): void {
  if (event.key !== 'Escape' || modalStack.length === 0) return;

  const id = modalStack[modalStack.length - 1];
  const refs = modalRefs.get(id);
  if (!refs) return;

  event.preventDefault();
  event.stopPropagation();
  if (refs.modalsState[id]?.isOpen) {
    closeModal(id, refs.modalsState);
  }
}

document.addEventListener('keydown', handleDocumentKeydown, true);

// ── Auto-trigger registry ─────────────────────────────────────────────────────
// Add entries here to wire up new automatic open triggers without touching init().

type TriggerSetup = (id: string, modals: Record<string, ModalState>) => void;

const AUTO_TRIGGERS: Array<{ flag: keyof ModalState; setup: TriggerSetup }> = [
  { flag: 'exitIntentTrigger', setup: setupExitIntent },
  { flag: 'scrollDepthTrigger', setup: setupScrollDepthTrigger },
];

// ── Exit intent detection ─────────────────────────────────────────────────────

function setupExitIntent(
  id: string,
  modalsState: Record<string, ModalState>
): void {
  const modal = modalsState[id];
  if (!modal) return;

  if (isExitIntentDismissed(id, modal.exitIntentReshowDays)) return;

  let triggered = false;

  // Arm after 2 seconds to avoid false-triggering on page load.
  let armed = false;
  setTimeout(() => {
    armed = true;
  }, 2000);

  // Desktop: cursor leaving the viewport through the top edge (toward browser chrome).
  // relatedTarget === null confirms the cursor left the document entirely.
  document.addEventListener('mouseout', (e: MouseEvent) => {
    if (armed && !triggered && e.relatedTarget === null && e.clientY <= 0) {
      triggered = true;
      openModal(id, modalsState);
      markExitIntentDismissed(id);
    }
  });
}

// ── Scroll depth detection ────────────────────────────────────────────────────

function setupScrollDepthTrigger(
  id: string,
  modalsState: Record<string, ModalState>
): void {
  const modal = modalsState[id];
  if (!modal) return;

  const percent = modal.scrollDepthPercent ?? 50;
  let triggered = false;

  const handleScroll = (): void => {
    if (triggered) return;
    const scrolled = window.scrollY + window.innerHeight;
    const total = document.documentElement.scrollHeight;
    if ((scrolled / total) * 100 >= percent) {
      triggered = true;
      window.removeEventListener('scroll', handleScroll);
      openModal(id, modalsState);
    }
  };

  window.addEventListener('scroll', handleScroll, { passive: true });
  requestAnimationFrame(handleScroll);
}

// ── Store ─────────────────────────────────────────────────────────────────────

const { state } = store<ModalStore>('aggressive-apparel/modal', {
  state: {
    modals: {},
  },
  actions: {
    /** Called via data-wp-init. Binds external triggers and handles openOnLoad. */
    init(): void {
      const { id } = getContext<ModalContext>();
      if (!id || !state.modals[id]) return;

      getBuiltInTrigger(id)?.setAttribute('aria-expanded', 'false');
      getExternalTriggers(id).forEach(el =>
        bindExternalTrigger(el, id, state.modals)
      );

      if (initializedModalIds.has(id)) return;
      initializedModalIds.add(id);

      if (state.modals[id].openOnLoad) {
        if (!shouldSkipOpenOnLoadOnce(id, state.modals[id].openOnLoadOnce)) {
          openModal(id, state.modals);
        }
      }

      for (const { flag, setup } of AUTO_TRIGGERS) {
        if (state.modals[id][flag]) setup(id, state.modals);
      }
    },

    openModal(): void {
      const { id } = getContext<ModalContext>();
      openModal(id, state.modals);
    },

    closeModal(): void {
      const { id } = getContext<ModalContext>();
      closeModal(id, state.modals);
    },
  },
});
