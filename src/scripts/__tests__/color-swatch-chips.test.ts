/**
 * @jest-environment jsdom
 */

import { initColorSwatchChips } from '../color-swatch-chips';

const flush = () => new Promise(resolve => setTimeout(resolve, 0));

const MAP = {
  blue: { name: 'Blue', ring: '#0000ff', pattern: false, color: '#0000ff' },
  plaid: {
    name: 'Plaid',
    ring: 'rgb(0 0 0 / 40%)',
    pattern: true,
    color: '',
    tooltip: 'Plaid — Flannel',
  },
};

const chip = (value: string, decorated = false) =>
  `<button class="wc-block-product-filter-chips__item${decorated ? ' aggressive-apparel-color-chip' : ''}" value="${value}" title="${value}" aria-label="${value}"><span class="wc-block-product-filter-chips__label"><span class="wc-block-product-filter-chips__swatch"></span></span></button>`;

const render = () => {
  document.body.innerHTML = `
    <div class="wc-block-product-filter-chips is-style-swatch" data-aa-color-swatches='${JSON.stringify(MAP)}'>
      ${chip('blue', true)}
    </div>
    <div class="wc-block-product-filter-chips">
      <button class="wc-block-product-filter-chips__item" title="M" value="m">M</button>
    </div>`;
  return document.querySelector('[data-aa-color-swatches]')!;
};

afterEach(() => {
  document.body.innerHTML = '';
});

it('drops colour chip titles but keeps their accessible names', () => {
  render();
  initColorSwatchChips();

  const blue = document.querySelector('button[value="blue"]')!;
  expect(blue.hasAttribute('title')).toBe(false);
  expect(blue.getAttribute('aria-label')).toBe('blue');
  expect(
    document.querySelector('button[value="m"]')?.getAttribute('title')
  ).toBe('M');
});

it('drops a title WooCommerce sets again after hydrating', async () => {
  render();
  initColorSwatchChips();

  const blue = document.querySelector('button[value="blue"]')!;
  blue.setAttribute('title', 'Blue');
  await flush();

  expect(blue.hasAttribute('title')).toBe(false);
});

it('decorates chips WooCommerce renders on "+N more" from the map', async () => {
  const list = render();
  initColorSwatchChips();

  list.insertAdjacentHTML('beforeend', chip('plaid'));
  await flush();

  const plaid = list.querySelector<HTMLElement>('button[value="plaid"]')!;
  const swatch = plaid.querySelector<HTMLElement>(
    '.wc-block-product-filter-chips__swatch'
  )!;
  expect(plaid.classList.contains('aggressive-apparel-color-chip')).toBe(true);
  expect(plaid.hasAttribute('title')).toBe(false);
  expect(plaid.style.getPropertyValue('--swatch-color')).toBe(
    'rgb(0 0 0 / 40%)'
  );
  expect(
    swatch.classList.contains('aggressive-apparel-color-swatch__circle')
  ).toBe(true);
  expect(
    swatch.classList.contains('aggressive-apparel-color-swatch--pattern')
  ).toBe(true);
  expect(swatch.dataset.colorName).toBe('Plaid');
  expect(swatch.dataset.tooltip).toBe('Plaid — Flannel');
  expect(swatch.dataset.color).toBeUndefined();
});

it('leaves chips with no swatch data alone', async () => {
  const list = render();
  initColorSwatchChips();

  list.insertAdjacentHTML('beforeend', chip('unknown'));
  await flush();

  const unknown = list.querySelector('button[value="unknown"]')!;
  expect(unknown.classList.contains('aggressive-apparel-color-chip')).toBe(
    false
  );
});

it('does nothing on pages without colour chip lists', () => {
  document.body.innerHTML = '<button title="Size">M</button>';

  expect(() => initColorSwatchChips()).not.toThrow();
  expect(document.querySelector('button')?.getAttribute('title')).toBe('Size');
});
