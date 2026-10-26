/**
 * Card swatches deselect on a second press, Escape, or a click outside the
 * card, and put back the card's original image and links.
 *
 * @jest-environment jsdom
 */

import '../view';

const card = (id: string) => `
  <li class="wc-block-product" data-product-id="${id}">
    <a data-aa-product-link href="/product/${id}/">
      <img data-aa-product-image src="/${id}.jpg" alt="${id}" />
    </a>
    <div class="aa-product-color-swatches" data-wp-context='{"linkToVariation":true,"transition":"fade"}'>
      <button class="aa-product-color-swatches__swatch" aria-pressed="false"
        data-wp-context='{"slug":"red","imageUrl":"/${id}-red.jpg","imageSrcset":"/${id}-red.jpg 1x","imageAlt":"${id} in red"}'></button>
      <button class="aa-product-color-swatches__swatch" aria-pressed="false"
        data-wp-context='{"slug":"blue","imageUrl":"/${id}-blue.jpg","imageSrcset":"","imageAlt":"${id} in blue"}'></button>
      <button class="aa-product-color-swatches__swatch" aria-pressed="false"
        data-wp-context='{"slug":"black","imageUrl":"","imageSrcset":"","imageAlt":""}'></button>
      <button class="aa-product-color-swatches__swatch" aria-pressed="false"
        data-wp-context='{"slug":"white","imageUrl":"/${id}.jpg","imageSrcset":"/${id}-white.jpg 2x","imageAlt":"White"}'></button>
    </div>
    <button class="wishlist" type="button">Save</button>
  </li>`;

const $ = <T extends Element>(selector: string) =>
  document.querySelector<T>(selector)!;

const swatch = (id: string, n: number) =>
  document.querySelectorAll<HTMLButtonElement>(
    `[data-product-id="${id}"] .aa-product-color-swatches__swatch`
  )[n];

const image = (id: string) =>
  $<HTMLImageElement>(`[data-product-id="${id}"] img`);

const link = (id: string) =>
  $<HTMLAnchorElement>(`[data-product-id="${id}"] a`);

beforeEach(() => {
  document.body.innerHTML = `<ul>${card('a')}${card('b')}</ul><p id="outside">Elsewhere</p>`;
});

it('selects a colour, then a click outside returns the card to its default', () => {
  swatch('a', 0).click();
  expect(swatch('a', 0).getAttribute('aria-pressed')).toBe('true');
  expect(image('a').getAttribute('src')).toBe('/a-red.jpg');
  expect(link('a').getAttribute('href')).toContain('attribute_pa_color=red');

  $<HTMLElement>('#outside').click();

  expect(swatch('a', 0).getAttribute('aria-pressed')).toBe('false');
  expect(swatch('a', 0).classList.contains('is-active')).toBe(false);
  expect(image('a').getAttribute('src')).toBe('/a.jpg');
  expect(image('a').hasAttribute('srcset')).toBe(false);
  expect(image('a').alt).toBe('a');
  expect(link('a').getAttribute('href')).toBe('/product/a/');
});

it('keeps the selection when the click follows the card link', () => {
  swatch('a', 1).click();
  link('a').dispatchEvent(
    new MouseEvent('click', { bubbles: true, cancelable: true })
  );

  expect(swatch('a', 1).getAttribute('aria-pressed')).toBe('true');
  expect(link('a').getAttribute('href')).toContain('attribute_pa_color=blue');
});

it('switching colours within a card keeps the original to return to', () => {
  swatch('a', 0).click();
  swatch('a', 1).click();
  expect(image('a').getAttribute('src')).toBe('/a-blue.jpg');

  $<HTMLElement>('#outside').click();
  expect(image('a').getAttribute('src')).toBe('/a.jpg');
});

it("a swatch in another card deselects this one's", () => {
  swatch('a', 0).click();
  swatch('b', 1).click();

  expect(swatch('a', 0).getAttribute('aria-pressed')).toBe('false');
  expect(image('a').getAttribute('src')).toBe('/a.jpg');
  expect(swatch('b', 1).getAttribute('aria-pressed')).toBe('true');
});

it('Escape inside the swatches deselects them', () => {
  swatch('a', 0).click();
  swatch('a', 0).dispatchEvent(
    new KeyboardEvent('keydown', { key: 'Escape', bubbles: true })
  );

  expect(swatch('a', 0).getAttribute('aria-pressed')).toBe('false');
  expect(image('a').getAttribute('src')).toBe('/a.jpg');
});

it('pressing the selected swatch again deselects it', () => {
  swatch('a', 0).click();
  swatch('a', 0).click();

  expect(swatch('a', 0).getAttribute('aria-pressed')).toBe('false');
  expect(image('a').getAttribute('src')).toBe('/a.jpg');
  expect(link('a').getAttribute('href')).toBe('/product/a/');
});

it('a colour without its own photo shows the product photo', () => {
  swatch('a', 0).click();
  swatch('a', 2).click();

  expect(swatch('a', 2).getAttribute('aria-pressed')).toBe('true');
  expect(image('a').getAttribute('src')).toBe('/a.jpg');
  expect(link('a').getAttribute('href')).toContain('attribute_pa_color=black');
});

it('controls elsewhere in the same card keep the selection', () => {
  swatch('a', 0).click();
  $<HTMLButtonElement>('[data-product-id="a"] .wishlist').click();

  expect(swatch('a', 0).getAttribute('aria-pressed')).toBe('true');
  expect(image('a').getAttribute('src')).toBe('/a-red.jpg');
});

it("restores alt and srcset when a colour's photo is the card's own", () => {
  swatch('a', 3).click();
  expect(image('a').alt).toBe('White');

  $<HTMLElement>('#outside').click();

  expect(image('a').getAttribute('src')).toBe('/a.jpg');
  expect(image('a').alt).toBe('a');
  expect(image('a').hasAttribute('srcset')).toBe(false);
});

it('an element reused for another product starts from that product', () => {
  swatch('a', 0).click();
  $<HTMLElement>('#outside').click();

  // A filter navigation reuses the card's image and link for product c.
  image('a').setAttribute('src', '/c.jpg');
  image('a').alt = 'c';
  link('a').setAttribute('href', '/product/c/');

  swatch('a', 1).click();
  $<HTMLElement>('#outside').click();

  expect(image('a').getAttribute('src')).toBe('/c.jpg');
  expect(image('a').alt).toBe('c');
  expect(link('a').getAttribute('href')).toBe('/product/c/');
});

it('leaves an element reused while selected alone', () => {
  swatch('a', 0).click();
  image('a').setAttribute('src', '/c.jpg');
  link('a').setAttribute('href', '/product/c/');

  $<HTMLElement>('#outside').click();

  expect(swatch('a', 0).getAttribute('aria-pressed')).toBe('false');
  expect(image('a').getAttribute('src')).toBe('/c.jpg');
  expect(link('a').getAttribute('href')).toBe('/product/c/');
});

it('ignores events dispatched on the document', () => {
  swatch('a', 0).click();

  expect(() => {
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    document.dispatchEvent(new MouseEvent('click'));
  }).not.toThrow();
  expect(swatch('a', 0).getAttribute('aria-pressed')).toBe('true');
});

it('restores only what it saw, never values planted in the markup', () => {
  const a = link('a');
  a.setAttribute('data-aa-cs-href', 'javascript:alert(1)');
  a.setAttribute('data-aa-cs-applied', '/product/a/');
  image('a').setAttribute('data-aa-cs-src', 'javascript:alert(1)');

  swatch('a', 0).click();
  $<HTMLElement>('#outside').click();

  expect(a.getAttribute('href')).toBe('/product/a/');
  expect(image('a').getAttribute('src')).toBe('/a.jpg');
});
