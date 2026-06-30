/**
 * Tests for closed-panel inert state.
 *
 * @jest-environment jsdom
 */

beforeAll(() => {
  if (!('inert' in HTMLElement.prototype)) {
    Object.defineProperty(HTMLElement.prototype, 'inert', {
      configurable: true,
      get() {
        return this.hasAttribute('inert');
      },
      set(value: boolean) {
        if (value) {
          this.setAttribute('inert', '');
        } else {
          this.removeAttribute('inert');
        }
      },
    });
  }
});

import { setPanelInert } from '../utils';

describe('setPanelInert', () => {
  it('marks the panel inert when closed', () => {
    const panel = document.createElement('div');
    setPanelInert(panel, true);
    expect(panel.inert).toBe(true);
  });

  it('clears inert when the panel opens', () => {
    const panel = document.createElement('div');
    panel.inert = true;
    setPanelInert(panel, false);
    expect(panel.inert).toBe(false);
  });
});
