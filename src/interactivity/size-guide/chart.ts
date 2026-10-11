/**
 * Size Guide — structured chart behaviour.
 *
 * Both units are in the server markup; switching only flips `data-unit` on
 * the chart (CSS shows the matching values), so no text is rewritten. The
 * switch itself sits in the dialog header, beside the heading. The
 * visitor's choice is remembered per browser; first-time visitors get inches
 * where inches are the norm (US, Liberia, Myanmar) and centimetres elsewhere.
 * When the guide opens, the size picked in the add to cart form is
 * highlighted.
 *
 * Registered as an extra store() call so the runtime merges it into the
 * size guide namespace.
 *
 * @package Aggressive_Apparel
 */

import { store, getContext, getElement } from '@wordpress/interactivity';
import { selectedSizes, sizeKey } from './sizes';
import { announceUnit, preferredUnit, saveUnit, type Unit } from './units';

/**
 * Show one unit across a scope: the size guide dialog (its switch sits in the
 * header, outside the chart) or, outside a dialog, the chart itself.
 */
function applyUnit(scope: HTMLElement, unit: Unit, announce: boolean): void {
  scope
    .querySelectorAll<HTMLElement>('.aa-size-chart')
    .forEach(chart => (chart.dataset.unit = unit));
  if (scope.matches('.aa-size-chart')) {
    scope.dataset.unit = unit;
  }
  scope
    .querySelectorAll<HTMLButtonElement>('.aa-size-chart__unit-button')
    .forEach(button =>
      button.setAttribute('aria-pressed', String(button.dataset.unit === unit))
    );

  if (announce) {
    const live = scope.querySelector<HTMLElement>('.aa-size-chart__live');
    const message = live?.dataset[unit === 'cm' ? 'cm' : 'in'] ?? '';
    if (live && message) {
      live.textContent = message;
    }
  }
}

function scopeOf(element: HTMLElement): HTMLElement {
  return (
    element.closest<HTMLElement>('[role="dialog"]') ??
    element.closest<HTMLElement>('.aa-size-chart') ??
    element
  );
}

store('aggressive-apparel/size-guide', {
  actions: {
    // Reads the button from getElement(), not the event, so the action
    // needs no synchronous event access (withSyncEvent).
    setUnit() {
      const { ref: button } = getElement();
      const unit = button instanceof HTMLElement ? button.dataset.unit : '';
      if (
        !(button instanceof HTMLElement) ||
        (unit !== 'in' && unit !== 'cm')
      ) {
        return;
      }
      applyUnit(scopeOf(button), unit, true);
      saveUnit(unit);
      announceUnit(unit);
    },
  },
  callbacks: {
    initChart() {
      const { ref } = getElement();
      if (ref instanceof HTMLElement) {
        applyUnit(scopeOf(ref), preferredUnit(), false);
      }
    },
    highlightSize() {
      const context = getContext<{ isOpen: boolean }>();
      const { ref } = getElement();
      if (!context.isOpen || !(ref instanceof HTMLElement)) {
        return;
      }
      const selected = selectedSizes();
      ref.querySelectorAll<HTMLTableRowElement>('tbody tr').forEach(row => {
        const match = selected.includes(sizeKey(row.dataset.size ?? ''));
        row.classList.toggle('is-selected', match);
        // Screen readers hear "(your selected size)" on the row header; the
        // text is in the markup, so only its hidden flag changes.
        const note = row.querySelector<HTMLElement>('.aa-size-chart__current');
        if (note) {
          note.hidden = !match;
        }
      });
    },
  },
});
