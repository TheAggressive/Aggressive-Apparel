/**
 * Overlay close lifecycle tests.
 *
 * @jest-environment jsdom
 */

jest.mock(
  '@aggressive-apparel/scroll-lock',
  () => ({ lockScroll: jest.fn(), unlockScroll: jest.fn() }),
  { virtual: true }
);
jest.mock(
  '@aggressive-apparel/helpers',
  () => jest.requireActual('../helpers'),
  { virtual: true }
);

import { activateOverlayFocus, closeOverlay } from '../use-overlay';

function transitionEnd(target: HTMLElement, propertyName: string): void {
  const event = new Event('transitionend', { bubbles: true });
  Object.defineProperty(event, 'propertyName', { value: propertyName });
  target.dispatchEvent(event);
}

describe('closeOverlay', () => {
  beforeEach(() => {
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  it('waits for the panel’s own transition, ignoring other properties and children', () => {
    const shell = document.createElement('div');
    const panel = document.createElement('div');
    const child = document.createElement('img');
    panel.appendChild(child);
    shell.appendChild(panel);
    shell.classList.add('is-open');

    closeOverlay({ shell, panel, transitionProperty: 'transform' });
    expect(shell.classList.contains('is-open')).toBe(false);

    // A child's transition bubbles up; the panel's opacity ends first.
    transitionEnd(child, 'transform');
    transitionEnd(panel, 'opacity');
    expect(shell.hidden).toBe(false);

    transitionEnd(panel, 'transform');
    expect(shell.hidden).toBe(true);
  });

  it('still hides the shell when no transition ends', () => {
    const shell = document.createElement('div');
    const panel = document.createElement('div');
    shell.appendChild(panel);

    closeOverlay({ shell, panel, transitionProperty: 'transform' });
    expect(shell.hidden).toBe(false);

    jest.runAllTimers();
    expect(shell.hidden).toBe(true);
  });
});

describe('activateOverlayFocus', () => {
  it('does not steal focus after the overlay was closed before the next frame', () => {
    jest.useFakeTimers();
    document.body.innerHTML =
      '<button id="trigger">Open</button><div id="shell"><button>Close</button></div>';
    const shell = document.getElementById('shell')!;
    const trigger = document.getElementById('trigger')!;
    trigger.focus();
    const cleanup = activateOverlayFocus({ shell, panel: shell });
    cleanup();
    jest.runAllTimers();
    expect(document.activeElement).toBe(trigger);
    document.body.innerHTML = '';
    jest.useRealTimers();
  });
});
