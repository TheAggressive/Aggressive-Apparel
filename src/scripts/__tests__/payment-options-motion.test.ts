/**
 * @jest-environment jsdom
 */

import {
  toMilliseconds,
  watchPaymentOptions,
} from '../_payment-options-motion';

const heights = new Map<HTMLElement, number>();
const animate = jest.fn();
let stop: () => void = () => {};

/** Build a three-method list; the first method is selected. */
const setList = () => {
  document.body.innerHTML = `
    <div class="wc-block-checkout__payment-method">
      <div class="wc-block-components-radio-control">
        ${['card', 'klarna', 'paypal']
          .map(
            (value, i) => `
          <div class="wc-block-components-radio-control-accordion-option">
            <label><input type="radio" name="pm" value="${value}" ${i === 0 ? 'checked' : ''}></label>
          </div>`
          )
          .join('')}
      </div>
    </div>`;

  const options = Array.from(
    document.querySelectorAll<HTMLElement>(
      '.wc-block-components-radio-control-accordion-option'
    )
  );

  options.forEach((option, i) => {
    heights.set(option, i === 0 ? 200 : 60);
    option.getBoundingClientRect = () =>
      ({ height: heights.get(option) }) as DOMRect;
    option.animate = animate;
  });
  document
    .querySelectorAll<HTMLInputElement>('input')
    .forEach(input => (input.animate = animate));

  return options;
};

/** Let the MutationObserver callback run. */
const flush = () => new Promise(resolve => setTimeout(resolve, 0));

/** Select an option the way WooCommerce re-renders: click, then new layout. */
const select = async (options: HTMLElement[], index: number) => {
  options[index].querySelector('input')!.click();

  options.forEach((option, i) => heights.set(option, i === index ? 200 : 60));
  options[index].classList.add('is-selected');

  await flush();
};

beforeEach(() => {
  animate.mockReset();
  animate.mockImplementation(() => ({ cancel: jest.fn() }));
  heights.clear();
  window.matchMedia = jest.fn().mockReturnValue({ matches: false });
  stop = watchPaymentOptions(document);
});

afterEach(() => stop());

describe('watchPaymentOptions', () => {
  it('animates the closing and opening cards from their old heights', async () => {
    const options = setList();

    await select(options, 2);

    const keyframes = animate.mock.calls.map(([frames]) => [
      frames[0].height,
      frames[1].height,
    ]);

    expect(keyframes).toEqual([
      ['200px', '60px'],
      ['60px', '200px'],
    ]);
    expect(animate.mock.instances).toEqual([options[0], options[2]]);
  });

  it('leaves cards whose height did not change alone', async () => {
    const options = setList();

    await select(options, 2);

    expect(animate.mock.instances).not.toContain(options[1]);
  });

  it('skips the animation for reduced motion', async () => {
    window.matchMedia = jest.fn().mockReturnValue({ matches: true });
    const options = setList();

    await select(options, 1);

    expect(animate).not.toHaveBeenCalled();
  });

  it('ignores radios outside the payment list', async () => {
    const options = setList();
    document.body.insertAdjacentHTML(
      'beforeend',
      '<input type="radio" name="shipping" id="shipping">'
    );

    document.getElementById('shipping')!.click();
    options[0].classList.add('is-selected');
    await flush();

    expect(animate).not.toHaveBeenCalled();
  });

  it('stops listening once stopped', async () => {
    const options = setList();
    stop();

    await select(options, 1);

    expect(animate).not.toHaveBeenCalled();
  });
});

describe('toMilliseconds', () => {
  it('reads milliseconds and seconds', () => {
    expect(toMilliseconds('300ms')).toBe(300);
    expect(toMilliseconds('0.25s')).toBe(250);
  });

  it('falls back when unparseable', () => {
    expect(toMilliseconds('')).toBe(300);
  });
});
