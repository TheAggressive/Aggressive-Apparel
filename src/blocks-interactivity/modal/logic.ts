/**
 * Pure modal helpers (storage keys, dismiss windows, focus restore, exit anim).
 * Kept free of Interactivity API so Jest can cover them without a store mock.
 *
 * @package Aggressive_Apparel
 */

export interface ExitAnimDef {
  /** Full CSS transition string. */
  transition: string;
  /** Inline style properties to apply as the exit target. */
  styles: Partial<CSSStyleDeclaration>;
}

const DEFAULT_EASE = 'cubic-bezier(0.4, 0, 0.2, 1)';
const DAY_MS = 86400000;

export function getExitIntentStorageKey(id: string): string {
  return `aa_exit_intent_${id}`;
}

export function getOpenOnLoadSeenKey(id: string): string {
  return `aa_modal_seen_${id}`;
}

/**
 * Whether exit-intent should stay suppressed for this modal.
 */
export function isExitIntentDismissed(
  id: string,
  reshowDays: number,
  storage: Pick<Storage, 'getItem'> = localStorage,
  now: number = Date.now()
): boolean {
  try {
    const ts = storage.getItem(getExitIntentStorageKey(id));
    if (!ts) {
      return false;
    }
    return now - parseInt(ts, 10) < reshowDays * DAY_MS;
  } catch {
    return false;
  }
}

/**
 * Persist exit-intent dismissal timestamp.
 */
export function markExitIntentDismissed(
  id: string,
  storage: Pick<Storage, 'setItem'> = localStorage,
  now: number = Date.now()
): void {
  try {
    storage.setItem(getExitIntentStorageKey(id), String(now));
  } catch {
    // Private browsing or quota exceeded.
  }
}

/**
 * openOnLoadOnce: returns true when this visit should skip auto-open.
 * On first visit, marks the key then returns false so the modal still opens.
 */
export function shouldSkipOpenOnLoadOnce(
  id: string,
  openOnLoadOnce: boolean,
  storage: Pick<Storage, 'getItem' | 'setItem'> = localStorage
): boolean {
  if (!openOnLoadOnce) {
    return false;
  }

  const seenKey = getOpenOnLoadSeenKey(id);

  try {
    if (storage.getItem(seenKey)) {
      return true;
    }
    storage.setItem(seenKey, '1');
    return false;
  } catch {
    return false;
  }
}

/**
 * Safe return-focus target after close (skip body / detached nodes).
 */
export function canRestoreFocus(
  element: Element | null
): element is HTMLElement {
  return (
    !!element &&
    element instanceof HTMLElement &&
    element.isConnected &&
    element !== document.body &&
    typeof element.focus === 'function'
  );
}

/**
 * Build inline exit transition styles for a named animation.
 */
export function buildExitAnimation(
  name: string,
  duration: number,
  ease: string = DEFAULT_EASE
): ExitAnimDef {
  const d = `${duration}ms`;
  const t = `${d} ${ease}`;

  // Every exit explicitly sets opacity, transform AND filter so the exit is
  // fully self-contained. The enter-animation class stays on the dialog during
  // close; without these explicit resets its leftover transform/filter "before"
  // state would bleed into the exit.
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
    case 'position':
      // Drawers and sheets get their closed transform from the position class.
      // Do not set transform inline here or it would override that CSS target.
      return {
        transition: `opacity ${t}, transform ${t}`,
        styles: { opacity: '0', filter: 'none' },
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
