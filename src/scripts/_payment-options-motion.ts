/**
 * Payment options — cards resize smoothly when the selected method changes.
 *
 * WooCommerce mounts a method's panel (card fields, instructions) only while
 * that method is selected, so a switch removes one panel and adds another in
 * a single render and every card below jumps. This measures each option card
 * just before the switch and again once WooCommerce's render lands, then
 * animates each card's height between the two: the old card shrinks while
 * the new one grows, and the cards between them glide.
 *
 * The "before" measurement comes from the radio's click (arrow keys fire one
 * too) in the document's capture phase, which runs ahead of React's root
 * listener, so the old layout is still in place. A short-lived
 * MutationObserver catches the render; a second render inside the window
 * restarts from the heights on screen. Skipped for reduced motion and where
 * Element#animate is missing.
 *
 * @package Aggressive_Apparel
 */

const PAYMENT_OPTIONS_LIST =
  '.wc-block-checkout__payment-method .wc-block-components-radio-control';

const OPTION = '.wc-block-components-radio-control-accordion-option';
const FALLBACK_DURATION = 300;
const FALLBACK_EASING = 'cubic-bezier(0.4, 0, 0.2, 1)';

/**
 * A theme motion token as an animation option, or a fallback.
 *
 * @param list     Element to read the custom property from.
 * @param property Custom property name.
 * @param fallback Value when the token is unset.
 * @return The token's computed value.
 */
function token(list: Element, property: string, fallback: string): string {
  const view = list.ownerDocument.defaultView;
  const value = view?.getComputedStyle(list).getPropertyValue(property).trim();

  return value || fallback;
}

/**
 * A CSS time ("300ms", "0.3s") in milliseconds.
 *
 * @param value CSS time.
 * @return Milliseconds, or the fallback duration when unparseable.
 */
export function toMilliseconds(value: string): number {
  const amount = parseFloat(value);

  if (!Number.isFinite(amount)) {
    return FALLBACK_DURATION;
  }

  return value.trim().endsWith('ms') ? amount : amount * 1000;
}

/**
 * Keep option cards' heights animated across WooCommerce's method switches.
 *
 * @param doc Document to watch.
 * @return Stop watching.
 */
export function watchPaymentOptions(doc: Document = document): () => void {
  const view = doc.defaultView;

  if (!view) {
    return () => {};
  }

  const running = new Map<HTMLElement, Animation>();
  let session: { observer: MutationObserver; timer: number } | null = null;

  const options = (list: Element) =>
    Array.from(list.querySelectorAll<HTMLElement>(OPTION));

  const height = (option: HTMLElement) => option.getBoundingClientRect().height;

  const endSession = () => {
    if (session) {
      session.observer.disconnect();
      view.clearTimeout(session.timer);
      session = null;
    }
  };

  /**
   * Animate every card whose height changed from `from` to its new height.
   *
   * @return The duration and each card's new resting height.
   */
  const animate = (list: Element, from: Map<HTMLElement, number>) => {
    const ends = new Map<HTMLElement, number>();
    const duration = toMilliseconds(
      token(list, '--aa-duration-normal', `${FALLBACK_DURATION}ms`)
    );
    const easing = token(list, '--aa-ease-default', FALLBACK_EASING);

    running.forEach(animation => animation.cancel());
    running.clear();

    options(list).forEach(option => {
      const start = from.get(option);
      const end = height(option);

      ends.set(option, end);

      if (start === undefined || Math.abs(start - end) < 1) {
        return;
      }

      // Clip while the height is pinned so a growing panel can't spill
      // over the next card; the card's own outline isn't clipped.
      const animation = option.animate(
        [
          { height: `${start}px`, overflow: 'clip' },
          { height: `${end}px`, overflow: 'clip' },
        ],
        { duration, easing }
      );

      running.set(option, animation);
      animation.onfinish = () => running.delete(option);
    });

    return { duration, ends };
  };

  const onClick = (event: Event) => {
    const input = event.target;

    if (!(input instanceof view.HTMLInputElement) || input.type !== 'radio') {
      return;
    }

    const list = input.closest(PAYMENT_OPTIONS_LIST);

    if (
      !list ||
      typeof view.Element.prototype.animate !== 'function' ||
      view.matchMedia?.('(prefers-reduced-motion: reduce)').matches
    ) {
      return;
    }

    endSession();

    // Each card's height before the render: as shown right now, which is the
    // mid-animation value if it's still resizing from a previous switch.
    let resting = new Map(
      options(list).map(option => [option, height(option)])
    );

    const observer = new view.MutationObserver(() => {
      // A card still animating starts from where it is on screen; the rest
      // from where they last came to rest (the DOM already holds the new
      // layout, so their current height is the end, not the start). Cards
      // that appeared in this render have no start and aren't animated.
      const from = new Map<HTMLElement, number>();

      options(list).forEach(option => {
        const start = running.has(option)
          ? height(option)
          : resting.get(option);

        if (start !== undefined) {
          from.set(option, start);
        }
      });
      const { duration, ends } = animate(list, from);

      resting = ends;

      if (session) {
        view.clearTimeout(session.timer);
        session.timer = view.setTimeout(endSession, duration);
      }
    });

    observer.observe(list, {
      subtree: true,
      childList: true,
      attributes: true,
      attributeFilter: ['class'],
    });

    session = {
      observer,
      timer: view.setTimeout(endSession, FALLBACK_DURATION * 2),
    };
  };

  doc.addEventListener('click', onClick, true);

  return () => {
    doc.removeEventListener('click', onClick, true);
    endSession();
    running.forEach(animation => animation.cancel());
    running.clear();
  };
}
