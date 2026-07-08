/**
 * Search Block — Interactivity API Store.
 *
 * Drives the full-screen search modal: a debounced REST search across products,
 * articles and pages, scope tabs, keyboard navigation, and an imperative
 * result renderer (`paintSearchResults`) that updates the listbox after each
 * state change. Shared by the trigger button and the portaled modal (wp_footer)
 * via global store state.
 *
 * @package Aggressive_Apparel
 */

import { store, getElement, withScope } from '@wordpress/interactivity';
import {
  prepareOverlayOpen,
  activateOverlayFocus,
  closeOverlay,
} from '@aggressive-apparel/use-overlay';
import {
  SEARCH_MODAL_ID,
  SEARCH_OPEN_BODY_CLASS,
} from '../nav-shared/overlay-coordination';

type SearchErrorCode =
  | ''
  | 'rate_limited'
  | 'server_error'
  | 'network'
  | 'invalid_response';

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

interface SearchSuccessResponse {
  query: string;
  groups: SearchGroup[];
  viewAll?: string;
  total?: number;
}

interface SearchErrorPayload {
  error?: string;
  message?: string;
  retryAfter?: number;
}

class SearchRequestError extends Error {
  code: SearchErrorCode;

  messageText: string;

  constructor(code: SearchErrorCode, messageText: string) {
    super(messageText);
    this.name = 'SearchRequestError';
    this.code = code;
    this.messageText = messageText;
  }
}

interface SearchState {
  restUrl: string;
  isOpen: boolean;
  query: string;
  scope: string;
  isLoading: boolean;
  hasSearched: boolean;
  hasError: boolean;
  errorCode: SearchErrorCode;
  errorMessage: string;
  groups: SearchGroup[];
  viewAllUrl: string;
  placeholders: string[];
  i18n: {
    noResults: string;
    resultSingular: string;
    resultPlural: string;
    searchError: string;
    rateLimited: string;
    retry: string;
    tabEmpty: string;
    viewAllTab: string;
    tabLabels: Record<string, string>;
  };
  readonly hasResults: boolean;
  readonly hasQuery: boolean;
  readonly hideHint: boolean;
  readonly hideError: boolean;
  readonly showEmpty: boolean;
  readonly showTabEmpty: boolean;
  readonly tabEmptyMessage: string;
  readonly errorDisplay: string;
  readonly announcement: string;
}

interface SearchStore {
  state: SearchState;
  actions: Record<string, (...args: never[]) => unknown>;
  callbacks: Record<string, (...args: never[]) => unknown>;
}

const SEARCH_STORE = 'aggressive-apparel/search';
const MODAL_ID = SEARCH_MODAL_ID;
const RESULTS_ID = 'aa-search-results';
const BODY_OPEN_CLASS = SEARCH_OPEN_BODY_CLASS;
const DEBOUNCE_MS = 300;
const MIN_CHARS = 2;
const DEFAULT_SCOPE = 'all';

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

/**
 * Drop stale in-page modal copies so only the portaled wp_footer shell remains.
 * Duplicate ids break getElementById and leave data-wp-watch bound to a dead node.
 */
function normalizeSearchShell(): void {
  const modals = Array.from(
    document.querySelectorAll<HTMLElement>(`#${MODAL_ID}`)
  );
  if (modals.length <= 1) {
    return;
  }

  modals.slice(0, -1).forEach(modal => modal.remove());
}

function getModal(): HTMLElement | null {
  return document.getElementById(MODAL_ID);
}

function getResultsContainer(): HTMLElement | null {
  return getModal()?.querySelector<HTMLElement>(`#${RESULTS_ID}`) ?? null;
}

function getDialog(): HTMLElement | null {
  return getModal()?.querySelector<HTMLElement>('.aa-search__dialog') ?? null;
}

function getInput(): HTMLInputElement | null {
  return (
    getModal()?.querySelector<HTMLInputElement>('.aa-search__input') ?? null
  );
}

/** Move focus into the search field after the shell is painted and interactive. */
function focusSearchInput(): void {
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      getInput()?.focus({ preventScroll: true });
    });
  });
}

function getOptions(): HTMLElement[] {
  const results = getResultsContainer();
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

function abortInFlightSearch(): void {
  if (controller) {
    controller.abort();
    controller = null;
  }
}

function clearSearchError(): void {
  state.hasError = false;
  state.errorCode = '';
  state.errorMessage = '';
}

function resetSearchResults(): void {
  state.groups = [];
  state.isLoading = false;
  state.hasSearched = false;
  clearSearchError();
  focusedIndex = -1;
  paintSearchResults();
}

function mapErrorCode(raw: string | undefined): SearchErrorCode {
  if (raw === 'rate_limited') return 'rate_limited';
  if (raw === 'server_error') return 'server_error';
  return 'server_error';
}

/**
 * Parse a REST response into a typed success payload or throw a SearchRequestError.
 */
async function parseSearchResponse(
  response: Response
): Promise<SearchSuccessResponse> {
  let payload: SearchSuccessResponse & SearchErrorPayload = {
    query: '',
    groups: [],
  };

  try {
    payload = (await response.json()) as SearchSuccessResponse &
      SearchErrorPayload;
  } catch {
    throw new SearchRequestError(
      response.ok ? 'invalid_response' : 'network',
      state.i18n.searchError
    );
  }

  if (!response.ok) {
    const code = mapErrorCode(payload.error);
    const fallback =
      code === 'rate_limited' ? state.i18n.rateLimited : state.i18n.searchError;
    throw new SearchRequestError(code, payload.message || fallback);
  }

  if (!Array.isArray(payload.groups)) {
    throw new SearchRequestError('invalid_response', state.i18n.searchError);
  }

  return {
    query: typeof payload.query === 'string' ? payload.query : '',
    groups: payload.groups,
    viewAll: payload.viewAll,
    total: payload.total,
  };
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
  return state.scope === DEFAULT_SCOPE
    ? state.groups
    : state.groups.filter(group => group.type === state.scope);
}

function visibleTotal(): number {
  return visibleGroups().reduce((sum, group) => sum + group.items.length, 0);
}

function globalTotal(): number {
  return state.groups.reduce((sum, group) => sum + group.items.length, 0);
}

function scopeTabLabel(scope: string): string {
  return state.i18n.tabLabels?.[scope] ?? scope;
}

function buildResultsHtml(query: string, groups: SearchGroup[]): string {
  if (groups.length === 0) {
    return '';
  }

  let index = 0;
  return groups
    .map((group: SearchGroup) => {
      const items = (group.items ?? [])
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
}

/**
 * Imperatively paint the active modal's result list.
 *
 * Interactivity API directives do not hydrate innerHTML we generate here, so
 * every state change that affects visible rows must call this explicitly.
 */
function paintSearchResults(): void {
  const container = getResultsContainer();
  if (!container) {
    return;
  }

  container.innerHTML = buildResultsHtml(state.query, visibleGroups());
  focusedIndex = -1;
  getInput()?.removeAttribute('aria-activedescendant');
}

const { state, actions } = store<SearchStore>(SEARCH_STORE, {
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
    get hideError(): boolean {
      return !state.hasError || state.isLoading;
    },
    get showEmpty(): boolean {
      return (
        state.hasSearched &&
        !state.isLoading &&
        !state.hasError &&
        state.query.length >= MIN_CHARS &&
        visibleTotal() === 0 &&
        globalTotal() === 0
      );
    },
    get showTabEmpty(): boolean {
      return (
        state.hasSearched &&
        !state.isLoading &&
        !state.hasError &&
        state.query.length >= MIN_CHARS &&
        state.scope !== DEFAULT_SCOPE &&
        visibleTotal() === 0 &&
        globalTotal() > 0
      );
    },
    get tabEmptyMessage(): string {
      if (!state.showTabEmpty) return '';
      const otherCount = globalTotal();
      return state.i18n.tabEmpty
        .replace('%1$s', scopeTabLabel(state.scope))
        .replace('%2$d', String(otherCount));
    },
    get errorDisplay(): string {
      return state.errorMessage || state.i18n.searchError;
    },
    get announcement(): string {
      if (state.isLoading) return '';
      if (state.query.length < MIN_CHARS) return '';
      if (state.hasError) return state.errorDisplay;
      if (state.showTabEmpty) return state.tabEmptyMessage;
      const total = visibleTotal();
      const { noResults, resultSingular, resultPlural } = state.i18n;
      if (total === 0) return noResults;
      if (total === 1) return resultSingular;
      return resultPlural.replace('%d', String(total));
    },
  },

  actions: {
    open(): void {
      normalizeSearchShell();
      const modal = getModal();
      const dialog = getDialog();
      if (!modal || !dialog) return;

      const { ref } = getElement();
      triggerElement = (ref as HTMLElement) ?? null;

      prepareOverlayOpen(modal, { manageOpenClass: false });
      modal.classList.add('is-open');
      document.body.classList.add(BODY_OPEN_CLASS);
      state.isOpen = true;

      focusTrapCleanup = activateOverlayFocus({
        shell: modal,
        panel: dialog,
        focusSelector: '.aa-search__input',
      });

      focusSearchInput();

      startTypewriter();
    },

    close(): void {
      const modal = getModal();
      const dialog = getDialog();
      if (!modal || !dialog || !state.isOpen) return;

      stopTypewriter();
      abortInFlightSearch();
      state.isOpen = false;
      modal.classList.remove('is-open');
      document.body.classList.remove(BODY_OPEN_CLASS);

      closeOverlay({
        shell: modal,
        panel: dialog,
        focusTrapCleanup,
        triggerElement,
        manageOpenClass: false,
        isStillOpen: () => state.isOpen,
        onFinish: () => {
          document.body.classList.remove(BODY_OPEN_CLASS);
          modal.classList.remove('is-open');
        },
      });
      focusTrapCleanup = null;
    },

    handleInput(event: Event): void {
      const value = (event.target as HTMLInputElement).value.trim();
      state.query = value;

      if (debounceTimer) clearTimeout(debounceTimer);

      // Cancel any in-flight request as soon as the query changes so a slow
      // response for an older term cannot overwrite the current input.
      abortInFlightSearch();

      if (value.length < MIN_CHARS) {
        resetSearchResults();
        return;
      }

      clearSearchError();
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
      paintSearchResults();
      getInput()?.focus({ preventScroll: true });
    },

    setScopeAll(): void {
      state.scope = DEFAULT_SCOPE;
      paintSearchResults();
      getInput()?.focus({ preventScroll: true });
    },

    clear(): void {
      abortInFlightSearch();
      if (debounceTimer) clearTimeout(debounceTimer);
      debounceTimer = null;
      state.query = '';
      state.scope = DEFAULT_SCOPE;
      resetSearchResults();
      const input = getInput();
      if (input) {
        input.value = '';
        input.focus({ preventScroll: true });
      }
    },

    retry(): void {
      if (state.query.length < MIN_CHARS) return;
      clearSearchError();
      state.isLoading = true;
      actions.performSearch();
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
      const requestQuery = state.query;
      if (requestQuery.length < MIN_CHARS) return;

      abortInFlightSearch();
      controller = new AbortController();

      // Always fetch every type so the scope tabs can filter client-side.
      const url = `${state.restUrl}?query=${encodeURIComponent(
        requestQuery
      )}&scope=all`;

      fetch(url, { signal: controller.signal })
        .then(parseSearchResponse)
        .then(
          withScope((data: SearchSuccessResponse) => {
            if (data.query !== state.query) return;

            state.groups = data.groups;
            state.viewAllUrl = data.viewAll ?? '';
            state.isLoading = false;
            state.hasSearched = true;
            clearSearchError();
            focusedIndex = -1;
            paintSearchResults();
          })
        )
        .catch(
          withScope((error: Error) => {
            if (error.name === 'AbortError') return;
            if (requestQuery !== state.query) return;

            const requestError =
              error instanceof SearchRequestError
                ? error
                : new SearchRequestError('network', state.i18n.searchError);

            state.hasError = true;
            state.errorCode = requestError.code;
            state.errorMessage = requestError.messageText;
            state.groups = [];
            state.viewAllUrl = '';
            state.isLoading = false;
            state.hasSearched = true;
            focusedIndex = -1;
            paintSearchResults();
          })
        );
    },
  },

  callbacks: {
    isActiveScope(): boolean {
      const { ref } = getElement();
      return (ref as HTMLElement)?.dataset.scope === state.scope;
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
