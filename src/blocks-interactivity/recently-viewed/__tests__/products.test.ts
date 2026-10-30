/** @jest-environment jsdom */

import { historyIds, isRecentProduct, renderProducts } from '../products';

const product = {
  id: 7,
  name: 'Tom &amp; "Jerry" <img src=x onerror=alert(1)>',
  permalink: '/product/tee/',
  images: [{ src: '/full.jpg', thumbnail: '/thumbnail.jpg' }],
  prices: { price: '2500', currency_prefix: '$', currency_minor_unit: 2 },
};

it('bounds and normalizes visitor-controlled history', () => {
  expect(historyIds([7, 7, -1, 0, '4', null, {}, 2.5, 9])).toEqual([7, 9]);
  expect(historyIds({ length: 100 })).toEqual([]);
  expect(historyIds(Array.from({ length: 100 }, (_, i) => i + 1))).toHaveLength(
    12
  );
});

it('validates the product boundary before formatting prices', () => {
  expect(isRecentProduct(product)).toBe(true);
  expect(
    isRecentProduct({
      ...product,
      prices: { price: '100', currency_minor_unit: 100 },
    })
  ).toBe(false);
  expect(isRecentProduct({ ...product, images: [null] })).toBe(false);
  expect(isRecentProduct({ ...product, prices: null })).toBe(true);
});

it('renders API names as text and uses a sized, lazy thumbnail', () => {
  const root = document.createElement('div');
  renderProducts(root, [product]);
  expect(root.querySelector('img')!.getAttribute('src')).toContain(
    '/thumbnail.jpg'
  );
  expect(root.querySelector('img')!.loading).toBe('lazy');
  expect(root.querySelector('img')!.width).toBe(300);
  expect(root.querySelectorAll('img')).toHaveLength(1);
  expect(root.querySelector('[onerror]')).toBeNull();
  expect(
    root.querySelector('.aggressive-apparel-recently-viewed__name')!.textContent
  ).toBe('Tom & "Jerry" <img src=x onerror=alert(1)>');
  expect(
    root.querySelector('.aggressive-apparel-recently-viewed__price')!
      .textContent
  ).toBe('$25.00');
});

it('rejects executable URLs and replaces stale nodes when products change', () => {
  const root = document.createElement('div');
  renderProducts(root, [product]);
  renderProducts(root, [{ ...product, permalink: 'javascript:alert(1)' }]);
  expect(root.children).toHaveLength(0);
  renderProducts(root, [
    { ...product, images: [{ src: 'data:image/svg+xml,evil' }] },
  ]);
  expect(root.querySelector('img')).toBeNull();
  expect(root.querySelector('a')).not.toBeNull();
});
