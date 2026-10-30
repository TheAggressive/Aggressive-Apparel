import { decodeEntities, parsePrice } from '../../interactivity/helpers';
import type { StoreApiPrices } from '../../interactivity/helpers';

export const STORAGE_KEY = 'aggressive_apparel_recently_viewed';
export const MAX_STORED = 12;

export interface RecentProduct {
  id: number;
  name: string;
  permalink: string;
  images?: { src: string; thumbnail?: string }[];
  prices: StoreApiPrices | null;
}

/** Storage is visitor-controlled; keep only bounded, unique product IDs. */
export function historyIds(value: unknown): number[] {
  return Array.isArray(value)
    ? [
        ...new Set(value.filter(id => Number.isSafeInteger(id) && id > 0)),
      ].slice(0, MAX_STORED)
    : [];
}

export function isRecentProduct(value: unknown): value is RecentProduct {
  if (!value || typeof value !== 'object') return false;
  const p = value as RecentProduct;
  return (
    Number.isSafeInteger(p.id) &&
    p.id > 0 &&
    typeof p.name === 'string' &&
    typeof p.permalink === 'string' &&
    (p.images === undefined ||
      (Array.isArray(p.images) &&
        p.images.every(
          image =>
            image &&
            typeof image.src === 'string' &&
            (image.thumbnail === undefined ||
              typeof image.thumbnail === 'string')
        ))) &&
    (p.prices === null ||
      (!!p.prices &&
        typeof p.prices.price === 'string' &&
        /^\d*$/.test(p.prices.price) &&
        (p.prices.currency_minor_unit === undefined ||
          (Number.isInteger(p.prices.currency_minor_unit) &&
            p.prices.currency_minor_unit >= 0 &&
            p.prices.currency_minor_unit <= 6)) &&
        (p.prices.currency_prefix === undefined ||
          typeof p.prices.currency_prefix === 'string') &&
        (p.prices.currency_suffix === undefined ||
          typeof p.prices.currency_suffix === 'string')))
  );
}

/** Validate URL protocols before assigning navigation or image sources. */
export function httpUrl(value: string): string {
  try {
    const url = new URL(value, location.href);
    return ['http:', 'https:'].includes(url.protocol) ? url.href : '';
  } catch {
    return '';
  }
}

/** Build nodes rather than interpolating remote values into HTML. */
export function renderProducts(
  root: HTMLElement,
  products: RecentProduct[]
): void {
  const fragment = document.createDocumentFragment();
  for (const product of products) {
    const link = httpUrl(product.permalink);
    if (!link) continue;
    const item = document.createElement('div');
    item.className = 'aggressive-apparel-recently-viewed__item';
    const name = decodeEntities(product.name);
    const firstImage = product.images?.[0];
    const image = firstImage && httpUrl(firstImage.thumbnail || firstImage.src);
    if (image) {
      const anchor = document.createElement('a');
      anchor.href = link;
      const img = document.createElement('img');
      img.src = image;
      img.alt = name;
      img.loading = 'lazy';
      img.decoding = 'async';
      img.width = 300;
      img.height = 300;
      anchor.append(img);
      item.append(anchor);
    }
    const title = document.createElement('a');
    title.href = link;
    title.className = 'aggressive-apparel-recently-viewed__name';
    title.textContent = name;
    item.append(title);
    const price = parsePrice(product.prices).current;
    if (price) {
      const amount = document.createElement('span');
      amount.className = 'aggressive-apparel-recently-viewed__price';
      amount.textContent = decodeEntities(price);
      item.append(amount);
    }
    fragment.append(item);
  }
  root.replaceChildren(fragment);
}
