/**
 * Floating debug panel for the shared scroll-debug tooling.
 *
 * Hardened vs. the old per-block panels:
 * - every listener (including the document-level drag listeners) is
 *   registered through one AbortController and removed on destroy;
 * - drag AND restored positions are clamped to the viewport, so a panel
 *   can never be saved off-screen and become unreachable;
 * - localStorage access never throws (private browsing, quota);
 * - multiple panels cascade instead of stacking on the same corner.
 *
 * @package Aggressive Apparel
 */

import { getStrings } from './i18n';
import { el, safeStorageGet, safeStorageSet } from './utils';

export type RowKind = 'text' | 'badge' | 'meter';

export interface PanelRowSpec {
  id: string;
  label: string;
  kind: RowKind;
  /** Meter only: tick positions as 0..1 fractions (e.g. thresholds). */
  markers?: number[];
}

export interface PanelSectionSpec {
  id: string;
  label: string;
  startCollapsed?: boolean;
  rows: PanelRowSpec[];
}

export interface LegendItem {
  /** Swatch style: becomes `aa-dbg-legend__swatch--<swatch>`. */
  swatch: string;
  text: string;
}

export interface PanelOptions {
  title: string;
  /** Muted instance id next to the title (maps panel ↔ element tag). */
  subtitle?: string;
  /** Identity color dot in the header, matching the element outline. */
  accent?: string;
  /** localStorage key for position/collapse persistence. */
  storageKey: string;
  sections: PanelSectionSpec[];
  /** Optional collapsed "Legend" section explaining the overlays. */
  legend?: LegendItem[];
}

export interface PanelHandle {
  element: HTMLElement;
  setText: (rowId: string, text: string) => void;
  /** Badge modifier becomes `aa-dbg-badge--<modifier>` (colors). */
  setBadge: (rowId: string, text: string, modifier: string) => void;
  /** Fraction drives the fill width; text is the value readout. */
  setMeter: (rowId: string, fraction: number, text: string) => void;
  /** Show a warning banner, or hide it with null. */
  setWarning: (message: string | null) => void;
  destroy: () => void;
}

interface RowRefs {
  value: HTMLElement;
  /**
   * Dedicated Text node for value updates. Mutating `Text.data` is a
   * CharacterData edit; `textContent =` REPLACES the node — a childList
   * mutation that re-triggers `:has()` invalidation from the theme's
   * global CSS on every write and tanked the frame rate.
   */
  valueText: Text;
  fill?: HTMLElement;
  lastModifier?: string;
}

interface PersistedPanelState {
  left?: number;
  top?: number;
  collapsed?: boolean;
}

const VIEWPORT_MARGIN = 8;
const CASCADE_OFFSET = 36;

const clampToViewport = (
  left: number,
  top: number,
  width: number,
  height: number
): { left: number; top: number } => ({
  left: Math.max(
    VIEWPORT_MARGIN,
    Math.min(left, window.innerWidth - width - VIEWPORT_MARGIN)
  ),
  top: Math.max(
    VIEWPORT_MARGIN,
    Math.min(top, window.innerHeight - height - VIEWPORT_MARGIN)
  ),
});

export const createDebugPanel = (options: PanelOptions): PanelHandle => {
  const strings = getStrings();
  const abort = new AbortController();
  const { signal } = abort;
  const rows = new Map<string, RowRefs>();

  const panel = el('div', 'aa-dbg-panel');
  panel.setAttribute('role', 'region');
  panel.setAttribute('aria-label', options.title);

  // ---- Header ---------------------------------------------------------
  const header = el('div', 'aa-dbg-panel__header');
  const title = el('span', 'aa-dbg-panel__title', options.title);
  if (options.accent) {
    const dot = el('span', 'aa-dbg-panel__dot');
    dot.style.background = options.accent;
    title.prepend(dot);
  }
  if (options.subtitle) {
    title.appendChild(el('span', 'aa-dbg-panel__subtitle', options.subtitle));
  }
  const collapseButton = el(
    'button',
    'aa-dbg-panel__collapse',
    '−'
  ) as HTMLButtonElement;
  collapseButton.type = 'button';
  collapseButton.setAttribute('aria-expanded', 'true');
  collapseButton.setAttribute('aria-label', strings.panelCollapse);
  header.append(title, collapseButton);

  const warning = el('div', 'aa-dbg-panel__warning');
  const warningText = document.createTextNode('');
  warning.appendChild(warningText);
  warning.hidden = true;

  const body = el('div', 'aa-dbg-panel__body');

  /** Collapsible section scaffold shared by metric sections + legend. */
  const buildSection = (
    label: string,
    startCollapsed: boolean | undefined
  ): { sectionEl: HTMLElement; sectionBody: HTMLElement } => {
    const sectionEl = el('section', 'aa-dbg-section');
    const sectionButton = el(
      'button',
      'aa-dbg-section__header'
    ) as HTMLButtonElement;
    sectionButton.type = 'button';
    sectionButton.append(
      el('span', 'aa-dbg-section__label', label),
      el('span', 'aa-dbg-section__chevron', startCollapsed ? '+' : '−')
    );
    sectionButton.setAttribute(
      'aria-expanded',
      startCollapsed ? 'false' : 'true'
    );

    const sectionBody = el('div', 'aa-dbg-section__body');
    sectionBody.hidden = Boolean(startCollapsed);

    sectionButton.addEventListener(
      'click',
      () => {
        const collapsed = !sectionBody.hidden;
        sectionBody.hidden = collapsed;
        sectionButton.setAttribute('aria-expanded', String(!collapsed));
        const chevron = sectionButton.querySelector('.aa-dbg-section__chevron');
        if (chevron) {
          chevron.textContent = collapsed ? '+' : '−';
        }
      },
      { signal }
    );

    sectionEl.append(sectionButton, sectionBody);
    return { sectionEl, sectionBody };
  };

  // ---- Sections + rows -------------------------------------------------
  options.sections.forEach(section => {
    const { sectionEl, sectionBody } = buildSection(
      section.label,
      section.startCollapsed
    );

    section.rows.forEach(rowSpec => {
      const row = el('div', `aa-dbg-row aa-dbg-row--${rowSpec.kind}`);
      const label = el('span', 'aa-dbg-row__label', rowSpec.label);

      if (rowSpec.kind === 'meter') {
        const head = el('div', 'aa-dbg-row__head');
        const value = el('span', 'aa-dbg-row__value', '—');
        const valueText = value.firstChild as Text;
        head.append(label, value);

        const track = el('div', 'aa-dbg-meter');
        const fill = el('div', 'aa-dbg-meter__fill');
        track.appendChild(fill);
        (rowSpec.markers ?? []).forEach(marker => {
          const tick = el('span', 'aa-dbg-meter__marker');
          tick.style.left = `${Math.max(0, Math.min(1, marker)) * 100}%`;
          track.appendChild(tick);
        });

        row.append(head, track);
        rows.set(rowSpec.id, { value, valueText, fill });
      } else {
        const value = el(
          'span',
          rowSpec.kind === 'badge'
            ? 'aa-dbg-row__value aa-dbg-badge'
            : 'aa-dbg-row__value',
          '—'
        );
        row.append(label, value);
        rows.set(rowSpec.id, { value, valueText: value.firstChild as Text });
      }

      sectionBody.appendChild(row);
    });

    body.appendChild(sectionEl);
  });

  // ---- Legend (collapsed) -------------------------------------------------
  if (options.legend && options.legend.length > 0) {
    const { sectionEl, sectionBody } = buildSection(strings.legend, true);
    const list = el('div', 'aa-dbg-legend');
    options.legend.forEach(item => {
      const entry = el('div', 'aa-dbg-legend__item');
      entry.append(
        el(
          'span',
          `aa-dbg-legend__swatch aa-dbg-legend__swatch--${item.swatch}`
        ),
        el('span', 'aa-dbg-legend__text', item.text)
      );
      list.appendChild(entry);
    });
    sectionBody.appendChild(list);
    body.appendChild(sectionEl);
  }

  panel.append(header, warning, body);
  if (options.accent) {
    // Expose the identity color to legend swatches etc.
    panel.style.setProperty('--aa-dbg-identity', options.accent);
  }
  // DOM count, not module state: each block type bundles its own copy of
  // this module, so only the DOM sees panels from other block types.
  const cascadeIndex = document.querySelectorAll('.aa-dbg-panel').length;
  document.body.appendChild(panel);

  // ---- Position: restore (clamped) or cascade --------------------------
  const persisted = safeStorageGet<PersistedPanelState>(options.storageKey);

  const persist = (): void => {
    const rect = panel.getBoundingClientRect();
    safeStorageSet(options.storageKey, {
      left: rect.left,
      top: rect.top,
      collapsed: body.hidden,
    } satisfies PersistedPanelState);
  };

  const applyPosition = (left: number, top: number): void => {
    const rect = panel.getBoundingClientRect();
    const clamped = clampToViewport(left, top, rect.width, rect.height);
    panel.style.left = `${clamped.left}px`;
    panel.style.top = `${clamped.top}px`;
    panel.style.right = 'auto';
    panel.style.bottom = 'auto';
  };

  if (
    persisted &&
    typeof persisted.left === 'number' &&
    typeof persisted.top === 'number'
  ) {
    applyPosition(persisted.left, persisted.top);
  } else {
    const cascade = cascadeIndex * CASCADE_OFFSET;
    panel.style.right = `${16 + cascade}px`;
    panel.style.bottom = `${16 + cascade}px`;
  }

  // ---- Collapse --------------------------------------------------------
  const setCollapsed = (collapsed: boolean): void => {
    body.hidden = collapsed;
    warning.style.display = collapsed ? 'none' : '';
    collapseButton.textContent = collapsed ? '+' : '−';
    collapseButton.setAttribute('aria-expanded', String(!collapsed));
    collapseButton.setAttribute(
      'aria-label',
      collapsed ? strings.panelExpand : strings.panelCollapse
    );
  };

  if (persisted?.collapsed) {
    setCollapsed(true);
  }

  collapseButton.addEventListener(
    'click',
    () => {
      setCollapsed(!body.hidden);
      persist();
    },
    { signal }
  );

  // ---- Drag (pointer events, clamped) -----------------------------------
  let dragging = false;
  const dragOffset = { x: 0, y: 0 };

  header.addEventListener(
    'pointerdown',
    event => {
      // Don't start a drag from the collapse button.
      if ((event.target as HTMLElement).closest('button')) {
        return;
      }
      dragging = true;
      const rect = panel.getBoundingClientRect();
      dragOffset.x = event.clientX - rect.left;
      dragOffset.y = event.clientY - rect.top;
      panel.classList.add('is-dragging');
      event.preventDefault();
    },
    { signal }
  );

  document.addEventListener(
    'pointermove',
    event => {
      if (!dragging) {
        return;
      }
      applyPosition(event.clientX - dragOffset.x, event.clientY - dragOffset.y);
      event.preventDefault();
    },
    { signal }
  );

  const endDrag = (): void => {
    if (!dragging) {
      return;
    }
    dragging = false;
    panel.classList.remove('is-dragging');
    persist();
  };
  document.addEventListener('pointerup', endDrag, { signal });
  document.addEventListener('pointercancel', endDrag, { signal });

  // Keep the panel reachable when the viewport shrinks.
  window.addEventListener(
    'resize',
    () => {
      if (panel.style.left) {
        applyPosition(
          parseFloat(panel.style.left),
          parseFloat(panel.style.top || '0')
        );
      }
    },
    { signal }
  );

  // ---- Handle -----------------------------------------------------------
  return {
    element: panel,

    setText: (rowId, text) => {
      const row = rows.get(rowId);
      if (row && row.valueText.data !== text) {
        row.valueText.data = text;
      }
    },

    setBadge: (rowId, text, modifier) => {
      const row = rows.get(rowId);
      if (!row) {
        return;
      }
      if (row.valueText.data !== text) {
        row.valueText.data = text;
      }
      if (row.lastModifier !== modifier) {
        if (row.lastModifier) {
          row.value.classList.remove(`aa-dbg-badge--${row.lastModifier}`);
        }
        row.value.classList.add(`aa-dbg-badge--${modifier}`);
        row.lastModifier = modifier;
      }
    },

    setMeter: (rowId, fraction, text) => {
      const row = rows.get(rowId);
      if (!row || !row.fill) {
        return;
      }
      const clamped = Math.max(0, Math.min(1, fraction));
      row.fill.style.width = `${clamped * 100}%`;
      if (row.valueText.data !== text) {
        row.valueText.data = text;
      }
    },

    setWarning: message => {
      if (message) {
        warningText.data = `⚠ ${message}`;
        warning.hidden = false;
      } else {
        warning.hidden = true;
      }
    },

    destroy: () => {
      abort.abort();
      panel.remove();
    },
  };
};
