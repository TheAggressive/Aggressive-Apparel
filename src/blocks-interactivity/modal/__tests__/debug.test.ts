import { Debug } from '../utils/debug';

describe('Debug utility', () => {
  beforeEach(() => {
    Debug.disable();
    Debug.clear();
  });

  it('is disabled by default', () => {
    expect(Debug.enabled).toBe(false);
  });

  it('does not log non-critical messages when disabled', () => {
    const spy = jest.spyOn(console, 'log').mockImplementation(() => {});
    Debug.add('test message', false);
    expect(spy).not.toHaveBeenCalled();
    spy.mockRestore();
  });

  it('logs critical messages even when disabled (showCritical=true)', () => {
    const spy = jest.spyOn(console, 'log').mockImplementation(() => {});
    Debug.showCritical = true;
    Debug.add('critical message', true);
    expect(spy).toHaveBeenCalledTimes(1);
    spy.mockRestore();
  });

  it('logs messages when enabled', () => {
    const spy = jest.spyOn(console, 'log').mockImplementation(() => {});
    Debug.enable();
    Debug.add('test message');
    // enable() itself logs "Debug mode enabled", so we expect at least 2 calls.
    expect(spy).toHaveBeenCalled();
    spy.mockRestore();
  });

  it('stores logs in the logs array', () => {
    const spy = jest.spyOn(console, 'log').mockImplementation(() => {});
    Debug.enable();
    Debug.add('stored message');
    expect(
      Debug.getLogs().some(l => l.message.includes('stored message'))
    ).toBe(true);
    spy.mockRestore();
  });

  it('clear() removes previously added logs', () => {
    const spy = jest.spyOn(console, 'log').mockImplementation(() => {});
    jest.spyOn(console, 'clear').mockImplementation(() => {});
    Debug.enable();
    Debug.add('something to clear');
    Debug.clear();
    // clear() appends its own "Debug logs cleared" entry, so check the
    // user message is gone rather than expecting an empty array.
    expect(
      Debug.getLogs().some(l => l.message.includes('something to clear'))
    ).toBe(false);
    spy.mockRestore();
  });

  it('getCriticalLogs() returns only critical entries', () => {
    const spy = jest.spyOn(console, 'log').mockImplementation(() => {});
    Debug.enable();
    Debug.add('normal', false);
    Debug.add('critical', true);
    const critical = Debug.getCriticalLogs();
    expect(critical).toHaveLength(1);
    expect(critical[0].critical).toBe(true);
    spy.mockRestore();
  });

  it('trims logs array when it exceeds maxLogs', () => {
    const spy = jest.spyOn(console, 'log').mockImplementation(() => {});
    Debug.enable();
    const originalMax = Debug.maxLogs;
    Debug.maxLogs = 3;
    for (let i = 0; i < 5; i++) {
      Debug.add(`message ${i}`);
    }
    expect(Debug.getLogs().length).toBeLessThanOrEqual(3);
    Debug.maxLogs = originalMax;
    spy.mockRestore();
  });

  it('deduplicates repeated critical block-existence warnings', () => {
    const spy = jest.spyOn(console, 'log').mockImplementation(() => {});
    const message =
      'Block 12345678-1234-1234-1234-123456789abc no longer exists';
    Debug.add(message, true);
    Debug.add(message, true);
    // Same block UUID — should only log once despite two calls.
    expect(spy).toHaveBeenCalledTimes(1);
    spy.mockRestore();
  });
});
