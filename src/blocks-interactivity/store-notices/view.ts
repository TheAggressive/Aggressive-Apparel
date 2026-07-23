/**
 * Store Notices (Toasts) — Interactivity API Store.
 *
 * Renders the server-seeded notice queue (see render.php) as a dismissible,
 * auto-expiring toast stack via `data-wp-each`, and — when the block's
 * `captureBlockNotices` attribute is on — mirrors WooCommerce's own block
 * cart/checkout and classic notices into the same stack, hiding the originals.
 *
 * Per-toast auto-dismiss timers live in a module-level Map keyed by notice id
 * rather than in context, because timer handles are not serialisable state and
 * must survive across the item's directive callbacks. Context holds only the
 * reactive `leaving` flag that drives the exit transition.
 *
 * @package Aggressive_Apparel
 */

import {
  store,
  getContext,
  getElement,
  withSyncEvent,
} from '@wordpress/interactivity';
import { sanitizeNoticeHtml } from './sanitize';

type NoticeType = 'success' | 'error' | 'notice';

interface StoreNotice {
  id: string;
  type: NoticeType;
  message: string;
  leaving: boolean;
  thumbnail?: string;
  noThumbnail?: boolean;
  isSuccess?: boolean;
  isError?: boolean;
  isNotice?: boolean;
}

/**
 * Build a notice object with the full field set the render template binds to.
 * The SSR path seeds these in PHP; client-added (bridged) notices must match or
 * their toast paints untyped and shows a spurious empty thumbnail slot.
 */
export function makeNotice(
  type: NoticeType,
  message: string,
  id: string
): StoreNotice {
  return {
    id,
    type,
    message,
    leaving: false,
    thumbnail: '',
    noThumbnail: true,
    isSuccess: type === 'success',
    isError: type === 'error',
    isNotice: type === 'notice',
  };
}

interface NoticesContext {
  notices: StoreNotice[];
  maxVisible: number;
  durations: Record<NoticeType, number>;
  captureBlockNotices: boolean;
  nextId: number;
  i18n: { dismiss: string };
  /** Present only inside a `data-wp-each` item scope. */
  notice?: StoreNotice;
}

interface TimerRecord {
  el: HTMLElement;
  notices: StoreNotice[];
  id: string;
  duration: number;
  timeoutId: ReturnType<typeof setTimeout> | null;
  remaining: number;
  startedAt: number;
}

/** How long the exit transition runs before the node is spliced (ms). */
const EXIT_MS = 350;

const timers = new Map<string, TimerRecord>();

/** Guards the singleton block-notice bridge so it wires up at most once. */
let bridgeStarted = false;

const currentNotice = (): StoreNotice | undefined =>
  getContext<NoticesContext>().notice;

/** The timer record for the notice in the current `data-wp-each` item scope. */
const currentRecord = (): TimerRecord | undefined => {
  const notice = currentNotice();
  return notice ? timers.get(notice.id) : undefined;
};

/**
 * Announce a notice to assistive tech via the persistent live region rendered
 * in render.php (`[data-aa-live]`). Errors go to the assertive region so they
 * interrupt; everything else to the polite one. The message is reduced to plain
 * text so screen readers read the words, not the markup.
 */
function announce(toastEl: HTMLElement, notice: StoreNotice): void {
  const container = toastEl.closest('.aa-notices');
  if (!container) {
    return;
  }
  const target = notice.type === 'error' ? 'assertive' : 'polite';
  const region = container.querySelector<HTMLElement>(
    `[data-aa-live="${target}"]`
  );
  if (!region) {
    return;
  }
  // notice.message is already sanitised (server wp_kses / client sanitize on
  // capture); this scratch node is detached and only read for its text, so no
  // second sanitiser pass is needed to pull the plain-text announcement.
  const scratch = document.createElement('div');
  scratch.innerHTML = notice.message;
  const text = (scratch.textContent ?? '').trim();
  if (!text) {
    return;
  }
  // Append each announcement as its own node (the regions are non-atomic) so
  // simultaneous notices are each read instead of clobbering one another, then
  // clear it so the region doesn't accumulate.
  const line = document.createElement('div');
  line.textContent = text;
  region.appendChild(line);
  setTimeout(() => line.remove(), 1000);
}

/**
 * Splice a notice out of its array after the exit transition completes.
 * Idempotent via a `done` flag; falls back to a timeout when no transitionend
 * fires (reduced motion, display:none edge cases).
 */
function beginExit(record: TimerRecord): void {
  const notice = record.notices.find(n => n.id === record.id);
  if (!notice || notice.leaving) {
    return;
  }

  notice.leaving = true;

  if (record.timeoutId) {
    clearTimeout(record.timeoutId);
    record.timeoutId = null;
  }

  let done = false;
  const finish = (): void => {
    if (done) {
      return;
    }
    done = true;
    const index = record.notices.findIndex(n => n.id === record.id);
    if (index !== -1) {
      record.notices.splice(index, 1);
    }
    timers.delete(record.id);
  };

  const onEnd = (event: TransitionEvent): void => {
    if (event.propertyName === 'opacity') {
      record.el.removeEventListener('transitionend', onEnd as EventListener);
      finish();
    }
  };

  record.el.addEventListener('transitionend', onEnd as EventListener);
  setTimeout(finish, EXIT_MS + 50);
}

function startTimer(record: TimerRecord): void {
  if (record.duration <= 0) {
    return; // Sticky (errors default to this): wait for manual dismiss.
  }
  if (record.remaining <= 0) {
    record.remaining = record.duration;
  }
  record.startedAt = Date.now();
  record.timeoutId = setTimeout(() => beginExit(record), record.remaining);
}

function pauseTimer(record: TimerRecord): void {
  if (record.timeoutId === null) {
    return;
  }
  clearTimeout(record.timeoutId);
  record.timeoutId = null;
  const elapsed = Date.now() - record.startedAt;
  record.remaining = Math.max(record.remaining - elapsed, 0);
}

const BRIDGE_SOURCES: Array<{ selector: string; type: NoticeType }> = [
  { selector: '.wc-block-components-notice-banner.is-error', type: 'error' },
  {
    selector: '.wc-block-components-notice-banner.is-success',
    type: 'success',
  },
  { selector: '.wc-block-components-notice-banner.is-info', type: 'notice' },
  { selector: '.wc-block-components-notice-banner.is-warning', type: 'notice' },
  { selector: '.woocommerce-error', type: 'error' },
  { selector: '.woocommerce-message', type: 'success' },
  { selector: '.woocommerce-info', type: 'notice' },
];

/**
 * Bridge WooCommerce's own rendered notices into the toast stack.
 *
 * The block cart/checkout render notices client-side via the Store API, and
 * classic pages print `.woocommerce-*` banners — neither goes through the
 * store-notices block. We observe the document, lift matching nodes into the
 * toast context (as plain text, so nothing is re-injected as HTML), and hide
 * the originals. Selectors target WooCommerce's current markup and degrade to
 * "do nothing" if that markup changes.
 */
function startBridge(ctx: NoticesContext, root: HTMLElement): void {
  if (bridgeStarted) {
    return;
  }
  bridgeStarted = true;

  const seen = new WeakSet<Element>();

  const contentOf = (node: Element): string => {
    const inner = node.querySelector(
      '.wc-block-components-notice-banner__content'
    );
    // Keep the message's inline markup (links included) but run it through the
    // same allowlist sanitiser as everything else before it can be stored.
    return sanitizeNoticeHtml((inner ?? node).innerHTML ?? '');
  };

  const capture = (node: Element, type: NoticeType): void => {
    if (seen.has(node)) {
      return;
    }
    seen.add(node);

    const message = contentOf(node);
    if (!message.trim()) {
      return;
    }

    // Hide WooCommerce's own copy; ours replaces it.
    node.setAttribute('hidden', '');
    (node as HTMLElement).style.display = 'none';

    // WooCommerce re-renders its block notices as fresh DOM nodes (the WeakSet
    // above only dedups a given node), so skip if an identical toast is already
    // live — otherwise a re-render stacks a duplicate. Ignore ones already
    // animating out so a re-issued notice isn't swallowed by the exiting copy.
    if (
      ctx.notices.some(
        n => !n.leaving && n.type === type && n.message === message
      )
    ) {
      return;
    }

    if (ctx.notices.length >= ctx.maxVisible) {
      const oldest = ctx.notices[0];
      if (oldest) {
        const record = timers.get(oldest.id);
        if (record) {
          beginExit(record);
        } else {
          ctx.notices.shift();
        }
      }
    }

    ctx.notices.push(
      makeNotice(type, message, `aa-notice-bridged-${ctx.nextId++}`)
    );
  };

  const scan = (scope: ParentNode): void => {
    for (const { selector, type } of BRIDGE_SOURCES) {
      scope.querySelectorAll?.(selector).forEach(node => {
        if (!root.contains(node)) {
          capture(node, type);
        }
      });
      if (
        scope instanceof Element &&
        scope.matches?.(selector) &&
        !root.contains(scope)
      ) {
        capture(scope, type);
      }
    }
  };

  scan(document.body);

  const observer = new MutationObserver(mutations => {
    for (const mutation of mutations) {
      mutation.addedNodes.forEach(node => {
        if (node.nodeType === Node.ELEMENT_NODE) {
          scan(node as Element);
        }
      });
    }
  });

  observer.observe(document.body, { childList: true, subtree: true });
}

export const storeNoticesStore = store('aggressive-apparel/store-notices', {
  state: {
    get dismissLabel(): string {
      return getContext<NoticesContext>().i18n.dismiss;
    },
  },

  actions: {
    dismiss(): void {
      const record = currentRecord();
      if (record) {
        beginExit(record);
      }
    },

    pause(): void {
      const record = currentRecord();
      if (record) {
        pauseTimer(record);
      }
    },

    resume(): void {
      const record = currentRecord();
      if (record && record.timeoutId === null && !currentNotice()?.leaving) {
        startTimer(record);
      }
    },

    /** Dismiss the focused toast on Escape (keyboard users). */
    onKeydown: withSyncEvent((event: KeyboardEvent): void => {
      if (event.key !== 'Escape' && event.key !== 'Esc') {
        return;
      }
      const record = currentRecord();
      if (!record) {
        return;
      }
      event.stopPropagation();
      beginExit(record);
    }),
  },

  callbacks: {
    init(): void {
      const ctx = getContext<NoticesContext>();
      const { ref } = getElement();
      if (ctx.captureBlockNotices && ref) {
        startBridge(ctx, ref as HTMLElement);
      }
    },

    /**
     * Paint the notice message as sanitised HTML. The Interactivity API has no
     * `data-wp-html` directive, so trusted HTML is applied imperatively — the
     * same pattern the social-proof toast uses. Every message passes through
     * the allowlist sanitiser here regardless of origin (defence-in-depth).
     */
    syncMessage(): void {
      const notice = currentNotice();
      const { ref } = getElement();
      if (!ref) {
        return;
      }
      ref.innerHTML = sanitizeNoticeHtml(notice?.message ?? '');
    },

    /**
     * Point the toast's product thumbnail at the captured image URL. Avoids
     * assigning src="" (which would fire a request to the page URL) when a
     * notice has no image.
     */
    syncThumb(): void {
      const notice = currentNotice();
      const { ref } = getElement();
      if (!ref) {
        return;
      }
      const img = ref as HTMLImageElement;
      const src = notice?.thumbnail?.trim() ?? '';
      if (src) {
        img.src = src;
      } else {
        img.removeAttribute('src');
      }
    },

    initToast(): void {
      const ctx = getContext<NoticesContext>();
      const notice = ctx.notice;
      const { ref } = getElement();
      if (!notice || !ref || timers.has(notice.id)) {
        return;
      }

      const record: TimerRecord = {
        el: ref as HTMLElement,
        notices: ctx.notices,
        id: notice.id,
        duration: ctx.durations[notice.type] ?? 0,
        timeoutId: null,
        remaining: 0,
        startedAt: 0,
      };
      timers.set(notice.id, record);
      startTimer(record);
      announce(ref as HTMLElement, notice);
    },
  },
});
