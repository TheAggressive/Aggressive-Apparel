/**
 * Tests for the shared navigation DOM & logging utilities.
 *
 * @jest-environment jsdom
 */

import {
  logError,
  logWarning,
  prefersReducedMotion,
  safeGetElementById,
  safeQuerySelector,
  safeQuerySelectorAll,
} from '../dom';

describe('safeGetElementById', () => {
  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('returns the element when it exists', () => {
    document.body.innerHTML = '<div id="target"></div>';
    expect(safeGetElementById('target')).toBe(
      document.getElementById('target')
    );
  });

  it('returns null for a missing element without throwing', () => {
    expect(safeGetElementById('nope', false)).toBeNull();
  });

  it('returns null for an empty id', () => {
    expect(safeGetElementById('', false)).toBeNull();
  });
});

describe('safeQuerySelector', () => {
  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('finds a descendant element', () => {
    document.body.innerHTML = '<div class="wrap"><span class="x"></span></div>';
    const wrap = document.querySelector('.wrap') as HTMLElement;
    expect(safeQuerySelector(wrap, '.x')).toBe(wrap.querySelector('.x'));
  });

  it('returns null (not throw) for an invalid selector', () => {
    const spy = jest.spyOn(console, 'error').mockImplementation(() => {});
    expect(safeQuerySelector(document, '::::bad')).toBeNull();
    expect(spy).toHaveBeenCalled();
    spy.mockRestore();
  });
});

describe('safeQuerySelectorAll', () => {
  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('returns an array of all matches', () => {
    document.body.innerHTML = '<i class="a"></i><i class="a"></i>';
    const result = safeQuerySelectorAll(document, '.a');
    expect(Array.isArray(result)).toBe(true);
    expect(result).toHaveLength(2);
  });

  it('returns an empty array (not throw) for an invalid selector', () => {
    const spy = jest.spyOn(console, 'error').mockImplementation(() => {});
    expect(safeQuerySelectorAll(document, '))(bad')).toEqual([]);
    spy.mockRestore();
  });
});

describe('prefersReducedMotion', () => {
  it('reflects the matchMedia result', () => {
    const addEventListener = jest.fn();
    window.matchMedia = jest.fn().mockReturnValue({
      matches: true,
      addEventListener,
    }) as unknown as typeof window.matchMedia;

    expect(prefersReducedMotion()).toBe(true);
    // Registers a change listener so it stays in sync.
    expect(addEventListener).toHaveBeenCalledWith(
      'change',
      expect.any(Function)
    );
  });
});

describe('loggers', () => {
  it('logError always writes to console.error', () => {
    const spy = jest.spyOn(console, 'error').mockImplementation(() => {});
    logError('boom');
    expect(spy).toHaveBeenCalledWith('[Nav] boom', '');
    spy.mockRestore();
  });

  it('logWarning is silent in production', () => {
    const env = (
      globalThis as unknown as { process: { env: { NODE_ENV?: string } } }
    ).process.env;
    const prev = env.NODE_ENV;
    env.NODE_ENV = 'production';
    const spy = jest.spyOn(console, 'warn').mockImplementation(() => {});
    logWarning('quiet');
    expect(spy).not.toHaveBeenCalled();
    spy.mockRestore();
    env.NODE_ENV = prev;
  });
});
