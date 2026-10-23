/** The theme marks native buttons; WooCommerce owns their actions and state. */
export function observeNativeWishlist(refresh: () => void): void {
  let timer: ReturnType<typeof setTimeout>;
  const selector = '[data-aa-wishlist-native]';
  const observed = new WeakSet<Element>();
  const observe = (): void => {
    document.querySelectorAll(selector).forEach(button => {
      if (observed.has(button)) return;
      observed.add(button);
      new MutationObserver(() => {
        clearTimeout(timer);
        timer = setTimeout(refresh, 100);
      }).observe(button, {
        attributes: true,
        attributeFilter: ['aria-pressed'],
      });
    });
  };
  document.addEventListener('aa:cards-rendered', observe);
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', observe, { once: true });
  } else {
    observe();
  }
}
