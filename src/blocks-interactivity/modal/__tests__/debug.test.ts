import { Debug } from '../utils/debug';

describe('Debug utility', () => {
  beforeEach(() => {
    Debug.enabled = false;
    Debug.showCritical = true;
    Debug.logs = [];
    Debug.maxLogs = 100;
    Debug.loggedCritical.clear();
  });

  it('is disabled by default', () => {
    expect(Debug.enabled).toBe(false);
  });

  it('does not log non-critical messages when disabled', () => {
    Debug.add('test message', false);
    expect(Debug.getLogs()).toHaveLength(0);
  });

  it('stores critical messages even when disabled (showCritical=true)', () => {
    Debug.showCritical = true;
    Debug.add('critical message', true);
    expect(Debug.getCriticalLogs()).toHaveLength(1);
    expect(Debug.getCriticalLogs()[0].message).toContain('critical message');
  });

  it('stores messages when enabled', () => {
    Debug.enable();
    Debug.add('test message');
    expect(Debug.getLogs().map(log => log.message)).toEqual(
      expect.arrayContaining([
        expect.stringContaining('Debug mode enabled'),
        expect.stringContaining('test message'),
      ])
    );
  });

  it('stores logs in the logs array', () => {
    Debug.enable();
    Debug.add('stored message');
    expect(
      Debug.getLogs().some(l => l.message.includes('stored message'))
    ).toBe(true);
  });

  it('clear() removes previously added logs', () => {
    Debug.enable();
    Debug.add('something to clear');
    Debug.clear();
    // clear() appends its own "Debug logs cleared" entry, so check the
    // user message is gone rather than expecting an empty array.
    expect(
      Debug.getLogs().some(l => l.message.includes('something to clear'))
    ).toBe(false);
  });

  it('getCriticalLogs() returns only critical entries', () => {
    Debug.enable();
    Debug.add('normal', false);
    Debug.add('critical', true);
    const critical = Debug.getCriticalLogs();
    expect(critical).toHaveLength(1);
    expect(critical[0].critical).toBe(true);
  });

  it('trims logs array when it exceeds maxLogs', () => {
    Debug.enable();
    Debug.maxLogs = 3;
    for (let i = 0; i < 5; i++) {
      Debug.add(`message ${i}`);
    }
    expect(Debug.getLogs().length).toBeLessThanOrEqual(3);
  });

  it('deduplicates repeated critical block-existence warnings', () => {
    const message =
      'Block 12345678-1234-1234-1234-123456789abc no longer exists';
    Debug.add(message, true);
    Debug.add(message, true);
    expect(Debug.getCriticalLogs()).toHaveLength(1);
  });
});
