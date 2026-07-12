/**
 * Tests for the card-flip face accessibility syncing.
 *
 * @jest-environment jsdom
 */

jest.mock(
  '@wordpress/interactivity',
  () => ({
    store: () => ({}),
    getContext: () => ({}),
    getElement: () => ({ ref: null }),
  }),
  { virtual: true }
);

import { applyFaceInert } from '../view';

function buildCard(): HTMLElement {
  const root = document.createElement('div');
  root.className = 'aa-card-flip';
  root.innerHTML = [
    '<div class="aa-card-flip__face aa-card-flip__face--front"></div>',
    '<div class="aa-card-flip__face aa-card-flip__face--back"></div>',
  ].join('');
  return root;
}

const front = (r: HTMLElement) =>
  r.querySelector('.aa-card-flip__face--front') as HTMLElement;
const back = (r: HTMLElement) =>
  r.querySelector('.aa-card-flip__face--back') as HTMLElement;

describe('applyFaceInert', () => {
  it('inerts the back while showing the front (not flipped)', () => {
    const root = buildCard();
    applyFaceInert(root, false);

    expect(front(root).hasAttribute('inert')).toBe(false);
    expect(back(root).hasAttribute('inert')).toBe(true);
  });

  it('inerts the front while showing the back (flipped)', () => {
    const root = buildCard();
    applyFaceInert(root, true);

    expect(front(root).hasAttribute('inert')).toBe(true);
    expect(back(root).hasAttribute('inert')).toBe(false);
  });

  it('flips the inert side back and forth on repeated calls', () => {
    const root = buildCard();

    applyFaceInert(root, true);
    applyFaceInert(root, false);

    expect(front(root).hasAttribute('inert')).toBe(false);
    expect(back(root).hasAttribute('inert')).toBe(true);
  });

  it('does not throw when a face is missing', () => {
    const root = document.createElement('div');
    root.innerHTML =
      '<div class="aa-card-flip__face aa-card-flip__face--front"></div>';

    expect(() => applyFaceInert(root, true)).not.toThrow();
    expect(front(root).hasAttribute('inert')).toBe(true);
  });
});
