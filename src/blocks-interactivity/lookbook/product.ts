/**
 * Lookbook Block — product data.
 *
 * Price/URL formatting and the cached Store API product fetch.
 *
 * @package Aggressive_Apparel
 */

import type { StoreApiPrices, StoreApiProduct } from './types';

const productCache = new Map<string, StoreApiProduct>();
const productRequestCache = new Map<string, Promise<StoreApiProduct>>();

export function stripTags(value: string): string {
  const template = document.createElement('template');
  template.innerHTML = value;
  return template.content.textContent ?? '';
}

export function formatPrice(prices?: StoreApiPrices): string {
  if (!prices?.price) return '';

  const minorUnit = prices.currency_minor_unit ?? 2;
  const divisor = Math.pow(10, minorUnit);
  const parsedPrice = parseInt(prices.price, 10);

  if (!Number.isFinite(parsedPrice)) {
    return '';
  }

  const [intPart, fracPart = ''] = (parsedPrice / divisor)
    .toFixed(minorUnit)
    .split('.');
  const thousandSeparator = prices.currency_thousand_separator ?? ',';
  const decimalSeparator = prices.currency_decimal_separator ?? '.';
  const grouped = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, thousandSeparator);
  const value = fracPart ? `${grouped}${decimalSeparator}${fracPart}` : grouped;
  const prefix = prices.currency_prefix ?? '';
  const suffix = prices.currency_suffix ?? '';

  return `${prefix}${value}${suffix}`;
}

export function safeUrl(value: string | undefined, fallback = '#'): string {
  if (!value) return fallback;

  if (
    (value.startsWith('/') && !value.startsWith('//')) ||
    value.startsWith('#') ||
    /^https?:\/\//i.test(value)
  ) {
    return value;
  }

  return fallback;
}

export function safeMediaUrl(value: string | undefined): string {
  if (!value) return '';

  if (
    (value.startsWith('/') && !value.startsWith('//')) ||
    /^https?:\/\//i.test(value)
  ) {
    return value;
  }

  return '';
}

function getProductEndpoint(restBase: string, productId: number): string {
  const base = restBase || '/wp-json/wc/store/v1/products/';

  return `${base.replace(/\/?$/, '/')}${encodeURIComponent(String(productId))}`;
}

export function fetchProduct(
  productId: number,
  restBase: string
): Promise<StoreApiProduct> {
  if (!Number.isInteger(productId) || productId < 1) {
    return Promise.reject(new Error('Invalid product id'));
  }

  const endpoint = getProductEndpoint(restBase, productId);

  if (productCache.has(endpoint)) {
    return Promise.resolve(productCache.get(endpoint)!);
  }

  const pendingRequest = productRequestCache.get(endpoint);
  if (pendingRequest) {
    return pendingRequest;
  }

  const request = window
    .fetch(endpoint)
    .then((res: Response) => {
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }

      return res.json();
    })
    .then((data: StoreApiProduct) => {
      productCache.set(endpoint, data);
      productRequestCache.delete(endpoint);

      return data;
    })
    .catch(error => {
      productRequestCache.delete(endpoint);
      throw error;
    });

  productRequestCache.set(endpoint, request);

  return request;
}
