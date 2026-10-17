import { FilterRequestManager } from '../request-manager';

describe('FilterRequestManager', () => {
  beforeEach(() => jest.useFakeTimers());
  afterEach(() => jest.useRealTimers());

  it('coalesces facet updates and cancels them before navigation', () => {
    const manager = new FilterRequestManager();
    const first = jest.fn();
    const second = jest.fn();

    manager.scheduleFacets(first);
    manager.scheduleFacets(second);
    jest.advanceTimersByTime(200);

    expect(first).not.toHaveBeenCalled();
    expect(second).toHaveBeenCalledTimes(1);

    const signal = manager.beginFacets();
    manager.cancelAll();
    expect(signal.aborted).toBe(true);
  });
});
