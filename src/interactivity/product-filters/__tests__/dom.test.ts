/**
 * @jest-environment jsdom
 */

import {
  chosenOption,
  escapeHtml,
  isUnavailable,
  setFilterVisibility,
} from '../dom';

it('keeps quoted filter labels inside their HTML attributes', () => {
  const label = `Kids' " onmouseover="alert(1) & <sale>`;
  const root = document.createElement('div');
  root.innerHTML = `<button aria-label="${escapeHtml(label)}">${escapeHtml(label)}</button>`;
  const button = root.querySelector('button')!;
  expect(button.getAttribute('aria-label')).toBe(label);
  expect(button.textContent).toBe(label);
  expect(button.getAttributeNames()).toEqual(['aria-label']);
});

const SWATCH = '.aa-product-filters__color-swatch';

function swatches(): HTMLButtonElement[] {
  document.body.innerHTML = ['black', 'red']
    .map(
      value =>
        `<button class="aa-product-filters__color-swatch" data-filter-value="${value}"><span>${value}</span></button>`
    )
    .join('');
  return [...document.querySelectorAll<HTMLButtonElement>(SWATCH)];
}

function clickOn(target: Element): Event {
  const event = new MouseEvent('click', { bubbles: true });
  Object.defineProperty(event, 'target', { value: target });
  return event;
}

describe('setFilterVisibility', () => {
  it('marks rejected options aria-disabled, keeping them focusable', () => {
    const [black, red] = swatches();

    setFilterVisibility(SWATCH, el => el.dataset.filterValue === 'black');

    expect(isUnavailable(black)).toBe(false);
    expect(red.getAttribute('aria-disabled')).toBe('true');
    expect(red.hasAttribute('tabindex')).toBe(false);
    expect(red.hasAttribute('aria-hidden')).toBe(false);
  });

  it('makes an option available again', () => {
    const [, red] = swatches();
    setFilterVisibility(SWATCH, () => false);

    setFilterVisibility(SWATCH, () => true);

    expect(red.hasAttribute('aria-disabled')).toBe(false);
  });
});

describe('chosenOption', () => {
  it('returns the value of the option clicked, from any child', () => {
    const [black] = swatches();

    expect(chosenOption(clickOn(black.querySelector('span')!), SWATCH)).toBe(
      'black'
    );
  });

  it('ignores unavailable options (aria-disabled does not block clicks)', () => {
    const [, red] = swatches();
    setFilterVisibility(SWATCH, el => el !== red);

    expect(chosenOption(clickOn(red), SWATCH)).toBe('');
  });

  it('ignores clicks outside an option', () => {
    swatches();

    expect(chosenOption(clickOn(document.body), SWATCH)).toBe('');
  });
});
