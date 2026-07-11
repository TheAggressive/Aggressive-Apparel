/**
 * Tests for parallax observer geometry — the activation buffer.
 *
 * @jest-environment jsdom
 */

import { getObserverRootMargin } from '../utils/geometry';

const ZERO_BOUNDARY = { top: '0%', right: '0%', bottom: '0%', left: '0%' };

describe('getObserverRootMargin', () => {
  it('adds the default 20% buffer to top and bottom only', () => {
    expect(getObserverRootMargin(ZERO_BOUNDARY)).toBe('20% 0% 20% 0%');
  });

  it('respects a custom activationBuffer', () => {
    expect(getObserverRootMargin(ZERO_BOUNDARY, 50)).toBe('50% 0% 50% 0%');
  });

  it('with buffer 0 the effective boundary equals the configured one', () => {
    expect(getObserverRootMargin(ZERO_BOUNDARY, 0)).toBe('0% 0% 0% 0%');
    expect(
      getObserverRootMargin(
        { top: '-10%', right: '0%', bottom: '-10%', left: '0%' },
        0
      )
    ).toBe('-10% 0% -10% 0%');
  });

  it('adds to configured percentage values', () => {
    expect(
      getObserverRootMargin(
        { top: '10%', right: '5%', bottom: '-30%', left: '0%' },
        20
      )
    ).toBe('30% 5% -10% 0%');
  });

  it('converts the buffer to px against the viewport for px sides', () => {
    // 20% of a 1000px viewport = 200px.
    expect(
      getObserverRootMargin(
        { top: '100px', right: '0%', bottom: '0px', left: '0%' },
        20,
        1000
      )
    ).toBe('300px 0% 200px 0%');
  });

  it('clamps negative buffers to zero', () => {
    expect(getObserverRootMargin(ZERO_BOUNDARY, -15)).toBe('0% 0% 0% 0%');
  });

  it('treats empty sides as 0% plus the buffer', () => {
    expect(
      getObserverRootMargin({ top: '', right: '', bottom: '', left: '' }, 20)
    ).toBe('20% 0% 20% 0%');
  });
});
