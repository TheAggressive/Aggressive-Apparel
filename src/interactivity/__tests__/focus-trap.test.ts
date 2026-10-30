/** @jest-environment jsdom */

import { getFocusableElements, setupFocusTrap } from '../helpers';

describe('overlay keyboard navigation', () => {
  let panel: HTMLElement;
  let cleanup: () => void;

  beforeEach(() => {
    document.body.innerHTML = `<div id="panel">
      <button id="first">Close</button>
      <button tabindex="-1">Programmatic focus only</button>
      <input type="hidden" />
      <fieldset disabled><button>Disabled by fieldset</button></fieldset>
      <div hidden><button>Hidden</button></div>
      <div inert><button>Inert</button></div>
      <div style="display:none"><button>CSS-hidden ancestor</button></div>
      <button style="visibility:hidden">Invisible</button>
      <a id="last" href="/product/">Product</a>
    </div>`;
    panel = document.getElementById('panel')!;
    // jsdom has no layout; emulate boxes only for rendered elements.
    jest
      .spyOn(HTMLElement.prototype, 'getClientRects')
      .mockImplementation(function (this: HTMLElement): DOMRectList {
        let node: HTMLElement | null = this;
        while (node) {
          if (getComputedStyle(node).display === 'none') {
            return [] as unknown as DOMRectList;
          }
          node = node.parentElement;
        }
        return [{}] as unknown as DOMRectList;
      });
    cleanup = setupFocusTrap(panel);
  });

  afterEach(() => {
    cleanup();
    jest.restoreAllMocks();
    document.body.innerHTML = '';
  });

  function tab(shiftKey = false): void {
    document.activeElement?.dispatchEvent(
      new KeyboardEvent('keydown', {
        key: 'Tab',
        shiftKey,
        bubbles: true,
        cancelable: true,
      })
    );
  }

  it('excludes controls that cannot take sequential keyboard focus', () => {
    expect(getFocusableElements(panel).map(el => el.id)).toEqual([
      'first',
      'last',
    ]);
  });

  it('tabs past hidden controls and wraps in both directions', () => {
    const first = document.getElementById('first')!;
    const last = document.getElementById('last')!;
    first.focus();
    tab();
    expect(document.activeElement).toBe(last);
    tab();
    expect(document.activeElement).toBe(first);
    tab(true);
    expect(document.activeElement).toBe(last);
  });

  it('rechecks visibility after a control is hidden', () => {
    document.getElementById('last')!.style.display = 'none';
    const first = document.getElementById('first')!;
    first.focus();
    tab();
    expect(document.activeElement).toBe(first);
  });
});
