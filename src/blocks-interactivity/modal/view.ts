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

// ── Types ────────────────────────────────────────────────────────────────────

interface ModalState {
  isOpen: boolean;
  openOnLoad: boolean;
  animationDuration: number;
  exitIntentTrigger: boolean;
  exitIntentReshowDays: number;
}

interface ModalContext {
  id: string;
}

interface ModalStore {
  state: {
    modals: Record<string, ModalState>;
  };
  actions: Record<string, (...args: any[]) => any>;
}

// ── Module-level references ───────────────────────────────────────────────────

/** Safety buffer added to animationDuration before the finish() fallback fires. */
const FALLBACK_BUFFER_MS = 50;

interface ModalRefs {
  /** Element that had focus before the modal opened; restored on close. */
  triggerElement: HTMLElement | null;
  /** Cleanup function returned by setupFocusTrap(); called on close. */
  focusTrapCleanup: (() => void) | null;
}

/** Per-modal focus and trap state, keyed by modal ID. */
const modalRefs = new Map<string, ModalRefs>();

// ── DOM helpers ───────────────────────────────────────────────────────────────

function getShell(id: string): HTMLElement | null {
  return document.querySelector(
    `.wp-block-aggressive-apparel-modal__shell[data-modal-id="${id}"]`
  );
}

function getDialog(id: string): HTMLElement | null {
  return document.querySelector(
    `#${id}.wp-block-aggressive-apparel-modal__dialog`
  );
}

function getAnnouncer(id: string): HTMLElement | null {
  return document.querySelector(
    `.wp-block-aggressive-apparel-modal__announcer[data-modal-id="${id}"]`
  );
}

// ── Core open / close ─────────────────────────────────────────────────────────

function openModal(id: string, modalsState: Record<string, ModalState>): void {
  if (!id || !modalsState[id]) return;

  console.log( '[modal] openModal', id );

  modalRefs.set(id, {
    triggerElement: document.activeElement as HTMLElement | null,
    focusTrapCleanup: null,
  });

  lockScroll();

  const shell = getShell(id);
  console.log( '[modal] shell', shell );
  if (shell) {
    shell.hidden = false;
    void shell.offsetHeight; // Force reflow so the browser captures the "before" state.
    shell.classList.add('is-open');
  }

  modalsState[id].isOpen = true;
  console.log( '[modal] isOpen set to true, shell classes:', shell?.className );

  // Announce to screen readers that do not fire on programmatic focus alone.
  const announcer = getAnnouncer(id);
  if (announcer) {
    announcer.textContent = ''; // Clear so re-opening re-triggers aria-live.
    requestAnimationFrame(() => {
      announcer.textContent = announcer.dataset.label ?? 'Dialog opened';
    });
  }

  requestAnimationFrame(() => {
    const dialog = getDialog(id);
    console.log( '[modal] dialog', dialog );
    if (dialog) {
      const refs = modalRefs.get(id);
      if (refs) refs.focusTrapCleanup = setupFocusTrap(dialog);
      // Focus the dialog (tabindex="-1") so screen readers announce
      // "Dialog: [label]" before the user tabs into content.
      dialog.focus();
    }
  });
}

function closeModal(id: string, modalsState: Record<string, ModalState>): void {
  if (!id || !modalsState[id]) return;

  modalsState[id].isOpen = false;

  const announcer = getAnnouncer(id);
  if (announcer) announcer.textContent = '';

  const refs = modalRefs.get(id);

  if (refs?.focusTrapCleanup) {
    refs.focusTrapCleanup();
    refs.focusTrapCleanup = null;
  }

  if (refs?.triggerElement) {
    refs.triggerElement.focus();
  }

  const duration = modalsState[id].animationDuration ?? 300;
  const shell = getShell(id);
  const dialog = getDialog(id);

  let done = false;
  const finish = (): void => {
    if (done) return;
    done = true;
    unlockScroll();
    if (shell) {
      shell.classList.remove('is-open');
      shell.hidden = true;
    }
    modalRefs.delete(id);
  };

  if (dialog) {
    dialog.addEventListener(
      'transitionend',
      (e: Event) => {
        if ((e as TransitionEvent).propertyName === 'opacity') finish();
      },
      { once: true }
    );
  }

  // Safety fallback: fires if transitionend never occurs (reduced motion, no CSS, etc.).
  setTimeout(finish, duration + FALLBACK_BUFFER_MS);
}

// ── Exit intent detection ─────────────────────────────────────────────────────

function getExitIntentKey(id: string): string {
  return `aa_exit_intent_${id}`;
}

function isExitIntentDismissed(id: string, reshowDays: number): boolean {
  try {
    const ts = localStorage.getItem(getExitIntentKey(id));
    if (!ts) return false;
    return Date.now() - parseInt(ts, 10) < reshowDays * 86400000;
  } catch {
    return false;
  }
}

function markExitIntentDismissed(id: string): void {
  try {
    localStorage.setItem(getExitIntentKey(id), String(Date.now()));
  } catch {
    // Private browsing or quota exceeded.
  }
}

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
    if ( armed && ! triggered && e.relatedTarget === null && e.clientY <= 0 ) {
      triggered = true;
      openModal( id, modalsState );
      markExitIntentDismissed( id );
    }
  } );
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
      console.log( '[modal] init', id, state.modals[ id ] );
      if (!id || !state.modals[id]) return;

      document
        .querySelectorAll<HTMLElement>(`.modal-trigger-${id}`)
        .forEach(el => {
          el.addEventListener('click', (e: Event) => {
            e.preventDefault();
            openModal(id, state.modals);
          });
        });

      if (state.modals[id].openOnLoad) {
        openModal(id, state.modals);
      }

      if (state.modals[id].exitIntentTrigger) {
        console.log( '[modal] setting up exit intent for', id );
        setupExitIntent(id, state.modals);
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

    handleKeydown(event?: Event): void {
      if ((event as KeyboardEvent)?.key !== 'Escape') return;
      const { id } = getContext<ModalContext>();
      event?.preventDefault();
      closeModal(id, state.modals);
    },
  },
});
