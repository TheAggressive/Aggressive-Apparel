/**
 * Site Search — Interactivity API Store.
 *
 * Drives the full-screen search modal: a debounced REST search across products,
 * articles and pages, scope tabs, keyboard navigation, and an imperative,
 * type-aware result renderer. Shared by the trigger button (header) and the
 * portaled modal (wp_footer) via global store state.
 *
 * @package Aggressive_Apparel
 * @since 1.104.0
 */

import { store, getElement, withScope } from '@wordpress/interactivity';
import {
  prepareOverlayOpen,
  activateOverlayFocus,
  closeOverlay,
} from '@aggressive-apparel/use-overlay';

interface SearchResultItem {
  id: number;
  title: string;
  url: string;
  thumbnail?: string;
  price?: string;
  onSale?: boolean;
  excerpt?: string;
  date?: string;
}

interface SearchGroup {
  type: 'product' | 'post' | 'page';
  label: string;
  items: SearchResultItem[];
}

interface SearchResponse {
  groups: SearchGroup[];
  viewAll?: string;
}

interface SearchState {
  restUrl: string;
  isOpen: boolean;
  query: string;
  scope: string;
  isLoading: boolean;
  hasSearched: boolean;
  groups: SearchGroup[];
  viewAllUrl: string;
  placeholders: string[];
  i18n: {
    noResults: string;
    resultSingular: string;
    resultPlural: string;
  };
  readonly hasResults: boolean;
  readonly hasQuery: boolean;
  readonly hideHint: boolean;
  readonly showEmpty: boolean;
  readonly announcement: string;
}

interface SearchStore {
  state: SearchState;
  actions: Record<string, (...args: never[]) => unknown>;
  callbacks: Record<string, (...args: never[]) => unknown>;
}

const MODAL_ID = 'aa-search-modal';
const RESULTS_ID = 'aa-search-results';
const DEBOUNCE_MS = 300;
const MIN_CHARS = 2;

let debounceTimer: ReturnType<typeof setTimeout> | null = null;
let controller: AbortController | null = null;
let focusTrapCleanup: (() => void) | null = null;
let triggerElement: HTMLElement | null = null;
let focusedIndex = -1;

function escapeHtml(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

/**
 * Defense-in-depth: only allow http(s) or root-relative URLs as navigation
 * targets. Result URLs come from server-side get_permalink() so this should
 * always pass, but it neutralises any `javascript:`/`data:` value before it can
 * reach an href or window.location.
 */
function safeUrl(url: string): string {
  return /^https?:\/\//i.test(url) || url.startsWith('/') ? url : '#';
}

function highlight(text: string, query: string): string {
  const safe = escapeHtml(text);
  if (query.length < MIN_CHARS) return safe;
  const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  return safe.replace(new RegExp(`(${escaped})`, 'gi'), '<mark>$1</mark>');
}

function getModal(): HTMLElement | null {
  return document.getElementById(MODAL_ID);
}

function getDialog(): HTMLElement | null {
  return getModal()?.querySelector<HTMLElement>('.aa-search__dialog') ?? null;
}

function getInput(): HTMLInputElement | null {
  return (
    getModal()?.querySelector<HTMLInputElement>('.aa-search__input') ?? null
  );
}

function getOptions(): HTMLElement[] {
  const results = document.getElementById(RESULTS_ID);
  if (!results) return [];
  return Array.from(results.querySelectorAll<HTMLElement>('[role="option"]'));
}

function setActiveOption(index: number): void {
  const options = getOptions();
  const input = getInput();
  focusedIndex = index;
  options.forEach((option, i) => {
    const active = i === index;
    option.classList.toggle('is-focused', active);
    option.setAttribute('aria-selected', active ? 'true' : 'false');
  });
  if (index >= 0 && options[index]) {
    options[index].scrollIntoView({ block: 'nearest' });
    input?.setAttribute('aria-activedescendant', options[index].id);
  } else {
    input?.removeAttribute('aria-activedescendant');
  }
}

// --- Animated placeholder (typewriter) -------------------------------------
const TYPE_MS = 38;
const ERASE_MS = 20;
const HOLD_FULL_MS = 1100;
const HOLD_EMPTY_MS = 220;
const START_DELAY_MS = 180;

let typeTimer: ReturnType<typeof setTimeout> | null = null;
let phraseIndex = 0;
let charIndex = 0;
let erasing = false;

function prefersReducedMotion(): boolean {
  return (
    typeof window.matchMedia === 'function' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches
  );
}

function stopTypewriter(): void {
  if (typeTimer) {
    clearTimeout(typeTimer);
    typeTimer = null;
  }
}

/**
 * Cycle the input placeholder through the configured phrases with a type/erase
 * effect. Pauses while the user has a query (placeholder is hidden anyway), and
 * falls back to a static phrase when reduced motion is requested.
 */
function startTypewriter(): void {
  stopTypewriter();
  const input = getInput();
  const phrases = state.placeholders;
  if (!input || !Array.isArray(phrases) || phrases.length === 0) return;

  if (prefersReducedMotion()) {
    input.placeholder = phrases[0];
    return;
  }

  phraseIndex = 0;
  charIndex = 0;
  erasing = false;
  input.placeholder = '';

  const tick = (): void => {
    const el = getInput();
    if (!el) {
      stopTypewriter();
      return;
    }
    // Pause while the user is typing — the placeholder isn't visible then.
    if (state.query.length > 0) {
      typeTimer = setTimeout(tick, HOLD_FULL_MS);
      return;
    }

    const phrase = phrases[phraseIndex % phrases.length];

    if (!erasing) {
      charIndex += 1;
      el.placeholder = phrase.slice(0, charIndex);
      if (charIndex >= phrase.length) {
        erasing = true;
        typeTimer = setTimeout(tick, HOLD_FULL_MS);
      } else {
        typeTimer = setTimeout(tick, TYPE_MS);
      }
    } else {
      charIndex -= 1;
      el.placeholder = phrase.slice(0, Math.max(0, charIndex));
      if (charIndex <= 0) {
        erasing = false;
        phraseIndex = (phraseIndex + 1) % phrases.length;
        typeTimer = setTimeout(tick, HOLD_EMPTY_MS);
      } else {
        typeTimer = setTimeout(tick, ERASE_MS);
      }
    }
  };

  typeTimer = setTimeout(tick, START_DELAY_MS);
}

// A search fetches every type at once; the scope tabs are a client-side filter
// of that already-loaded data, so switching tabs is instant (no refetch, no
// blank flash). Reading state.scope here makes the result watch + getters react
// to tab changes.
function visibleGroups(): SearchGroup[] {
  return state.scope === 'all'
    ? state.groups
    : state.groups.filter(group => group.type === state.scope);
}

function visibleTotal(): number {
  return visibleGroups().reduce((sum, group) => sum + group.items.length, 0);
}

const { state, actions } = store<SearchStore>('aggressive-apparel/search', {
  state: {
    get hasResults(): boolean {
      return visibleTotal() > 0;
    },
    get hasQuery(): boolean {
      return state.query.length > 0;
    },
    get hideHint(): boolean {
      return state.query.length > 0;
    },
    get showEmpty(): boolean {
      return (
        state.hasSearched &&
        !state.isLoading &&
        state.query.length >= MIN_CHARS &&
        visibleTotal() === 0
      );
    },
    get announcement(): string {
      if (state.isLoading) return '';
      if (state.query.length < MIN_CHARS) return '';
      const total = visibleTotal();
      const { noResults, resultSingular, resultPlural } = state.i18n;
      if (total === 0) return noResults;
      if (total === 1) return resultSingular;
      return resultPlural.replace('%d', String(total));
    },
  },

  actions: {
    open(): void {
      const modal = getModal();
      const dialog = getDialog();
      if (!modal || !dialog) return;

      const { ref } = getElement();
      triggerElement = (ref as HTMLElement) ?? null;

      prepareOverlayOpen(modal, { manageOpenClass: false });
      state.isOpen = true;

      focusTrapCleanup = activateOverlayFocus({
        shell: modal,
        panel: dialog,
        focusSelector: '.aa-search__input',
      });

      startTypewriter();
    },

    close(): void {
      const modal = getModal();
      const dialog = getDialog();
      if (!modal || !dialog || !state.isOpen) return;

      stopTypewriter();
      state.isOpen = false;

      closeOverlay({
        shell: modal,
        panel: dialog,
        focusTrapCleanup,
        triggerElement,
        manageOpenClass: false,
        isStillOpen: () => state.isOpen,
      });
      focusTrapCleanup = null;
    },

    handleInput(event: Event): void {
      const value = (event.target as HTMLInputElement).value.trim();
      state.query = value;

      if (debounceTimer) clearTimeout(debounceTimer);

      if (value.length < MIN_CHARS) {
        state.groups = [];
        state.isLoading = false;
        state.hasSearched = false;
        focusedIndex = -1;
        return;
      }

      state.isLoading = true;
      debounceTimer = setTimeout(
        withScope(() => {
          actions.performSearch();
        }),
        DEBOUNCE_MS
      );
    },

    setScope(event: Event): void {
      const scope = (event.currentTarget as HTMLElement).dataset.scope;
      if (!scope || scope === state.scope) return;
      // Pure client-side filter — the data for every type is already loaded, so
      // no refetch (the result watch re-renders from the new scope instantly).
      state.scope = scope;
      getInput()?.focus({ preventScroll: true });
    },

    clear(): void {
      state.query = '';
      state.groups = [];
      state.isLoading = false;
      state.hasSearched = false;
      focusedIndex = -1;
      const input = getInput();
      if (input) {
        input.value = '';
        input.focus({ preventScroll: true });
      }
    },

    handleKeydown(event: KeyboardEvent): void {
      if (!state.isOpen) return;

      if (event.key === 'Escape') {
        event.preventDefault();
        actions.close();
        return;
      }

      const options = getOptions();
      if (options.length === 0) return;

      switch (event.key) {
        case 'ArrowDown':
          event.preventDefault();
          setActiveOption(Math.min(focusedIndex + 1, options.length - 1));
          break;
        case 'ArrowUp':
          event.preventDefault();
          setActiveOption(Math.max(focusedIndex - 1, -1));
          break;
        case 'Enter':
          if (focusedIndex >= 0 && options[focusedIndex]) {
            const href = options[focusedIndex].getAttribute('href');
            if (href) {
              event.preventDefault();
              window.location.href = href;
            }
          }
          break;
        default:
          break;
      }
    },

    performSearch(): void {
      if (controller) controller.abort();
      controller = new AbortController();

      // Always fetch every type so the scope tabs can filter client-side.
      const url = `${state.restUrl}?query=${encodeURIComponent(
        state.query
      )}&scope=all`;

      fetch(url, { signal: controller.signal })
        .then((res: Response) => {
          if (!res.ok) throw new Error('Search request failed');
          return res.json();
        })
        .then(
          withScope((data: SearchResponse) => {
            state.groups = Array.isArray(data.groups) ? data.groups : [];
            state.viewAllUrl = data.viewAll ?? '';
            state.isLoading = false;
            state.hasSearched = true;
            focusedIndex = -1;
          })
        )
        .catch(
          withScope((error: Error) => {
            if (error.name === 'AbortError') return;
            state.groups = [];
            state.isLoading = false;
            state.hasSearched = true;
          })
        );
    },
  },

  callbacks: {
    isActiveScope(): boolean {
      const { ref } = getElement();
      return (ref as HTMLElement)?.dataset.scope === state.scope;
    },

    renderResults(): void {
      const { ref } = getElement();
      const container = ref as HTMLElement;
      if (!container) return;

      // Touch reactive signals (query + scope via visibleGroups) so the watch
      // re-runs when the query OR the active tab changes.
      const query = state.query;
      const groups = visibleGroups();

      if (groups.length === 0) {
        container.innerHTML = '';
        focusedIndex = -1;
        return;
      }

      let index = 0;
      const html = groups
        .map((group: SearchGroup) => {
          const items = group.items
            .map((item: SearchResultItem) => {
              const optionId = `aa-search-opt-${index}`;
              index += 1;
              return renderItem(group.type, item, query, optionId);
            })
            .join('');
          return (
            `<div class="aa-search__group" role="group" aria-label="${escapeHtml(
              group.label
            )}">` +
            `<h2 class="aa-search__group-title">${escapeHtml(group.label)}</h2>` +
            `<div class="aa-search__group-list aa-search__group-list--${group.type}">${items}</div>` +
            `</div>`
          );
        })
        .join('');

      container.innerHTML = html;
      focusedIndex = -1;
      getInput()?.removeAttribute('aria-activedescendant');
    },
  },
});

/** Result thumbnail (image when present, otherwise an empty placeholder box). */
function renderThumb(url?: string): string {
  return url
    ? `<img class="aa-search__thumb" src="${escapeHtml(
        url
      )}" alt="" loading="lazy" width="52" height="52" />`
    : `<span class="aa-search__thumb aa-search__thumb--empty" aria-hidden="true"></span>`;
}

/**
 * Build the markup for a single result, branching on content type so each kind
 * is displayed sensibly (product: thumb + price; post: thumb + excerpt + date;
 * page: title only).
 */
function renderItem(
  type: SearchGroup['type'],
  item: SearchResultItem,
  query: string,
  optionId: string
): string {
  const url = escapeHtml(safeUrl(item.url));
  const title = highlight(item.title, query);

  if (type === 'product') {
    const media = renderThumb(item.thumbnail);
    const sale = item.onSale
      ? `<span class="aa-search__badge">Sale</span>`
      : '';
    const price = item.price
      ? `<span class="aa-search__price">${escapeHtml(item.price)}</span>`
      : '';
    // Name on the left, price + sale stacked on the right.
    return (
      `<a class="aa-search__result aa-search__result--product" role="option" id="${optionId}" href="${url}">` +
      media +
      `<span class="aa-search__result-body">` +
      `<span class="aa-search__result-title">${title}</span>` +
      `</span>` +
      `<span class="aa-search__result-end">${price}${sale}</span>` +
      `</a>`
    );
  }

  if (type === 'post') {
    const media = renderThumb(item.thumbnail);
    const excerpt = item.excerpt
      ? `<span class="aa-search__excerpt">${escapeHtml(item.excerpt)}</span>`
      : '';
    const date = item.date
      ? `<span class="aa-search__date">${escapeHtml(item.date)}</span>`
      : '';
    return (
      `<a class="aa-search__result aa-search__result--post" role="option" id="${optionId}" href="${url}">` +
      media +
      `<span class="aa-search__result-body">` +
      `<span class="aa-search__result-title">${title}</span>` +
      excerpt +
      date +
      `</span></a>`
    );
  }

  // Page.
  return (
    `<a class="aa-search__result aa-search__result--page" role="option" id="${optionId}" href="${url}">` +
    `<span class="aa-search__result-title">${title}</span>` +
    `<span class="aa-search__result-kind" aria-hidden="true">Page</span>` +
    `</a>`
  );
}
