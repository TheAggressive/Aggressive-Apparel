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
    handleKeydown: (event?: Event) => void;
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

// ── Exit animation engine ─────────────────────────────────────────────────────

const EASE = 'cubic-bezier(0.4, 0, 0.2, 1)';

interface ExitAnimDef {
  /** Full CSS transition string. */
  transition: string;
  /** Inline style properties to apply as the exit target. */
  styles: Partial<CSSStyleDeclaration>;
}

function buildExitAnimation(name: string, duration: number): ExitAnimDef {
  const d = `${duration}ms`;
  const t = `${d} ${EASE}`;

  // Every exit explicitly sets opacity, transform AND filter so the exit is
  // fully self-contained. The enter-animation class stays on the dialog during
  // close; without these explicit resets its leftover transform/filter "before"
  // state would bleed into the exit (making the exit appear to mirror the enter).
  switch (name) {
    case 'slide-up':
      return {
        transition: `opacity ${t}, transform ${t}`,
        styles: {
          opacity: '0',
          transform: 'translateY(-2rem)',
          filter: 'none',
        },
      };
    case 'slide-down':
      return {
        transition: `opacity ${t}, transform ${t}`,
        styles: { opacity: '0', transform: 'translateY(2rem)', filter: 'none' },
      };
    case 'slide-left':
      return {
        transition: `opacity ${t}, transform ${t}`,
        styles: {
          opacity: '0',
          transform: 'translateX(-2rem)',
          filter: 'none',
        },
      };
    case 'slide-right':
      return {
        transition: `opacity ${t}, transform ${t}`,
        styles: { opacity: '0', transform: 'translateX(2rem)', filter: 'none' },
      };
    case 'zoom-out':
      return {
        transition: `opacity ${t}, transform ${t}`,
        styles: { opacity: '0', transform: 'scale(0.9)', filter: 'none' },
      };
    case 'zoom-in':
      return {
        transition: `opacity ${t}, transform ${t}`,
        styles: { opacity: '0', transform: 'scale(1.1)', filter: 'none' },
      };
    case 'expand':
      return {
        transition: `opacity ${t}, transform ${t}`,
        styles: { opacity: '0', transform: 'scale(0.96)', filter: 'none' },
      };
    case 'recede':
      return {
        transition: `opacity ${t}, transform ${t}`,
        styles: { opacity: '0', transform: 'scale(1.04)', filter: 'none' },
      };
    case 'pop':
      return {
        transition: `opacity ${t}, transform ${t}`,
        styles: { opacity: '0', transform: 'scale(0.88)', filter: 'none' },
      };
    case 'flip-down':
      return {
        transition: `opacity ${t}, transform ${t}`,
        styles: {
          opacity: '0',
          transform: 'perspective(800px) rotateX(80deg)',
          filter: 'none',
        },
      };
    case 'blur':
      return {
        transition: `opacity ${t}, filter ${t}`,
        styles: { opacity: '0', transform: 'none', filter: 'blur(16px)' },
      };
    case 'none':
      return {
        transition: 'opacity 0ms',
        styles: { opacity: '0', transform: 'none', filter: 'none' },
      };
    default: // 'fade'
      return {
        transition: `opacity ${t}`,
        styles: { opacity: '0', transform: 'none', filter: 'none' },
      };
  }
}

function clearExitStyles(dialog: HTMLElement): void {
  dialog.style.removeProperty('transition');
  dialog.style.removeProperty('opacity');
  dialog.style.removeProperty('transform');
  dialog.style.removeProperty('filter');
}

// ── Core open / close ─────────────────────────────────────────────────────────

function openModal(id: string, modalsState: Record<string, ModalState>): void {
  if (!id || !modalsState[id]) return;

  modalRefs.set(id, {
    triggerElement: document.activeElement as HTMLElement | null,
    focusTrapCleanup: null,
  });

  lockScroll();
  document.dispatchEvent(new CustomEvent('aa:modal:open', { detail: { id } }));

  // Clear any stale exit-animation inline styles from a previous close so
  // the CSS enter animation starts from a clean state.
  const dialogEl = getDialog(id);
  if (dialogEl) clearExitStyles(dialogEl);

  const shell = getShell(id);
  if (shell) {
    shell.hidden = false;
    void shell.offsetHeight; // Force reflow so the browser captures the "before" state.
    shell.classList.add('is-open');
  }

  modalsState[id].isOpen = true;

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
  document.dispatchEvent(new CustomEvent('aa:modal:close', { detail: { id } }));

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

  // Remove is-open to dismiss backdrop and shell while the exit animation plays.
  if (shell) shell.classList.remove('is-open');

  // Apply exit animation via inline styles — completely independent of the CSS
  // enter-animation classes, so enter and exit can be mixed freely.
  if (dialog) {
    const exitName = dialog.dataset.exitAnimation ?? 'fade';
    const { transition, styles } = buildExitAnimation(exitName, duration);
    dialog.style.transition = transition;
    Object.assign(dialog.style, styles);
  }

  let done = false;
  const finish = (): void => {
    if (done) return;
    done = true;
    // Clear inline exit styles so the next open starts from clean CSS state.
    if (dialog) clearExitStyles(dialog);
    unlockScroll();
    if (shell) shell.hidden = true;
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

// ── Auto-trigger registry ─────────────────────────────────────────────────────
// Add entries here to wire up new automatic open triggers without touching init().

type TriggerSetup = (id: string, modals: Record<string, ModalState>) => void;

const AUTO_TRIGGERS: Array<{ flag: keyof ModalState; setup: TriggerSetup }> = [
  { flag: 'exitIntentTrigger', setup: setupExitIntent },
  { flag: 'scrollDepthTrigger', setup: setupScrollDepthTrigger },
];

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

      document
        .querySelectorAll<HTMLElement>(`.modal-trigger-${id}`)
        .forEach(el => {
          el.addEventListener('click', (e: Event) => {
            e.preventDefault();
            openModal(id, state.modals);
          });
        });

      if (state.modals[id].openOnLoad) {
        const once = state.modals[id].openOnLoadOnce;
        const seenKey = `aa_modal_seen_${id}`;
        let alreadySeen = false;
        if (once) {
          try {
            alreadySeen = !!localStorage.getItem(seenKey);
            if (!alreadySeen) localStorage.setItem(seenKey, '1');
          } catch {
            // Private browsing — treat as not seen.
          }
        }
        if (!alreadySeen) openModal(id, state.modals);
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

    handleKeydown(event?: Event): void {
      if ((event as KeyboardEvent)?.key !== 'Escape') return;
      const { id } = getContext<ModalContext>();
      event?.preventDefault();
      closeModal(id, state.modals);
    },
  },
});
