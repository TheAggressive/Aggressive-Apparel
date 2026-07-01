/**
 * @jest-environment jsdom
 */

import {
  isNavigationEligible,
  isPrefetchEligible,
  shouldUseFallbackPrefetch,
  supportsNativeSpeculationRules,
} from '../page-transitions';

const originalSupports = Object.getOwnPropertyDescriptor(
  HTMLScriptElement,
  'supports'
);

function setNativeSupport(supported: boolean): void {
  Object.defineProperty(HTMLScriptElement, 'supports', {
    configurable: true,
    value: jest.fn(() => supported),
  });
}

function link(href: string, attributes: Record<string, string> = {}) {
  const anchor = document.createElement('a');
  anchor.href = href;

  for (const [name, value] of Object.entries(attributes)) {
    anchor.setAttribute(name, value);
  }

  document.body.append(anchor);
  return anchor;
}

describe('page transition prefetch eligibility', () => {
  beforeEach(() => {
    window.history.replaceState({}, '', '/shop/');
  });

  afterEach(() => {
    document.head
      .querySelectorAll('script[type="speculationrules"], link[rel="prefetch"]')
      .forEach(node => node.remove());
    document.body.replaceChildren();

    if (originalSupports) {
      Object.defineProperty(HTMLScriptElement, 'supports', originalSupports);
    } else {
      Reflect.deleteProperty(HTMLScriptElement, 'supports');
    }
  });

  it('accepts ordinary same-origin product links', () => {
    const productLink = link('/product/hoodie/');

    expect(isNavigationEligible(productLink)).toBe(true);
    expect(isPrefetchEligible(productLink)).toBe(true);
  });

  it.each(['/cart/', '/checkout/', '/my-account/'])(
    'allows progress but not prefetch for sensitive route %s',
    route => {
      const sensitiveLink = link(route);

      expect(isNavigationEligible(sensitiveLink)).toBe(true);
      expect(isPrefetchEligible(sensitiveLink)).toBe(false);
    }
  );

  it('does not confuse ordinary paths with commerce route prefixes', () => {
    expect(isPrefetchEligible(link('/cartoon-collection/'))).toBe(true);
  });

  it('allows query navigation feedback without prefetching the URL', () => {
    const filteredShop = link('/shop/?orderby=price');

    expect(isNavigationEligible(filteredShop)).toBe(true);
    expect(isPrefetchEligible(filteredShop)).toBe(false);
  });

  it('honors explicit prefetch opt-outs without hiding progress feedback', () => {
    const nofollow = link('/product/hoodie/', { rel: 'nofollow' });
    const optedOut = link('/product/jacket/', { 'data-no-prefetch': '' });

    expect(isNavigationEligible(nofollow)).toBe(true);
    expect(isPrefetchEligible(nofollow)).toBe(false);
    expect(isNavigationEligible(optedOut)).toBe(true);
    expect(isPrefetchEligible(optedOut)).toBe(false);
  });

  it('rejects non-navigation commerce actions', () => {
    const addToCart = link('/product/hoodie/', {
      class: 'add_to_cart_button',
    });

    expect(isNavigationEligible(addToCart)).toBe(false);
    expect(isPrefetchEligible(addToCart)).toBe(false);
  });

  it('uses the fallback only when emitted rules lack native support', () => {
    const rules = document.createElement('script');
    rules.type = 'speculationrules';
    document.head.append(rules);

    setNativeSupport(false);
    expect(supportsNativeSpeculationRules()).toBe(false);
    expect(shouldUseFallbackPrefetch()).toBe(true);

    setNativeSupport(true);
    expect(supportsNativeSpeculationRules()).toBe(true);
    expect(shouldUseFallbackPrefetch()).toBe(false);
  });

  it('does not override a server decision to emit no rules', () => {
    setNativeSupport(false);

    expect(shouldUseFallbackPrefetch()).toBe(false);
  });
});
